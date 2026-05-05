<?php

namespace App\Modules\Commission\Services;

use App\Models\CommissionSale;
use App\Models\User;
use App\Support\CommissionMoney;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommissionMonthlyReportService
{
    /**
     * Start and end of calendar month (start 00:00:00, end 23:59:59 app timezone).
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function monthBounds(string $yearMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    /**
     * Human label e.g. "May 2026".
     */
    public function monthLabel(string $yearMonth): string
    {
        return Carbon::createFromFormat('Y-m', $yearMonth)->translatedFormat('F Y');
    }

    /**
     * All commission rows for the month (by recorded date / created_at).
     */
    public function salesForMonth(string $yearMonth): Collection
    {
        [$start, $end] = $this->monthBounds($yearMonth);

        return CommissionSale::query()
            ->with([
                'customer:id,name,business_name',
                'lead:id,customer_id,product_id',
                'lead.product:id,name',
                'leadItem:id,lead_id,product_id',
                'leadItem.product:id,name',
                'creditedUser:id,name,email',
                'assignedBy:id,name',
            ])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('credited_user_id')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Commission rows recorded in the given inclusive date range (by created_at, app timezone).
     *
     * @return Collection<int, CommissionSale>
     */
    public function salesBetween(
        string $fromYmd,
        string $toYmd,
        ?int $creditedUserId = null,
        ?string $currency = null,
        ?string $commissionRole = null,
    ): Collection {
        $start = Carbon::parse($fromYmd)->startOfDay();
        $end = Carbon::parse($toYmd)->endOfDay();

        return CommissionSale::query()
            ->with([
                'customer:id,name,business_name',
                'lead:id,customer_id,product_id',
                'lead.product:id,name',
                'leadItem:id,lead_id,product_id',
                'leadItem.product:id,name',
                'creditedUser:id,name,email',
                'assignedBy:id,name',
            ])
            ->whereBetween('created_at', [$start, $end])
            ->when($creditedUserId, fn ($q) => $q->where('credited_user_id', $creditedUserId))
            ->when($currency, fn ($q) => $q->where('commission_currency', $currency))
            ->when($commissionRole, fn ($q) => $q->where('commission_role', $commissionRole))
            ->orderBy('credited_user_id')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Human-readable period for emails and PDFs (inclusive dates).
     */
    public function periodLabel(string $fromYmd, string $toYmd): string
    {
        $a = Carbon::parse($fromYmd)->startOfDay();
        $b = Carbon::parse($toYmd)->startOfDay();
        if ($a->toDateString() === $b->toDateString()) {
            return $a->translatedFormat('j F Y');
        }
        if ($a->year === $b->year) {
            return $a->translatedFormat('j F').' – '.$b->translatedFormat('j F Y');
        }

        return $a->translatedFormat('j F Y').' – '.$b->translatedFormat('j F Y');
    }

    public function productNameFor(CommissionSale $sale): string
    {
        return $sale->leadItem?->product?->name
            ?: $sale->lead?->product?->name
            ?: '—';
    }

    public function customerNameFor(CommissionSale $sale): string
    {
        return $sale->customer?->business_name ?: $sale->customer?->name ?: '—';
    }

    /**
     * @return Collection<int, array{product: string, currency: string, total: float, lines: int}>
     */
    public function productWiseTotals(Collection $salesForUser): Collection
    {
        return $salesForUser
            ->groupBy(fn (CommissionSale $s) => $this->productNameFor($s).'|'.$s->commission_currency)
            ->map(function (Collection $group) {
                /** @var CommissionSale $first */
                $first = $group->first();

                $total = round((float) $group->sum(fn (CommissionSale $s) => (float) $s->commission_amount), 2);

                return [
                    'product' => $this->productNameFor($first),
                    'currency' => $first->commission_currency,
                    'total' => $total,
                    'formatted_total' => CommissionMoney::format($first->commission_currency, $total),
                    'lines' => $group->count(),
                ];
            })
            ->values();
    }

    /**
     * @return array<string, float>
     */
    public function currencyTotals(Collection $sales): array
    {
        $totals = ['GBP' => 0.0, 'PKR' => 0.0];

        foreach ($sales as $sale) {
            $c = $sale->commission_currency;
            if (isset($totals[$c])) {
                $totals[$c] += (float) $sale->commission_amount;
            }
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return $totals;
    }

    /**
     * Active users with configured admin roles — used for summary email recipients.
     *
     * @return Collection<int, User>
     */
    public function adminRecipients(): Collection
    {
        $roles = config('commission.admin_role_names', ['Admin', 'System Admin', 'Manager']);

        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', $roles))
            ->whereNotNull('email')
            ->orderBy('name')
            ->get()
            ->unique('email')
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function adminEmailAddresses(): Collection
    {
        $emails = $this->adminRecipients()->pluck('email')->filter(fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL))->values();

        $extra = config('commission.extra_admin_emails', []);
        foreach ($extra as $e) {
            if (is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails->push($e);
            }
        }

        return $emails->unique()->values();
    }

    /**
     * Rows for PDF / HTML tables (flattened Sale + resolved names).
     *
     * @return list<array<string, mixed>>
     */
    public function tableRows(Collection $sales): array
    {
        return $sales->map(fn (CommissionSale $s) => [
            'credited_user_id' => $s->credited_user_id,
            'credited_user_name' => $s->creditedUser?->name ?? 'User #'.$s->credited_user_id,
            'customer_name' => $this->customerNameFor($s),
            'product_name' => $this->productNameFor($s),
            'commission_currency' => $s->commission_currency,
            'commission_amount' => (float) $s->commission_amount,
            'commission_role' => $s->commission_role,
            'commission_role_label' => CommissionMoney::humanizeRole($s->commission_role),
            'assigned_by_name' => $s->assignedBy?->name,
            'created_at' => $s->created_at?->toDateTimeString(),
            'formatted_amount' => CommissionMoney::format($s->commission_currency, $s->commission_amount),
        ])->all();
    }

    /**
     * Aggregate rows by user for admin email body grouping.
     *
     * @return array<int, array{name: string, lines: array<int, array<string, mixed>>, totals: array<string, float>}>
     */
    public function groupRowsByRecipient(Collection $sales): array
    {
        $groups = [];

        foreach ($sales->groupBy('credited_user_id') as $userId => $userSales) {
            /** @var CommissionSale|null $first */
            $first = $userSales->first();
            $name = $first?->creditedUser?->name ?? 'User #'.$userId;
            $groups[(int) $userId] = [
                'name' => $name,
                'lines' => $this->tableRows($userSales),
                'totals' => $this->currencyTotals($userSales),
            ];
        }

        uasort($groups, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $groups;
    }
}
