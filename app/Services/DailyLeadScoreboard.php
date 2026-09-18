<?php

namespace App\Services;

use App\Models\User;
use App\Modules\CRM\Models\Lead;
use App\Modules\HR\Models\EmployeeTarget;
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
        }

        return $this;
    }

    /** @var array<int, int>|null userId => their own daily lead target */
    private ?array $targets = null;

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
            ->where('month', $month)
            ->where('target_daily_leads', '>', 0)
            ->pluck('target_daily_leads', 'user_id');

        $this->targets = $rows->map(fn ($n) => max(1, (int) $n))->all();

        return $this->people = User::query()
            ->where('is_active', true)
            ->whereIn('id', $rows->keys())
            ->with('role')
            ->orderBy('name')
            ->get();
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
