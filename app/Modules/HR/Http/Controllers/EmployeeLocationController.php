<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\EmployeeLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Location readings from a member of staff's phone, during their shift.
 *
 * The rule that matters is here, in {@see store()}: a reading that does not
 * belong to an open shift is refused. The phone is told to stop rather than
 * quietly ignored, and nothing is written.
 *
 * That has to live on the server. "We only track during working hours" is
 * either a property of the system or it is a promise made by an app on somebody
 * else's phone - an app that can be an old version, or wrong, or left running.
 * Tracking a person at nine in the evening is the thing that turns this from an
 * operational tool into a liability, so it is not left to the client to avoid.
 */
class EmployeeLocationController extends Controller
{
    /**
     * Accept one reading, or a backlog of them.
     *
     * A phone in a basement or on a dead signal cannot post for an hour, so it
     * queues and sends the lot when it reconnects. Taking a batch is what makes
     * the trail continuous instead of full of holes that look like somebody
     * turned it off.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:200'],
            'points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'points.*.recorded_at' => ['required', 'date'],
            'points.*.battery_level' => ['nullable', 'integer', 'between:0,100'],
            'source' => ['nullable', 'string', 'in:app,browser'],
        ]);

        $shift = $this->openShiftFor($user);

        if (! $shift) {
            // Deliberately explicit rather than a silent 204: the app needs to
            // know to stop its timer, and a person reading their own audit
            // trail should be able to see the refusal.
            return response()->json([
                'message' => 'Not clocked in. Location is only recorded during a shift.',
                'tracking' => false,
            ], 409);
        }

        $source = $data['source'] ?? 'app';
        $stored = 0;
        $rejected = 0;

        foreach ($data['points'] as $point) {
            $recordedAt = Carbon::parse($point['recorded_at']);

            // A backlog is fine; a reading from before the shift began, or from
            // the future because a phone's clock is wrong, is not.
            if ($recordedAt->lt($shift->check_in_at) || $recordedAt->gt(now()->addMinutes(5))) {
                $rejected++;

                continue;
            }

            // updateOrCreate rather than create: a phone that did not see our
            // acknowledgement will send the same backlog again, and the trail
            // must not double up. The unique key on the table backs this.
            EmployeeLocation::updateOrCreate(
                [
                    'attendance_id' => $shift->id,
                    'recorded_at' => $recordedAt,
                ],
                [
                    'user_id' => $user->id,
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                    'accuracy' => $point['accuracy'] ?? null,
                    'battery_level' => $point['battery_level'] ?? null,
                    'source' => $source,
                ]
            );

            $stored++;
        }

        return response()->json([
            'tracking' => true,
            'stored' => $stored,
            'rejected' => $rejected,
            'shift_started_at' => $shift->check_in_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Whether this phone should be recording at all.
     *
     * Called on launch and after a clock-out so the app can stop its timer
     * without waiting to be refused on the next post.
     */
    public function status(Request $request)
    {
        $shift = $this->openShiftFor($request->user());

        return response()->json([
            'tracking' => (bool) $shift,
            'shift_started_at' => $shift?->check_in_at?->toIso8601String(),
            'interval_seconds' => 900,
        ]);
    }

    /**
     * One person's trail for one day.
     *
     * Management sees anybody; everybody else sees only themselves, because a
     * colleague's movements are not theirs to read.
     */
    public function index(Request $request, $userId)
    {
        $actor = $request->user();
        $isManagement = $actor->isRole('Admin') || $actor->isRole('Manager') || $actor->isRole('System Admin');

        if (! $isManagement && (int) $userId !== $actor->id) {
            abort(403, 'You can only see your own location history.');
        }

        $request->validate(['date' => ['nullable', 'date']]);

        $date = $request->date ? Carbon::parse($request->date)->toDateString() : now()->toDateString();

        // whereDate, not where: `date` is cast to a date on the model, so
        // Eloquent stores it as a full datetime and a plain string comparison
        // never matches.
        $shift = Attendance::where('user_id', $userId)->whereDate('date', $date)->first();

        if (! $shift) {
            return response()->json(['date' => $date, 'points' => [], 'shift' => null]);
        }

        $points = EmployeeLocation::where('attendance_id', $shift->id)
            ->orderBy('recorded_at')
            ->get();

        return response()->json([
            'date' => $date,
            'user' => User::find($userId)?->only(['id', 'name']),
            'shift' => [
                'check_in_at' => $shift->check_in_at?->toIso8601String(),
                'check_out_at' => $shift->check_out_at?->toIso8601String(),
            ],
            'points' => $points->map(fn (EmployeeLocation $p) => [
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'accuracy' => $p->accuracy === null ? null : (float) $p->accuracy,
                'recorded_at' => $p->recorded_at->toIso8601String(),
                'battery_level' => $p->battery_level,
                // Sent rather than filtered out, so the map can show a vague
                // reading as vague instead of dropping it and leaving a gap
                // nobody can explain.
                'usable' => $p->isUsable(),
            ]),
        ]);
    }

    /** The shift this person is currently in, if any. */
    private function openShiftFor(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereDate('date', '>=', now()->subDay()->toDateString())
            ->latest('check_in_at')
            ->first();
    }
}
