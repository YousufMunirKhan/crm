<?php

namespace App\Http\Controllers;

use App\Modules\Ticket\Models\Ticket;
use Illuminate\Http\Request;

class PosSupportTicketAdminController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->allowsNavSection('pos_support')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Ticket::query()
            ->where('source', 'pos_support')
            ->orderByDesc('pos_submitted_at')
            ->orderByDesc('created_at');

        if ($request->filled('pos_support_status')) {
            $query->where('pos_support_status', $request->pos_support_status);
        }

        $tickets = $query
            ->with(['customer'])
            ->paginate((int) $request->get('per_page', 20));

        return response()->json($tickets);
    }

    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user->allowsNavSection('pos_support')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $ticket = Ticket::query()
            ->where('source', 'pos_support')
            ->whereKey($id)
            ->firstOrFail();

        $data = $request->validate([
            'pos_support_status' => ['required', 'string', 'in:pending,solved,not_an_issue'],
            'pos_resolution_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if (in_array($data['pos_support_status'], ['solved', 'not_an_issue'], true)) {
            $notes = trim((string) ($data['pos_resolution_notes'] ?? ''));
            if ($notes === '') {
                return response()->json([
                    'message' => 'Resolution notes are required when status is Solved or Not an Issue.',
                    'errors' => ['pos_resolution_notes' => ['Required for this status.']],
                ], 422);
            }
            $data['pos_resolution_notes'] = $notes;
        } else {
            $data['pos_resolution_notes'] = null;
        }

        $updates = $data;
        if ($data['pos_support_status'] === 'solved') {
            $updates['status'] = 'resolved';
            $updates['resolved_at'] = $ticket->resolved_at ?? now();
        } elseif ($data['pos_support_status'] === 'not_an_issue') {
            $updates['status'] = 'closed';
        } else {
            $updates['status'] = 'open';
            $updates['resolved_at'] = null;
        }

        $ticket->update($updates);

        return response()->json($ticket->fresh(['customer']));
    }
}
