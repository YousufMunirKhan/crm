<?php

namespace App\Services;

use App\Models\User;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\HR\Models\EmployeeTarget;
use App\Modules\Reporting\Services\ReportingService;
use Carbon\Carbon;

/**
 * Everybody who has been set a target, ranked on how far through it they are.
 *
 * Being set a target is the whole test for being on the board, the same rule
 * the lead emails use: role does not come into it, and somebody nobody has
 * given a number to is not a row with blanks in it.
 *
 * Three things are measured, all of them counts of work done: leads a day,
 * and appointments and sales a month. The board opens on today, since that is
 * the day anybody can still do something about. A sale is a product line won,
 * never an amount - prices are not recorded, and the board is read by the
 * whole team.
 *
 * Lead counts come from DailyLeadScoreboard and sales from ReportingService
 * rather than being counted again here, so the board, the morning emails and
 * the targets screen cannot disagree about a figure.
 */
class SalesLeaderboard
{
    public const PERIODS = ['today', 'week', 'month'];

    /** How many days the chart looks back, today included. */
    private const CHART_DAYS = 7;

    public function __construct(
        private DailyLeadScoreboard $scoreboard,
        private ReportingService $reporting,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $period = 'today', ?int $viewerId = null, ?string $today = null): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'today';

        $tz = $this->scoreboard->timezone();
        $day = ($today ? Carbon::parse($today, $tz) : now($tz))->startOfDay();
        $todayStr = $day->toDateString();

        $monthStart = $day->copy()->startOfMonth();
        $monthEnd = $day->copy()->endOfMonth();

        // A week is Monday to Saturday. Opened on a Sunday, that is the week just worked.
        [$from, $to] = match ($period) {
            'month' => [$monthStart, $monthEnd],
            'week' => [$day->copy()->startOfWeek(Carbon::MONDAY), $day->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)],
            default => [$day, $day],
        };

        // Today is measured even on a Sunday: somebody looking at the board on
        // one is asking about that day, not being told it does not count.
        $periodDays = $period === 'today' ? [$todayStr] : $this->workingDays($from, $to);
        $monthDays = $this->workingDays($monthStart, $monthEnd);

        // Daily targets belong to a month, so a week that straddles two is
        // measured on the one today falls in.
        $targets = EmployeeTarget::query()
            ->where('month', $day->format('Y-m'))
            ->where(fn ($q) => $q
                ->where('target_daily_leads', '>', 0)
                ->orWhere('target_appointments', '>', 0)
                ->orWhere('target_sales', '>', 0))
            ->with('lines')
            ->get()
            ->keyBy('user_id');

        $people = User::query()
            ->where('is_active', true)
            ->whereIn('id', $targets->keys())
            ->with('role')
            ->orderBy('name')
            ->get();

        $chartStart = $day->copy()->subDays(self::CHART_DAYS - 1);
        $booked = $this->appointmentsBooked(min($from, $monthStart, $chartStart), $day);

        // Pace is judged on days that are over. Today's work counts, but nobody
        // is behind on a day that has not finished.
        $periodDaysDone = count(array_filter($periodDays, fn (string $d) => $d < $todayStr));
        $monthShareDone = $monthDays ? count(array_filter($monthDays, fn (string $d) => $d < $todayStr)) / count($monthDays) : 0;

        $countsToday = $this->scoreboard->countsOn($todayStr);

        $rows = $people->map(function (User $u) use (
            $period, $targets, $periodDays, $todayStr, $countsToday, $booked, $day, $monthStart, $monthEnd, $periodDaysDone, $monthShareDone, $viewerId
        ) {
            $target = $targets->get($u->id);
            $dailyLeads = (int) $target->target_daily_leads;
            $monthAppointments = (int) $target->target_appointments;

            $leads = null;
            $appointments = null;
            $sales = null;
            $pace = [];
            $hit = [];

            if ($dailyLeads > 0) {
                $done = 0;
                foreach ($periodDays as $date) {
                    if ($date <= $todayStr) {
                        $done += $this->scoreboard->countFor($u->id, $date);
                    }
                }

                $wanted = $dailyLeads * count($periodDays);
                $expected = $dailyLeads * $periodDaysDone;

                $leads = [
                    'daily_target' => $dailyLeads,
                    'today' => $countsToday[$u->id] ?? 0,
                    'target' => $wanted,
                    'done' => $done,
                    'percent' => $this->percent($done, $wanted),
                ];
                $pace[] = $expected > 0 ? min(1, $done / $expected) : 1;
                $hit[] = $done >= $wanted;
            }

            if ($monthAppointments > 0) {
                $done = $this->countBooked($booked, [$u->id], $monthStart->toDateString(), $todayStr);
                $expected = $monthAppointments * $monthShareDone;

                $appointments = [
                    'target' => $monthAppointments,
                    'today' => $this->countBooked($booked, [$u->id], $todayStr, $todayStr),
                    'done' => $done,
                    'percent' => $this->percent($done, $monthAppointments),
                ];
                $pace[] = $expected > 0 ? min(1, $done / $expected) : 1;
                $hit[] = $done >= $monthAppointments;
            }

            // The reporting service owns what counts as a sale - product and
            // category lines included - and is asked with the same month the
            // emails ask it with.
            $resolved = $this->reporting->resolveSalesTargetAndAchieved(
                $target, $u->id, $monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()
            );

            if ($resolved['target_sales'] > 0) {
                $done = (int) $resolved['achieved_sales'];
                $wanted = (int) $resolved['target_sales'];
                $expected = $wanted * $monthShareDone;

                $sales = [
                    'target' => $wanted,
                    'today' => $this->reporting->countWonLeadItemsForAgent(
                        $u->id, $day->copy()->startOfDay(), $day->copy()->endOfDay()
                    ),
                    'done' => $done,
                    'percent' => $this->percent($done, $wanted),
                ];
                $pace[] = $expected > 0 ? min(1, $done / $expected) : 1;
                $hit[] = $done >= $wanted;
            }

            // A target row with nothing on it that the board measures.
            if (! $leads && ! $appointments && ! $sales) {
                return null;
            }

            if ($period === 'today') {
                // Today's board is about today's work. The appointment and
                // sales targets are monthly, so they are shown but only rank
                // somebody who has no lead target to be ranked on.
                $measured = $leads ?? $appointments ?? $sales;
                $progress = $measured['percent'];
                $status = match (true) {
                    $measured['done'] >= $measured['target'] => 'achieved',
                    $measured['today'] > 0 => 'in_progress',
                    default => 'not_started',
                };
            } else {
                $percents = array_column(array_filter([$leads, $appointments, $sales]), 'percent');
                $onPace = array_sum($pace) / count($pace);
                $progress = (int) round(array_sum($percents) / count($percents));
                $status = match (true) {
                    ! in_array(false, $hit, true) => 'achieved',
                    $onPace >= 1 => 'on_track',
                    $onPace >= 0.7 => 'behind',
                    default => 'at_risk',
                };
            }

            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->name,
                'is_me' => $viewerId !== null && $u->id === $viewerId,
                'leads' => $leads,
                'appointments' => $appointments,
                'sales' => $sales,
                'progress' => $progress,
                'status' => $status,
                'leads_done' => $leads['done'] ?? 0,
                'appointments_today' => $appointments['today'] ?? 0,
            ];
        })
            ->filter()
            ->sortBy([['progress', 'desc'], ['leads_done', 'desc'], ['appointments_today', 'desc'], ['name', 'asc']])
            ->values()
            ->map(function (array $row, int $i) {
                unset($row['leads_done'], $row['appointments_today']);

                return ['rank' => $i + 1] + $row;
            });

        $everyone = $rows->pluck('user_id')->all();
        $onLeads = $rows->whereNotNull('leads');
        $onAppointments = $rows->whereNotNull('appointments');
        $onSales = $rows->whereNotNull('sales');
        $leadIds = array_flip($onLeads->pluck('user_id')->all());
        $dailyTarget = (int) $onLeads->sum('leads.daily_target');
        $leadsToday = array_sum(array_intersect_key($countsToday, $leadIds));
        $appointmentsMonth = $this->countBooked(
            $booked, $onAppointments->pluck('user_id')->all(), $monthStart->toDateString(), $todayStr
        );

        $chart = [];
        for ($d = $chartStart->copy(); $d->lte($day); $d->addDay()) {
            // Sundays are not worked, so a zero there is not a miss.
            if ($d->isSunday()) {
                continue;
            }

            $date = $d->toDateString();

            $chart[] = [
                'date' => $date,
                'label' => $d->format('D j'),
                'leads' => array_sum(array_intersect_key($this->scoreboard->countsOn($date), $leadIds)),
                'lead_target' => $dailyTarget,
                'appointments' => $this->countBooked($booked, $everyone, $date, $date),
                'is_today' => $date === $todayStr,
            ];
        }

        return [
            'period' => $period,
            'today' => $todayStr,
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => match ($period) {
                    'month' => $day->format('F Y'),
                    'week' => $from->format($from->isSameMonth($to) ? 'j' : 'j M').' – '.$to->format('j M Y'),
                    default => $day->format('l j F Y'),
                },
            ],
            'today_label' => $day->format('l j F Y'),
            'month_label' => $day->format('F Y'),
            'summary' => [
                'members' => $rows->count(),
                'leads_today' => [
                    'done' => $leadsToday,
                    'target' => $dailyTarget,
                    'percent' => $this->percent($leadsToday, $dailyTarget),
                ],
                'leads_period' => [
                    'done' => (int) $onLeads->sum('leads.done'),
                    'target' => (int) $onLeads->sum('leads.target'),
                    'percent' => $this->percent((int) $onLeads->sum('leads.done'), (int) $onLeads->sum('leads.target')),
                ],
                'appointments_today' => $this->countBooked($booked, $everyone, $todayStr, $todayStr),
                'appointments_month' => [
                    'done' => $appointmentsMonth,
                    'target' => (int) $onAppointments->sum('appointments.target'),
                    'percent' => $this->percent($appointmentsMonth, (int) $onAppointments->sum('appointments.target')),
                ],
                'sales_today' => (int) $onSales->sum('sales.today'),
                'sales_month' => [
                    'done' => (int) $onSales->sum('sales.done'),
                    'target' => (int) $onSales->sum('sales.target'),
                    'percent' => $this->percent((int) $onSales->sum('sales.done'), (int) $onSales->sum('sales.target')),
                ],
            ],
            'rows' => $rows->all(),
            'chart' => $chart,
        ];
    }

    /**
     * Every UK date in the span that somebody is expected to work.
     *
     * @return list<string>
     */
    private function workingDays(Carbon $from, Carbon $to): array
    {
        $days = [];

        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            if (! $d->isSunday()) {
                $days[] = $d->toDateString();
            }
        }

        return $days;
    }

    /**
     * Appointments booked in the span, each with its UK date and who it counts for.
     *
     * Credited the way the targets screen credits them - to whoever booked it
     * and whoever it was booked for - but kept one entry per appointment, so a
     * team total does not count the same booking once for each of them.
     *
     * @return list<array{date: string, users: list<int>}>
     */
    private function appointmentsBooked(Carbon $from, Carbon $to): array
    {
        $tz = $this->scoreboard->timezone();

        return LeadActivity::query()
            ->where('type', 'appointment')
            ->whereBetween('created_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])
            ->get(['id', 'user_id', 'assigned_user_id', 'created_at'])
            ->map(fn (LeadActivity $a) => [
                'date' => $a->created_at->copy()->setTimezone($tz)->toDateString(),
                'users' => array_values(array_unique(array_map('intval', array_filter([$a->user_id, $a->assigned_user_id])))),
            ])
            ->all();
    }

    /**
     * @param  list<array{date: string, users: list<int>}>  $booked
     * @param  list<int>  $userIds
     */
    private function countBooked(array $booked, array $userIds, string $from, string $to): int
    {
        return count(array_filter(
            $booked,
            fn (array $a) => $a['date'] >= $from && $a['date'] <= $to && array_intersect($a['users'], $userIds) !== []
        ));
    }

    private function percent(int $done, int $target): int
    {
        return $target > 0 ? (int) round($done / $target * 100) : 0;
    }
}
