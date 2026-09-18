<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LeadHandoverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Marking somebody inactive, and deciding where their pipeline goes.
 *
 * These were two separate acts and only the first one was ever done: a person
 * was switched off and their leads stayed assigned to an account that no longer
 * logs in, so they fell out of every list that starts from an active user. One
 * person here was switched off months ago still holding sixteen quotations and
 * hot leads that nobody had looked at since.
 *
 * So the screen asks the question at the moment it matters, and both happen
 * together or neither does.
 */
class UserDeactivationController extends Controller
{
    public function __construct(private LeadHandoverService $handover) {}

    /** What this person is still holding, so the screen can say so before asking. */
    public function openWork(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'open_work' => $this->handover->openWorkFor($user),
        ]);
    }

    public function deactivate(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot switch off your own account.'], 422);
        }

        $data = $request->validate([
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $recipients = User::query()
            ->whereIn('id', $data['recipients'] ?? [])
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get();

        if (($data['recipients'] ?? []) !== [] && $recipients->isEmpty()) {
            return response()->json([
                'message' => 'Nobody usable was chosen to take the work on.',
            ], 422);
        }

        // One transaction: switching somebody off and leaving their pipeline
        // behind is the exact failure this exists to prevent, so a half-done
        // version of it must not be possible either.
        $shares = DB::transaction(function () use ($user, $recipients) {
            $moved = $recipients->isEmpty() ? [] : $this->handover->handOver($user, $recipients);

            $user->update(['is_active' => false]);

            return $moved;
        });

        return response()->json([
            'message' => $shares === []
                ? $user->name.' is now inactive.'
                : $user->name.' is now inactive and their leads have been handed on.',
            'handed_over' => collect($shares)->map(fn ($share) => [
                'user' => ['id' => $share['user']->id, 'name' => $share['user']->name],
                'count' => $share['count'],
                'emailed' => $share['emailed'],
            ])->values(),
        ]);
    }
}
