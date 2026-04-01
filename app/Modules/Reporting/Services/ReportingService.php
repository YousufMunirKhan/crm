<?php

namespace App\Modules\Reporting\Services;

use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Communication\Models\Communication;
use App\Models\User;
use App\Modules\HR\Models\EmployeeTarget;
use Carbon\Carbon;

class ReportingService
{
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

        $agents = User::where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereIn('name', ['Sales', 'CallAgent', 'Support']);
            })->get()->map(function ($agent) use ($from, $to) {
            $leads = $agent->leads()->whereBetween('created_at', [$from, $to])->with('items')->get();

            // Count won products (primary sales metric)
            $wonProducts = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where('assigned_to', $agent->id)
                  ->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->count();

            // Count leads that have at least one won product (won deals)
            $wonLeads = $leads->filter(function($lead) {
                return $lead->items->where('status', LeadItem::STATUS_WON)->count() > 0;
            })->count();

            // Revenue from won items
            $leadRevenue = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where('assigned_to', $agent->id)
                  ->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->sum('total_price');
            
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

            // Product-level stats
            $wonProducts = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->count();
            
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
            
            $wonRevenue = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->sum('total_price');

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
            // Revenue from WON items (status = 'won')
            $leadRevenue = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->sum('total_price');
            
            // Product stats
            $wonProducts = LeadItem::whereHas('lead', function($q) use ($agent, $from, $to) {
                $q->where(function($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                             $userQ->where('user_id', $agent->id);
                         });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->count();
            
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

        $items = LeadItem::where('status', LeadItem::STATUS_WON)
            ->whereHas('lead', function ($q) use ($agentId, $from, $to) {
                $q->where(function ($subQ) use ($agentId) {
                    $subQ->where('assigned_to', $agentId)
                        ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agentId) {
                            $userQ->where('user_id', $agentId);
                        });
                })->whereBetween('created_at', [$from, $to]);
            })
            ->with(['product', 'lead.customer'])
            ->orderBy('closed_at', 'desc')
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

        $targetsByUser = EmployeeTarget::where('month', $month)->get()->keyBy('user_id');

        $rows = [];
        foreach ($agents as $agent) {
            $target = $targetsByUser->get($agent->id);
            $targetAppts = $target?->target_appointments ?? 0;
            $targetSales = $target?->target_sales ?? 0;
            $targetRevenue = (float) ($target?->target_revenue ?? 0);

            $appointmentsCount = LeadActivity::where('type', 'appointment')
                ->whereBetween('created_at', [$from, $to])
                ->where(function ($q) use ($agent) {
                    $q->where('assigned_user_id', $agent->id)->orWhere('user_id', $agent->id);
                })
                ->count();

            $wonProducts = LeadItem::whereHas('lead', function ($q) use ($agent, $from, $to) {
                $q->where(function ($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                        ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                            $userQ->where('user_id', $agent->id);
                        });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->count();

            $wonRevenue = LeadItem::whereHas('lead', function ($q) use ($agent, $from, $to) {
                $q->where(function ($subQ) use ($agent) {
                    $subQ->where('assigned_to', $agent->id)
                        ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agent) {
                            $userQ->where('user_id', $agent->id);
                        });
                })->whereBetween('created_at', [$from, $to]);
            })->where('status', LeadItem::STATUS_WON)->sum('total_price');

            $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($agent) {
                $q->whereHas('assignedUsers', function ($sub) use ($agent) {
                    $sub->where('user_id', $agent->id);
                })->orWhereHas('leads', function ($sub) use ($agent) {
                    $sub->where('assigned_to', $agent->id);
                });
            })->whereBetween('invoice_date', [$from, $to])->sum('total');

            $totalRevenue = (float) $wonRevenue + (float) $invoiceRevenue;

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
            $target = EmployeeTarget::where('user_id', $userId)->where('month', $month)->first();
            $from = Carbon::parse($month . '-01')->startOfMonth();
            $to = $from->copy()->endOfMonth();

            $appointmentsCount = LeadActivity::where('type', 'appointment')
                ->whereBetween('created_at', [$from, $to])
                ->where(fn ($q) => $q->where('assigned_user_id', $userId)->orWhere('user_id', $userId))
                ->count();
            $wonProducts = LeadItem::whereHas('lead', fn ($q) => $q->where('assigned_to', $userId)->whereBetween('created_at', [$from, $to]))
                ->where('status', LeadItem::STATUS_WON)->count();
            $wonRevenue = LeadItem::whereHas('lead', fn ($q) => $q->where('assigned_to', $userId)->whereBetween('created_at', [$from, $to]))
                ->where('status', LeadItem::STATUS_WON)->sum('total_price');
            $invoiceRevenue = Invoice::whereHas('customer', fn ($q) => $q->whereHas('assignedUsers', fn ($s) => $s->where('user_id', $userId)))
                ->whereBetween('invoice_date', [$from, $to])->sum('total');
            $totalRevenue = (float) $wonRevenue + (float) $invoiceRevenue;

            $targetAppts = $target?->target_appointments ?? 0;
            $targetSales = $target?->target_sales ?? 0;
            $targetRevenue = (float) ($target?->target_revenue ?? 0);
            $myRow = [
                'employee_id' => $userId,
                'employee_name' => $agent?->name ?? 'Unknown',
                'target_appointments' => $targetAppts,
                'achieved_appointments' => $appointmentsCount,
                'appointment_progress' => $targetAppts > 0 ? round($appointmentsCount / $targetAppts * 100, 1) : 0,
                'target_sales' => $targetSales,
                'achieved_sales' => $wonProducts,
                'sales_progress' => $targetSales > 0 ? round($wonProducts / $targetSales * 100, 1) : 0,
                'target_revenue' => $targetRevenue,
                'achieved_revenue' => $totalRevenue,
                'revenue_progress' => $targetRevenue > 0 ? round($totalRevenue / $targetRevenue * 100, 1) : 0,
                'overall_progress' => 0,
                'rank' => null,
            ];
        }

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


