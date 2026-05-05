<?php

namespace App\Modules\Commission\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\CommissionMonthlyAdminMail;
use App\Mail\CommissionMonthlyUserMail;
use App\Models\CommissionSale;
use App\Models\User;
use App\Modules\Commission\Services\CommissionMonthlyReportService;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\Settings\Models\Setting;
use App\Services\MailConfigFromDatabase;
use App\Support\PdfDocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommissionManagementController extends Controller
{
    private const ROLES_ALLOWED = ['Admin', 'Manager', 'System Admin'];

    public function sales(Request $request)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'credited_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'processed' => ['nullable', 'in:all,yes,no'],
        ]);

        $from = $request->input('from');
        $to = $request->input('to');

        // Default: last ~2 months (same-day two months back through today) when no range sent.
        if (! $from && ! $to) {
            $to = Carbon::today()->toDateString();
            $from = Carbon::today()->subMonthsNoOverflow(2)->toDateString();
        } elseif ($from && ! $to) {
            $to = Carbon::today()->toDateString();
        } elseif (! $from && $to) {
            $from = Carbon::parse($to)->subMonthsNoOverflow(2)->toDateString();
        }

        $creditedUserId = $request->integer('credited_user_id');
        $processed = $request->input('processed', 'all');

        $query = LeadItem::query()
            ->with([
                'lead.customer:id,name,business_name',
                'lead.assignee:id,name',
                'product:id,name',
            ])
            ->where('status', LeadItem::STATUS_WON)
            ->whereDate('closed_at', '>=', $from)
            ->whereDate('closed_at', '<=', $to)
            ->orderByDesc('closed_at');

        $items = $query->get();
        $leadIds = $items->pluck('lead_id')->unique()->values()->all();
        $leadItemIds = $items->pluck('id')->unique()->values()->all();

        $commissionQuery = CommissionSale::query()
            ->with(['creditedUser:id,name', 'assignedBy:id,name'])
            ->where(function ($q) use ($leadIds, $leadItemIds) {
                $q->whereIn('lead_item_id', $leadItemIds)
                    ->orWhere(function ($nested) use ($leadIds) {
                        $nested->whereNull('lead_item_id')
                            ->whereIn('lead_id', $leadIds);
                    });
            });

        if ($creditedUserId) {
            $commissionQuery->where('credited_user_id', $creditedUserId);
        }

        $commissions = $commissionQuery->orderByDesc('id')->get();
        $commissionByItem = $commissions->groupBy(fn (CommissionSale $sale) => $sale->lead_item_id ?: ('lead:'.$sale->lead_id));

        $rows = $items->map(function (LeadItem $item) use ($commissionByItem) {
            $key = $item->id;
            $entries = $commissionByItem->get($key, collect());
            if ($entries->isEmpty()) {
                $entries = $commissionByItem->get('lead:'.$item->lead_id, collect());
            }

            return [
                'lead_id' => $item->lead_id,
                'lead_item_id' => $item->id,
                'customer_id' => $item->lead?->customer_id,
                'customer_name' => $item->lead?->customer?->business_name ?: $item->lead?->customer?->name,
                'product_name' => $item->product?->name,
                'closed_at' => optional($item->closed_at)->toDateTimeString(),
                'total_price' => (float) $item->total_price,
                'lead_assigned_to' => $item->lead?->assigned_to,
                'lead_assigned_to_name' => $item->lead?->assignee?->name,
                'commission_entries' => $entries->values()->map(fn (CommissionSale $entry) => [
                    'id' => $entry->id,
                    'credited_user_id' => $entry->credited_user_id,
                    'credited_user_name' => $entry->creditedUser?->name,
                    'assigned_by_user_id' => $entry->assigned_by_user_id,
                    'assigned_by_user_name' => $entry->assignedBy?->name,
                    'commission_amount' => (float) $entry->commission_amount,
                    'commission_currency' => $entry->commission_currency,
                    'commission_role' => $entry->commission_role,
                    'notes' => $entry->notes,
                    'created_at' => optional($entry->created_at)->toDateTimeString(),
                ]),
                'commission_processed' => $entries->isNotEmpty(),
            ];
        })->filter(function (array $row) use ($processed) {
            if ($processed === 'yes') {
                return $row['commission_processed'] === true;
            }
            if ($processed === 'no') {
                return $row['commission_processed'] === false;
            }

            return true;
        })->values();

        return response()->json($rows);
    }

    public function users(Request $request)
    {
        $this->authorizeAdmin($request);

        $users = User::query()
            ->with('role:id,name')
            ->orderBy('name')
            ->get(['id', 'role_id', 'name', 'email', 'commission_eligible']);

        return response()->json($users);
    }

    public function toggleEligibility(Request $request, int $id)
    {
        $actor = $this->authorizeAdmin($request);

        $data = $request->validate([
            'commission_eligible' => ['required', 'boolean'],
        ]);

        $user = User::query()->findOrFail($id);
        $user->update(['commission_eligible' => $data['commission_eligible']]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'commission_eligible' => (bool) $user->commission_eligible,
            'updated_by' => $actor->name,
        ]);
    }

    public function allocate(Request $request)
    {
        $actor = $this->authorizeAdmin($request);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'lead_item_id' => ['nullable', 'integer', 'exists:lead_items,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.credited_user_id' => ['required', 'integer', 'exists:users,id'],
            'allocations.*.commission_amount' => ['required', 'numeric', 'gt:0'],
            'allocations.*.commission_currency' => ['required', 'string', 'in:GBP,PKR'],
            'allocations.*.commission_role' => ['nullable', 'string', 'in:single_owner,closer,appointment_creator'],
            'allocations.*.notes' => ['nullable', 'string'],
        ]);

        $leadItem = null;
        if (! empty($data['lead_item_id'])) {
            $leadItem = LeadItem::query()->with('lead')->findOrFail($data['lead_item_id']);
            if ((int) $leadItem->lead_id !== (int) $data['lead_id']) {
                return response()->json(['message' => 'lead_item_id does not belong to lead_id.'], 422);
            }
            if ($leadItem->status !== LeadItem::STATUS_WON) {
                return response()->json(['message' => 'Commission can only be assigned to won sales.'], 422);
            }
        }

        $creditedIds = collect($data['allocations'])->pluck('credited_user_id')->map(fn ($v) => (int) $v)->unique()->values();
        $eligibleCount = User::query()
            ->whereIn('id', $creditedIds->all())
            ->where('commission_eligible', true)
            ->count();
        if ($eligibleCount !== $creditedIds->count()) {
            return response()->json(['message' => 'One or more selected users are not commission eligible.'], 422);
        }

        $customerId = $leadItem?->lead?->customer_id;
        if (! $customerId) {
            $customerId = DB::table('leads')->where('id', $data['lead_id'])->value('customer_id');
        }

        $existing = CommissionSale::query()
            ->where('lead_id', $data['lead_id'])
            ->where('lead_item_id', $data['lead_item_id'] ?? null)
            ->get();

        $created = DB::transaction(function () use ($data, $actor, $customerId) {
            CommissionSale::query()
                ->where('lead_id', $data['lead_id'])
                ->where('lead_item_id', $data['lead_item_id'] ?? null)
                ->delete();

            $rows = [];
            foreach ($data['allocations'] as $allocation) {
                $rows[] = CommissionSale::create([
                    'lead_id' => $data['lead_id'],
                    'lead_item_id' => $data['lead_item_id'] ?? null,
                    'customer_id' => $customerId,
                    'credited_user_id' => $allocation['credited_user_id'],
                    'assigned_by_user_id' => $actor->id,
                    'commission_amount' => $allocation['commission_amount'],
                    'commission_currency' => $allocation['commission_currency'],
                    'commission_role' => $allocation['commission_role'] ?? (count($data['allocations']) > 1 ? 'closer' : 'single_owner'),
                    'notes' => $allocation['notes'] ?? null,
                ]);
            }

            return collect($rows);
        });

        $this->writeAllocationActivity(
            (int) $data['lead_id'],
            $actor->id,
            $existing,
            $created,
            $data['lead_item_id'] ?? null
        );

        return response()->json($created->values(), 201);
    }

    public function reassign(Request $request, int $id)
    {
        $actor = $this->authorizeAdmin($request);
        $data = $request->validate([
            'credited_user_id' => ['required', 'integer', 'exists:users,id'],
            'commission_amount' => ['nullable', 'numeric', 'gt:0'],
            'commission_currency' => ['nullable', 'string', 'in:GBP,PKR'],
            'commission_role' => ['nullable', 'string', 'in:single_owner,closer,appointment_creator'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = User::query()->findOrFail($data['credited_user_id']);
        if (! $user->commission_eligible) {
            return response()->json(['message' => 'Selected user is not commission eligible.'], 422);
        }

        $sale = CommissionSale::query()->findOrFail($id);
        $before = $sale->replicate();

        $sale->update([
            'credited_user_id' => $data['credited_user_id'],
            'commission_amount' => $data['commission_amount'] ?? $sale->commission_amount,
            'commission_currency' => $data['commission_currency'] ?? $sale->commission_currency,
            'commission_role' => $data['commission_role'] ?? $sale->commission_role,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $sale->notes,
            'assigned_by_user_id' => $actor->id,
        ]);

        LeadActivity::create([
            'lead_id' => $sale->lead_id,
            'user_id' => $actor->id,
            'type' => 'note',
            'description' => sprintf(
                'Commission reassigned from %s to %s (%s %0.2f).',
                User::query()->where('id', $before->credited_user_id)->value('name') ?: ('User #'.$before->credited_user_id),
                $user->name,
                $sale->commission_currency,
                (float) $sale->commission_amount
            ),
            'meta' => [
                'commission_sale_id' => $sale->id,
                'old_credited_user_id' => $before->credited_user_id,
                'new_credited_user_id' => $sale->credited_user_id,
                'old_amount' => (float) $before->commission_amount,
                'new_amount' => (float) $sale->commission_amount,
                'old_currency' => $before->commission_currency,
                'new_currency' => $sale->commission_currency,
            ],
        ]);

        return response()->json($sale->fresh(['creditedUser:id,name', 'assignedBy:id,name']));
    }

    public function summary(Request $request)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = CommissionSale::query()
            ->with('creditedUser:id,name')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')));

        $rows = $query->get();

        $byUser = $rows
            ->groupBy('credited_user_id')
            ->map(function ($userRows, $userId) {
                return [
                    'credited_user_id' => (int) $userId,
                    'credited_user_name' => $userRows->first()->creditedUser?->name,
                    'totals' => $userRows
                        ->groupBy('commission_currency')
                        ->map(fn ($currencyRows) => round((float) $currencyRows->sum('commission_amount'), 2)),
                ];
            })->values();

        $byCurrency = $rows
            ->groupBy('commission_currency')
            ->map(fn ($currencyRows) => round((float) $currencyRows->sum('commission_amount'), 2));

        return response()->json([
            'total_entries' => $rows->count(),
            'single_entries' => $rows->where('commission_role', 'single_owner')->count(),
            'split_entries' => $rows->whereIn('commission_role', ['closer', 'appointment_creator'])->count(),
            'by_user' => $byUser,
            'by_currency' => $byCurrency,
        ]);
    }

    public function report(Request $request)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'credited_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['nullable', 'in:GBP,PKR'],
            'commission_role' => ['nullable', 'in:single_owner,closer,appointment_creator'],
        ]);

        $from = $request->input('from');
        $to = $request->input('to');

        if (! $from && ! $to) {
            $to = Carbon::today()->toDateString();
            $from = Carbon::today()->subMonthsNoOverflow(2)->toDateString();
        } elseif ($from && ! $to) {
            $to = Carbon::today()->toDateString();
        } elseif (! $from && $to) {
            $from = Carbon::parse($to)->subMonthsNoOverflow(2)->toDateString();
        }

        $rows = CommissionSale::query()
            ->with([
                'customer:id,name,business_name',
                'lead:id,customer_id,product_id',
                'lead.product:id,name',
                'leadItem:id,lead_id,product_id',
                'leadItem.product:id,name',
                'creditedUser:id,name',
                'assignedBy:id,name',
            ])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($request->filled('credited_user_id'), fn ($q) => $q->where('credited_user_id', (int) $request->input('credited_user_id')))
            ->when($request->filled('currency'), fn ($q) => $q->where('commission_currency', $request->input('currency')))
            ->when($request->filled('commission_role'), fn ($q) => $q->where('commission_role', $request->input('commission_role')))
            ->latest('id')
            ->get()
            ->map(function (CommissionSale $sale) {
                return [
                    'id' => $sale->id,
                    'created_at' => optional($sale->created_at)->toDateTimeString(),
                    'customer_name' => $sale->customer?->business_name ?: $sale->customer?->name,
                    'product_name' => $sale->leadItem?->product?->name ?: $sale->lead?->product?->name,
                    'credited_user_id' => $sale->credited_user_id,
                    'credited_user_name' => $sale->creditedUser?->name,
                    'assigned_by_user_name' => $sale->assignedBy?->name,
                    'commission_amount' => (float) $sale->commission_amount,
                    'commission_currency' => $sale->commission_currency,
                    'commission_role' => $sale->commission_role,
                    'notes' => $sale->notes,
                ];
            })->values();

        return response()->json($rows);
    }

    public function sendReportToUser(Request $request, CommissionMonthlyReportService $reportService)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'credited_user_id' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['nullable', 'in:GBP,PKR'],
            'commission_role' => ['nullable', 'in:single_owner,closer,appointment_creator'],
        ]);

        $sales = $reportService->salesBetween(
            $data['from'],
            $data['to'],
            (int) $data['credited_user_id'],
            $data['currency'] ?? null,
            $data['commission_role'] ?? null,
        );

        if ($sales->isEmpty()) {
            return response()->json(['message' => 'No commission entries for this user in the selected filters.'], 422);
        }

        $user = User::query()->findOrFail((int) $data['credited_user_id']);
        if (! $user->email || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'This user has no valid email address on file.'], 422);
        }

        $companyName = Setting::query()->where('key', 'company_name')->value('value')
            ?? config('app.name', 'CRM');
        $periodLabel = $reportService->periodLabel($data['from'], $data['to']);
        $fileSlug = str_replace('-', '_', (string) $data['from']).'_'.str_replace('-', '_', (string) $data['to']);

        MailConfigFromDatabase::apply();

        Mail::to($user->email)->send(new CommissionMonthlyUserMail(
            companyName: (string) $companyName,
            monthLabel: $periodLabel,
            yearMonth: $fileSlug,
            userName: $user->name,
            detailRows: $reportService->tableRows($sales),
            productTotals: $reportService->productWiseTotals($sales)->all(),
            currencyTotals: $reportService->currencyTotals($sales),
        ));

        return response()->json([
            'message' => 'Commission report emailed to '.$user->name.'.',
            'sent_to' => $user->email,
        ]);
    }

    public function sendInternalReport(Request $request, CommissionMonthlyReportService $reportService)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'credited_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['nullable', 'in:GBP,PKR'],
            'commission_role' => ['nullable', 'in:single_owner,closer,appointment_creator'],
        ]);

        $creditedId = isset($data['credited_user_id']) ? (int) $data['credited_user_id'] : null;

        $sales = $reportService->salesBetween(
            $data['from'],
            $data['to'],
            $creditedId,
            $data['currency'] ?? null,
            $data['commission_role'] ?? null,
        );

        if ($sales->isEmpty()) {
            return response()->json(['message' => 'No commission entries for the selected filters.'], 422);
        }

        $companyName = Setting::query()->where('key', 'company_name')->value('value')
            ?? config('app.name', 'CRM');
        $periodLabel = $reportService->periodLabel($data['from'], $data['to']);

        $userBlocks = [];
        foreach ($sales->groupBy('credited_user_id') as $uid => $userSales) {
            $first = $userSales->first();
            $userBlocks[] = [
                'user_id' => (int) $uid,
                'name' => $first?->creditedUser?->name ?? 'User #'.$uid,
                'product_totals' => $reportService->productWiseTotals($userSales)->all(),
                'lines' => $reportService->tableRows($userSales),
            ];
        }
        usort($userBlocks, fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        $overallTotals = $reportService->currencyTotals($sales);
        $distinctPeople = $sales->pluck('credited_user_id')->unique()->count();

        $intro = $distinctPeople === 0
            ? 'No commission allocations matched this report.'
            : 'Commission allocations in '.$periodLabel
                .($creditedId ? ' for the selected user' : '')
                ." — {$distinctPeople} user(s). The PDF attachment is the full consolidated report.";

        $pdfBase = str_replace('-', '', (string) $data['from']).'_'.str_replace('-', '', (string) $data['to']);
        $pdfFileName = 'commission_report_'.$pdfBase.'.pdf';

        $addrList = $reportService->adminEmailAddresses();
        if ($addrList->isEmpty()) {
            return response()->json(['message' => 'No admin or manager email addresses are configured for internal reports.'], 422);
        }

        MailConfigFromDatabase::apply();

        $to = $addrList->first();
        $bcc = $addrList->slice(1)->values()->all();
        $adminMessage = new CommissionMonthlyAdminMail(
            companyName: (string) $companyName,
            monthLabel: $periodLabel,
            introduction: $intro,
            userBlocks: $userBlocks,
            overallTotals: $overallTotals,
            pdfFileName: $pdfFileName,
        );
        $send = Mail::to($to);
        if ($bcc !== []) {
            $send->bcc($bcc);
        }
        $send->send($adminMessage);

        return response()->json([
            'message' => 'Internal commission report emailed to '.$addrList->count().' address(es).',
            'recipients_count' => $addrList->count(),
        ]);
    }

    public function downloadUserCommissionPdf(Request $request, CommissionMonthlyReportService $reportService)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'credited_user_id' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['nullable', 'in:GBP,PKR'],
            'commission_role' => ['nullable', 'in:single_owner,closer,appointment_creator'],
        ]);

        $sales = $reportService->salesBetween(
            $data['from'],
            $data['to'],
            (int) $data['credited_user_id'],
            $data['currency'] ?? null,
            $data['commission_role'] ?? null,
        );

        if ($sales->isEmpty()) {
            return response()->json(['message' => 'No commission entries for this user in the selected filters.'], 422);
        }

        $user = User::query()->findOrFail((int) $data['credited_user_id']);

        $companyName = Setting::query()->where('key', 'company_name')->value('value')
            ?? config('app.name', 'CRM');
        $periodLabel = $reportService->periodLabel($data['from'], $data['to']);
        $stamp = Carbon::now()->toDayDateTimeString();
        $brand = PdfDocumentBranding::package();

        $pdf = Pdf::loadView('commission.pdf_monthly_user', [
            'companyName' => (string) $companyName,
            'monthLabel' => $periodLabel,
            'userName' => $user->name,
            'productTotals' => $reportService->productWiseTotals($sales)->all(),
            'detailRows' => $reportService->tableRows($sales),
            'currencyTotals' => $reportService->currencyTotals($sales),
            'generatedAt' => $stamp,
            'logoUrl' => $brand['logoUrl'],
            'settings' => $brand['settings'],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('enable-local-file-access', true);

        $fromSlug = str_replace('-', '', (string) $data['from']);
        $toSlug = str_replace('-', '', (string) $data['to']);
        $safeName = Str::slug($user->name) ?: 'user';

        return $pdf->download("commission_summary_{$safeName}_{$fromSlug}_{$toSlug}.pdf");
    }

    public function downloadFullCommissionPdf(Request $request, CommissionMonthlyReportService $reportService)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'credited_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['nullable', 'in:GBP,PKR'],
            'commission_role' => ['nullable', 'in:single_owner,closer,appointment_creator'],
        ]);

        $creditedId = isset($data['credited_user_id']) ? (int) $data['credited_user_id'] : null;

        $sales = $reportService->salesBetween(
            $data['from'],
            $data['to'],
            $creditedId,
            $data['currency'] ?? null,
            $data['commission_role'] ?? null,
        );

        if ($sales->isEmpty()) {
            return response()->json(['message' => 'No commission entries for the selected filters.'], 422);
        }

        $companyName = Setting::query()->where('key', 'company_name')->value('value')
            ?? config('app.name', 'CRM');
        $periodLabel = $reportService->periodLabel($data['from'], $data['to']);

        $userBlocks = [];
        foreach ($sales->groupBy('credited_user_id') as $uid => $userSales) {
            $first = $userSales->first();
            $userBlocks[] = [
                'user_id' => (int) $uid,
                'name' => $first?->creditedUser?->name ?? 'User #'.$uid,
                'product_totals' => $reportService->productWiseTotals($userSales)->all(),
                'lines' => $reportService->tableRows($userSales),
            ];
        }
        usort($userBlocks, fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        $overallTotals = $reportService->currencyTotals($sales);
        $stamp = Carbon::now()->toDayDateTimeString();
        $brand = PdfDocumentBranding::package();

        $pdf = Pdf::loadView('commission.pdf_monthly_full', [
            'companyName' => (string) $companyName,
            'monthLabel' => $periodLabel,
            'userBlocks' => $userBlocks,
            'overallTotals' => $overallTotals,
            'generatedAt' => $stamp,
            'logoUrl' => $brand['logoUrl'],
            'settings' => $brand['settings'],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('enable-local-file-access', true);

        $fromSlug = str_replace('-', '', (string) $data['from']);
        $toSlug = str_replace('-', '', (string) $data['to']);

        return $pdf->download("commission_report_{$fromSlug}_{$toSlug}.pdf");
    }

    private function authorizeAdmin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $allowed = collect(self::ROLES_ALLOWED)->contains(fn (string $role) => $user->isRole($role));
        if (! $allowed) {
            abort(response()->json(['message' => 'Unauthorized'], 403));
        }

        return $user;
    }

    private function writeAllocationActivity(int $leadId, int $actorId, $oldRows, $newRows, ?int $leadItemId): void
    {
        $oldText = $oldRows->map(function (CommissionSale $row) {
            return sprintf(
                '%s %0.2f to user #%d (%s)',
                $row->commission_currency,
                (float) $row->commission_amount,
                $row->credited_user_id,
                $row->commission_role
            );
        })->join(', ');

        $newText = $newRows->map(function (CommissionSale $row) {
            return sprintf(
                '%s %0.2f to user #%d (%s)',
                $row->commission_currency,
                (float) $row->commission_amount,
                $row->credited_user_id,
                $row->commission_role
            );
        })->join(', ');

        $prefix = $leadItemId ? "Lead item #{$leadItemId}" : 'Lead-level sale';
        LeadActivity::create([
            'lead_id' => $leadId,
            'user_id' => $actorId,
            'type' => 'note',
            'description' => $prefix.' commission allocation updated.',
            'meta' => [
                'commission_old' => $oldText,
                'commission_new' => $newText,
                'commission_entries_count' => $newRows->count(),
                'lead_item_id' => $leadItemId,
            ],
        ]);
    }
}
