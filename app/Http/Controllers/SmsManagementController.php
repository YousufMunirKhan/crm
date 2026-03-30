<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Models\SentCommunication;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\LeadItem;
use App\Services\SmsService;
use Illuminate\Http\Request;

class SmsManagementController extends Controller
{
    public function __construct(
        private SmsService $smsService
    ) {}

    /**
     * Check SMS settings status (from Settings page).
     */
    public function smsStatus()
    {
        $apiKey = \App\Modules\Settings\Models\Setting::where('key', 'sms_api_key')->first()?->value;
        $secretKey = \App\Modules\Settings\Models\Setting::where('key', 'sms_secret_key')->first()?->value;
        $configured = !empty(trim($apiKey ?? '')) && !empty(trim($secretKey ?? ''));
        return response()->json([
            'configured' => $configured,
            'message' => $configured ? 'SMS settings are configured from Settings → SMS.' : 'Please configure SMS (VoodooSMS API Key & Secret) in Settings → SMS before sending messages.',
        ]);
    }

    /**
     * Build the same base query as EmailManagementController but for contacts with phone.
     */
    private function buildFilteredQuery(Request $request)
    {
        $audience = $request->input('audience');
        $productFilters = $request->input('product_filters', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

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

        foreach ($productFilters as $filter) {
            if (($filter['rule'] ?? '') === 'all') {
                continue;
            }
            $productId = (int) ($filter['product_id'] ?? 0);
            $rule = $filter['rule'] ?? '';
            if ($productId && $rule === 'has') {
                $query->where(function ($q) use ($productId, $audience) {
                    $this->applyHasProduct($q, $productId, $audience);
                });
            } elseif ($productId && $rule === 'does_not_have') {
                $query->where(function ($q) use ($productId, $audience) {
                    $this->applyDoesNotHaveProduct($q, $productId, $audience);
                });
            }
        }

        return $query;
    }

    /**
     * Get contacts matching the same filters as email, but with phone numbers.
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
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min((int) $request->input('per_page', 50), 100);
        $query = $this->buildFilteredQuery($request);
        $paginator = $query->orderBy('name')->paginate($perPage, ['id', 'name', 'phone', 'business_name', 'type']);

        return response()->json([
            'contacts' => collect($paginator->items())->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
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
     * Preview a message template with sample customer data.
     */
    public function previewTemplate(int $templateId)
    {
        $template = MessageTemplate::where('is_active', true)->findOrFail($templateId);
        $sample = $this->getSampleCustomerForPreview();
        $message = $this->replaceVariables($template->message, $sample);

        return response()->json([
            'message' => $message,
            'template_name' => $template->name,
        ]);
    }

    /**
     * Send bulk SMS to filtered contacts using the given template or custom message.
     */
    public function sendBulk(Request $request)
    {
        $request->validate([
            'template_id' => 'nullable|exists:message_templates,id',
            'message' => 'nullable|string|max:1600',
            'audience' => 'required|in:prospect,customer,both',
            'product_filters' => 'nullable|array',
            'product_filters.*.product_id' => 'required_with:product_filters|integer|exists:products,id',
            'product_filters.*.rule' => 'required_with:product_filters|in:has,does_not_have,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $templateId = $request->input('template_id');
        $customMessage = $request->input('message');

        if (!$templateId && empty(trim($customMessage ?? ''))) {
            return response()->json(['message' => 'Either template_id or message is required'], 422);
        }

        $template = $templateId ? MessageTemplate::find($templateId) : null;
        $query = $this->buildFilteredQuery($request);

        $sent = 0;
        $failed = 0;
        $failedList = [];

        $query->orderBy('id')->chunk(50, function ($contacts) use ($template, $customMessage, &$sent, &$failed, &$failedList) {
            foreach ($contacts as $customer) {
                $rawMessage = $template ? $template->message : trim($customMessage);
                $message = $this->replaceVariables($rawMessage, $customer);

                if (empty(trim($message))) {
                    $failed++;
                    $failedList[] = ['phone' => $customer->phone, 'name' => $customer->name, 'error' => 'Empty message after variable replacement'];
                    $this->logSmsSent($template, $customer, $message, 'failed', 'Empty message');
                    continue;
                }

                $result = $this->smsService->send($customer->phone, $message);

                if ($result['success']) {
                    $sent++;
                    $this->logSmsSent($template, $customer, $message, 'sent');
                } else {
                    $failed++;
                    $errMsg = strlen($result['message'] ?? '') > 200 ? substr($result['message'], 0, 200) . '...' : ($result['message'] ?? 'Unknown error');
                    $failedList[] = ['phone' => $customer->phone, 'name' => $customer->name, 'error' => $errMsg];
                    $this->logSmsSent($template, $customer, $message, 'failed', $result['message'] ?? '');
                }
            }
        });

        $total = $sent + $failed;
        $msg = "Sent: {$sent}, Failed: {$failed}";
        return response()->json([
            'message' => $msg,
            'sent' => $sent,
            'failed' => $failed,
            'total' => $total,
            'failed_list' => $failedList,
        ]);
    }

    /**
     * Report: paginated list of sent SMS.
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
            ->where('type', 'sms')
            ->with(['customer:id,name,phone,type', 'sender:id,name'])
            ->orderByDesc('sent_at');

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $paginator = $query->paginate($perPage);

        $summaryQuery = SentCommunication::query()->where('type', 'sms');
        if ($request->filled('date_from')) {
            $summaryQuery->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryQuery->whereDate('sent_at', '<=', $request->date_to);
        }
        $totalSent = (clone $summaryQuery)->where('status', 'sent')->count();
        $totalFailed = (clone $summaryQuery)->where('status', 'failed')->count();

        $templateIds = collect($paginator->items())->pluck('template_id')->filter()->unique()->values()->all();
        $templates = $templateIds ? MessageTemplate::whereIn('id', $templateIds)->pluck('name', 'id') : collect();

        $items = collect($paginator->items())->map(function ($row) use ($templates) {
            return [
                'id' => $row->id,
                'recipient_name' => $row->customer?->name ?? '—',
                'recipient_phone' => $row->recipient_phone ?? $row->customer?->phone ?? '—',
                'template_name' => $templates[$row->template_id] ?? ($row->template_id ? 'Template #' . $row->template_id : 'Custom'),
                'content' => \Illuminate\Support\Str::limit($row->content ?? '', 80),
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

    private function logSmsSent(?MessageTemplate $template, Customer $customer, string $message, string $status, ?string $errorMessage = null): void
    {
        SentCommunication::create([
            'type' => 'sms',
            'template_type' => 'message_template',
            'template_id' => $template?->id,
            'customer_id' => $customer->id,
            'recipient_phone' => $customer->phone,
            'subject' => $template?->name ?? 'SMS',
            'content' => $message,
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => $status === 'sent' ? now() : null,
            'sent_by' => auth()->id(),
        ]);
    }

    private function replaceVariables(string $text, Customer $customer): string
    {
        $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_name', 'company_phone', 'company_address',
        ])->pluck('value', 'key');

        $firstName = trim(explode(' ', $customer->name ?? '')[0] ?? '');

        return str_replace(
            [
                '{{customer_name}}',
                '{{first_name}}',
                '{{customer_phone}}',
                '{{customer_email}}',
                '{{company_name}}',
                '{{company_phone}}',
                '{{company_address}}',
            ],
            [
                $customer->name ?? '',
                $firstName,
                $customer->phone ?? '',
                $customer->email ?? '',
                $settings['company_name'] ?? '',
                $settings['company_phone'] ?? '',
                $settings['company_address'] ?? '',
            ],
            $text
        );
    }

    private function getSampleCustomerForPreview(): Customer
    {
        $customer = Customer::whereNotNull('phone')->where('phone', '!=', '')->first();
        if ($customer) {
            return $customer;
        }
        $sample = new Customer([
            'name' => 'John Smith',
            'phone' => '07700900123',
            'email' => 'john@example.com',
            'business_name' => 'Sample Business',
            'type' => 'customer',
        ]);
        $sample->id = 0;
        return $sample;
    }

    private function applyHasProduct($query, int $productId, string $audience): void
    {
        if ($audience === 'prospect') {
            $query->whereHas('leads', function ($q) use ($productId) {
                $q->where(function ($q2) use ($productId) {
                    $q2->where('product_id', $productId)
                        ->orWhereHas('items', fn ($q3) => $q3->where('product_id', $productId));
                });
            });
        } elseif ($audience === 'customer') {
            $query->whereHas('leads', function ($q) use ($productId) {
                $q->where('stage', 'won')->where(function ($q2) use ($productId) {
                    $q2->where('product_id', $productId)
                        ->orWhereHas('items', fn ($q3) => $q3->where('product_id', $productId)->where('status', LeadItem::STATUS_WON));
                });
            });
        } else {
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
}
