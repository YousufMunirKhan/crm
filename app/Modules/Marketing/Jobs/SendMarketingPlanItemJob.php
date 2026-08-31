<?php

namespace App\Modules\Marketing\Jobs;

use App\Http\Controllers\EmailManagementController;
use App\Models\EmailTemplate;
use App\Models\SentCommunication;
use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Models\MarketingPlanEvent;
use App\Modules\Marketing\Models\MarketingPlanItem;
use App\Modules\Marketing\Services\MarketingGuardrails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends one approved row of a plan.
 *
 * One job per row rather than one per batch, so a single bad address fails on
 * its own instead of taking the rest of the week's plan with it, and so the
 * review screen can show a real per-person outcome.
 *
 * The guardrails are re-checked here, not just at planning time. A plan may be
 * approved on Monday and sent on Thursday, and somebody can unsubscribe in
 * between - the check that matters is the one at the moment of sending.
 */
class SendMarketingPlanItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $itemId,
        public ?int $sentByUserId = null,
        public ?int $campaignId = null,
    ) {}

    public function handle(EmailManagementController $emailManagement, MarketingGuardrails $rails): void
    {
        $item = MarketingPlanItem::with(['customer', 'emailTemplate'])->find($this->itemId);

        if ($item === null || $item->status !== MarketingPlanItem::STATUS_APPROVED) {
            return;
        }

        $customer = $item->customer;

        if ($customer === null) {
            $this->markFailed($item, 'Customer no longer exists');

            return;
        }

        // Consent can change between approval and sending.
        $check = $rails->check($customer, $item->channel);

        if (! $check['allowed']) {
            $item->update([
                'status' => MarketingPlanItem::STATUS_BLOCKED,
                'blocked_reason' => $check['reason'],
            ]);

            // Consent can change between approval and sending, and a row that
            // silently vanishes at that point is the worst kind of missing.
            MarketingPlanEvent::record(
                $item->marketing_plan_id,
                MarketingPlanEvent::BLOCKED,
                'Not sent to '.($customer->name ?: $customer->email).' - '.$check['reason'],
                $item->id,
                $this->sentByUserId,
            );

            $this->tally($item->marketing_plan_id);

            return;
        }

        if ($item->channel !== 'email') {
            // SMS and WhatsApp go through their own providers; wired separately.
            $this->markFailed($item, ucfirst($item->channel).' sending is not enabled yet');

            return;
        }

        $template = $this->templateFor($item);

        if ($template === null) {
            $this->markFailed($item, 'Template is missing');

            return;
        }

        try {
            $before = SentCommunication::max('id') ?? 0;
            $result = $emailManagement->sendTemplateToOneRecipient($template, $customer, $this->sentByUserId);

            $record = SentCommunication::where('id', '>', $before)
                ->where('customer_id', $customer->id)
                ->latest('id')
                ->first();

            // The frequency cap counts rows that carry a campaign_id, so an
            // agent send has to be stamped with one or the same person could be
            // picked again next week.
            if ($record && $this->campaignId) {
                $record->update(['campaign_id' => $this->campaignId]);
            }

            $sent = ($result['status'] ?? '') !== 'skipped' && ($result['status'] ?? '') !== 'failed';

            $item->update([
                'status' => $sent ? MarketingPlanItem::STATUS_SENT : MarketingPlanItem::STATUS_FAILED,
                'blocked_reason' => $sent ? null : ($result['message'] ?? 'Send was skipped'),
                'sent_communication_id' => $record?->id,
            ]);

            MarketingPlanEvent::record(
                $item->marketing_plan_id,
                $sent ? MarketingPlanEvent::SENT : MarketingPlanEvent::FAILED,
                ($sent ? 'Sent to ' : 'Failed to send to ').($customer->name ?: $customer->email),
                $item->id,
                $this->sentByUserId,
                ['email' => $customer->email],
            );
        } catch (\Throwable $e) {
            Log::error('Marketing plan item failed to send', [
                'item_id' => $item->id,
                'message' => $e->getMessage(),
            ]);
            $this->markFailed($item, $e->getMessage());
        }

        $this->tally($item->marketing_plan_id);
    }

    /**
     * An edited row uses its override. The instance is not saved - it borrows
     * the real template's id so the record still points at the right template,
     * but the edited copy goes only to this one person.
     */
    private function templateFor(MarketingPlanItem $item): ?EmailTemplate
    {
        $template = $item->emailTemplate;

        if ($template === null || ! $item->isEdited()) {
            return $template;
        }

        $copy = $template->replicate();
        $copy->id = $template->id;
        $copy->exists = true;

        if ($item->subject_override !== null) {
            $copy->subject = $item->subject_override;
        }
        if ($item->body_override !== null) {
            $copy->content = $item->body_override;
        }

        return $copy;
    }

    private function markFailed(MarketingPlanItem $item, string $reason): void
    {
        $item->update([
            'status' => MarketingPlanItem::STATUS_FAILED,
            'blocked_reason' => $reason,
        ]);

        MarketingPlanEvent::record(
            $item->marketing_plan_id,
            MarketingPlanEvent::FAILED,
            'Failed: '.$reason,
            $item->id,
            $this->sentByUserId,
        );
        $this->tally($item->marketing_plan_id);
    }

    /** Keeps the plan's counters honest as rows finish one at a time. */
    private function tally(int $planId): void
    {
        $plan = MarketingPlan::find($planId);

        if ($plan === null) {
            return;
        }

        $sent = $plan->items()->where('status', MarketingPlanItem::STATUS_SENT)->count();
        $failed = $plan->items()->where('status', MarketingPlanItem::STATUS_FAILED)->count();
        $outstanding = $plan->items()->where('status', MarketingPlanItem::STATUS_APPROVED)->count();

        $plan->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            // Only "sent" once there is nothing left waiting either - a plan
            // with rows still pending is a plan still in progress.
            'status' => $plan->status === MarketingPlan::STATUS_SENDING && $outstanding === 0 && $plan->isFinished()
                ? MarketingPlan::STATUS_SENT
                : $plan->status,
        ]);
    }
}
