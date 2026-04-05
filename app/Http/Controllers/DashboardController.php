<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private function userCanViewOrganizationDashboard(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isRole('Admin') || $user->isRole('System Admin') || $user->isRole('Manager');
    }

    /**
     * Dashboard: organization-wide for Admin / System Admin / Manager; everyone else sees only their pipeline.
     */
    public function index(Request $request)
    {
        $authUser = $request->user()?->loadMissing('role');
        $canViewAll = $this->userCanViewOrganizationDashboard($authUser);

        // Non-privileged users cannot spoof agent_id — always scope to themselves
        $agentId = $canViewAll
            ? ($request->filled('agent_id') ? (int) $request->agent_id : null)
            : ($authUser?->id);

        // Get filter parameters
        $fromDate = $request->has('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfMonth();
        $toDate = $request->has('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        // Date filters for comparisons
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();
        
        // Base query with filters
        $leadQuery = Lead::query();
        
        if ($agentId) {
            $leadQuery->where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                  ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($agentId) {
                      $subQuery->where('user_id', $agentId);
                  });
            });
        }

        // Filtered stats (using from/to dates) - exclude lost/won (leads = active opportunities only)
        $filteredLeads = (clone $leadQuery)
            ->where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->whereNotIn('stage', ['won', 'lost'])
            ->count();
        $filteredWon = (clone $leadQuery)
            ->where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->where('stage', 'won')
            ->count();
        // Total opportunities in filtered period (all stages)
        $filteredTotalOpportunities = (clone $leadQuery)
            ->where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->count();
        $filteredRevenue = $this->calculateRevenueForPeriod($fromDate, $toDate, $agentId);
        $filteredWonProducts = $this->getWonProductAggregatesForPeriod($fromDate, $toDate, $agentId);

        // Daily stats (today only) - leads = active opportunities, exclude lost/won
        $dailyLeadsQuery = (clone $leadQuery)->whereDate('created_at', $today)->whereNotIn('stage', ['won', 'lost']);
        $dailyLeads = $dailyLeadsQuery->count();
        $dailyWon = (clone $leadQuery)->whereDate('created_at', $today)->where('stage', 'won')->count();
        // Total opportunities today (all stages)
        $dailyTotalOpportunities = (clone $leadQuery)->whereDate('created_at', $today)->count();
        $dailyRevenue = $this->calculateRevenueForPeriod($today, $today->copy()->endOfDay(), $agentId);
        $dailyWonProducts = $this->getWonProductAggregatesForPeriod($today, $today->copy()->endOfDay(), $agentId);

        // Monthly stats - leads = active opportunities, exclude lost/won
        $monthlyLeadsQuery = (clone $leadQuery)->where('created_at', '>=', $thisMonth)->whereNotIn('stage', ['won', 'lost']);
        $monthlyLeads = $monthlyLeadsQuery->count();
        $monthlyWon = (clone $leadQuery)->where('created_at', '>=', $thisMonth)->where('stage', 'won')->count();
        // Total opportunities this month (all stages)
        $monthlyTotalOpportunities = (clone $leadQuery)->where('created_at', '>=', $thisMonth)->count();
        $monthlyRevenue = $this->calculateRevenueForPeriod($thisMonth, now(), $agentId);
        $monthlyWonProducts = $this->getWonProductAggregatesForPeriod($thisMonth, now(), $agentId);

        // Yearly stats - leads = active opportunities, exclude lost/won
        $yearlyLeadsQuery = (clone $leadQuery)->where('created_at', '>=', $thisYear)->whereNotIn('stage', ['won', 'lost']);
        $yearlyLeads = $yearlyLeadsQuery->count();
        $yearlyWon = (clone $leadQuery)->where('created_at', '>=', $thisYear)->where('stage', 'won')->count();
        // Total opportunities this year (all stages)
        $yearlyTotalOpportunities = (clone $leadQuery)->where('created_at', '>=', $thisYear)->count();
        $yearlyRevenue = $this->calculateRevenueForPeriod($thisYear, now(), $agentId);
        $yearlyWonProducts = $this->getWonProductAggregatesForPeriod($thisYear, now(), $agentId);

        // Product stats (using filter dates)
        $productStats = $this->getProductStatsForPeriod($fromDate, $toDate, $agentId);

        // Follow-ups for today
        $followUpQuery = Lead::whereDate('next_follow_up_at', $today)
            ->where('stage', '!=', 'won')
            ->where('stage', '!=', 'lost')
            ->with(['customer', 'items.product', 'assignee']);
        
        if ($agentId) {
            $followUpQuery->where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                  ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($agentId) {
                      $subQuery->where('user_id', $agentId);
                  });
            });
        }
        $todayFollowUps = $followUpQuery->get();

        // Today's appointments (from LeadActivity type=appointment)
        $todayStr = $today->toDateString();
        $appointmentQuery = LeadActivity::where('type', 'appointment')
            ->where('created_at', '>=', $today->copy()->subDays(60))
            ->with(['lead.customer', 'lead.assignee', 'user']);
        if ($agentId) {
            $appointmentQuery->whereHas('lead', function ($q) use ($agentId) {
                $q->where(function ($subQ) use ($agentId) {
                    $subQ->where('assigned_to', $agentId)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agentId) {
                             $userQ->where('user_id', $agentId);
                         });
                });
            });
        }
        $appointmentActivities = $appointmentQuery->get();
        $todayAppointments = $appointmentActivities
            ->filter(function ($a) use ($todayStr) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                $appDate = $meta['appointment_date'] ?? null;
                if (!$appDate) {
                    return false;
                }
                try {
                    return Carbon::parse($appDate)->startOfDay()->toDateString() === $todayStr;
                } catch (\Exception $e) {
                    return false;
                }
            })
            ->sortBy(function ($a) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                return $meta['appointment_time'] ?? '00:00';
            })
            ->values()
            ->map(function ($a) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                return [
                    'id' => $a->id,
                    'lead_id' => $a->lead_id,
                    'customer' => $a->lead?->customer,
                    'customer_id' => $a->lead?->customer_id,
                    'description' => $a->description,
                    'appointment_date' => $meta['appointment_date'] ?? null,
                    'appointment_time' => $meta['appointment_time'] ?? '10:00',
                    'user' => $a->user,
                ];
            });

        // Recent leads
        $recentLeadsQuery = Lead::with(['customer', 'items.product', 'assignee'])
            ->where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->orderBy('created_at', 'desc');
        
        if ($agentId) {
            $recentLeadsQuery->where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                  ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($agentId) {
                      $subQuery->where('user_id', $agentId);
                  });
            });
        }
        $recentLeads = $recentLeadsQuery->limit(10)->get();

        // Pipeline summary (with filters)
        $pipelineQuery = clone $leadQuery;
        $pipeline = [
            'follow_up' => (clone $pipelineQuery)->where('stage', 'follow_up')->count(),
            'lead' => (clone $pipelineQuery)->where('stage', 'lead')->count(),
            'hot_lead' => (clone $pipelineQuery)->where('stage', 'hot_lead')->count(),
            'quotation' => (clone $pipelineQuery)->where('stage', 'quotation')->count(),
            'won' => (clone $pipelineQuery)->where('stage', 'won')->count(),
            'lost' => (clone $pipelineQuery)->where('stage', 'lost')->count(),
        ];

        // Lead sources breakdown (using filter dates)
        $sourcesQuery = Lead::where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->whereNotNull('source');
        
        if ($agentId) {
            $sourcesQuery->where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                  ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($agentId) {
                      $subQuery->where('user_id', $agentId);
                  });
            });
        }
        $leadSources = $sourcesQuery
            ->selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->orderByDesc('count')
            ->pluck('count', 'source')
            ->toArray();

        // Total **customers** / invoices — scoped when viewing a single agent (or self)
        if ($agentId) {
            $totalCustomers = Customer::where('type', Customer::TYPE_CUSTOMER)
                ->where(function ($q) use ($agentId) {
                    $q->whereHas('assignedUsers', function ($sub) use ($agentId) {
                        $sub->where('user_id', $agentId);
                    })->orWhereHas('leads', function ($sub) use ($agentId) {
                        $sub->where('assigned_to', $agentId);
                    });
                })
                ->count();
            $totalInvoices = Invoice::whereHas('customer', function ($q) use ($agentId) {
                $q->whereHas('assignedUsers', function ($sub) use ($agentId) {
                    $sub->where('user_id', $agentId);
                })->orWhereHas('leads', function ($sub) use ($agentId) {
                    $sub->where('assigned_to', $agentId);
                });
            })->count();
        } else {
            $totalCustomers = Customer::where('type', Customer::TYPE_CUSTOMER)->count();
            $totalInvoices = Invoice::count();
        }

        return response()->json([
            'meta' => [
                'viewer_scope' => $canViewAll ? 'organization' : 'self',
                'effective_agent_id' => $agentId,
                'can_filter_employees' => $canViewAll,
            ],
            'stats' => [
                'filtered' => [
                    'leads' => $filteredLeads,
                    'won' => $filteredWon,
                    'revenue' => $filteredRevenue,
                    'total_opportunities' => $filteredTotalOpportunities,
                    'won_product_units' => $filteredWonProducts['won_product_units'],
                    'won_product_lines' => $filteredWonProducts['won_product_lines'],
                ],
                'daily' => [
                    'leads' => $dailyLeads,
                    'won' => $dailyWon,
                    'revenue' => $dailyRevenue,
                    'total_opportunities' => $dailyTotalOpportunities,
                    'won_product_units' => $dailyWonProducts['won_product_units'],
                    'won_product_lines' => $dailyWonProducts['won_product_lines'],
                ],
                'monthly' => [
                    'leads' => $monthlyLeads,
                    'won' => $monthlyWon,
                    'revenue' => $monthlyRevenue,
                    'total_opportunities' => $monthlyTotalOpportunities,
                    'won_product_units' => $monthlyWonProducts['won_product_units'],
                    'won_product_lines' => $monthlyWonProducts['won_product_lines'],
                ],
                'yearly' => [
                    'leads' => $yearlyLeads,
                    'won' => $yearlyWon,
                    'revenue' => $yearlyRevenue,
                    'total_opportunities' => $yearlyTotalOpportunities,
                    'won_product_units' => $yearlyWonProducts['won_product_units'],
                    'won_product_lines' => $yearlyWonProducts['won_product_lines'],
                ],
            ],
            'products' => $productStats,
            'today_follow_ups' => $todayFollowUps,
            'today_appointments' => $todayAppointments,
            'recent_leads' => $recentLeads,
            'all_customers' => $totalCustomers,
            'total_invoices' => $totalInvoices,
            'lead_sources' => $leadSources,
            'pipeline' => $pipeline,
            'filters' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'agent_id' => $agentId,
            ],
        ]);
    }

    /**
     * Get sales agent dashboard data
     */
    public function salesAgent(Request $request)
    {
        $user = auth()->user();
        
        // Date filters
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();
        
        // Get leads assigned to agent OR customers assigned to agent
        $leadQuery = Lead::where(function ($q) use ($user) {
            $q->where('assigned_to', $user->id)
              ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($user) {
                  $subQuery->where('user_id', $user->id);
              });
        });

        // Get customers assigned to agent
        $customerQuery = Customer::whereHas('assignedUsers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->orWhereHas('leads', function ($q) use ($user) {
            $q->where('assigned_to', $user->id);
        });

        // Daily stats - leads = active opportunities only (exclude won/lost)
        $dailyLeads = (clone $leadQuery)->whereDate('created_at', $today)->whereNotIn('stage', ['won', 'lost'])->count();
        $dailyWon = (clone $leadQuery)->whereDate('created_at', $today)->where('stage', 'won')->count();
        // Total opportunities today (all stages)
        $dailyTotalOpportunities = (clone $leadQuery)->whereDate('created_at', $today)->count();
        $dailyRevenue = $this->calculateDailyRevenue($user, $today);

        // Monthly stats - leads = active opportunities only (exclude won/lost)
        $monthlyLeads = (clone $leadQuery)->where('created_at', '>=', $thisMonth)->whereNotIn('stage', ['won', 'lost'])->count();
        $monthlyWon = (clone $leadQuery)->where('created_at', '>=', $thisMonth)->where('stage', 'won')->count();
        // Total opportunities this month (all stages)
        $monthlyTotalOpportunities = (clone $leadQuery)->where('created_at', '>=', $thisMonth)->count();
        $monthlyRevenue = $this->calculateMonthlyRevenue($user, $thisMonth);

        // Yearly stats - leads = active opportunities only (exclude won/lost)
        $yearlyLeads = (clone $leadQuery)->where('created_at', '>=', $thisYear)->whereNotIn('stage', ['won', 'lost'])->count();
        $yearlyWon = (clone $leadQuery)->where('created_at', '>=', $thisYear)->where('stage', 'won')->count();
        // Total opportunities this year (all stages)
        $yearlyTotalOpportunities = (clone $leadQuery)->where('created_at', '>=', $thisYear)->count();
        $yearlyRevenue = $this->calculateYearlyRevenue($user, $thisYear);

        // Product stats (monthly)
        $monthlyProductStats = $this->getProductStats($thisMonth, $user->id);

        // Follow-ups for today
        $todayFollowUps = (clone $leadQuery)
            ->whereDate('next_follow_up_at', $today)
            ->where('stage', '!=', 'won')
            ->where('stage', '!=', 'lost')
            ->with(['customer', 'items.product', 'assignee'])
            ->orderBy('next_follow_up_at')
            ->get();

        // Next 7 days follow-ups (excluding today)
        $next7DaysStart = $today->copy()->addDay();
        $next7DaysEnd = $today->copy()->addDays(7)->endOfDay();
        $next7DaysFollowUps = (clone $leadQuery)
            ->whereBetween('next_follow_up_at', [$next7DaysStart, $next7DaysEnd])
            ->where('stage', '!=', 'won')
            ->where('stage', '!=', 'lost')
            ->with(['customer', 'items.product', 'assignee'])
            ->orderBy('next_follow_up_at')
            ->get();

        // Today's appointments (from LeadActivity type=appointment)
        $todayStr = $today->toDateString();
        $appointmentActivities = LeadActivity::where('type', 'appointment')
            ->whereHas('lead', function ($q) use ($user) {
                $q->where(function ($subQ) use ($user) {
                    $subQ->where('assigned_to', $user->id)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($user) {
                             $userQ->where('user_id', $user->id);
                         });
                });
            })
            ->where('created_at', '>=', $today->copy()->subDays(60))
            ->with(['lead.customer', 'lead.assignee', 'user'])
            ->get();
        $todayAppointments = $appointmentActivities
            ->filter(function ($a) use ($todayStr) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                $appDate = $meta['appointment_date'] ?? null;
                if (!$appDate) {
                    return false;
                }
                try {
                    return Carbon::parse($appDate)->startOfDay()->toDateString() === $todayStr;
                } catch (\Exception $e) {
                    return false;
                }
            })
            ->sortBy(function ($a) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                return $meta['appointment_time'] ?? '00:00';
            })
            ->values()
            ->map(function ($a) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                return [
                    'id' => $a->id,
                    'lead_id' => $a->lead_id,
                    'customer' => $a->lead?->customer,
                    'customer_id' => $a->lead?->customer_id,
                    'description' => $a->description,
                    'appointment_date' => $meta['appointment_date'] ?? null,
                    'appointment_time' => $meta['appointment_time'] ?? '10:00',
                    'user' => $a->user,
                ];
            });

        // Optional: follow-ups for a specific date (from date picker)
        $followUpsByDate = [];
        if ($request->has('date')) {
            $selectedDate = Carbon::parse($request->date)->startOfDay();
            $followUpsByDate = (clone $leadQuery)
                ->whereDate('next_follow_up_at', $selectedDate)
                ->where('stage', '!=', 'won')
                ->where('stage', '!=', 'lost')
                ->with(['customer', 'items.product', 'assignee'])
                ->orderBy('next_follow_up_at')
                ->get();
        }

        // Recent leads
        $recentLeads = (clone $leadQuery)
            ->with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Assigned customers
        $assignedCustomers = $customerQuery
            ->with(['leads' => function ($q) use ($user) {
                $q->where('assigned_to', $user->id)->latest()->limit(1);
            }])
            ->limit(10)
            ->get();

        // Pipeline summary
        $pipeline = [
            'follow_up' => (clone $leadQuery)->where('stage', 'follow_up')->count(),
            'lead' => (clone $leadQuery)->where('stage', 'lead')->count(),
            'hot_lead' => (clone $leadQuery)->where('stage', 'hot_lead')->count(),
            'quotation' => (clone $leadQuery)->where('stage', 'quotation')->count(),
            'won' => (clone $leadQuery)->where('stage', 'won')->count(),
            'lost' => (clone $leadQuery)->where('stage', 'lost')->count(),
        ];

        $response = [
            'stats' => [
                'daily' => [
                    'leads' => $dailyLeads,
                    'won' => $dailyWon,
                    'revenue' => $dailyRevenue,
                    'total_opportunities' => $dailyTotalOpportunities,
                ],
                'monthly' => [
                    'leads' => $monthlyLeads,
                    'won' => $monthlyWon,
                    'revenue' => $monthlyRevenue,
                    'total_opportunities' => $monthlyTotalOpportunities,
                ],
                'yearly' => [
                    'leads' => $yearlyLeads,
                    'won' => $yearlyWon,
                    'revenue' => $yearlyRevenue,
                    'total_opportunities' => $yearlyTotalOpportunities,
                ],
            ],
            'products' => $monthlyProductStats,
            'today_follow_ups' => $todayFollowUps,
            'next_7_days_follow_ups' => $next7DaysFollowUps,
            'today_appointments' => $todayAppointments,
            'recent_leads' => $recentLeads,
            'assigned_customers' => $assignedCustomers,
            'pipeline' => $pipeline,
        ];

        if ($request->has('date')) {
            $response['follow_ups_by_date'] = $followUpsByDate;
        }

        return response()->json($response);
    }

    /**
     * Calculate daily revenue for user (from won items)
     */
    private function calculateDailyRevenue($user, $date)
    {
        // Revenue from won items
        $revenue = LeadItem::whereHas('lead', function ($q) use ($user, $date) {
            $q->where(function ($subQ) use ($user) {
                $subQ->where('assigned_to', $user->id)
                     ->orWhereHas('customer.assignedUsers', function ($userQ) use ($user) {
                         $userQ->where('user_id', $user->id);
                     });
            })->whereDate('created_at', $date);
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');

        // Also add invoice revenue for customers assigned to this agent
        $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($user) {
            $q->whereHas('assignedUsers', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            })->orWhereHas('leads', function ($subQuery) use ($user) {
                $subQuery->where('assigned_to', $user->id);
            });
        })
        ->whereDate('invoice_date', $date)
        ->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Calculate monthly revenue for user (from won items)
     */
    private function calculateMonthlyRevenue($user, $startDate)
    {
        // Revenue from won items
        $revenue = LeadItem::whereHas('lead', function ($q) use ($user, $startDate) {
            $q->where(function ($subQ) use ($user) {
                $subQ->where('assigned_to', $user->id)
                     ->orWhereHas('customer.assignedUsers', function ($userQ) use ($user) {
                         $userQ->where('user_id', $user->id);
                     });
            })->where('created_at', '>=', $startDate);
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');

        $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($user) {
            $q->whereHas('assignedUsers', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            })->orWhereHas('leads', function ($subQuery) use ($user) {
                $subQuery->where('assigned_to', $user->id);
            });
        })
        ->where('invoice_date', '>=', $startDate)
        ->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Calculate yearly revenue for user (from won items)
     */
    private function calculateYearlyRevenue($user, $startDate)
    {
        // Revenue from won items
        $revenue = LeadItem::whereHas('lead', function ($q) use ($user, $startDate) {
            $q->where(function ($subQ) use ($user) {
                $subQ->where('assigned_to', $user->id)
                     ->orWhereHas('customer.assignedUsers', function ($userQ) use ($user) {
                         $userQ->where('user_id', $user->id);
                     });
            })->where('created_at', '>=', $startDate);
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');

        $invoiceRevenue = Invoice::whereHas('customer', function ($q) use ($user) {
            $q->whereHas('assignedUsers', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            })->orWhereHas('leads', function ($subQuery) use ($user) {
                $subQuery->where('assigned_to', $user->id);
            });
        })
        ->where('invoice_date', '>=', $startDate)
        ->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Calculate daily revenue for all (admin) - from won items
     */
    private function calculateDailyRevenueAll($date)
    {
        // Revenue from won items
        $revenue = LeadItem::whereHas('lead', function ($q) use ($date) {
            $q->whereDate('created_at', $date);
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');

        $invoiceRevenue = Invoice::whereDate('invoice_date', $date)->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Calculate monthly revenue for all (admin) - from won items
     */
    private function calculateMonthlyRevenueAll($startDate)
    {
        // Revenue from won items
        $revenue = LeadItem::whereHas('lead', function ($q) use ($startDate) {
            $q->where('created_at', '>=', $startDate);
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');

        $invoiceRevenue = Invoice::where('invoice_date', '>=', $startDate)->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Calculate yearly revenue for all (admin) - from won items
     */
    private function calculateYearlyRevenueAll($startDate)
    {
        // Revenue from won items
        $revenue = LeadItem::whereHas('lead', function ($q) use ($startDate) {
            $q->where('created_at', '>=', $startDate);
        })->where('status', LeadItem::STATUS_WON)->sum('total_price');

        $invoiceRevenue = Invoice::where('invoice_date', '>=', $startDate)->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Get product stats (won, lost, pending) for a given period
     */
    private function getProductStats($startDate, $userId = null)
    {
        $query = LeadItem::whereHas('lead', function ($q) use ($startDate, $userId) {
            $q->where('created_at', '>=', $startDate);
            if ($userId) {
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('assigned_to', $userId)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($userId) {
                             $userQ->where('user_id', $userId);
                         });
                });
            }
        });

        return [
            'won' => (clone $query)->where('status', LeadItem::STATUS_WON)->count(),
            'lost' => (clone $query)->where('status', LeadItem::STATUS_LOST)->count(),
            'pending' => (clone $query)->where('status', LeadItem::STATUS_PENDING)->count(),
        ];
    }

    /**
     * Calculate revenue for a given period with optional agent filter
     */
    private function calculateRevenueForPeriod($fromDate, $toDate, $agentId = null)
    {
        // Revenue from won items
        $itemQuery = LeadItem::whereHas('lead', function ($q) use ($fromDate, $toDate, $agentId) {
            $q->where('created_at', '>=', $fromDate)
              ->where('created_at', '<=', $toDate);
            
            if ($agentId) {
                $q->where(function ($subQ) use ($agentId) {
                    $subQ->where('assigned_to', $agentId)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agentId) {
                             $userQ->where('user_id', $agentId);
                         });
                });
            }
        })->where('status', LeadItem::STATUS_WON);
        
        $revenue = $itemQuery->sum('total_price');

        // Invoice revenue
        $invoiceQuery = Invoice::where('invoice_date', '>=', $fromDate)
            ->where('invoice_date', '<=', $toDate);
        
        if ($agentId) {
            $invoiceQuery->whereHas('customer', function ($q) use ($agentId) {
                $q->whereHas('assignedUsers', function ($subQuery) use ($agentId) {
                    $subQuery->where('user_id', $agentId);
                })->orWhereHas('leads', function ($subQuery) use ($agentId) {
                    $subQuery->where('assigned_to', $agentId);
                });
            });
        }
        
        $invoiceRevenue = $invoiceQuery->sum('total');

        return $revenue + $invoiceRevenue;
    }

    /**
     * Get product stats for a specific date range
     */
    private function getProductStatsForPeriod($fromDate, $toDate, $agentId = null)
    {
        $query = LeadItem::whereHas('lead', function ($q) use ($fromDate, $toDate, $agentId) {
            $q->where('created_at', '>=', $fromDate)
              ->where('created_at', '<=', $toDate);
            
            if ($agentId) {
                $q->where(function ($subQ) use ($agentId) {
                    $subQ->where('assigned_to', $agentId)
                         ->orWhereHas('customer.assignedUsers', function ($userQ) use ($agentId) {
                             $userQ->where('user_id', $agentId);
                         });
                });
            }
        });

        return [
            'won' => (clone $query)->where('status', LeadItem::STATUS_WON)->count(),
            'lost' => (clone $query)->where('status', LeadItem::STATUS_LOST)->count(),
            'pending' => (clone $query)->where('status', LeadItem::STATUS_PENDING)->count(),
        ];
    }

    /**
     * Sum of quantities on won line items for leads created in the period (and optional agent scope).
     * Matches leads hub "won product units" semantics for the same date window.
     */
    private function getWonProductAggregatesForPeriod($fromDate, $toDate, $agentId = null): array
    {
        $leadQuery = Lead::query()
            ->where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate);

        if ($agentId) {
            $leadQuery->where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                    ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($agentId) {
                        $subQuery->where('user_id', $agentId);
                    });
            });
        }

        $wonLeadIds = (clone $leadQuery)->where('stage', 'won')->pluck('id');
        if ($wonLeadIds->isEmpty()) {
            return ['won_product_units' => 0, 'won_product_lines' => 0];
        }

        $itemBase = LeadItem::query()
            ->where('status', LeadItem::STATUS_WON)
            ->whereIn('lead_id', $wonLeadIds);

        return [
            'won_product_units' => (int) (clone $itemBase)->sum('quantity'),
            'won_product_lines' => (int) (clone $itemBase)->count(),
        ];
    }
}
