<?php

namespace App\Http\Controllers;

use App\Models\SentCommunication;
use App\Services\AutomatedEmailSender;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * What the system has been emailing, and whether anybody read it.
 *
 * These go out on a schedule with nobody watching, so the only evidence they
 * worked used to be the absence of a complaint. A send that failed - a bad
 * address, an SMTP refusal - was written to a log nobody opens, at a level
 * production drops.
 *
 * Reads sent_communications, which every automated send already writes to, so
 * an email added next month appears here without anybody wiring it up.
 */
class InternalEmailLogController extends Controller
{
    /**
     * Reference prefix to something a person would call it.
     *
     * Longest first: "lead-summary" must not be read as "lead-target" by a
     * loose match, and a preview is labelled a preview whatever it previews.
     */
    private const KINDS = [
        'preview' => 'Preview (test send)',
        'appointment-reminder' => 'Appointment reminder',
        'rep-day' => 'Daily appointment list',
        'follow-up-digest' => 'Follow-up digest',
        'invoice-due' => 'Invoice due soon',
        'invoice-overdue' => 'Invoice overdue chase',
        'lead-target' => 'Lead target (to the person)',
        'lead-summary' => 'Lead summary (to management)',
        'weekly-team' => 'Weekly team summary',
    ];

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'kind' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'in:sent,failed,pending'],
            'opened' => ['nullable', 'in:yes,no'],
            'search' => ['nullable', 'string', 'max:120'],
            'automated_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $query = SentCommunication::query()->where('type', 'email');

        if ($request->boolean('automated_only', true)) {
            $query->where('template_type', AutomatedEmailSender::TEMPLATE_TYPE);
        }

        if (! empty($data['from'])) {
            $query->where('created_at', '>=', Carbon::parse($data['from'])->startOfDay());
        }

        if (! empty($data['to'])) {
            $query->where('created_at', '<=', Carbon::parse($data['to'])->endOfDay());
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (($data['opened'] ?? null) === 'yes') {
            $query->whereNotNull('opened_at');
        } elseif (($data['opened'] ?? null) === 'no') {
            $query->whereNull('opened_at');
        }

        if (! empty($data['kind'])) {
            $query->where('reference', 'like', $data['kind'].':%');
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('recipient_email', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            });
        }

        $summary = (clone $query)
            ->selectRaw('count(*) total')
            ->selectRaw("sum(case when status = 'sent' then 1 else 0 end) sent")
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) failed")
            ->selectRaw('sum(case when opened_at is not null then 1 else 0 end) opened')
            ->first();

        $rows = $query
            ->with('customer:id,name,business_name')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->through(fn (SentCommunication $row) => [
                'id' => $row->id,
                'kind' => $this->kindOf($row->reference),
                'reference' => $row->reference,
                'recipient' => $row->recipient_email,
                'subject' => $row->subject,
                'status' => $row->status,
                'error' => $row->error_message,
                'sent_at' => $row->sent_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
                'opened_at' => $row->opened_at?->toIso8601String(),
                'open_count' => (int) ($row->open_count ?? 0),
                'customer' => $row->customer ? [
                    'id' => $row->customer->id,
                    'name' => $row->customer->business_name ?: $row->customer->name,
                ] : null,
            ]);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'sent' => (int) ($summary->sent ?? 0),
                'failed' => (int) ($summary->failed ?? 0),
                'opened' => (int) ($summary->opened ?? 0),
            ],
            'kinds' => collect(self::KINDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    private function kindOf(?string $reference): string
    {
        $reference = (string) $reference;

        foreach (self::KINDS as $prefix => $label) {
            if (str_starts_with($reference, $prefix.':')) {
                return $label;
            }
        }

        return $reference === '' ? 'Other' : 'Other';
    }
}
