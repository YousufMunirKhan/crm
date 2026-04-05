<?php

namespace App\Http\Controllers;

use App\Models\SentCommunication;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Models\WhatsAppTemplate;
use App\Modules\Communication\Services\WhatsAppServiceV2;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\LeadItem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppManagementController extends Controller
{
    public function __construct(
        private WhatsAppServiceV2 $whatsappService
    ) {}

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

    public function whatsappStatus()
    {
        $row = WhatsAppSetting::query()->orderByDesc('id')->first();
        $token = $row?->access_token;
        $configured = $row && $row->is_enabled && $token && trim($token) !== '';

        return response()->json([
            'configured' => $configured,
            'message' => $configured
                ? 'WhatsApp Cloud API is enabled. Templates must be approved in Meta.'
                : 'Enable WhatsApp and add your access token under Settings (or WhatsApp settings).',
        ]);
    }

    /**
     * Approved templates for bulk UI dropdown.
     */
    public function approvedTemplates()
    {
        $items = WhatsAppTemplate::approved()
            ->orderBy('name')
            ->get(['id', 'name', 'language', 'category']);

        return response()->json(['data' => $items]);
    }

    private function buildFilteredQuery(Request $request)
    {
        $audience = $request->input('audience');
        $productFilters = $request->input('product_filters', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = Customer::query()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('whatsapp_number')->where('whatsapp_number', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('phone')->where('phone', '!=', '');
                });
            });

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
        $query = $this->buildFilteredQuery($request);
        $paginator = $query->orderBy('name')->paginate($perPage, ['id', 'name', 'phone', 'whatsapp_number', 'business_name', 'type']);

        return response()->json([
            'contacts' => collect($paginator->items())->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'whatsapp_number' => $c->whatsapp_number,
                'display_phone' => $c->whatsapp_number ?: $c->phone,
                'business_name' => $c->business_name,
                'type' => $c->type,
            ])->values()->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

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

        $contacts = $this->buildFilteredQuery($request)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'whatsapp_number', 'business_name', 'type']);

        $filename = 'whatsapp-contacts-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($contacts) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'WhatsApp number', 'Phone', 'Business Name', 'Type']);
            foreach ($contacts as $c) {
                fputcsv($out, [
                    $c->id,
                    $c->name,
                    $c->whatsapp_number ?? '',
                    $c->phone ?? '',
                    $c->business_name ?? '',
                    $c->type,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function previewTemplate(int $templateId)
    {
        $template = WhatsAppTemplate::approved()->findOrFail($templateId);
        $sample = Customer::query()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('whatsapp_number')->where('whatsapp_number', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('phone')->where('phone', '!=', '');
                });
            })
            ->orderBy('id')
            ->first();

        $preview = $this->whatsappService->previewTemplatePayload(
            $template->name,
            [],
            $template->language,
            null,
            $sample,
            null
        );

        return response()->json(array_merge($preview, [
            'template_name' => $template->name,
            'template_id' => $template->id,
        ]));
    }

    public function sendBulk(Request $request)
    {
        $request->validate([
            'whatsapp_template_id' => 'required|integer|exists:whatsapp_templates,id',
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

        $template = WhatsAppTemplate::where('id', $request->integer('whatsapp_template_id'))
            ->where('status', 'APPROVED')
            ->firstOrFail();

        $query = $this->buildFilteredQuery($request);
        $exclude = $this->normalizeExcludeCustomerIds($request->input('exclude_customer_ids'));
        if ($exclude !== []) {
            $query->whereNotIn('id', $exclude);
        }

        $sent = 0;
        $failed = 0;
        $failedList = [];
        $n = 0;

        $query->orderBy('id')->chunk(50, function ($contacts) use ($template, &$sent, &$failed, &$failedList, &$n) {
            foreach ($contacts as $customer) {
                if ($n > 0 && $n % 10 === 0) {
                    usleep(200000);
                }
                $n++;

                $phone = $customer->whatsapp_number ?? $customer->phone;
                if (!$phone || trim((string) $phone) === '') {
                    $failed++;
                    $failedList[] = ['name' => $customer->name, 'phone' => '—', 'error' => 'No WhatsApp or phone number'];
                    SentCommunication::create([
                        'type' => 'whatsapp',
                        'template_type' => 'whatsapp_template',
                        'template_id' => $template->id,
                        'customer_id' => $customer->id,
                        'recipient_phone' => null,
                        'subject' => $template->name,
                        'content' => '',
                        'status' => 'failed',
                        'error_message' => 'No WhatsApp or phone number',
                        'sent_by' => auth()->id(),
                    ]);

                    continue;
                }

                try {
                    $this->whatsappService->sendTemplateMessage(
                        $customer,
                        $template->name,
                        [],
                        $template->language ?: 'en_US',
                        null
                    );
                    SentCommunication::create([
                        'type' => 'whatsapp',
                        'template_type' => 'whatsapp_template',
                        'template_id' => $template->id,
                        'customer_id' => $customer->id,
                        'recipient_phone' => $phone,
                        'subject' => $template->name,
                        'content' => 'WhatsApp template message',
                        'status' => 'sent',
                        'sent_at' => now(),
                        'sent_by' => auth()->id(),
                    ]);
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                    $err = $e->getMessage();
                    if (strlen($err) > 500) {
                        $err = substr($err, 0, 500) . '…';
                    }
                    $failedList[] = ['name' => $customer->name, 'phone' => $phone, 'error' => $err];
                    SentCommunication::create([
                        'type' => 'whatsapp',
                        'template_type' => 'whatsapp_template',
                        'template_id' => $template->id,
                        'customer_id' => $customer->id,
                        'recipient_phone' => $phone,
                        'subject' => $template->name,
                        'content' => '',
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'sent_by' => auth()->id(),
                    ]);
                }
            }
        });

        $total = $sent + $failed;

        return response()->json([
            'message' => "Sent: {$sent}, Failed: {$failed}",
            'sent' => $sent,
            'failed' => $failed,
            'total' => $total,
            'failed_list' => array_slice($failedList, 0, 150),
        ]);
    }

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
            ->where('type', 'whatsapp')
            ->with(['customer:id,name,phone,whatsapp_number,type', 'sender:id,name'])
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at');

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $paginator = $query->paginate($perPage);

        $summaryQuery = SentCommunication::query()->where('type', 'whatsapp');
        if ($request->filled('date_from')) {
            $summaryQuery->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryQuery->whereDate('sent_at', '<=', $request->date_to);
        }
        $totalSent = (clone $summaryQuery)->where('status', 'sent')->count();
        $totalFailed = (clone $summaryQuery)->where('status', 'failed')->count();

        $templateIds = collect($paginator->items())->pluck('template_id')->filter()->unique()->values()->all();
        $templates = $templateIds
            ? WhatsAppTemplate::whereIn('id', $templateIds)->pluck('name', 'id')
            : collect();

        $items = collect($paginator->items())->map(function ($row) use ($templates) {
            return [
                'id' => $row->id,
                'recipient_name' => $row->customer?->name ?? '—',
                'recipient_phone' => $row->recipient_phone ?? $row->customer?->whatsapp_number ?? $row->customer?->phone ?? '—',
                'template_name' => $templates[$row->template_id] ?? ($row->template_id ? 'Template #' . $row->template_id : '—'),
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
