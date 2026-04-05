<?php

namespace App\Modules\CRM\Services;

use App\Models\SentCommunication;
use App\Modules\Communication\Models\Communication;
use App\Modules\CRM\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerTimelineService
{
    /**
     * @param  \Illuminate\Support\Collection<int, \App\Modules\CRM\Models\Lead>  $leadsWithActivities
     * @param  \Illuminate\Support\Collection  $tickets
     */
    public function buildTimeline(
        Customer $customer,
        Collection $leadsWithActivities,
        Collection $tickets,
        ?int $filterLeadId = null
    ): Collection {
        $timeline = collect();

        foreach (Communication::where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get() as $comm) {
            if (! $this->matchesLeadFilter($filterLeadId, $comm->lead_id, 'communication')) {
                continue;
            }
            $channelIcons = [
                'whatsapp' => '💬 WhatsApp',
                'email' => '📧 Email',
                'sms' => '📱 SMS',
                'phone' => '📞 Phone Call',
            ];
            $channelLabel = $channelIcons[$comm->channel] ?? ucfirst((string) $comm->channel);
            $directionLabel = $comm->direction === 'outbound' ? 'sent' : 'received';

            $timeline->push([
                'id' => 'comm-' . $comm->id,
                'kind' => 'communication',
                'type' => 'communication',
                'channel' => $comm->channel,
                'lead_id' => $comm->lead_id,
                'actor_user_id' => null,
                'title' => $channelLabel . ' ' . $directionLabel,
                'body' => $comm->message,
                'when' => $comm->created_at->diffForHumans(),
                'created_at' => $comm->created_at->toIso8601String(),
                'meta' => 'Status: ' . ucfirst((string) $comm->status),
            ]);
        }

        foreach (SentCommunication::where('customer_id', $customer->id)->orderByDesc('sent_at')->orderByDesc('created_at')->get() as $sent) {
            if (! $this->matchesLeadFilter($filterLeadId, $sent->lead_id, 'communication')) {
                continue;
            }
            $channel = $sent->type;
            if (! in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
                continue;
            }
            $channelIcons = [
                'whatsapp' => '💬 WhatsApp',
                'email' => '📧 Email',
                'sms' => '📱 SMS',
            ];
            $channelLabel = $channelIcons[$channel] ?? ucfirst($channel);
            $body = $sent->content ?? '';
            if ($sent->subject && $channel === 'email') {
                $body = trim($sent->subject . "\n\n" . $body);
            }
            $createdAt = $sent->sent_at ?? $sent->created_at;
            $timeline->push([
                'id' => 'sent-' . $sent->id,
                'kind' => 'sent_template',
                'type' => 'communication',
                'channel' => $channel,
                'lead_id' => $sent->lead_id,
                'actor_user_id' => $sent->sent_by ? (int) $sent->sent_by : null,
                'title' => $channelLabel . ' sent (template)',
                'body' => $body,
                'when' => $createdAt->diffForHumans(),
                'created_at' => $createdAt->toIso8601String(),
                'meta' => 'Status: ' . ucfirst((string) $sent->status),
            ]);
        }

        foreach ($leadsWithActivities as $leadItem) {
            if ($filterLeadId !== null && (int) $leadItem->id !== $filterLeadId) {
                continue;
            }

            $stageLabels = [
                'follow_up' => 'Follow-up',
                'lead' => 'Lead',
                'hot_lead' => 'Hot Lead',
                'quotation' => 'Quotation',
                'won' => 'Won',
                'lost' => 'Lost',
            ];
            $stageLabel = $stageLabels[$leadItem->stage] ?? ucfirst((string) $leadItem->stage);
            $products = $leadItem->items->pluck('product.name')->filter()->implode(', ') ?: 'No products';
            $metaParts = [];
            if ($leadItem->source) {
                $metaParts[] = 'Source: ' . $leadItem->source;
            }
            if ($leadItem->assignee) {
                $metaParts[] = 'Assigned: ' . $leadItem->assignee->name;
            }
            if ($leadItem->next_follow_up_at) {
                $metaParts[] = 'Next: ' . Carbon::parse($leadItem->next_follow_up_at)->format('d M Y, H:i');
            }

            $latestReminderNote = null;
            if ($leadItem->relationLoaded('activities')) {
                $latestReminder = $leadItem->activities
                    ->where('type', 'reminder')
                    ->sortByDesc('created_at')
                    ->first();
            } else {
                $latestReminder = $leadItem->activities()
                    ->where('type', 'reminder')
                    ->latest()
                    ->first();
            }
            if ($latestReminder && $latestReminder->description) {
                $latestReminderNote = $latestReminder->description;
            }

            $leadCreatedBody = $products;
            if ($latestReminderNote) {
                $leadCreatedBody .= "\n" . $latestReminderNote;
            }

            $timeline->push([
                'id' => 'lead-created-' . $leadItem->id,
                'kind' => 'lead_created',
                'type' => 'lead_created',
                'lead_id' => $leadItem->id,
                'actor_user_id' => $leadItem->created_by ? (int) $leadItem->created_by : null,
                'title' => $stageLabel . ' created',
                'body' => $leadCreatedBody,
                'when' => $leadItem->created_at->diffForHumans(),
                'created_at' => $leadItem->created_at->toIso8601String(),
                'meta' => implode(' · ', $metaParts),
            ]);

            $leadCreatedAt = Carbon::parse($leadItem->created_at);
            $activitiesForLead = $leadItem->relationLoaded('activities')
                ? $leadItem->activities->sortByDesc('created_at')->values()
                : $leadItem->activities()->with('user')->latest()->get();
            foreach ($activitiesForLead as $activity) {
                if ($activity->type === 'followup') {
                    $activityCreatedAt = Carbon::parse($activity->created_at);
                    if ($activityCreatedAt->diffInMinutes($leadCreatedAt) <= 2) {
                        continue;
                    }
                }

                if ($activity->type === 'stage_change') {
                    $meta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
                    if (! isset($meta['from_stage']) || ! isset($meta['to_stage'])) {
                        continue;
                    }

                    $fromStage = $stageLabels[$meta['from_stage']] ?? ucfirst((string) $meta['from_stage']);
                    $toStage = $stageLabels[$meta['to_stage']] ?? ucfirst((string) $meta['to_stage']);

                    $timeline->push([
                        'id' => 'activity-' . $activity->id,
                        'kind' => 'stage_change',
                        'type' => 'stage_change',
                        'lead_id' => $leadItem->id,
                        'actor_user_id' => $activity->user_id ? (int) $activity->user_id : null,
                        'title' => '🔄 Stage Changed',
                        'body' => 'Moved from ' . $fromStage . ' → ' . $toStage,
                        'when' => $activity->created_at->diffForHumans(),
                        'created_at' => $activity->created_at->toIso8601String(),
                        'meta' => 'By: ' . ($activity->user->name ?? 'Unknown') . ' | Lead #' . $leadItem->id,
                    ]);
                    continue;
                }

                $activityIcons = [
                    'call' => '📞 Call',
                    'meeting' => '🤝 Meeting',
                    'appointment' => '📅 Appointment',
                    'email' => '📧 Email',
                    'visit' => '🏢 Site Visit',
                    'whatsapp' => '💬 WhatsApp',
                    'sms' => '📱 SMS',
                    'quote_sent' => '📄 Quote Sent',
                    'note' => '📝 Note',
                    'reminder' => '⏰ Reminder',
                    'followup' => '📅 Follow-up Scheduled',
                    'other' => '📋 Activity',
                ];
                $activityLabel = $activityIcons[$activity->type] ?? ucfirst(str_replace('_', ' ', (string) $activity->type));

                $meta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
                $outcome = $meta['outcome'] ?? null;
                $outcomeLabels = [
                    'positive' => '✅ Positive',
                    'neutral' => '➖ Neutral',
                    'negative' => '❌ Negative',
                    'no_answer' => '📵 No Answer',
                ];
                $outcomeLabel = $outcome ? ($outcomeLabels[$outcome] ?? ucfirst((string) $outcome)) : null;

                $metaText = 'By: ' . ($activity->user->name ?? 'Unknown');
                if ($outcomeLabel) {
                    $metaText .= ' | Outcome: ' . $outcomeLabel;
                }
                if (isset($meta['duration']) && $meta['duration']) {
                    $metaText .= ' | Duration: ' . $meta['duration'] . ' min';
                }

                $timelineType = 'activity';
                if (in_array($activity->type, ['call', 'meeting', 'visit', 'appointment'], true)) {
                    $timelineType = $activity->type;
                } elseif ($activity->type === 'reminder' || $activity->type === 'followup') {
                    $timelineType = 'reminder';
                } elseif ($activity->type === 'note') {
                    $timelineType = 'note';
                }

                $timeline->push([
                    'id' => 'activity-' . $activity->id,
                    'kind' => 'activity',
                    'type' => $timelineType,
                    'lead_id' => $leadItem->id,
                    'actor_user_id' => $activity->user_id ? (int) $activity->user_id : null,
                    'title' => $activityLabel . ' (Lead #' . $leadItem->id . ')',
                    'body' => $activity->description,
                    'when' => $activity->created_at->diffForHumans(),
                    'created_at' => $activity->created_at->toIso8601String(),
                    'meta' => $metaText,
                ]);
            }

            foreach ($leadItem->items as $item) {
                if ($item->status === 'won' && $item->closed_at) {
                    $timeline->push([
                        'id' => 'item-won-' . $item->id,
                        'kind' => 'item_won',
                        'type' => 'won',
                        'lead_id' => $leadItem->id,
                        'actor_user_id' => null,
                        'title' => '🎉 Product Won',
                        'body' => ($item->product?->name ?? 'Product') . ' - Qty: ' . $item->quantity . ' × £' . number_format((float) $item->unit_price, 2) . ' = £' . number_format((float) $item->total_price, 2),
                        'when' => Carbon::parse($item->closed_at)->diffForHumans(),
                        'created_at' => Carbon::parse($item->closed_at)->toIso8601String(),
                        'meta' => 'Lead #' . $leadItem->id,
                    ]);
                } elseif ($item->status === 'lost' && $item->closed_at) {
                    $timeline->push([
                        'id' => 'item-lost-' . $item->id,
                        'kind' => 'item_lost',
                        'type' => 'lost',
                        'lead_id' => $leadItem->id,
                        'actor_user_id' => null,
                        'title' => '❌ Product Lost',
                        'body' => $item->product?->name ?? 'Product',
                        'when' => Carbon::parse($item->closed_at)->diffForHumans(),
                        'created_at' => Carbon::parse($item->closed_at)->toIso8601String(),
                        'meta' => 'Lead #' . $leadItem->id,
                    ]);
                }
            }
        }

        if ($filterLeadId === null) {
            foreach ($tickets as $ticket) {
                $timeline->push([
                    'id' => 'ticket-' . $ticket->id,
                    'kind' => 'ticket',
                    'type' => 'ticket',
                    'lead_id' => null,
                    'actor_user_id' => $ticket->created_by ? (int) $ticket->created_by : null,
                    'title' => '🎫 Ticket: ' . $ticket->subject,
                    'body' => $ticket->description,
                    'when' => $ticket->created_at->diffForHumans(),
                    'created_at' => $ticket->created_at->toIso8601String(),
                    'meta' => 'Priority: ' . ucfirst((string) $ticket->priority) . ' | Status: ' . ucfirst(str_replace('_', ' ', (string) $ticket->status)),
                ]);
            }
        }

        return $timeline->sortByDesc(fn ($item) => $item['created_at'])->values();
    }

    /**
     * Filter merged timeline by event time and optional actor (user who logged the activity / sent template / created lead / ticket).
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $timeline
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function applyTimelineFilters(Collection $timeline, ?Carbon $fromInclusive, ?Carbon $toInclusive, ?int $actorUserId): Collection
    {
        return $timeline
            ->filter(function (array $item) use ($fromInclusive, $toInclusive, $actorUserId) {
                $at = Carbon::parse($item['created_at']);
                if ($fromInclusive && $at->lt($fromInclusive)) {
                    return false;
                }
                if ($toInclusive && $at->gt($toInclusive)) {
                    return false;
                }
                if ($actorUserId !== null) {
                    $actor = $item['actor_user_id'] ?? null;
                    if ($actor === null || (int) $actor !== $actorUserId) {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc(fn ($item) => $item['created_at'])
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Modules\CRM\Models\Lead>  $leadsWithActivities
     */
    public function collectAppointments(Collection $leadsWithActivities): Collection
    {
        $appointments = collect();
        foreach ($leadsWithActivities as $leadItem) {
            $acts = $leadItem->relationLoaded('activities')
                ? $leadItem->activities->sortByDesc('created_at')->values()
                : $leadItem->activities()->with('assignee')->latest()->get();
            foreach ($acts as $activity) {
                if ($activity->type !== 'appointment') {
                    continue;
                }
                $actMeta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
                $date = $activity->appointment_date
                    ? $activity->appointment_date->format('Y-m-d')
                    : ($actMeta['appointment_date'] ?? null);
                $time = $activity->appointment_time ?? ($actMeta['appointment_time'] ?? '10:00');
                $appointments->push([
                    'id' => $activity->id,
                    'lead_id' => $leadItem->id,
                    'description' => $activity->description ?: 'Appointment',
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'appointment_status' => $activity->appointment_status ?? 'pending',
                    'outcome_notes' => $activity->outcome_notes,
                    'assignee' => $activity->assignee,
                    'created_at' => $activity->created_at->toIso8601String(),
                ]);
            }
        }

        return $appointments->sort(function ($a, $b) {
            $dateA = $a['appointment_date'] ?? '';
            $dateB = $b['appointment_date'] ?? '';
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }

            return strcmp($a['appointment_time'] ?? '00:00', $b['appointment_time'] ?? '00:00');
        })->values();
    }

    private function matchesLeadFilter(?int $filterLeadId, ?int $itemLeadId, string $logicalType): bool
    {
        if ($filterLeadId === null) {
            return true;
        }
        if ($logicalType === 'ticket') {
            return true;
        }
        if ($itemLeadId === null) {
            return false;
        }

        return (int) $itemLeadId === $filterLeadId;
    }
}
