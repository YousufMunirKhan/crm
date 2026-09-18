<?php

namespace App\Services;

use App\Models\User;
use App\Modules\CRM\Models\Lead;
use App\Modules\HR\Models\EmployeeTarget;
use App\Modules\Reporting\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * How many leads each person put on the board, per day, against the target.
 *
 * Counting happens here rather than in the command because two different
 * emails ask the same question - one person's own week, and everybody's day -
 * and they must not be able to disagree about the answer.
 *
 * Leads are stored in UTC and a working day is a UK one, so a day is the span
 * between its UK boundaries converted to UTC. Counting on whereDate() would put
 * an hour of every evening into the wrong day for seven months of the year.
 */
class DailyLeadScoreboard
{
    /** @var array<string, array<int, int>> counts[date][userId] */
    private array $cache = [];

    private ?Collection $people = null;

    private ?string $month = null;

    /** Report on the month the given UK date falls in, rather than today's. */
    public function forMonthOf(string $date): static
    {
        $month = Carbon::parse($date, $this->timezone())->format('Y-m');

        if ($month !== $this->month) {
            $this->month = $month;
            $this->people = null;
            $this->targets = null;
            $this->rows = null;
        }

        return $this;
    }

    /** @var array<int, int>|null userId => their own daily lead target */
    private ?array $targets = null;

    /** @var Collection<int, EmployeeTarget>|null the rows those came from */
    private ?Collection $rows = null;

    public function __construct(private ReportingService $reporting) {}

    /** The fallback when nobody has set one, and the default a new row gets. */
    public function defaultTarget(): int
    {
        return max(1, (int) config('leads.daily_target', 5));
    }

    /** What this person is asked for in a day. */
    public function targetFor(int $userId): int
    {
        $this->people();

        return $this->targets[$userId] ?? $this->defaultTarget();
    }

    public function timezone(): string
    {
        return (string) config('app.display_timezone');
    }

    /**
     * The people measured on leads: whoever has a daily lead target this month.
     *
     * Role deliberately does not come into it. This started as a list of sales
     * roles and so told four people they were short of a number nobody had ever
     * given them, while leaving out the two - an admin and a manager - creating
     * the most leads on the system. It then keyed off any target at all, which
     * measured people on leads because somebody had set them a revenue figure.
     * The daily lead target is its own number now, so it can answer for itself.
     */
    public function people(): Collection
    {
        if ($this->people !== null) {
            return $this->people;
        }

        $month = $this->month ?? now($this->timezone())->format('Y-m');

        $rows = EmployeeTarget::query()
            ->with('lines')
            ->where('month', $month)
            ->where('target_daily_leads', '>', 0)
            ->get()
            ->keyBy('user_id');

        $this->rows = $rows;
        $this->targets = $rows->map(fn (EmployeeTarget $t) => max(1, (int) $t->target_daily_leads))->all();

        return $this->people = User::query()
            ->where('is_active', true)
            ->whereIn('id', $rows->keys())
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    /**
     * Days left in the month, today included, on the UK calendar.
     */
    public function daysLeftInMonth(string $date): int
    {
        $day = Carbon::parse($date, $this->timezone());

        return (int) $day->diffInDays($day->copy()->endOfMonth()) + 1;
    }

    /**
     * Near enough the end of the month that the monthly sales figure is the
     * thing worth saying, rather than a number nobody can move yet.
     */
    public function isMonthEndRun(string $date): bool
    {
        return $this->daysLeftInMonth($date) <= (int) config('leads.month_end_push_days', 10);
    }

    /**
     * One person's monthly sales target and what they have done against it.
     *
     * The reporting service owns the rule for what counts as a sale - product
     * and category lines included - so it is asked rather than reimplemented.
     *
     * @return array{target: int, achieved: int, short: int}|null
     */
    public function salesProgressFor(int $userId, string $date): ?array
    {
        $this->people();

        $target = $this->rows?->get($userId);

        if (! $target) {
            return null;
        }

        $day = Carbon::parse($date, $this->timezone());
        $resolved = $this->reporting->resolveSalesTargetAndAchieved(
            $target,
            $userId,
            $day->copy()->startOfMonth()->startOfDay(),
            $day->copy()->endOfMonth()->endOfDay(),
        );

        $wanted = (int) ($resolved['target_sales'] ?? 0);
        $done = (int) ($resolved['achieved_sales'] ?? 0);

        // Returned even with no sales target set: "you have made three this
        // month" is worth saying on its own, and the caller decides whether
        // there is a target to be behind.
        return [
            'target' => $wanted,
            'achieved' => $done,
            'short' => max(0, $wanted - $done),
        ];
    }

    /**
     * When this person last sold something, and what.
     *
     * A month-to-date count answers "how many"; it does not answer "when did
     * this person last do it", which is the figure that shows somebody has
     * gone quiet rather than merely started slowly.
     *
     * @return array{at: Carbon, days_ago: int, customer: string|null, product: string|null}|null
     */
    public function lastSaleFor(int $userId): ?array
    {
        $item = $this->reporting->lastWonLeadItemForAgent($userId);

        if (! $item) {
            return null;
        }

        $when = ($item->closed_at ?? $item->created_at)->copy()->setTimezone($this->timezone());

        return [
            'at' => $when,
            'days_ago' => (int) $when->copy()->startOfDay()->diffInDays(now($this->timezone())->startOfDay()),
            'customer' => $item->lead?->customer?->business_name ?: $item->lead?->customer?->name,
            'product' => $item->product?->name,
        ];
    }

    /**
     * Everybody's monthly sales progress, furthest behind first.
     *
     * @return array<int, array{name: string, target: int, achieved: int, short: int}>
     */
    public function salesTable(string $date): array
    {
        return $this->people()
            ->map(function (User $u) use ($date) {
                $progress = $this->salesProgressFor($u->id, $date);

                // The team table is about a target, so somebody without one is
                // not a row with a blank in it.
                return ($progress && $progress['target'] > 0)
                    ? array_merge(['name' => $u->name], $progress)
                    : null;
            })
            ->filter()
            ->sortBy([['short', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /** The people who get everybody's figures. */
    public function watchers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', (array) config('leads.summary_roles', [])))
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    /** Leads assigned to one person on one UK day. */
    public function countFor(int $userId, string $date): int
    {
        return $this->countsOn($date)[$userId] ?? 0;
    }

    /**
     * Every person's count on one UK day, in one query.
     *
     * @return array<int, int> userId => count
     */
    public function countsOn(string $date): array
    {
        if (isset($this->cache[$date])) {
            return $this->cache[$date];
        }

        $tz = $this->timezone();
        $from = Carbon::parse($date, $tz)->startOfDay()->utc();
        $to = Carbon::parse($date, $tz)->endOfDay()->utc();

        return $this->cache[$date] = Lead::query()
            ->whereNotNull('assigned_to')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * The last few days for one person, oldest first, shaped for the chart.
     *
     * @return array<int, array{date: string, label: string, count: int, target: int, is_today: bool}>
     */
    public function recentDaysFor(int $userId, string $upTo, int $days = 7): array
    {
        $tz = $this->timezone();
        $end = Carbon::parse($upTo, $tz);
        $rows = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $end->copy()->subDays($i);

            // Sundays are not worked, so a zero there is not a miss.
            if ($day->isSunday()) {
                continue;
            }

            $date = $day->toDateString();

            $rows[] = [
                'date' => $date,
                'label' => $day->format('D'),
                'count' => $this->countFor($userId, $date),
                'target' => $this->targetFor($userId),
                'is_today' => $date === $end->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * The same shape as one person's week, but for everybody's totals, so both
     * emails draw the identical chart from the identical code.
     *
     * @return array<int, array{date: string, label: string, count: int, target: int, is_today: bool}>
     */
    public function recentDaysForTeam(string $upTo, int $days = 7): array
    {
        $tz = $this->timezone();
        $end = Carbon::parse($upTo, $tz);
        $people = $this->people()->pluck('id')->all();
        $target = array_sum(array_map(fn (int $id) => $this->targetFor($id), $people));
        $rows = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $end->copy()->subDays($i);

            if ($day->isSunday()) {
                continue;
            }

            $date = $day->toDateString();
            $counts = $this->countsOn($date);

            $rows[] = [
                'date' => $date,
                'label' => $day->format('D'),
                'count' => array_sum(array_intersect_key($counts, array_flip($people))),
                'target' => $target,
                'is_today' => $date === $end->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * The working week that has just finished, Monday to Saturday.
     *
     * Sunday is not worked, so a week measured Sunday to Saturday would carry a
     * guaranteed empty day and make every total look worse than it was.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function lastWorkingWeek(string $onDate): array
    {
        $day = Carbon::parse($onDate, $this->timezone());
        $monday = $day->copy()->startOfWeek(Carbon::MONDAY);

        // Run on a Sunday the week that just ended is the one before this one.
        if ($day->isSunday()) {
            $monday = $monday->subWeek();
        }

        return [$monday->copy()->startOfDay(), $monday->copy()->addDays(5)->endOfDay()];
    }

    /**
     * How everybody did over a week: leads against what was asked of them, and
     * sales both for the week and for the month they sit in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function weekTable(string $onDate): array
    {
        [$from, $to] = $this->lastWorkingWeek($onDate);
        $this->forMonthOf($to->toDateString());

        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days[] = $d->toDateString();
        }

        return $this->people()
            ->map(function (User $u) use ($days, $from, $to) {
                $leads = 0;
                foreach ($days as $date) {
                    $leads += $this->countFor($u->id, $date);
                }

                $leadTarget = $this->targetFor($u->id) * count($days);
                $month = $this->salesProgressFor($u->id, $to->toDateString());

                return [
                    'name' => $u->name,
                    'role' => $u->role?->name,
                    'leads' => $leads,
                    'lead_target' => $leadTarget,
                    'lead_short' => max(0, $leadTarget - $leads),
                    'sales_week' => $this->reporting->countWonLeadItemsForAgent($u->id, $from, $to),
                    'sales_month' => $month['achieved'] ?? 0,
                    'sales_target' => $month['target'] ?? 0,
                    'sales_short' => $month['short'] ?? 0,
                    'last_sale' => $this->lastSaleFor($u->id),
                ];
            })
            ->sortBy([['lead_short', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * The team's totals per day across a week, for the chart.
     *
     * @return array<int, array{date: string, label: string, count: int, target: int, is_today: bool}>
     */
    public function weekChart(string $onDate): array
    {
        [$from, $to] = $this->lastWorkingWeek($onDate);
        $this->forMonthOf($to->toDateString());

        $people = $this->people()->pluck('id')->all();
        $target = array_sum(array_map(fn (int $id) => $this->targetFor($id), $people));
        $rows = [];

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $counts = $this->countsOn($d->toDateString());

            $rows[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('D'),
                'count' => array_sum(array_intersect_key($counts, array_flip($people))),
                'target' => $target,
                'is_today' => false,
            ];
        }

        return $rows;
    }

    /**
     * Everybody's figures for one day, worst shortfall first.
     *
     * @return array<int, array{name: string, role: string|null, count: int, target: int, short: int}>
     */
    public function tableFor(string $date): array
    {
        $counts = $this->countsOn($date);

        return $this->people()
            ->map(fn (User $u) => [
                'name' => $u->name,
                'role' => $u->role?->name,
                'count' => $counts[$u->id] ?? 0,
                'target' => $this->targetFor($u->id),
                'short' => max(0, $this->targetFor($u->id) - ($counts[$u->id] ?? 0)),
            ])
            ->sortBy([['short', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }
}
