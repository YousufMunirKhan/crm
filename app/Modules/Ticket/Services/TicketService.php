<?php

namespace App\Modules\Ticket\Services;

use App\Mail\TicketAssignedMail;
use App\Mail\TicketCommentMail;
use App\Mail\TicketStatusChangedMail;
use App\Modules\Settings\Models\Setting;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\TicketMessage;
use App\Services\MailConfigFromDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    public function normalizeAssigneeIdsFromPayload(array $data): array
    {
        if (! empty($data['assigned_user_ids']) && is_array($data['assigned_user_ids'])) {
            $ids = $data['assigned_user_ids'];
        } elseif (! empty($data['assigned_to'])) {
            $ids = [(int) $data['assigned_to']];
        } else {
            $ids = [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public function create(array $data, ?int $userId = null): Ticket
    {
        $customer = null;
        if (isset($data['customer_id'])) {
            $customer = Customer::findOrFail($data['customer_id']);
        } elseif (isset($data['customer_phone'])) {
            $customer = Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['name' => $data['customer_phone']]
            );
        }

        $priority = $data['priority'] ?? 'medium';
        $estimatedHours = isset($data['estimated_resolve_hours']) && $data['estimated_resolve_hours'] !== '' && $data['estimated_resolve_hours'] !== null
            ? (int) $data['estimated_resolve_hours']
            : null;

        $slaDue = $estimatedHours !== null && $estimatedHours > 0
            ? now()->addHours($estimatedHours)
            : $this->calculateSLADueDate($priority);

        $assigneeIds = $this->normalizeAssigneeIdsFromPayload($data);

        $ticket = Ticket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'source' => 'crm',
            'customer_id' => $customer?->id,
            'created_by' => $userId ?? auth()->id(),
            'assigned_to' => $assigneeIds[0] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'reference_url' => $data['reference_url'] ?? null,
            'priority' => $priority,
            'estimated_resolve_hours' => $estimatedHours,
            'status' => 'open',
            'sla_due_at' => $slaDue,
        ]);

        $ticket->assignees()->sync($assigneeIds);
        $ticket->load(['customer', 'creator', 'assignee', 'assignees']);

        if ($assigneeIds !== []) {
            $this->notifyAssigned($ticket->fresh(['customer', 'creator', 'assignees', 'attachments']));
        }

        return $ticket;
    }

    public function generateTicketNumber(): string
    {
        return 'TKT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }

    public function calculateSLADueDate(string $priority): \Carbon\Carbon
    {
        $hours = match ($priority) {
            'urgent' => 2,
            'high' => 8,
            'medium' => 24,
            'low' => 72,
            default => 24,
        };

        return now()->addHours($hours);
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function saveUploadedAttachments(Ticket $ticket, array $files): void
    {
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store('ticket-attachments', 'public');

            $ticket->attachments()->create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    public function addMessage(Ticket $ticket, string $message, ?int $userId = null, bool $isInternal = false): TicketMessage
    {
        $ticketMessage = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId ?? auth()->id(),
            'is_internal' => $isInternal,
            'message' => $message,
        ]);

        $ticketMessage->load('user');
        $ticket->loadMissing(['customer', 'creator', 'assignee', 'assignees']);
        if (! $isInternal) {
            $this->notifyCommentPosted($ticket, $ticketMessage);
        }

        return $ticketMessage;
    }

    /**
     * @param  array<int>|null  $onlyUserIds  When set, only these users receive mail (e.g. newly added assignees).
     */
    public function notifyAssigned(Ticket $ticket, ?array $onlyUserIds = null): void
    {
        $ticket->loadMissing(['customer', 'creator', 'assignees', 'attachments']);
        $recipients = $ticket->assignees;
        if ($onlyUserIds !== null && count($onlyUserIds) > 0) {
            $recipients = $recipients->whereIn('id', $onlyUserIds);
        }
        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $user) {
            if (! $user->email) {
                continue;
            }
            try {
                MailConfigFromDatabase::apply();
                Mail::to($user->email)->send(new TicketAssignedMail($ticket, $user));
            } catch (\Throwable $e) {
                Log::warning('Ticket assignment email failed: ' . $e->getMessage(), ['ticket_id' => $ticket->id, 'user_id' => $user->id]);
            }
        }
    }

    public function notifyCommentPosted(Ticket $ticket, TicketMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }

        $ticket->refresh();
        $ticket->load(['assignees', 'assignee', 'creator', 'customer', 'attachments']);
        $message->loadMissing('user');

        $recipients = $this->commentNotificationRecipients($ticket, $message);

        if ($recipients->isEmpty()) {
            Log::info('Ticket comment skipped email: no recipients', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'commenter_user_id' => $message->user_id,
                'pivot_assignee_count' => DB::table('ticket_assignees')->where('ticket_id', $ticket->id)->count(),
                'has_creator' => (bool) $ticket->created_by,
                'assigned_to' => $ticket->assigned_to,
            ]);

            return;
        }

        MailConfigFromDatabase::apply();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new TicketCommentMail($ticket, $message));
            } catch (\Throwable $e) {
                Log::error('Ticket comment email failed: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                    'to' => $email,
                    'exception' => $e::class,
                ]);
            }
        }
    }

    /**
     * Assignee and creator receive comment mail unless they wrote the comment.
     * If no one else is on the ticket, fall back to Settings "admin_notification_email".
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function commentNotificationRecipients(Ticket $ticket, TicketMessage $message): \Illuminate\Support\Collection
    {
        $commenterId = $message->user_id !== null ? (int) $message->user_id : null;
        $commenterEmailNorm = null;
        if ($message->user && is_string($message->user->email)) {
            $commenterEmailNorm = strtolower(trim($message->user->email));
            if ($commenterEmailNorm === '') {
                $commenterEmailNorm = null;
            }
        }

        // Resolve recipients by ID from DB (pivot + legacy assigned_to + creator) so we never miss people due to stale relations.
        $notifyUserIds = collect(DB::table('ticket_assignees')->where('ticket_id', $ticket->id)->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->filter();

        if ($ticket->assigned_to) {
            $notifyUserIds->push((int) $ticket->assigned_to);
        }
        if ($ticket->created_by) {
            $notifyUserIds->push((int) $ticket->created_by);
        }

        $notifyUserIds = $notifyUserIds->unique()->values();

        $candidates = collect();
        if ($notifyUserIds->isNotEmpty()) {
            User::query()
                ->whereIn('id', $notifyUserIds->all())
                ->get(['id', 'email'])
                ->each(function (User $u) use ($commenterId, $candidates) {
                    if ($commenterId !== null && (int) $u->id === $commenterId) {
                        return;
                    }
                    $candidates->push($u->email);
                });
        }

        $recipients = $this->filterValidNotificationEmails($candidates, $commenterEmailNorm);

        if ($recipients->isEmpty()) {
            $admin = Setting::where('key', 'admin_notification_email')->value('value');
            $recipients = $this->filterValidNotificationEmails(collect([$admin]), $commenterEmailNorm);
        }

        return $recipients;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string|null>  $raw
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function filterValidNotificationEmails(\Illuminate\Support\Collection $raw, ?string $commenterEmailNorm): \Illuminate\Support\Collection
    {
        return $raw
            ->filter(fn ($v) => $v !== null && is_string($v) && trim($v) !== '')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->when($commenterEmailNorm !== null, fn ($c) => $c->reject(fn ($e) => strtolower($e) === $commenterEmailNorm))
            ->unique(fn ($e) => strtolower($e))
            ->values();
    }

    public function notifyStatusChanged(Ticket $ticket, string $previousStatus, ?int $actorUserId): void
    {
        if ($previousStatus === $ticket->status) {
            return;
        }

        $ticket->loadMissing(['customer', 'creator', 'assignee', 'assignees', 'attachments']);

        $emails = collect();
        $pivotIds = $ticket->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ticket->assignees as $assignee) {
            if ($assignee->email && (int) $assignee->id !== (int) ($actorUserId ?? 0)) {
                $emails->push($assignee->email);
            }
        }
        if ($ticket->assigned_to) {
            $pid = (int) $ticket->assigned_to;
            if (! in_array($pid, $pivotIds, true)) {
                $u = ($ticket->assignee && (int) $ticket->assignee->id === $pid)
                    ? $ticket->assignee
                    : User::query()->find($pid);
                if ($u && $u->email && (int) $u->id !== (int) ($actorUserId ?? 0)) {
                    $emails->push($u->email);
                }
            }
        }
        $creator = $ticket->creator ?? ($ticket->created_by ? User::query()->find($ticket->created_by) : null);
        if ($creator && $creator->email && (int) $creator->id !== (int) ($actorUserId ?? 0)) {
            $emails->push($creator->email);
        }

        MailConfigFromDatabase::apply();

        foreach ($emails->unique()->filter() as $email) {
            try {
                Mail::to($email)->send(new TicketStatusChangedMail($ticket, $previousStatus, $actorUserId));
            } catch (\Throwable $e) {
                Log::warning('Ticket status email failed: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                ]);
            }
        }
    }
}
