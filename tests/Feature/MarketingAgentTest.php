<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContactConsent;
use App\Models\Role;
use App\Models\SentCommunication;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Models\MarketingPlanItem;
use App\Modules\Marketing\Services\MarketingGuardrails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guardrails are the point of this feature. The planner may suggest anyone;
 * these rules decide who can actually be written to, and they live in code
 * rather than in the prompt because a model asked nicely to respect consent
 * will respect it almost always - and "almost always" across five hundred
 * contacts is a complaint to the regulator.
 */
class MarketingAgentTest extends TestCase
{
    use RefreshDatabase;

    private function rails(): MarketingGuardrails
    {
        return app(MarketingGuardrails::class);
    }

    private function manager(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Manager'], ['description' => 'Manager']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function campaign(): Campaign
    {
        return Campaign::create([
            'name' => 'Earlier campaign',
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    private function plan(): MarketingPlan
    {
        return MarketingPlan::create([
            'week_starting' => now()->startOfWeek(),
            'status' => MarketingPlan::STATUS_DRAFT,
        ]);
    }

    public function test_an_existing_customer_may_be_emailed_without_an_explicit_opt_in(): void
    {
        // PECR soft opt-in: an existing customer, similar products.
        $customer = Customer::create(['phone' => '07700901001', 'name' => 'A', 'email' => 'a@example.com', 'type' => Customer::TYPE_CUSTOMER]);

        $this->assertTrue($this->rails()->check($customer, 'email')['allowed']);
    }

    public function test_a_prospect_without_consent_may_not_be_emailed(): void
    {
        $prospect = Customer::create(['phone' => '07700901002', 'name' => 'B', 'email' => 'b@example.com', 'type' => Customer::TYPE_PROSPECT]);

        $result = $this->rails()->check($prospect, 'email');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('consent', $result['reason']);
    }

    public function test_a_prospect_with_recorded_consent_may_be_emailed(): void
    {
        $prospect = Customer::create(['phone' => '07700901003', 'name' => 'C', 'email' => 'c@example.com', 'type' => Customer::TYPE_PROSPECT]);

        ContactConsent::create([
            'identifier' => 'c@example.com',
            'channel' => 'email',
            'status' => ContactConsent::STATUS_OPT_IN,
            'source' => 'test',
            'customer_id' => $prospect->id,
        ]);

        $this->assertTrue($this->rails()->check($prospect, 'email')['allowed']);
    }

    public function test_an_opt_out_beats_being_a_customer(): void
    {
        $customer = Customer::create(['phone' => '07700901004', 'name' => 'D', 'email' => 'd@example.com', 'type' => Customer::TYPE_CUSTOMER]);

        ContactConsent::create([
            'identifier' => 'd@example.com',
            'channel' => 'email',
            'status' => ContactConsent::STATUS_OPT_OUT,
            'source' => 'test',
            'customer_id' => $customer->id,
        ]);

        $this->assertFalse($this->rails()->check($customer, 'email')['allowed']);
    }

    /**
     * Counted across all channels. Per-channel counting would let one contact
     * get an email, a text and a WhatsApp in the same week, each one looking
     * compliant on its own.
     */
    public function test_a_recently_messaged_contact_is_held_back(): void
    {
        $customer = Customer::create(['phone' => '07700901005', 'name' => 'E', 'email' => 'e@example.com', 'type' => Customer::TYPE_CUSTOMER]);

        SentCommunication::create([
            'campaign_id' => $this->campaign()->id,
            'type' => 'email',
            'customer_id' => $customer->id,
            'recipient_email' => 'e@example.com',
            'subject' => 'Earlier campaign',
            'content' => 'Body',
            'status' => 'sent',
            'sent_at' => now()->subDays(3),
        ]);

        $result = $this->rails()->check($customer, 'email');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('recently', $result['reason']);
    }

    public function test_a_contact_becomes_eligible_again_after_the_window(): void
    {
        $customer = Customer::create(['phone' => '07700901006', 'name' => 'F', 'email' => 'f@example.com', 'type' => Customer::TYPE_CUSTOMER]);

        SentCommunication::create([
            'campaign_id' => $this->campaign()->id,
            'type' => 'email',
            'customer_id' => $customer->id,
            'recipient_email' => 'f@example.com',
            'subject' => 'Earlier campaign',
            'content' => 'Body',
            'status' => 'sent',
            'sent_at' => now()->subDays(MarketingGuardrails::MIN_DAYS_BETWEEN_MESSAGES + 1),
        ]);

        $this->assertTrue($this->rails()->check($customer, 'email')['allowed']);
    }

    /** Email is nearly free; the others cost money per message. */
    public function test_the_cheapest_usable_channel_is_chosen(): void
    {
        $customer = Customer::create([
            'name' => 'G',
            'email' => 'g@example.com',
            'phone' => '07700900123',
            'type' => Customer::TYPE_CUSTOMER,
        ]);

        $this->assertSame('email', $this->rails()->bestChannel($customer)['channel']);
    }

    /**
     * SMS has no provider wired up yet, so a contact reachable only by phone is
     * refused at planning time with a reason on screen - rather than queued and
     * failed one by one an hour later.
     */
    public function test_a_contact_with_only_a_phone_is_refused_while_sms_is_off(): void
    {
        $customer = Customer::create([
            'name' => 'H',
            'phone' => '07700900124',
            'type' => Customer::TYPE_CUSTOMER,
        ]);

        $best = $this->rails()->bestChannel($customer);

        $this->assertNull($best['channel']);
        $this->assertStringContainsString('not switched on', $best['reasons']['sms']);
    }

    /**
     * The model may suggest a channel; it does not get to pick one. Letting it
     * choose undid the whole reason the ordering exists - it put a contact with
     * a perfectly good email address on SMS, which costs money per message.
     */
    public function test_the_models_channel_suggestion_cannot_override_the_ordering(): void
    {
        $customer = Customer::create([
            'name' => 'M',
            'email' => 'm@example.com',
            'phone' => '07700900125',
            'type' => Customer::TYPE_CUSTOMER,
        ]);

        $this->assertSame('email', $this->rails()->bestChannel($customer, 'sms')['channel']);
    }

    public function test_a_contact_with_no_details_at_all_is_unreachable(): void
    {
        // customers.phone is NOT NULL, so "no details" means empty, not absent.
        $customer = Customer::create(['phone' => '', 'name' => 'I', 'type' => Customer::TYPE_CUSTOMER]);

        $this->assertNull($this->rails()->bestChannel($customer)['channel']);
    }

    // ------------------------------------------------------------ the screen

    public function test_a_sales_agent_cannot_see_marketing_plans(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);
        $agent = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($agent)->getJson('/api/marketing/agent/plans')->assertForbidden();
    }

    public function test_a_blocked_row_cannot_be_approved(): void
    {
        $plan = $this->plan();
        $customer = Customer::create(['phone' => '07700901008', 'name' => 'J', 'type' => Customer::TYPE_PROSPECT]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_BLOCKED,
            'blocked_reason' => 'No consent recorded for email',
        ]);

        $this->actingAs($this->manager())
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", ['status' => 'approved'])
            ->assertStatus(422);

        $this->assertSame(MarketingPlanItem::STATUS_BLOCKED, $item->fresh()->status);
    }

    public function test_sending_refuses_when_nothing_is_approved(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->manager())
            ->postJson("/api/marketing/agent/plans/{$plan->id}/send")
            ->assertStatus(422);
    }

    /**
     * Editing one row must not change anyone else's message - that confusion is
     * why the override lives on the row rather than on the template.
     */
    public function test_an_override_is_stored_against_the_row_only(): void
    {
        $plan = $this->plan();
        $customer = Customer::create(['phone' => '07700901009', 'name' => 'K', 'email' => 'k@example.com', 'type' => Customer::TYPE_CUSTOMER]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_PENDING,
        ]);

        $this->actingAs($this->manager())
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", [
                'subject_override' => 'Just for this one',
            ])
            ->assertOk();

        $this->assertSame('Just for this one', $item->fresh()->subject_override);
        $this->assertTrue($item->fresh()->isEdited());
    }

    // ------------------------------------------------------- history and log

    /**
     * Rebuilding used to delete the previous plan, so "what did we decide last
     * week and what came of it" had no answer. The old one is kept and marked.
     */
    public function test_a_rebuild_supersedes_the_old_plan_instead_of_deleting_it(): void
    {
        $old = $this->plan();

        $old->update([
            'status' => MarketingPlan::STATUS_SUPERSEDED,
            'superseded_by_id' => null,
            'superseded_at' => now(),
        ]);

        $this->assertDatabaseHas('marketing_plans', [
            'id' => $old->id,
            'status' => MarketingPlan::STATUS_SUPERSEDED,
        ]);
    }

    public function test_approving_a_row_is_written_to_the_activity_log(): void
    {
        $plan = $this->plan();
        $customer = Customer::create([
            'phone' => '07700901020', 'name' => 'Log Me',
            'email' => 'log@example.com', 'type' => Customer::TYPE_CUSTOMER,
        ]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_PENDING,
        ]);

        $manager = $this->manager();

        $this->actingAs($manager)
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", ['status' => 'approved'])
            ->assertOk();

        $this->assertDatabaseHas('marketing_plan_events', [
            'marketing_plan_id' => $plan->id,
            'marketing_plan_item_id' => $item->id,
            'action' => 'approved',
            'user_id' => $manager->id,
        ]);
    }

    /** Cancelling one person is exactly the thing people ask about afterwards. */
    public function test_skipping_a_row_records_who_did_it(): void
    {
        $plan = $this->plan();
        $customer = Customer::create([
            'phone' => '07700901021', 'name' => 'Skip Me',
            'email' => 'skip@example.com', 'type' => Customer::TYPE_CUSTOMER,
        ]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_PENDING,
        ]);

        $manager = $this->manager();

        $this->actingAs($manager)
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", ['status' => 'skipped'])
            ->assertOk();

        $event = \App\Modules\Marketing\Models\MarketingPlanEvent::where('action', 'skipped')->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('Skip Me', $event->summary);
        $this->assertSame($manager->id, $event->user_id);
    }

    public function test_editing_one_message_is_logged_as_affecting_that_person_only(): void
    {
        $plan = $this->plan();
        $customer = Customer::create([
            'phone' => '07700901022', 'name' => 'Edited One',
            'email' => 'edit@example.com', 'type' => Customer::TYPE_CUSTOMER,
        ]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_PENDING,
        ]);

        $this->actingAs($this->manager())
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", [
                'subject_override' => 'Only for them',
            ])
            ->assertOk();

        $event = \App\Modules\Marketing\Models\MarketingPlanEvent::where('action', 'edited')->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('only', $event->summary);
    }

    /**
     * Results count against delivered, not attempted. A bounce is not a person
     * who ignored you, and putting it in the denominator understates every
     * campaign for ever.
     */
    public function test_results_exclude_bounces_from_the_open_rate(): void
    {
        $plan = $this->plan();
        $campaign = $this->campaign();

        foreach ([['delivered', true], ['bounced', false]] as [$name, $ok]) {
            $customer = Customer::create([
                'phone' => '0770090200'.($ok ? '1' : '2'),
                'name' => $name,
                'email' => $name.'@example.com',
                'type' => Customer::TYPE_CUSTOMER,
            ]);

            $comm = SentCommunication::create([
                'campaign_id' => $campaign->id,
                'type' => 'email',
                'customer_id' => $customer->id,
                'recipient_email' => $customer->email,
                'subject' => 'x',
                'content' => 'y',
                'status' => $ok ? 'sent' : 'failed',
                'opened_at' => $ok ? now() : null,
                'sent_at' => now(),
            ]);

            MarketingPlanItem::create([
                'marketing_plan_id' => $plan->id,
                'customer_id' => $customer->id,
                'channel' => 'email',
                'purpose' => 'check-in',
                'status' => $ok ? MarketingPlanItem::STATUS_SENT : MarketingPlanItem::STATUS_FAILED,
                'sent_communication_id' => $comm->id,
            ]);
        }

        $results = app(\App\Modules\Marketing\Services\MarketingResultsService::class)->forPlan($plan);

        $this->assertSame(2, $results['totals']['attempted']);
        $this->assertSame(1, $results['totals']['delivered']);
        $this->assertSame(1, $results['totals']['bounced']);
        // 1 of 1 delivered, not 1 of 2 attempted.
        $this->assertSame(100.0, $results['totals']['open_rate']);
    }

    /**
     * A failure is almost always the mail server rather than the message - the
     * first real batch bounced entirely against a placeholder SMTP host - so
     * one address must be retryable without resending the batch, and without
     * losing the approvals and skips already decided.
     */
    public function test_one_failed_message_can_be_sent_again_on_its_own(): void
    {
        $plan = $this->plan();
        $plan->update(['status' => MarketingPlan::STATUS_SENDING]);

        $rows = [];

        foreach (['one', 'two'] as $i => $name) {
            $customer = Customer::create([
                'phone' => '0770090300'.$i,
                'name' => $name,
                'email' => $name.'@example.com',
                'type' => Customer::TYPE_CUSTOMER,
            ]);

            $rows[$name] = MarketingPlanItem::create([
                'marketing_plan_id' => $plan->id,
                'customer_id' => $customer->id,
                'channel' => 'email',
                'purpose' => 'check-in',
                'status' => MarketingPlanItem::STATUS_FAILED,
                'blocked_reason' => 'Connection refused',
            ]);
        }

        // Faked, otherwise the sync queue runs the job inside the request and
        // the row has already moved on before the assertions look at it.
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->manager())
            ->postJson("/api/marketing/agent/plans/{$plan->id}/retry", ['item_ids' => [$rows['one']->id]])
            ->assertOk()
            ->assertJsonPath('queued', 1);

        // Exactly one job, and it is for the row that was chosen.
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Modules\Marketing\Jobs\SendMarketingPlanItemJob::class,
            fn ($job) => $job->itemId === $rows['one']->id,
        );
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Modules\Marketing\Jobs\SendMarketingPlanItemJob::class,
            1,
        );

        // The chosen one is back in the queue with its stale error cleared.
        $this->assertSame(MarketingPlanItem::STATUS_APPROVED, $rows['one']->fresh()->status);
        $this->assertNull($rows['one']->fresh()->blocked_reason);

        // The other is untouched.
        $this->assertSame(MarketingPlanItem::STATUS_FAILED, $rows['two']->fresh()->status);
    }

    /**
     * Sending part of a plan used to lock the rest of it, which made the sane
     * way to run this - send a few, look at the results, then decide about the
     * others - impossible.
     */
    public function test_a_plan_can_still_be_worked_on_after_a_partial_send(): void
    {
        $plan = $this->plan();
        $plan->update(['status' => MarketingPlan::STATUS_SENDING]);

        $customer = Customer::create([
            'phone' => '07700904001', 'name' => 'Still waiting',
            'email' => 'waiting@example.com', 'type' => Customer::TYPE_CUSTOMER,
        ]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_PENDING,
        ]);

        $this->actingAs($this->manager())
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", ['status' => 'approved'])
            ->assertOk();

        $this->assertSame(MarketingPlanItem::STATUS_APPROVED, $item->fresh()->status);
    }

    /**
     * The cap has to span batches, or sending fifty and then approving fifty
     * more walks straight past it.
     */
    public function test_the_weekly_cap_counts_what_has_already_gone(): void
    {
        $plan = $this->plan();
        $cap = MarketingGuardrails::WEEKLY_RECIPIENT_CAP;

        // Fill the week's allowance with rows that already went.
        for ($i = 0; $i < $cap; $i++) {
            $c = Customer::create([
                'phone' => '079000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'name' => 'Sent '.$i,
                'email' => "sent{$i}@example.com",
                'type' => Customer::TYPE_CUSTOMER,
            ]);

            MarketingPlanItem::create([
                'marketing_plan_id' => $plan->id,
                'customer_id' => $c->id,
                'channel' => 'email',
                'purpose' => 'check-in',
                'status' => MarketingPlanItem::STATUS_SENT,
            ]);
        }

        $extra = Customer::create([
            'phone' => '07700904999', 'name' => 'One too many',
            'email' => 'toomany@example.com', 'type' => Customer::TYPE_CUSTOMER,
        ]);

        MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $extra->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_APPROVED,
        ]);

        $this->actingAs($this->manager())
            ->postJson("/api/marketing/agent/plans/{$plan->id}/send")
            ->assertStatus(422)
            ->assertJsonPath('message', "This week's cap is {$cap}. {$cap} have gone already, so there is room for 0 more.");
    }

    public function test_retrying_with_nothing_failed_is_refused(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->manager())
            ->postJson("/api/marketing/agent/plans/{$plan->id}/retry")
            ->assertStatus(422);
    }

    public function test_an_empty_override_clears_it_rather_than_sending_a_blank_subject(): void
    {
        $plan = $this->plan();
        $customer = Customer::create(['phone' => '07700901010', 'name' => 'L', 'email' => 'l@example.com', 'type' => Customer::TYPE_CUSTOMER]);

        $item = MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'purpose' => 'check-in',
            'status' => MarketingPlanItem::STATUS_PENDING,
            'subject_override' => 'Something',
        ]);

        $this->actingAs($this->manager())
            ->patchJson("/api/marketing/agent/plans/{$plan->id}/items/{$item->id}", ['subject_override' => '  '])
            ->assertOk();

        $this->assertNull($item->fresh()->subject_override);
    }
}
