<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\TicketAttachment;
use App\Modules\Ticket\Models\TicketMessage;
use App\Modules\Ticket\Services\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin');

        $query = Ticket::with(['customer', 'assignee', 'creator'])
            ->withCount(['messages', 'attachments'])
            ->where('source', 'crm');

        // Non-admin users: only see tickets they created or are assigned to
        if (!$isAdmin) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($tickets);
    }

    public function show($id)
    {
        $ticket = Ticket::with(['customer', 'assignee', 'creator', 'messages.user', 'attachments'])
            ->findOrFail($id);

        $this->authorizeTicketAccess($ticket);

        return response()->json($ticket);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_phone' => ['nullable', 'string'],
            'subject' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'reference_url' => ['nullable', 'string', 'max:2048'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'estimated_resolve_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $files = $request->file('attachments', []) ?: [];
        if (!is_array($files)) {
            $files = array_filter([$files]);
        }
        unset($data['attachments']);

        $ticket = $this->ticketService->create($data, auth()->id());
        $this->ticketService->saveUploadedAttachments($ticket, $files);

        // Broadcast notification
        event(new \App\Events\NewTicketCreated($ticket));

        return response()->json($ticket->load(['customer', 'assignee', 'creator', 'attachments']), 201);
    }

    public function update(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $this->authorizeTicketAccess($ticket);

        $data = $request->validate([
            'subject' => ['sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'reference_url' => ['nullable', 'string', 'max:2048'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'in:open,in_progress,on_hold,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'estimated_resolve_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $previousAssigned = $ticket->assigned_to;
        $previousStatus = $ticket->status;

        if (array_key_exists('status', $data)) {
            if (in_array($data['status'], ['resolved', 'closed'], true)) {
                $data['resolved_at'] = $ticket->resolved_at ?? now();
            } else {
                $data['resolved_at'] = null;
            }
        }

        if (array_key_exists('estimated_resolve_hours', $data)) {
            $priorityForSla = $data['priority'] ?? $ticket->priority;
            if ($data['estimated_resolve_hours'] !== null) {
                $data['sla_due_at'] = now()->addHours((int) $data['estimated_resolve_hours']);
            } else {
                $data['sla_due_at'] = $this->ticketService->calculateSLADueDate($priorityForSla);
            }
        }

        $ticket->update($data);

        if (array_key_exists('assigned_to', $data) && $ticket->assigned_to) {
            if ((int) $ticket->assigned_to !== (int) ($previousAssigned ?? 0)) {
                $this->ticketService->notifyAssigned($ticket->fresh(['customer', 'creator', 'assignee', 'attachments']));
            }
        }

        if (array_key_exists('status', $data) && $previousStatus !== $ticket->status) {
            $this->ticketService->notifyStatusChanged(
                $ticket->fresh(['customer', 'creator', 'assignee', 'attachments']),
                $previousStatus,
                auth()->id()
            );
        }

        return response()->json($ticket->load(['customer', 'assignee', 'creator', 'attachments']));
    }

    public function destroy($id)
    {
        return response()->json([
            'message' => 'Tickets cannot be deleted.',
        ], 403);
    }

    public function addMessage(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $this->authorizeTicketAccess($ticket);

        $data = $request->validate([
            'message' => ['required', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $message = $this->ticketService->addMessage(
            $ticket,
            $data['message'],
            auth()->id(),
            (bool) ($data['is_internal'] ?? false)
        );

        return response()->json($message->load('user'), 201);
    }

    public function storeAttachments(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorizeTicketAccess($ticket);

        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:20'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $files = $request->file('attachments', []) ?: [];
        if (!is_array($files)) {
            $files = array_filter([$files]);
        }
        $this->ticketService->saveUploadedAttachments($ticket, $files);

        return response()->json($ticket->fresh(['customer', 'assignee', 'creator', 'attachments']));
    }

    public function destroyAttachment(Request $request, $id, $attachmentId)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorizeTicketAccess($ticket);

        $attachment = TicketAttachment::where('ticket_id', $ticket->id)
            ->where('id', $attachmentId)
            ->firstOrFail();

        $attachment->delete();

        return response()->noContent();
    }

    /**
     * Ensure non-admin users can only access tickets they created or are assigned to.
     */
    protected function authorizeTicketAccess(Ticket $ticket): void
    {
        $user = auth()->user();
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin');

        if ($isAdmin) {
            return;
        }

        if ($ticket->isPosSupport() && $user->canAccessPosSupport()) {
            return;
        }

        if ($ticket->created_by === $user->id || $ticket->assigned_to === $user->id) {
            return;
        }

        abort(403, 'Unauthorized access to this ticket');
    }
}


