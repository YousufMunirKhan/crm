<?php

namespace App\Modules\Reporting\Services;

use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Communication\Models\Communication;
use App\Models\User;
use App\Modules\HR\Models\EmployeeTarget;
use App\Modules\HR\Models\EmployeeTargetLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportingService
{
    /**
     * Won line items credited to an agent (lead assignee OR customer-assigned rep).
     * Period: closed_at in range, or (legacy) closed_at null and lead created in range.
     */
    private function baseWonLeadItemsForAgentInPeriod(int $agentId, Carbon $from, Carbon $to): Builder
    {
        return LeadItem::query()
            ->where('status', LeadItem::STATUS_WON)
            ->where(function ($w) use ($from, $to) {
                $w->whereBetween('closed_at', [$from, $to])
                    ->orWhere(function ($o) use ($from, $to) {
                        $o->whereNull('closed_at')
                            ->whereHas('lead', fn ($l) => $l->whereBetween('created_at', [$from, $to]));
                    });
            })
            ->whereHas('lead', function ($q) use ($agentId) {
                $q->where(function ($subQ) use ($agentId) {
                    $subQ->where('assigned_to', $agentId)
                        ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agentId) {
                            $userQ->where('user_id', $agentId);
                        });
                });
            });
    }

    /**
     * Won line items for an agent on one calendar day (daily activity / export).
     */
    public function wonLeadItemsForAgentOnDate(int $agentId, Carbon $date): Collection
    {
        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();

        return $this->baseWonLeadItemsForAgentInPeriod($agentId, $from, $to)
            ->with(['product:id,name', 'lead:id,customer_id', 'lead.customer:id,name'])
            ->orderByRaw('COALESCE(closed_at, lead_items.created_at) ASC')
            ->get();
    }

    public function getExecutiveDashboard(array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $leadQuery = Lead::whereBetween('created_at', [$from, $to]);
        $invoiceQuery = Invoice::whereBetween('invoice_date', [$from, $to]);
        $ticketQuery = Ticket::whereBetween('created_at', [$from, $to]);

        if (isset($filters['agent_id'])) {
            $aid = $filters['agent_id'];
            $leadQuery->where('assigned_to', $aid);
            $ticketQuery->where(function ($q) use ($aid) {
                $q->where('assigned_to', $aid)
                    ->orWhereHas('assignees', function ($q2) use ($aid) {
                        $q2->where('users.id', $aid);
                    });
            });
        }

        $leadsCount = $leadQuery->count();
        
        // Count leads with at least one won item OR stage = won
        $wonLeadsCount = (clone $leadQuery)->where(function($q) {
            $q->where('stage', 'won')
              ->orWhereHas('items', function($itemQ) {
                  $itemQ->where('status', LeadItem::STATUS_WON);
              });
        })->count();
        
        $conversionRate = $leadsCount > 0 ? round($wonLeadsCount / $leadsCount * 100, 2) : 0;

        $pipelineValue = (clone $leadQuery)->whereNotIn('stage', ['won', 'lost'])->sum('pipeline_value');
        
        // Calculate revenue from WON items (status = 'won') in lead_items
        $wonItems = LeadItem::whereHas('lead', function($q) use ($from, $to, $filters) {
            $q->whereBetween('created_at', [$from, $to]);
            if (isset($filters['agent_id'])) {
                $q->where('assigned_to', $filters['agent_id']);
            }
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');
        
        $revenue = $wonItems;
        
        // Also add invoice revenue
        $revenue += (clone $invoiceQuery)->sum('total');

        $tickets = [
            'total' => $ticketQuery->count(),
            'open' => (clone $ticketQuery)->whereIn('status', ['open', 'in_progress', 'on_hold'])->count(),
            'closed' => (clone $ticketQuery)->whereIn('status', ['resolved', 'closed'])->count(),
        ];

        // Product statistics
        $wonProductsCount = LeadItem::whereHas('lead', function($q) use ($from, $to, $filters) {
            $q->whereBetween('created_at', [$from, $to]);
            if (isset($filters['agent_id'])) {
                $q->where('assigned_to', $filters['agent_id']);
            }
        })->where('status', LeadItem::STATUS_WON)->count();
        
        $lostProductsCount = LeadItem::whereHas('lead', function($q) use ($from, $to, $filters) {
            $q->whereBetween('created_at', [$from, $to]);
            if (isset($filters['agent_id'])) {
                $q->where('assigned_to', $filters['agent_id']);
            }
        })->where('status', LeadItem::STATUS_LOST)->count();
        
        $pendingProductsCount = LeadItem::whereHas('lead', function($q) use ($from, $to, $filters) {
            $q->whereBetween('created_at', [$from, $to]);
            if (isset($filters['agent_id'])) {
                $q->where('assigned_to', $filters['agent_id']);
            }
        })->where('status', LeadItem::STATUS_PENDING)->count();

        return [
            'followups_count' => $leadsCount,
            'conversion_rate' => $conversionRate,
            'pipeline_value' => $pipelineValue,
            'revenue' => $revenue,
            'tickets' => $tickets,
            'products' => [
                'won' => $wonProductsCount,
                'lost' => $lostProductsCount,
                'pending' => $pendingProductsCount,
            ],
        ];
    }

    public function getFunnelReport(array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $query = Lead::whereBetween('created_at', [$from, $to]);

        if (isset($filters['agent_id'])) {
            $query->where('assigned_to', $filters['agent_id']);
        }

        $stages = ['follow_up', 'lead', 'hot_lead', 'quotation', 'won', 'lost'];
        $funnel = [];

        foreach ($stages as $stage) {
            $count = (clone $query)->where('stage', $stage)->count();
            $funnel[$stage] = $count;
        }

        // Calculate conversion rates
        $total = $funnel['follow_up'];
        $conversions = [];
        foreach ($stages as $stage) {
            if ($total > 0) {
                $conversions[$stage] = round($funnel[$stage] / $total * 100, 2);
            } else {
                $conversions[$stage] = 0;
            }
        }

        // Lost reasons
        $lostReasons = (clone $query)
            ->where('stage', 'lost')
            ->whereNotNull('lost_reason')
            ->selectRaw('lost_reason, count(*) as count')
            ->groupBy('lost_reason')
            ->get()
            ->pluck('count', 'lost_reason')
            ->toArray();

        return [
            'funnel' => $funnel,
            'conversions' => $conversions,
            'lost_reasons' => $lostReasons,
        ];
    }

    public function getGeoAnalytics(array $filters = []): array
    {
        $query = Customer::whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (isset($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        if (isset($filters['postcode'])) {
            $query->where('postcode', 'like', '%' . $filters['postcode'] . '%');
        }

        $customers = $query->get()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'latitude' => (float) $customer->latitude,
                'longitude' => (float) $customer->longitude,
                'city' => $customer->city,
                'postcode' => $customer->postcode,
                'revenue' => $customer->invoices()->sum('total'),
                'tickets_count' => $customer->tickets()->count(),
            ];
        });

        return [
            'customers' => $customers,
            'total' => $customers->count(),
        ];
    }

    public function getCommunicationAnalytics(array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $query = Communication::whereBetween('created_at', [$from, $to]);
        
        // Filter by agent if provided
        if (isset($filters['agent_id'])) {
            $query->where('user_id', $filters['agent_id']);
        }

        $sent = (clone $query)->where('direction', 'outbound')->count();
        $received = (clone $query)->where('direction', 'inbound')->count();

        $byChannel = (clone $query)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->get()
            ->pluck('count', 'channel')
            ->toArray();

        return [
            'sent' => $sent,
            'received' => $received,
            'by_channel' => $byChannel,
        ];
    }

    public function getAgentPerformance(array $filters = []): array
    {
        if (isset($filters['month'])) {
            $from = Carbon::parse($filters['month'] . '-01')->startOfMonth();
            $to = $from->copy()->endOfMonth();
        } else {
            $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
            $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();
        }

        if (!empty($filters['agent_id'])) {
            // Single-user view (e.g. employee dashboard): include any active user, not only sales roles
            $agentsQuery = User::where('is_active', true)
                ->where('id', (int) $filters['agent_id']);
        } else {
            $agentsQuery = User::where('is_active', true)
                ->whereHas('role', function ($q) {
                    $q->whereIn('name', ['Sales', 'CallAgent', 'Support']);
                });
        }

        $agents = $agentsQuery->get()->map(function ($agent) use ($from, $to) {
            $leads = $agent->leads()->whereBetween('created_at', [$from, $to])->with('items')->get();

            // Won lines: same rules as monthly targets (assignee OR customer rep; month by closed_at)
            $wonProducts = (int) $this->baseWonLeadItemsForAgentInPeriod((int) $agent->id, $from, $to)->count();

            // Count leads that have at least one won product (won deals)
            $wonLeads = $leads->filter(function($lead) {
                return $lead->items->where('status', LeadItem::STATUS_WON)->count() > 0;
            })->count();

            // Revenue from won items (same scope as wonProducts)
            $leadRevenue = (float) $this->baseWonLeadItemsForAgentInPeriod((int) $agent->id, $from, $to)->sum('total_price');
            
            // Invoice revenue
            $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($agent) {
                $q->whereHas('leads', function ($l) use ($agent) {
                    $l->where('assigned_to', $agent->id);
                });
            })->whereBetween('invoice_date', [$from, $to])->sum('total');

            // Appointments created/handled by this agent in the period
            $appointmentsCount = LeadActivity::where('type', 'appointment')
                ->whereBetween('created_at', [$from, $to])
                ->where(function ($q) use ($agent) {
                    $q->where('assigned_user_id', $agent->id)
                      ->orWhere('user_id', $agent->id);
                })
                ->count();

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'leads_count' => $leads->count(),
                // Keep backward-compatible fields but redefine semantics:
                // - won_products: number of won items (sales)
                // - won_leads: number of leads that contain at least one won item
                // - won_count: alias of won_products so that existing frontends treat "sales" as products
                'won_products' => $wonProducts,
                'won_leads' => $wonLeads,
                'won_count' => $wonProducts,
                'conversion_rate' => $leads->count() > 0 ? round($wonLeads / $leads->count() * 100, 2) : 0,
                'revenue' => $leadRevenue + $invoiceRevenue,
                'appointments_count' => $appointmentsCount,
                'tickets_resolved' => $agent->tickets()->whereBetween('resolved_at', [$from, $to])->count(),
            ];
        });

        return $agents->toArray();
    }

    public function getAllEmployeesPipeline(array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $agentsQuery = User::where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereIn('name', ['Sales', 'CallAgent']);
            });
        
        // Filter by specific agent if provided
        if (isset($filters['agent_id'])) {
            $agentsQuery->where('id', $filters['agent_id']);
        }
        
        $agents = $agentsQuery->get();

        $pipeline = [];
        foreach ($agents as $agent) {
            $leads = Lead::where('assigned_to', $agent->id)
                ->orWhereHas('customer.assignedUsers', function ($q) use ($agent) {
                    $q->where('user_id', $agent->id);
                })
                ->whereBetween('created_at', [$from, $to])
                ->with('items')
                ->get();

            // Product-level stats (won lines in period by closed_at / legacy)
            $wonProducts = (int) $this->baseWonLeadItemsForAgentInPeriod((int) $agent->id, $from, $to)->count();
            
            $lostProducts = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_LOST)->count();
            
            $pendingProducts = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_PENDING)->count();
            
            $wonRevenue = (float) $this->baseWonLeadItemsForAgentInPeriod((int) $agent->id, $from, $to)->sum('total_price');

            $pipeline[] = [
                'employee_id' => $agent->id,
                'employee_name' => $agent->name,
                'follow_up' => $leads->where('stage', 'follow_up')->count(),
                'lead' => $leads->where('stage', 'lead')->count(),
                'hot_lead' => $leads->where('stage', 'hot_lead')->count(),
                'quotation' => $leads->where('stage', 'quotation')->count(),
                'won' => $leads->where('stage', 'won')->count(),
                'lost' => $leads->where('stage', 'lost')->count(),
                'total_value' => $leads->sum('pipeline_value'),
                'products' => [
                    'won' => $wonProducts,
                    'lost' => $lostProducts,
                    'pending' => $pendingProducts,
                ],
                'won_revenue' => $wonRevenue,
            ];
        }

        return $pipeline;
    }

    public function getTodaysFollowUps(array $filters = []): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->copy()->addDay()->startOfDay();

        $query = Lead::whereDate('next_follow_up_at', '>=', $today)
            ->whereDate('next_follow_up_at', '<', $tomorrow)
            ->where('stage', '!=', 'won')
            ->where('stage', '!=', 'lost')
            ->with(['customer', 'assignee', 'items.product']);

        if (isset($filters['agent_id'])) {
            $query->where('assigned_to', $filters['agent_id']);
        }

        $followUps = $query->get()->groupBy('assigned_to')->map(function ($leads, $agentId) {
            $agent = User::find($agentId);
            return [
                'agent_id' => $agentId,
                'agent_name' => $agent ? $agent->name : 'Unassigned',
                'count' => $leads->count(),
                'follow_ups' => $leads,
            ];
        })->values();

        return $followUps->toArray();
    }

    public function getSalesPerformance(array $filters = []): array
    {
        $period = $filters['period'] ?? 'month'; // day, week, month
        $agentId = $filters['agent_id'] ?? null;

        $query = Lead::query();
        
        if ($agentId) {
            $query->where('assigned_to', $agentId)
                ->orWhereHas('customer.assignedUsers', function ($q) use ($agentId) {
                    $q->where('user_id', $agentId);
                });
        }

        $data = [];
        
        switch ($period) {
            case 'day':
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->startOfDay();
                    $endDate = $date->copy()->endOfDay();
                    $count = (clone $query)->whereBetween('created_at', [$date, $endDate])
                        ->where('stage', 'won')
                        ->count();
                    $data[] = [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('D'),
                        'sales' => $count,
                    ];
                }
                break;
            case 'week':
                for ($i = 11; $i >= 0; $i--) {
                    $weekStart = now()->subWeeks($i)->startOfWeek();
                    $weekEnd = $weekStart->copy()->endOfWeek();
                    $count = (clone $query)->whereBetween('created_at', [$weekStart, $weekEnd])
                        ->where('stage', 'won')
                        ->count();
                    $data[] = [
                        'date' => $weekStart->format('Y-m-d'),
                        'label' => 'Week ' . $weekStart->format('W'),
                        'sales' => $count,
                    ];
                }
                break;
            case 'month':
            default:
                for ($i = 11; $i >= 0; $i--) {
                    $monthStart = now()->subMonths($i)->startOfMonth();
                    $monthEnd = $monthStart->copy()->endOfMonth();
                    $count = (clone $query)->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->where('stage', 'won')
                        ->count();
                    $data[] = [
                        'date' => $monthStart->format('Y-m-d'),
                        'label' => $monthStart->format('M Y'),
                        'sales' => $count,
                    ];
                }
                break;
        }

        return $data;
    }

    public function getRevenueByEmployee(array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $agentsQuery = User::where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereIn('name', ['Sales', 'CallAgent']);
            });
        
        // Filter by specific agent if provided
        if (isset($filters['agent_id'])) {
            $agentsQuery->where('id', $filters['agent_id']);
        }
        
        $agents = $agentsQuery->get();

        $revenue = [];
        foreach ($agents as $agent) {
            $aid = (int) $agent->id;
            $leadRevenue = (float) $this->baseWonLeadItemsForAgentInPeriod($aid, $from, $to)->sum('total_price');
            $wonProducts = (int) $this->baseWonLeadItemsForAgentInPeriod($aid, $from, $to)->count();
            
            $lostProducts = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_LOST)->count();

            // Revenue from invoices
            $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($agent) {
                $q->whereHas('assignedUsers', function ($subQuery) use ($agent) {
                    $subQuery->where('user_id', $agent->id);
                })->orWhereHas('leads', function ($subQuery) use ($agent) {
                    $subQuery->where('assigned_to', $agent->id);
                });
            })
            ->whereBetween('invoice_date', [$from, $to])
            ->sum('total');

            $revenue[] = [
                'employee_id' => $agent->id,
                'employee_name' => $agent->name,
                'revenue' => $leadRevenue + $invoiceRevenue,
                'lead_revenue' => $leadRevenue,
                'invoice_revenue' => $invoiceRevenue,
                'products' => [
                    'won' => $wonProducts,
                    'lost' => $lostProducts,
                ],
            ];
        }

        return $revenue;
    }

    public function getTeamLocationStatus(array $filters = []): array
    {
        // This would track who is currently with which customer
        // For now, we'll return recent customer visits/meetings
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $activities = \App\Modules\CRM\Models\LeadActivity::whereBetween('created_at', [$from, $to])
            ->whereIn('type', ['meeting', 'call', 'visit'])
            ->with(['lead.customer', 'lead.assignee', 'user'])
            ->latest()
            ->get()
            ->groupBy('lead.assignee_id')
            ->map(function ($activities, $agentId) {
                $agent = User::find($agentId);
                $customers = $activities->pluck('lead.customer')->unique('id');
                
                return [
                    'agent_id' => $agentId,
                    'agent_name' => $agent ? $agent->name : 'Unknown',
                    'customers' => $customers->map(function ($customer) {
                        return [
                            'id' => $customer->id,
                            'name' => $customer->name,
                            'phone' => $customer->phone,
                            'address' => $customer->address,
                        ];
                    })->values(),
                    'activity_count' => $activities->count(),
                ];
            })
            ->values();

        return $activities->toArray();
    }

    /**
     * One place for managers: previous calendar week (Mon–Sun) sales, selected month sales detail,
     * and monthly target vs achievement for the same month.
     *
     * @return array{
     *   month: string,
     *   last_week: array{period: array{from: string, to: string}, won_line_items: int, total_revenue: float, products: list<array>},
     *   selected_month: array{period: array{from: string, to: string}, won_line_items: int, total_revenue: float, products: list<array>},
     *   targets: array{self: ?array, total_employees_with_targets: int}
     * }
     */
    public function getEmployeePerformanceOverview(int $agentId, ?string $month = null): array
    {
        $month = $month ?? now()->format('Y-m');
        $monthStart = Carbon::parse($month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $lastWeekStart = Carbon::now()->startOfWeek()->subWeek();
        $lastWeekEnd = $lastWeekStart->copy()->endOfWeek();

        $lastWeek = $this->getProductsSoldByEmployee([
            'agent_id' => $agentId,
            'from' => $lastWeekStart->toDateString(),
            'to' => $lastWeekEnd->toDateString(),
        ]);

        $selectedMonth = $this->getProductsSoldByEmployee([
            'agent_id' => $agentId,
            'from' => $monthStart->toDateString(),
            'to' => $monthEnd->toDateString(),
        ]);

        $targets = $this->getEmployeeSelfReport($agentId, ['month' => $month]);

        return [
            'month' => $month,
            'last_week' => [
                'period' => $lastWeek['period'],
                'label' => $lastWeekStart->format('j M') . ' – ' . $lastWeekEnd->format('j M Y'),
                'won_line_items' => count($lastWeek['products'] ?? []),
                'total_revenue' => (float) ($lastWeek['total_revenue'] ?? 0),
                'products' => $lastWeek['products'] ?? [],
            ],
            'selected_month' => [
                'period' => $selectedMonth['period'],
                'won_line_items' => count($selectedMonth['products'] ?? []),
                'total_revenue' => (float) ($selectedMonth['total_revenue'] ?? 0),
                'products' => $selectedMonth['products'] ?? [],
            ],
            'targets' => [
                'self' => $targets['self'] ?? null,
                'total_employees_with_targets' => (int) ($targets['total_employees_with_targets'] ?? 0),
            ],
        ];
    }

    /**
     * Products sold by a specific employee in a given period (product-level detail).
     * Admin only - used for "Products by Employee" report.
     */
    public function getProductsSoldByEmployee(array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfMonth();
        $agentId = $filters['agent_id'] ?? null;

        if (!$agentId) {
            return [
                'employee_name' => null,
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'products' => [],
                'total_revenue' => 0,
            ];
        }

        $items = $this->baseWonLeadItemsForAgentInPeriod((int) $agentId, $from, $to)
            ->with(['product', 'lead.customer'])
            ->orderByDesc('closed_at')
            ->get();

        $agent = User::find($agentId);

        return [
            'employee_name' => $agent?->name,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'products' => $items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'customer_id' => $item->lead?->customer?->id,
                    'customer_name' => $item->lead?->customer?->name ?? 'Unknown',
                    'closed_at' => $item->closed_at?->toDateTimeString(),
                ];
            })->values()->toArray(),
            'total_revenue' => $items->sum('total_price'),
        ];
    }

    /**
     * Won lead line items for an employee (assignee or customer rep), optional product or category filter.
     * Product filter: exact product_id.
     * Category filter: counts every won line item whose product is in that category — one category can include
     * many products; each qualifying won row counts toward the category target (name match is case-insensitive, trimmed).
     */
    public function countWonLeadItemsForAgent(int $agentId, Carbon $from, Carbon $to, ?int $productId = null, ?string $categoryName = null): int
    {
        $q = $this->baseWonLeadItemsForAgentInPeriod($agentId, $from, $to);

        if ($productId !== null) {
            $q->where('product_id', $productId);
        } elseif ($categoryName !== null && trim($categoryName) !== '') {
            $catNorm = mb_strtolower(trim($categoryName), 'UTF-8');
            $productIdsInCategory = Product::query()
                ->whereNotNull('category')
                ->whereRaw('LOWER(TRIM(COALESCE(category, \'\'))) = ?', [$catNorm])
                ->select('id');
            // Must have a product on the line item so it can belong to the category set
            $q->whereNotNull('product_id')->whereIn('product_id', $productIdsInCategory);
        }

        return (int) $q->count();
    }

    /**
     * When target lines exist, sales target is the sum of line targets and achieved is summed per line
     * (overlapping product + category lines can double-count the same sale across lines).
     * When no lines, uses legacy target_sales and total won items for the employee.
     *
     * @return array{target_sales: int, achieved_sales: int}
     */
    public function resolveSalesTargetAndAchieved(?EmployeeTarget $target, int $agentId, Carbon $from, Carbon $to): array
    {
        $target?->loadMissing('lines');
        $lines = $target?->lines ?? new Collection;
        if ($lines->isEmpty()) {
            return [
                'target_sales' => (int) ($target?->target_sales ?? 0),
                'achieved_sales' => $this->countWonLeadItemsForAgent($agentId, $from, $to),
            ];
        }

        $targetSum = 0;
        $achievedSum = 0;
        foreach ($lines as $line) {
            $targetSum += max(0, (int) $line->target_quantity);
            if ($line->line_type === EmployeeTargetLine::TYPE_CATEGORY) {
                $achievedSum += $this->countWonLeadItemsForAgent($agentId, $from, $to, null, (string) ($line->category_name ?? ''));
            } else {
                $achievedSum += $this->countWonLeadItemsForAgent($agentId, $from, $to, $line->product_id);
            }
        }

        return [
            'target_sales' => $targetSum,
            'achieved_sales' => $achievedSum,
        ];
    }

    /**
     * Appointments, all won line items count, and revenue (won items + invoices) for an employee.
     *
     * @return array{appointments: int, sales: int, revenue: float}
     */
    public function getEmployeeAchievementTotals(int $userId, Carbon $from, Carbon $to): array
    {
        $appointmentsCount = LeadActivity::where('type', 'appointment')
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) use ($userId) {
                $q->where('assigned_user_id', $userId)->orWhere('user_id', $userId);
            })
            ->count();

        $sales = $this->countWonLeadItemsForAgent($userId, $from, $to);

        $wonRevenue = (float) $this->baseWonLeadItemsForAgentInPeriod($userId, $from, $to)->sum('total_price');

        $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($userId) {
            $q->whereHas('assignedUsers', function ($sub) use ($userId) {
                $sub->where('user_id', $userId);
            })->orWhereHas('leads', function ($sub) use ($userId) {
                $sub->where('assigned_to', $userId);
            });
        })->whereBetween('invoice_date', [$from, $to])->sum('total');

        $totalRevenue = (float) $wonRevenue + (float) $invoiceRevenue;

        return [
            'appointments' => $appointmentsCount,
            'sales' => $sales,
            'revenue' => $totalRevenue,
        ];
    }

    /**
     * @return list<array{line_type: string, label: string, target_quantity: int, achieved_quantity: int}>
     */
    public function getSalesTargetLinesBreakdown(?EmployeeTarget $target, int $userId, Carbon $from, Carbon $to): array
    {
        if (!$target) {
            return [];
        }
        $target->loadMissing('lines.product');
        $out = [];
        $categoryProductCounts = [];
        foreach ($target->lines as $line) {
            if ($line->line_type === EmployeeTargetLine::TYPE_CATEGORY) {
                $achieved = $this->countWonLeadItemsForAgent($userId, $from, $to, null, (string) ($line->category_name ?? ''));
                $rawCat = (string) ($line->category_name ?? '');
                $norm = mb_strtolower(trim($rawCat), 'UTF-8');
                if ($norm !== '') {
                    if (! array_key_exists($norm, $categoryProductCounts)) {
                        $categoryProductCounts[$norm] = (int) Product::query()
                            ->whereNotNull('category')
                            ->whereRaw('LOWER(TRIM(COALESCE(category, \'\'))) = ?', [$norm])
                            ->count();
                    }
                    $n = $categoryProductCounts[$norm];
                    $suffix = $n > 0 ? " — {$n} products in this group" : ' — no products use this category yet';
                    $label = 'Category: ' . $rawCat . $suffix;
                } else {
                    $label = 'Category: (not set)';
                }
            } else {
                $achieved = $this->countWonLeadItemsForAgent($userId, $from, $to, $line->product_id);
                $label = $line->product?->name ?? ('Product #' . (string) $line->product_id);
            }
            $out[] = [
                'line_type' => $line->line_type,
                'label' => $label,
                'target_quantity' => max(0, (int) $line->target_quantity),
                'achieved_quantity' => $achieved,
            ];
        }

        return $out;
    }

    /**
     * Target vs Achievement summary for all employees (admin).
     * Includes ranking by achievement percentage.
     */
    public function getTargetVsAchievementSummary(array $filters = []): array
    {
        $month = $filters['month'] ?? now()->format('Y-m');
        $from = Carbon::parse($month . '-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $agents = User::where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereIn('name', ['Sales', 'CallAgent']);
            })->get();

        $targetsByUser = EmployeeTarget::where('month', $month)->with('lines')->get()->keyBy('user_id');

        $rows = [];
        foreach ($agents as $agent) {
            $target = $targetsByUser->get($agent->id);
            $targetAppts = $target?->target_appointments ?? 0;
            $targetRevenue = (float) ($target?->target_revenue ?? 0);

            $appointmentsCount = LeadActivity::where('type', 'appointment')
                ->whereBetween('created_at', [$from, $to])
                ->where(function ($q) use ($agent) {
                    $q->where('assigned_user_id', $agent->id)->orWhere('user_id', $agent->id);
                })
                ->count();

            $salesResolved = $this->resolveSalesTargetAndAchieved($target, $agent->id, $from, $to);
            $targetSales = $salesResolved['target_sales'];
            $wonProducts = $salesResolved['achieved_sales'];

            $wonRevenue = (float) $this->baseWonLeadItemsForAgentInPeriod((int) $agent->id, $from, $to)->sum('total_price');

            $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($agent) {
                $q->whereHas('assignedUsers', function ($sub) use ($agent) {
                    $sub->where('user_id', $agent->id);
                })->orWhereHas('leads', function ($sub) use ($agent) {
                    $sub->where('assigned_to', $agent->id);
                });
            })->whereBetween('invoice_date', [$from, $to])->sum('total');

            $totalRevenue = $wonRevenue + (float) $invoiceRevenue;

            $apptPct = $targetAppts > 0 ? round($appointmentsCount / $targetAppts * 100, 1) : ($appointmentsCount > 0 ? 100 : 0);
            $salesPct = $targetSales > 0 ? round($wonProducts / $targetSales * 100, 1) : ($wonProducts > 0 ? 100 : 0);
            $revPct = $targetRevenue > 0 ? round($totalRevenue / $targetRevenue * 100, 1) : ($totalRevenue > 0 ? 100 : 0);

            $rows[] = [
                'employee_id' => $agent->id,
                'employee_name' => $agent->name,
                'target_appointments' => $targetAppts,
                'achieved_appointments' => $appointmentsCount,
                'appointment_progress' => $apptPct,
                'target_sales' => $targetSales,
                'achieved_sales' => $wonProducts,
                'sales_progress' => $salesPct,
                'target_revenue' => $targetRevenue,
                'achieved_revenue' => $totalRevenue,
                'revenue_progress' => $revPct,
            ];
        }

        $overall = array_map(function ($r) {
            $a = $r['target_appointments'] ? $r['appointment_progress'] : 0;
            $s = $r['target_sales'] ? $r['sales_progress'] : 0;
            $rev = $r['target_revenue'] ? $r['revenue_progress'] : 0;
            $denom = ((int) ($r['target_appointments'] > 0)) + ((int) ($r['target_sales'] > 0)) + ((int) ($r['target_revenue'] > 0));
            return $denom > 0 ? round(($a + $s + $rev) / $denom, 1) : 0;
        }, $rows);

        foreach ($rows as $i => &$r) {
            $r['overall_progress'] = $overall[$i];
        }

        usort($rows, fn ($a, $b) => $b['overall_progress'] <=> $a['overall_progress']);
        foreach ($rows as $rank => &$r) {
            $r['rank'] = $rank + 1;
        }

        return [
            'month' => $month,
            'data' => $rows,
        ];
    }

    /**
     * Self report for logged-in employee: target vs achievement + their ranking.
     * For employees: returns only their own data. For admins: can optionally pass agent_id.
     */
    public function getEmployeeSelfReport(int $userId, array $filters = []): array
    {
        $month = $filters['month'] ?? now()->format('Y-m');
        $summary = $this->getTargetVsAchievementSummary($filters);
        $rows = $summary['data'] ?? [];
        $myRow = null;
        foreach ($rows as $r) {
            if ((int) $r['employee_id'] === $userId) {
                $myRow = $r;
                break;
            }
        }

        if (!$myRow) {
            $agents = User::where('is_active', true)
                ->whereHas('role', fn ($q) => $q->whereIn('name', ['Sales', 'CallAgent']))->get();
            $agent = $agents->firstWhere('id', $userId);
            $target = EmployeeTarget::where('user_id', $userId)->where('month', $month)->with('lines')->first();
            $from = Carbon::parse($month . '-01')->startOfMonth();
            $to = $from->copy()->endOfMonth();

            $totals = $this->getEmployeeAchievementTotals($userId, $from, $to);
            $salesResolved = $this->resolveSalesTargetAndAchieved($target, $userId, $from, $to);

            $targetAppts = $target?->target_appointments ?? 0;
            $targetSales = $salesResolved['target_sales'];
            $wonProducts = $salesResolved['achieved_sales'];
            $targetRevenue = (float) ($target?->target_revenue ?? 0);
            $totalRevenue = $totals['revenue'];
            $myRow = [
                'employee_id' => $userId,
                'employee_name' => $agent?->name ?? 'Unknown',
                'target_appointments' => $targetAppts,
                'achieved_appointments' => $totals['appointments'],
                'appointment_progress' => $targetAppts > 0 ? round($totals['appointments'] / $targetAppts * 100, 1) : 0,
                'target_sales' => $targetSales,
                'achieved_sales' => $wonProducts,
                'sales_progress' => $targetSales > 0 ? round($wonProducts / $targetSales * 100, 1) : ($wonProducts > 0 ? 100 : 0),
                'target_revenue' => $targetRevenue,
                'achieved_revenue' => $totalRevenue,
                'revenue_progress' => $targetRevenue > 0 ? round($totalRevenue / $targetRevenue * 100, 1) : ($totalRevenue > 0 ? 100 : 0),
                'overall_progress' => 0,
                'rank' => null,
            ];
            $ap = $myRow['appointment_progress'];
            $sp = $myRow['sales_progress'];
            $rp = $myRow['revenue_progress'];
            $denom = (int) ($targetAppts > 0) + (int) ($targetSales > 0) + (int) ($targetRevenue > 0);
            $myRow['overall_progress'] = $denom > 0
                ? round(
                    (($targetAppts > 0 ? $ap : 0) + ($targetSales > 0 ? $sp : 0) + ($targetRevenue > 0 ? $rp : 0)) / $denom,
                    1
                )
                : 0;
        }

        $from = Carbon::parse($month . '-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $selfTarget = EmployeeTarget::where('user_id', $userId)->where('month', $month)->with(['lines.product'])->first();
        $myRow['sales_target_lines'] = $this->getSalesTargetLinesBreakdown($selfTarget, $userId, $from, $to);

        $totalWithTargets = collect($rows)->filter(fn ($r) => ($r['target_appointments'] ?? 0) > 0 || ($r['target_sales'] ?? 0) > 0 || ($r['target_revenue'] ?? 0) > 0)->count();

        return [
            'month' => $month,
            'self' => $myRow,
            'total_employees_with_targets' => $totalWithTargets,
        ];
    }

    /**
     * User IDs with at least one recorded sale: won lead / won line item (assignee or customer rep)
     * or an invoice they created. Used to scope sales-report employee pickers for non-admin users.
     *
     * @return list<int>
     */
    public function userIdsWithRecordedSales(): array
    {
        $assigneeIds = Lead::query()
            ->where(function ($q) {
                $q->where('stage', 'won')
                    ->orWhereHas('items', function ($iq) {
                        $iq->where('status', LeadItem::STATUS_WON);
                    });
            })
            ->whereNotNull('assigned_to')
            ->distinct()
            ->pluck('assigned_to');

        $fromAssignedCustomers = User::query()
            ->whereHas('assignedCustomers.leads', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('stage', 'won')
                        ->orWhereHas('items', function ($iq) {
                            $iq->where('status', LeadItem::STATUS_WON);
                        });
                });
            })
            ->pluck('id');

        $invoiceCreators = Invoice::query()
            ->whereNotNull('created_by')
            ->distinct()
            ->pluck('created_by');

        return $assigneeIds
            ->merge($fromAssignedCustomers)
            ->merge($invoiceCreators)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}


