<?php

namespace App\Http\Controllers;

use App\Models\EmailList;
use App\Models\EmailListRecipient;
use App\Models\EmailTemplate;
use App\Models\SentCommunication;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailManagementController extends Controller
{
    /**
     * @return int[]
     */
    private function normalizeExcludeCustomerIds(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $input))));

        return array_values(array_filter($ids, fn (int $id) => $id > 0));
    }

    /**
     * @param  Builder<\App\Modules\CRM\Models\Customer>  $query
     */
    private function applyEmailAudienceProductDateSearch(Request $request, Builder $query): void
    {
        $audience = $request->input('audience');
        $productFilters = $request->input('product_filters', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($audience === 'prospect') {
            $query->where('type', Customer::TYPE_PROSPECT);
        } elseif ($audience === 'customer') {
            $query->where('type', Customer::TYPE_CUSTOMER);
        }

        if (is_string($search) && trim($search) !== '') {
            $term = '%' . addcslashes(trim($search), '%_\\') . '%';
            $query->where('name', 'like', $term);
        }

        foreach ($productFilters as $filter) {
            if (($filter['rule'] ?? '') === 'all') {
                continue;
            }
            $productId = (int) $filter['product_id'];
            $rule = $filter['rule'];
            if ($rule === 'has') {
                $query->where(function ($q) use ($productId, $audience) {
                    $this->applyHasProduct($q, $productId, $audience);
                });
            } elseif ($rule === 'does_not_have') {
                $query->where(function ($q) use ($productId, $audience) {
                    $this->applyDoesNotHaveProduct($q, $productId, $audience);
                });
            }
        }
    }

    /**
     * @return Builder<\App\Modules\CRM\Models\Customer>
     */
    private function buildEmailFilteredQuery(Request $request, bool $applyExclude): Builder
    {
        $query = Customer::query()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        $this->applyEmailAudienceProductDateSearch($request, $query);

        if ($applyExclude) {
            $exclude = $this->normalizeExcludeCustomerIds($request->input('exclude_customer_ids'));
            if ($exclude !== []) {
                $query->whereNotIn('id', $exclude);
            }
        }

        return $query;
    }

    /**
     * Check SMTP settings status (from Settings page).
     */
    public function smtpStatus()
    {
        $host = \App\Modules\Settings\Models\Setting::where('key', 'smtp_host')->first()?->value;
        $configured = !empty(trim($host ?? ''));
        return response()->json([
            'configured' => $configured,
            'message' => $configured ? 'SMTP settings are configured from Settings → Email/SMTP.' : 'Please configure SMTP in Settings → Email/SMTP before sending emails.',
        ]);
    }

    /**
     * Get contacts (customers/prospects) matching the given audience, product filters, and date range.
     * Only returns contacts that have an email.
     */
    public function getFilteredContacts(Request $request)
    {
        $request->validate([
            'audience' => 'required|in:prospect,customer,both',
            'product_filters' => 'nullable|array',
            'product_filters.*.product_id' => 'required_with:product_filters|integer|exists:products,id',
            'product_filters.*.rule' => 'required_with:product_filters|in:has,does_not_have,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min((int) $request->input('per_page', 50), 100);

        $query = $this->buildEmailFilteredQuery($request, false);

        $paginator = $query->orderBy('name')->paginate($perPage, ['id', 'name', 'email', 'business_name', 'type']);

        return response()->json([
            'contacts' => collect($paginator->items())->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'business_name' => $c->business_name,
                'type' => $c->type,
            ])->values()->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * Export filtered contacts as CSV (same params as getFilteredContacts).
     */
    public function exportFilteredContacts(Request $request): StreamedResponse
    {
        $request->validate([
            'audience' => 'required|in:prospect,customer,both',
            'product_filters' => 'nullable|array',
            'product_filters.*.product_id' => 'required_with:product_filters|integer|exists:products,id',
            'product_filters.*.rule' => 'required_with:product_filters|in:has,does_not_have,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
        ]);

        $contacts = $this->buildEmailFilteredQuery($request, false)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'business_name', 'type']);

        $filename = 'email-contacts-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($contacts) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Business Name', 'Type']);
            foreach ($contacts as $c) {
                fputcsv($out, [$c->id, $c->name, $c->email, $c->business_name ?? '', $c->type]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Preview template: render with sample data (first contact or fake).
     */
    public function previewTemplate(Request $request, $templateId)
    {
        $template = EmailTemplate::where('is_active', true)->findOrFail($templateId);
        $sample = $this->getSampleCustomerForPreview();
        $subject = $this->replaceVariables($template->subject, $sample);
        $content = $this->renderTemplateForPreview($template, $sample);

        return response()->json([
            'subject' => $subject,
            'content' => $content,
            'template_name' => $template->name,
        ]);
    }

    /**
     * Send bulk email to all filtered contacts using the given template.
     */
    public function sendBulk(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'audience' => 'required|in:prospect,customer,both',
            'product_filters' => 'nullable|array',
            'product_filters.*.product_id' => 'required_with:product_filters|integer|exists:products,id',
            'product_filters.*.rule' => 'required_with:product_filters|in:has,does_not_have,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'exclude_customer_ids' => 'nullable|array',
            'exclude_customer_ids.*' => 'integer|exists:customers,id',
        ]);

        $template = EmailTemplate::findOrFail($request->template_id);

        $query = $this->buildEmailFilteredQuery($request, true);

        \App\Services\MailConfigFromDatabase::apply();

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $sentList = [];
        $failedList = [];

        $chunkSize = 50;
        $query->orderBy('id')->chunk($chunkSize, function ($contacts) use ($template, &$sent, &$failed, &$skipped, &$sentList, &$failedList) {
            foreach ($contacts as $customer) {
                if (\App\Models\EmailUnsubscribe::isUnsubscribed($customer->email)) {
                    $skipped++;
                    continue;
                }

                [$valid, $missingVars] = $this->validateRecipientForTemplate($customer, $template);
                if (!$valid) {
                    $skipped++;
                    $errorMsg = 'Skipped: missing data for variable(s): ' . implode(', ', $missingVars);
                    $failedList[] = ['email' => $customer->email, 'name' => $customer->name, 'error' => $errorMsg];
                    SentCommunication::create([
                        'type' => 'email',
                        'template_type' => 'email_template',
                        'template_id' => $template->id,
                        'customer_id' => $customer->id,
                        'recipient_email' => $customer->email,
                        'subject' => $template->subject ?? '',
                        'content' => '',
                        'status' => 'failed',
                        'error_message' => $errorMsg,
                        'sent_by' => auth()->id(),
                    ]);
                    continue;
                }

                try {
                    $subject = $this->replaceVariables($template->subject, $customer);
                    $content = $this->renderTemplateForPreview($template, $customer);
                    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($customer, $subject, $content) {
                        $message->to($customer->email)
                            ->subject($subject)
                            ->html($content);
                    });
                    SentCommunication::create([
                        'type' => 'email',
                        'template_type' => 'email_template',
                        'template_id' => $template->id,
                        'customer_id' => $customer->id,
                        'recipient_email' => $customer->email,
                        'subject' => $subject,
                        'content' => $content,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'sent_by' => auth()->id(),
                    ]);
                    $sent++;
                    $sentList[] = ['email' => $customer->email, 'name' => $customer->name];
                } catch (\Exception $e) {
                    $failed++;
                    $errMsg = strlen($e->getMessage()) > 200 ? substr($e->getMessage(), 0, 200) . '...' : $e->getMessage();
                    $failedList[] = ['email' => $customer->email, 'name' => $customer->name, 'error' => $errMsg];
                    SentCommunication::create([
                        'type' => 'email',
                        'template_type' => 'email_template',
                        'template_id' => $template->id,
                        'customer_id' => $customer->id,
                        'recipient_email' => $customer->email,
                        'subject' => $template->subject ?? '',
                        'content' => '',
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'sent_by' => auth()->id(),
                    ]);
                }
            }
        });

        $total = $sent + $failed + $skipped;
        $msg = "Sent: {$sent}, Failed: {$failed}";
        if ($skipped > 0) {
            $msg .= ", Skipped: {$skipped}";
        }
        return response()->json([
            'message' => $msg,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $total,
            'sent_list' => array_slice($sentList, 0, 100),
            'failed_list' => $failedList,
        ]);
    }

    /**
     * Report: paginated list of sent emails (who received, when, template, status).
     */
    public function getSentReport(Request $request)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $perPage = min((int) $request->input('per_page', 20), 100);
        $query = SentCommunication::query()
            ->where('type', 'email')
            ->with(['customer:id,name,email,type', 'sender:id,name'])
            ->orderByDesc('sent_at');

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $paginator = $query->paginate($perPage);

        // Summary: total sent vs failed (same filter)
        $summaryQuery = SentCommunication::query()->where('type', 'email');
        if ($request->filled('date_from')) {
            $summaryQuery->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryQuery->whereDate('sent_at', '<=', $request->date_to);
        }
        $totalSent = (clone $summaryQuery)->where('status', 'sent')->count();
        $totalFailed = (clone $summaryQuery)->where('status', 'failed')->count();

        $templateIds = collect($paginator->items())->pluck('template_id')->filter()->unique()->values()->all();
        $templates = $templateIds ? EmailTemplate::whereIn('id', $templateIds)->pluck('name', 'id') : collect();

        $items = collect($paginator->items())->map(function ($row) use ($templates) {
            return [
                'id' => $row->id,
                'recipient_name' => $row->customer?->name ?? '—',
                'recipient_email' => $row->recipient_email,
                'customer_type' => $row->customer?->type,
                'template_name' => $templates[$row->template_id] ?? 'Template #' . $row->template_id,
                'subject' => $row->subject,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'sent_at' => $row->sent_at?->format('c'),
                'sent_by_name' => $row->sender?->name,
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'summary' => [
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
            ],
        ]);
    }

    /**
     * Upload a CSV of emails; creates a new email list and saves recipients.
     * CSV: first row can be header (email, name); then one email per row (email required).
     */
    public function uploadList(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'template_id' => 'nullable|integer|exists:email_templates,id',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $rows = array_map('str_getcsv', file($path));
        if (empty($rows)) {
            return response()->json(['message' => 'CSV file is empty'], 422);
        }

        $header = array_map('strtolower', array_map('trim', $rows[0]));
        $emailIdx = array_search('email', $header);
        if ($emailIdx === false) {
            $emailIdx = 0;
        }
        $nameIdx = array_search('name', $header);
        if ($nameIdx === false && count($header) > 1) {
            $nameIdx = 1;
        }

        $emailList = EmailList::create([
            'name' => $request->name,
            'original_file_name' => $file->getClientOriginalName(),
            'template_id' => $request->filled('template_id') ? (int) $request->template_id : null,
            'created_by' => auth()->id(),
        ]);

        $seen = [];
        $count = 0;
        foreach (array_slice($rows, 1) as $row) {
            $email = trim($row[$emailIdx] ?? $row[0] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $name = $nameIdx !== false ? trim($row[$nameIdx] ?? '') : null;
            EmailListRecipient::create([
                'email_list_id' => $emailList->id,
                'email' => $email,
                'name' => $name ?: null,
            ]);
            $count++;
        }
        if ($count === 0 && count($rows) > 1) {
            $emailList->delete();
            return response()->json(['message' => 'No valid email addresses found in CSV'], 422);
        }

        $emailList->load('template:id,name');
        return response()->json([
            'message' => 'List uploaded successfully',
            'list' => [
                'id' => $emailList->id,
                'name' => $emailList->name,
                'template_id' => $emailList->template_id,
                'template_name' => $emailList->template?->name,
                'recipients_count' => $count,
                'created_at' => $emailList->created_at->format('c'),
            ],
        ], 201);
    }

    /**
     * List all uploaded email lists (paginated).
     */
    public function listLists(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $lists = EmailList::withCount([
            'recipients',
            'recipients as sent_count' => fn ($q) => $q->where('status', EmailListRecipient::STATUS_SENT),
            'recipients as failed_count' => fn ($q) => $q->where('status', EmailListRecipient::STATUS_FAILED),
        ])->with('creator:id,name')->with('template:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = $lists->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'original_file_name' => $list->original_file_name,
                'template_id' => $list->template_id,
                'template_name' => $list->template?->name,
                'total' => $list->recipients_count ?? 0,
                'sent_count' => $list->sent_count ?? 0,
                'failed_count' => $list->failed_count ?? 0,
                'created_at' => $list->created_at->format('c'),
                'created_by' => $list->creator?->name,
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $lists->total(),
            'current_page' => $lists->currentPage(),
            'per_page' => $lists->perPage(),
            'last_page' => $lists->lastPage(),
        ]);
    }

    /**
     * Get one email list with summary.
     */
    public function getList($id)
    {
        $list = EmailList::withCount([
            'recipients',
            'recipients as sent_count' => fn ($q) => $q->where('status', EmailListRecipient::STATUS_SENT),
            'recipients as failed_count' => fn ($q) => $q->where('status', EmailListRecipient::STATUS_FAILED),
        ])->with('creator:id,name')->with('template:id,name')->findOrFail($id);

        return response()->json([
            'id' => $list->id,
            'name' => $list->name,
            'original_file_name' => $list->original_file_name,
            'template_id' => $list->template_id,
            'template_name' => $list->template?->name,
            'total' => $list->recipients_count ?? 0,
            'sent_count' => $list->sent_count ?? 0,
            'failed_count' => $list->failed_count ?? 0,
            'created_at' => $list->created_at->format('c'),
            'created_by' => $list->creator?->name,
        ]);
    }

    /**
     * Get recipients of an email list (paginated).
     */
    public function getListRecipients(Request $request, $id)
    {
        $list = EmailList::findOrFail($id);
        $perPage = min((int) $request->input('per_page', 50), 100);
        $paginator = $list->recipients()->orderBy('id')->paginate($perPage, ['id', 'email', 'name', 'status', 'error_message', 'sent_at']);

        $items = collect($paginator->items())->map(function ($r) {
            return [
                'id' => $r->id,
                'email' => $r->email,
                'name' => $r->name,
                'status' => $r->status,
                'error_message' => $r->error_message,
                'sent_at' => $r->sent_at?->format('c'),
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * Send template email to all recipients in an uploaded list.
     */
    public function sendToList(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'email_list_id' => 'required|exists:email_lists,id',
        ]);

        $template = EmailTemplate::findOrFail($request->template_id);
        $list = EmailList::findOrFail($request->email_list_id);
        $recipients = $list->recipients()->get();

        \App\Services\MailConfigFromDatabase::apply();

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($recipients as $recipient) {
            if (\App\Models\EmailUnsubscribe::isUnsubscribed($recipient->email)) {
                continue;
            }
            $fakeCustomer = $this->fakeCustomerForRecipient($recipient->email, $recipient->name);
            [$valid, $missingVars] = $this->validateRecipientForTemplate($fakeCustomer, $template);
            if (!$valid) {
                $skipped++;
                $errorMsg = 'Skipped: missing data for variable(s): ' . implode(', ', $missingVars);
                $recipient->update([
                    'status' => EmailListRecipient::STATUS_FAILED,
                    'error_message' => $errorMsg,
                ]);
                SentCommunication::create([
                    'type' => 'email',
                    'template_type' => 'email_template',
                    'template_id' => $template->id,
                    'customer_id' => null,
                    'recipient_email' => $recipient->email,
                    'subject' => $template->subject ?? '',
                    'content' => '',
                    'status' => 'failed',
                    'error_message' => $errorMsg,
                    'sent_by' => auth()->id(),
                ]);
                continue;
            }
            try {
                $subject = $this->replaceVariables($template->subject, $fakeCustomer);
                $content = $this->renderTemplateForPreview($template, $fakeCustomer);
                \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($recipient, $subject, $content) {
                    $message->to($recipient->email)
                        ->subject($subject)
                        ->html($content);
                });
                SentCommunication::create([
                    'type' => 'email',
                    'template_type' => 'email_template',
                    'template_id' => $template->id,
                    'customer_id' => null,
                    'recipient_email' => $recipient->email,
                    'subject' => $subject,
                    'content' => $content,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'sent_by' => auth()->id(),
                ]);
                $recipient->update(['status' => EmailListRecipient::STATUS_SENT, 'sent_at' => now()]);
                $sent++;
            } catch (\Exception $e) {
                $recipient->update([
                    'status' => EmailListRecipient::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                SentCommunication::create([
                    'type' => 'email',
                    'template_type' => 'email_template',
                    'template_id' => $template->id,
                    'customer_id' => null,
                    'recipient_email' => $recipient->email,
                    'subject' => $template->subject ?? '',
                    'content' => '',
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'sent_by' => auth()->id(),
                ]);
                $failed++;
            }
        }

        $msg = "Sent: {$sent}, Failed: {$failed}";
        if ($skipped > 0) {
            $msg .= ", Skipped (missing data): {$skipped}";
        }
        return response()->json([
            'message' => $msg,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $recipients->count(),
        ]);
    }

    /**
     * Extract variables used in template (e.g. {{customer_name}}).
     */
    private function getVariablesUsedInTemplate(EmailTemplate $template): array
    {
        $text = $template->subject ?? '';
        foreach ($template->content['sections'] ?? [] as $section) {
            $content = $section['content'] ?? [];
            if (($section['type'] ?? '') === 'text' && !empty($content['blocks'])) {
                foreach ($content['blocks'] as $block) {
                    $text .= ' ' . ($block['text'] ?? '');
                }
            } else {
                foreach (['text', 'left_text', 'right_text'] as $key) {
                    if (!empty($content[$key])) {
                        $text .= ' ' . $content[$key];
                    }
                }
            }
        }
        preg_match_all('/\{\{(\w+)\}\}/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Variables that require non-empty data. If used in template and empty, skip send.
     */
    private const REQUIRED_VARIABLES = ['customer_name', 'first_name', 'customer_email'];

    /**
     * Validate that recipient has non-empty data for required variables used in template.
     * Returns [valid: bool, missingVars: string[]].
     */
    private function validateRecipientForTemplate($customer, EmailTemplate $template): array
    {
        $used = $this->getVariablesUsedInTemplate($template);
        $missing = [];

        foreach ($used as $var) {
            if (!in_array($var, self::REQUIRED_VARIABLES, true)) {
                continue;
            }
            $value = $this->getVariableValueForValidation($var, $customer);
            if ($value === null || trim((string) $value) === '') {
                $missing[] = '{{' . $var . '}}';
            }
        }

        return [empty($missing), $missing];
    }

    /**
     * Get variable value for validation (same logic as replaceVariables but returns raw value).
     */
    private function getVariableValueForValidation(string $var, $customer)
    {
        switch ($var) {
            case 'customer_name':
                return $customer->name ?? null;
            case 'first_name':
                $name = $customer->name ?? '';
                return trim(explode(' ', $name)[0] ?? '');
            case 'customer_email':
                return $customer->email ?? null;
            case 'customer_phone':
                return $customer->phone ?? null;
            case 'company_name':
            case 'company_phone':
            case 'company_address':
                $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
                    'company_name', 'company_phone', 'company_address'
                ])->pluck('value', 'key');
                return $settings[$var] ?? null;
            case 'prospect_products':
            case 'customer_products':
                if (!$customer->id) {
                    return '';
                }
                $customer->loadMissing(['leads.items.product']);
                $prospect = [];
                $cust = [];
                foreach ($customer->leads ?? [] as $lead) {
                    foreach ($lead->items ?? [] as $item) {
                        $name = $item->product->name ?? null;
                        if (!$name) continue;
                        if ($lead->stage === 'won' && $item->status === 'won') {
                            $cust[$name] = true;
                        } else {
                            $prospect[$name] = true;
                        }
                    }
                }
                return $var === 'prospect_products' ? implode(', ', array_keys($prospect)) : implode(', ', array_keys($cust));
            case 'unsubscribe_url':
                return $customer->email ? 'ok' : null;
            default:
                return null;
        }
    }

    /**
     * Create a minimal Customer-like object for template variable replacement (uploaded list recipients).
     */
    private function fakeCustomerForRecipient(string $email, ?string $name = null): Customer
    {
        $c = new Customer([
            'name' => $name ?? 'Recipient',
            'email' => $email,
            'phone' => '',
            'business_name' => '',
            'type' => 'customer',
        ]);
        $c->id = 0;
        $c->setRelation('leads', collect());
        return $c;
    }

    private function applyHasProduct($query, int $productId, string $audience): void
    {
        if ($audience === 'prospect') {
            $query->whereHas('leads', function ($q) use ($productId) {
                $q->where(function ($q2) use ($productId) {
                    $q2->where('product_id', $productId)
                        ->orWhereHas('items', function ($q3) use ($productId) {
                            $q3->where('product_id', $productId);
                        });
                });
            });
        } elseif ($audience === 'customer') {
            $query->whereHas('leads', function ($q) use ($productId) {
                $q->where('stage', 'won')->where(function ($q2) use ($productId) {
                    $q2->where('product_id', $productId)
                        ->orWhereHas('items', function ($q3) use ($productId) {
                            $q3->where('product_id', $productId)->where('status', LeadItem::STATUS_WON);
                        });
                });
            });
        } else {
            // both: prospect interested in X OR customer has (won) X
            $query->where(function ($q) use ($productId) {
                $q->where('type', Customer::TYPE_PROSPECT)->whereHas('leads', function ($q2) use ($productId) {
                    $q2->where(function ($q3) use ($productId) {
                        $q3->where('product_id', $productId)
                            ->orWhereHas('items', fn ($q4) => $q4->where('product_id', $productId));
                    });
                })->orWhere('type', Customer::TYPE_CUSTOMER)->whereHas('leads', function ($q2) use ($productId) {
                    $q2->where('stage', 'won')->where(function ($q3) use ($productId) {
                        $q3->where('product_id', $productId)
                            ->orWhereHas('items', fn ($q4) => $q4->where('product_id', $productId)->where('status', LeadItem::STATUS_WON));
                    });
                });
            });
        }
    }

    private function applyDoesNotHaveProduct($query, int $productId, string $audience): void
    {
        if ($audience === 'prospect') {
            $query->whereDoesntHave('leads', function ($q) use ($productId) {
                $q->where(function ($q2) use ($productId) {
                    $q2->where('product_id', $productId)
                        ->orWhereHas('items', fn ($q3) => $q3->where('product_id', $productId));
                });
            });
        } elseif ($audience === 'customer') {
            $query->whereDoesntHave('leads', function ($q) use ($productId) {
                $q->where('stage', 'won')->where(function ($q2) use ($productId) {
                    $q2->where('product_id', $productId)
                        ->orWhereHas('items', fn ($q3) => $q3->where('product_id', $productId)->where('status', LeadItem::STATUS_WON));
                });
            });
        } else {
            $query->where(function ($q) use ($productId) {
                $q->where('type', Customer::TYPE_PROSPECT)->whereDoesntHave('leads', function ($q2) use ($productId) {
                    $q2->where(function ($q3) use ($productId) {
                        $q3->where('product_id', $productId)
                            ->orWhereHas('items', fn ($q4) => $q4->where('product_id', $productId));
                    });
                })->orWhere('type', Customer::TYPE_CUSTOMER)->whereDoesntHave('leads', function ($q2) use ($productId) {
                    $q2->where('stage', 'won')->where(function ($q3) use ($productId) {
                        $q3->where('product_id', $productId)
                            ->orWhereHas('items', fn ($q4) => $q4->where('product_id', $productId)->where('status', LeadItem::STATUS_WON));
                    });
                });
            });
        }
    }

    private function getSampleCustomerForPreview(): Customer
    {
        $customer = Customer::with(['leads.items.product', 'leads.product'])
            ->whereNotNull('email')->where('email', '!=', '')->first();
        if ($customer) {
            return $customer;
        }
        $sample = new Customer([
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'phone' => '+44 7700 900000',
            'business_name' => 'Sample Business',
            'type' => 'customer',
        ]);
        $sample->id = 0;
        return $sample;
    }

    private function replaceVariables(string $text, Customer $customer): string
    {
        $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_name', 'company_phone', 'company_address', 'company_registration_no', 'company_vat'
        ])->pluck('value', 'key');

        if ($customer->id && $customer->relationLoaded('leads') === false) {
            $customer->load(['leads.items.product']);
        }
        $prospectProductNames = [];
        $customerProductNames = [];
        if ($customer->id && $customer->leads) {
            foreach ($customer->leads as $lead) {
                foreach ($lead->items ?? [] as $item) {
                    $name = $item->product->name ?? null;
                    if (!$name) continue;
                    if ($lead->stage === 'won' && $item->status === 'won') {
                        $customerProductNames[$name] = true;
                    } else {
                        $prospectProductNames[$name] = true;
                    }
                }
                if ($lead->product && $lead->stage !== 'won') {
                    $prospectProductNames[$lead->product->name] = true;
                }
            }
        }
        $prospectProducts = implode(', ', array_keys($prospectProductNames));
        $customerProducts = implode(', ', array_keys($customerProductNames));

        $unsubscribeUrl = config('app.url') . '/unsubscribe?email=' . urlencode($customer->email ?? '');
        $extra = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_website', 'logo_url',
            'social_facebook_url', 'social_linkedin_url', 'social_instagram_url', 'social_tiktok_url',
        ])->pluck('value', 'key');
        $settings = $settings->merge($extra);
        $appUrl = rtrim((string) config('app.url'), '/');
        $rawLogo = trim((string) ($settings['logo_url'] ?? ''));
        $logoSrc = '';
        if ($rawLogo !== '') {
            $logoSrc = $rawLogo;
            if (! str_starts_with($logoSrc, 'http')) {
                if (($logoSrc[0] ?? '') !== '/') {
                    $logoSrc = '/' . $logoSrc;
                }
                $logoSrc = $appUrl . $logoSrc;
            }
        }

        $welcomeLogoPath = public_path('images/email/welcome/main-logo.png');
        $defaultWelcomeLogoUrl = is_file($welcomeLogoPath)
            ? asset('images/email/welcome/main-logo.png')
            : '';

        if ($defaultWelcomeLogoUrl !== '') {
            $headerLogoUrl = $defaultWelcomeLogoUrl;
        } elseif ($logoSrc !== '') {
            $headerLogoUrl = $logoSrc;
        } else {
            $headerLogoUrl = $appUrl . '/images/email/welcome/main-logo.png';
        }

        $welcomeSlash = $defaultWelcomeLogoUrl !== '' ? strrpos($defaultWelcomeLogoUrl, '/') : false;
        $emailWelcomeDirUrl = $welcomeSlash !== false
            ? substr($defaultWelcomeLogoUrl, 0, $welcomeSlash)
            : ($appUrl . '/images/email/welcome');

        $text = (string) $text;

        return str_replace(
            [
                '{{customer_name}}',
                '{{first_name}}',
                '{{customer_email}}',
                '{{customer_phone}}',
                '{{company_name}}',
                '{{company_phone}}',
                '{{company_address}}',
                '{{prospect_products}}',
                '{{customer_products}}',
                '{{unsubscribe_url}}',
                '{{app_url}}',
                '{{logo_src}}',
                '{{header_logo_url}}',
                '{{email_welcome_dir_url}}',
                '{{company_website}}',
                '{{social_facebook_url}}',
                '{{social_linkedin_url}}',
                '{{social_instagram_url}}',
                '{{social_tiktok_url}}',
                '{{current_year}}',
            ],
            [
                $customer->name ?? '',
                trim(explode(' ', $customer->name ?? '')[0] ?? ''),
                $customer->email ?? '',
                $customer->phone ?? '',
                $settings['company_name'] ?? 'Switch & Save',
                $settings['company_phone'] ?? '',
                $settings['company_address'] ?? '',
                $prospectProducts ?: '—',
                $customerProducts ?: '—',
                $unsubscribeUrl,
                $appUrl,
                $logoSrc,
                $headerLogoUrl,
                $emailWelcomeDirUrl,
                $this->mergeTagOutboundUrl($settings['company_website'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_facebook_url'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_linkedin_url'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_instagram_url'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_tiktok_url'] ?? ''),
                (string) now()->year,
            ],
            $text
        );
    }

    private function mergeTagOutboundUrl(?string $url): string
    {
        $u = trim((string) $url);
        if ($u === '') {
            return '#';
        }
        if (! preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }

        return $u;
    }

    private function buildBrandFooter(string $unsubscribeUrl, bool $hasCustomFooter = false): string
    {
        $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_name', 'company_phone', 'company_address', 'company_registration_no', 'company_vat',
            'company_website', 'social_facebook_url', 'social_twitter_url', 'social_linkedin_url', 'social_instagram_url', 'social_tiktok_url',
        ])->pluck('value', 'key');
        $companyName = e($settings['company_name'] ?? 'Switch & Save');
        $html = '<div class="fluid-padding" style="text-align:center;padding:24px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;word-wrap:break-word;overflow-wrap:break-word;">';

        // If template already has a custom footer section, keep this minimal to avoid duplicated company block
        if (!$hasCustomFooter) {
            $html .= '<p style="margin:4px 0;font-weight:600;color:#475569;">' . $companyName . '</p>';
            if (!empty($settings['company_address'])) {
                $html .= '<p style="margin:4px 0;">' . nl2br(e($settings['company_address'])) . '</p>';
            }
            if (!empty($settings['company_phone'])) {
                $html .= '<p style="margin:4px 0;">📞 ' . e($settings['company_phone']) . '</p>';
            }
            if (!empty($settings['company_website'])) {
                $html .= '<p style="margin:4px 0;"><a href="' . e($settings['company_website']) . '" style="color:#0284c7;text-decoration:none;">' . e($settings['company_website']) . '</a></p>';
            }
            $socialUrls = [
                'social_facebook_url' => 'Facebook',
                'social_twitter_url' => 'Twitter',
                'social_linkedin_url' => 'LinkedIn',
                'social_instagram_url' => 'Instagram',
                'social_tiktok_url' => 'TikTok',
            ];
            $hasSocial = false;
            foreach ($socialUrls as $key => $label) {
                if (!empty($settings[$key])) {
                    if (!$hasSocial) {
                        $html .= '<p style="margin:12px 0 4px;">Follow us:</p>';
                        $hasSocial = true;
                    }
                    $url = $settings[$key];
                    if (!preg_match('#^https?://#', $url)) {
                        $url = 'https://' . $url;
                    }
                    $html .= '<a href="' . e($url) . '" style="display:inline-block;margin:0 6px;color:#0284c7;text-decoration:none;">' . e($label) . '</a>';
                }
            }
            if (!empty($settings['company_registration_no'])) {
                $html .= '<p style="margin:8px 0 4px;">Company No: ' . e($settings['company_registration_no']) . '</p>';
            }
            if (!empty($settings['company_vat'])) {
                $html .= '<p style="margin:4px 0;">VAT: ' . e($settings['company_vat']) . '</p>';
            }
        }

        $html .= '<p style="margin:16px 0 0;"><a href="' . e($unsubscribeUrl) . '" style="color:#94a3b8;text-decoration:underline;font-size:11px;">Unsubscribe from marketing emails</a></p>';
        $html .= '</div>';
        return $html;
    }

    private function renderTemplateForPreview(EmailTemplate $template, Customer $customer): string
    {
        $unsubscribeUrl = config('app.url') . '/unsubscribe?email=' . urlencode($customer->email ?? '');
        $responsiveStyles = '
<style type="text/css">
/* Base: iOS, Android, Gmail, Apple Mail, Samsung */
html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
body { margin: 0 !important; padding: 0 !important; width: 100% !important; overflow-x: hidden !important; -webkit-text-size-adjust: 100%; }
img { max-width: 100% !important; height: auto !important; border: 0; vertical-align: middle; -ms-interpolation-mode: bicubic; }
a { text-decoration: none; -webkit-tap-highlight-color: rgba(2, 132, 199, 0.2); }
table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
@media only screen and (max-width: 620px) {
  .email-wrapper { width: 100% !important; min-width: 0 !important; padding: 12px 15px !important; box-sizing: border-box !important; overflow-x: hidden !important; }
  .two-col { display: block !important; width: 100% !important; }
  .two-col > div { width: 100% !important; display: block !important; padding: 8px 0 !important; }
  .fluid-txt { font-size: 16px !important; line-height: 1.5 !important; }
  .fluid-txt-lg { font-size: 20px !important; }
  .fluid-padding { padding: 12px 15px !important; }
  .btn-block { display: block !important; width: 100% !important; min-height: 44px !important; text-align: center !important; padding: 14px 20px !important; box-sizing: border-box !important; }
  .header-txt { font-size: 22px !important; }
  .welcome-three-col > tbody > tr > td { display: block !important; width: 100% !important; max-width: 100% !important; padding: 6px 0 !important; box-sizing: border-box !important; }
  .welcome-offer-row > tbody > tr > td { display: block !important; width: 100% !important; max-width: 100% !important; text-align: center !important; padding: 10px 0 !important; box-sizing: border-box !important; }
}
</style>';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"><meta http-equiv="X-UA-Compatible" content="IE=edge"><title>Email</title>' . $responsiveStyles . '</head><body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,Roboto,Arial,sans-serif;line-height:1.6;color:#333;width:100%;-webkit-text-size-adjust:100%;overflow-x:hidden;">';
        $html .= '<div class="email-wrapper" style="max-width:600px;width:100%;margin:0 auto;padding:20px;background:#fff;box-sizing:border-box;">';

        $previewLine = trim($template->content['preview_line'] ?? '');
        if ($previewLine !== '') {
            $previewText = $this->replaceVariables($previewLine, $customer);
            $html .= '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:transparent;">' . e($previewText) . '</div>';
        }

        $sections = $template->content['sections'] ?? [];
        $hasCustomFooter = false;
        foreach ($sections as $section) {
            if (($section['type'] ?? '') === 'footer') {
                $hasCustomFooter = true;
            }
            $html .= $this->renderSection($section, $customer);
        }
        if (empty($template->content['skip_brand_footer'])) {
            $html .= $this->buildBrandFooter($unsubscribeUrl, $hasCustomFooter);
        }
        $html .= '</div></body></html>';
        return $html;
    }

    private function buildSectionTextStyle(array $content): string
    {
        $parts = [];
        if (!empty($content['font_family'])) {
            $parts[] = 'font-family:' . e($content['font_family']);
        }
        if (!empty($content['font_size'])) {
            $parts[] = 'font-size:' . e($content['font_size']);
        }
        if (!empty($content['font_color'])) {
            $color = preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $content['font_color']) ? $content['font_color'] : '#333333';
            $parts[] = 'color:' . e($color);
        }
        return implode(';', $parts);
    }

    private function renderSection(array $section, Customer $customer): string
    {
        $content = $section['content'] ?? [];
        switch ($section['type'] ?? '') {
            case 'raw_html':
                return $this->replaceVariables($content['html'] ?? '', $customer);
            case 'header':
                $logoUrl = $content['logo'] ?? null;
                if (empty($logoUrl)) {
                    $logoSetting = \App\Modules\Settings\Models\Setting::where('key', 'logo_url')->first();
                    if ($logoSetting && $logoSetting->value) {
                        $logoUrl = $logoSetting->value;
                    }
                }
                if (!empty($logoUrl) && !str_starts_with($logoUrl, 'http')) {
                    if ($logoUrl[0] !== '/') {
                        $logoUrl = '/' . $logoUrl;
                    }
                    $logoUrl = rtrim(config('app.url'), '/') . $logoUrl;
                }
                $text = $this->replaceVariables($content['text'] ?? '', $customer);
                $logoHtml = $logoUrl ? '<img src="' . e($logoUrl) . '" alt="Logo" style="max-height:50px;max-width:200px;width:auto;height:auto;margin-bottom:10px;">' : '';
                $textStyle = $this->buildSectionTextStyle($content);
                $h1Style = 'margin:0;font-size:24px;word-wrap:break-word;overflow-wrap:break-word;' . ($textStyle ? $textStyle . ';' : '');
                return '<div class="fluid-padding" style="text-align:center;padding:20px;background:linear-gradient(135deg,#1e293b 0%,#334155 100%);color:white;">' . $logoHtml . '<h1 class="header-txt" style="' . $h1Style . '">' . e($text) . '</h1></div>';
            case 'text':
                $blocks = $content['blocks'] ?? null;
                $sectionTextStyle = $this->buildSectionTextStyle($content);
                if ($blocks && is_array($blocks)) {
                    $parts = [];
                    foreach ($blocks as $block) {
                        $blockText = $this->replaceVariables($block['text'] ?? '', $customer);
                        $style = $sectionTextStyle ? ' style="' . $sectionTextStyle . ';margin:0 0 8px 0;word-wrap:break-word;overflow-wrap:break-word;max-width:100%;"' : ' style="margin:0 0 8px 0;word-wrap:break-word;overflow-wrap:break-word;max-width:100%;"';
                        $parts[] = '<p class="fluid-txt"' . $style . '>' . nl2br(e($blockText)) . '</p>';
                    }
                    return '<div class="fluid-padding" style="padding:20px;">' . implode('', $parts) . '</div>';
                }
                $text = $this->replaceVariables($content['text'] ?? '', $customer);
                $textStyle = $sectionTextStyle;
                $pStyle = 'padding:20px;margin:0;word-wrap:break-word;overflow-wrap:break-word;max-width:100%;' . ($textStyle ? $textStyle . ';' : '');
                return '<p class="fluid-txt fluid-padding" style="' . $pStyle . '">' . nl2br(e($text)) . '</p>';
            case 'image':
                if (!empty($content['image_url'])) {
                    $imageUrl = $content['image_url'];
                    if (!str_starts_with($imageUrl, 'http')) {
                        if ($imageUrl[0] !== '/') {
                            $imageUrl = '/' . $imageUrl;
                        }
                        $imageUrl = rtrim(config('app.url'), '/') . $imageUrl;
                    }
                    return '<div style="text-align:center;padding:20px;"><img src="' . e($imageUrl) . '" alt="" style="max-width:100%;height:auto;"></div>';
                }
                return '';
            case 'button':
                $text = $this->replaceVariables($content['text'] ?? 'Click Here', $customer);
                $url = $content['url'] ?? '#';
                $btnStyle = $this->buildSectionTextStyle($content);
                $aStyle = 'display:inline-block;padding:12px 24px;background:#0284c7;color:white;text-decoration:none;border-radius:6px;word-wrap:break-word;max-width:100%;box-sizing:border-box;' . ($btnStyle ? $btnStyle . ';' : '');
                return '<div class="fluid-padding" style="text-align:center;padding:20px;"><a href="' . e($url) . '" class="btn-block" style="' . $aStyle . '">' . e($text) . '</a></div>';
            case 'two_column':
                $left = nl2br(e($this->replaceVariables($content['left_text'] ?? '', $customer)));
                $right = nl2br(e($this->replaceVariables($content['right_text'] ?? '', $customer)));
                $colStyle = $this->buildSectionTextStyle($content);
                $colAttr = $colStyle ? ' style="' . $colStyle . 'word-wrap:break-word;overflow-wrap:break-word;min-width:0;"' : ' style="word-wrap:break-word;overflow-wrap:break-word;min-width:0;"';
                return '<div class="two-col fluid-padding" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:20px;width:100%;"><div class="fluid-txt"' . $colAttr . '>' . $left . '</div><div class="fluid-txt"' . $colAttr . '>' . $right . '</div></div>';
            case 'footer':
                $settings = \App\Modules\Settings\Models\Setting::whereIn('key', ['company_name', 'company_phone'])->pluck('value', 'key');
                $footerStyle = $this->buildSectionTextStyle($content);
                $pStyle = 'margin:4px 0;color:#64748b;font-size:14px;word-wrap:break-word;overflow-wrap:break-word;' . ($footerStyle ? $footerStyle . ';' : '');
                return '<div class="fluid-padding" style="text-align:center;padding:16px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;word-wrap:break-word;overflow-wrap:break-word;"><p style="' . $pStyle . '">' . e($settings['company_name'] ?? '') . '</p></div>';
            default:
                return '';
        }
    }
}
