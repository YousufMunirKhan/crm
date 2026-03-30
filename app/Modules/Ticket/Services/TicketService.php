<?php

namespace App\Modules\Ticket\Services;

use App\Mail\TicketAssignedMail;
use App\Mail\TicketCommentMail;
use App\Mail\TicketStatusChangedMail;
use App\Modules\Settings\Models\Setting;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\TicketMessage;
use App\Modules\CRM\Models\Customer;
use App\Services\MailConfigFromDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketService
{
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

        $ticket = Ticket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'source' => 'crm',
            'customer_id' => $customer?->id,
            'created_by' => $userId ?? auth()->id(),
            'assigned_to' => $data['assigned_to'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'reference_url' => $data['reference_url'] ?? null,
            'priority' => $priority,
            'estimated_resolve_hours' => $estimatedHours,
            'status' => 'open',
            'sla_due_at' => $slaDue,
        ]);

        $ticket->load(['customer', 'creator', 'assignee']);
        if ($ticket->assigned_to && $ticket->assignee?->email) {
            $this->notifyAssigned($ticket);
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
        $ticket->loadMissing(['customer', 'creator', 'assignee']);
        $this->notifyCommentPosted($ticket, $ticketMessage);

        return $ticketMessage;
    }

    public function notifyAssigned(Ticket $ticket): void
    {
        $ticket->loadMissing(['customer', 'creator', 'assignee', 'attachments']);
        if (!$ticket->assigned_to || !$ticket->assignee?->email) {
            return;
        }

        try {
            MailConfigFromDatabase::apply();
            Mail::to($ticket->assignee->email)->send(new TicketAssignedMail($ticket));
        } catch (\Throwable $e) {
            Log::warning('Ticket assignment email failed: ' . $e->getMessage(), ['ticket_id' => $ticket->id]);
        }
    }

    public function notifyCommentPosted(Ticket $ticket, TicketMessage $message): void
    {
        $ticket->loadMissing(['customer', 'creator', 'assignee', 'attachments']);
        $message->loadMissing('user');

        $recipients = $this->commentNotificationRecipients($ticket, $message);

        if ($recipients->isEmpty()) {
            Log::info('Ticket comment skipped email: no recipients', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'commenter_user_id' => $message->user_id,
                'has_assignee' => (bool) $ticket->assigned_to,
                'has_creator' => (bool) $ticket->created_by,
            ]);

            return;
        }

        MailConfigFromDatabase::apply();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new TicketCommentMail($ticket, $message));
            } catch (\Throwable $e) {
                Log::warning('Ticket comment email failed: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                    'to' => $email,
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

        $candidates = collect();

        if ($ticket->assigned_to && $ticket->assignee) {
            if ($commenterId === null || (int) $ticket->assignee->id !== $commenterId) {
                $candidates->push($ticket->assignee->email ?? null);
            }
        }

        if ($ticket->created_by && $ticket->creator) {
            if ($commenterId === null || (int) $ticket->creator->id !== $commenterId) {
                $candidates->push($ticket->creator->email ?? null);
            }
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

        $ticket->loadMissing(['customer', 'creator', 'assignee', 'attachments']);

        $emails = collect();
        if ($ticket->assigned_to && $ticket->assignee && $ticket->assignee->email) {
            if ((int) $ticket->assignee->id !== (int) ($actorUserId ?? 0)) {
                $emails->push($ticket->assignee->email);
            }
        }
        if ($ticket->created_by && $ticket->creator && $ticket->creator->email) {
            if ((int) $ticket->creator->id !== (int) ($actorUserId ?? 0)) {
                $emails->push($ticket->creator->email);
            }
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
