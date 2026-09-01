<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Support\LostReasons;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marking a lead lost used to cost a typed sentence, so out of 571 leads
 * exactly one carries a reason and the company's record reads 391 won against
 * 3 lost. These cover the replacement: a fixed code the database can count,
 * with the free text kept for detail rather than depended on.
 */
class LostReasonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    private function makeLead(): Lead
    {
        $customer = Customer::create([
            'name' => 'Test Shop',
            'phone' => '07700900001',
            'created_by' => $this->user->id,
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_marking_a_lead_lost_requires_a_reason_code(): void
    {
        $lead = $this->makeLead();

        $this->putJson("/api/leads/{$lead->id}", ['stage' => 'lost'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lost_reason_code');

        $this->assertSame('lead', $lead->fresh()->stage);
    }

    public function test_an_invented_reason_code_is_refused(): void
    {
        $lead = $this->makeLead();

        $this->putJson("/api/leads/{$lead->id}", [
            'stage' => 'lost',
            'lost_reason_code' => 'because_i_said_so',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason_code');
    }

    public function test_a_reason_that_explains_nothing_on_its_own_needs_detail(): void
    {
        $lead = $this->makeLead();

        // "Something else" and "went with a competitor" are the two where the
        // label alone leaves you no better off than not asking.
        $this->putJson("/api/leads/{$lead->id}", [
            'stage' => 'lost',
            'lost_reason_code' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason');

        $this->putJson("/api/leads/{$lead->id}", [
            'stage' => 'lost',
            'lost_reason_code' => 'competitor',
            'lost_reason' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason');
    }

    public function test_a_plain_reason_needs_no_typing_at_all(): void
    {
        $lead = $this->makeLead();

        $this->putJson("/api/leads/{$lead->id}", [
            'stage' => 'lost',
            'lost_reason_code' => 'price',
        ])->assertOk();

        $lead->refresh();

        $this->assertSame('lost', $lead->stage);
        $this->assertSame('price', $lead->lost_reason_code);
        // The free-text column still holds something a person can read, so every
        // screen and export already showing it keeps working.
        $this->assertSame('Price', $lead->lost_reason);
    }

    public function test_detail_is_appended_to_the_readable_reason(): void
    {
        $lead = $this->makeLead();

        $this->putJson("/api/leads/{$lead->id}", [
            'stage' => 'lost',
            'lost_reason_code' => 'competitor',
            'lost_reason' => 'Worldpay, 18 month deal',
        ])->assertOk();

        $lead->refresh();

        $this->assertSame('competitor', $lead->lost_reason_code);
        $this->assertSame('Went with a competitor - Worldpay, 18 month deal', $lead->lost_reason);
    }

    public function test_closing_a_product_line_as_lost_stores_a_countable_reason(): void
    {
        $lead = $this->makeLead();
        $product = Product::create(['name' => 'Card Terminal', 'is_active' => true]);

        $item = LeadItem::create([
            'lead_id' => $lead->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 0,
            'status' => LeadItem::STATUS_PENDING,
        ]);

        $this->postJson("/api/leads/{$lead->id}/items/{$item->id}/close", [
            'status' => 'lost',
            'lost_reason_code' => 'in_contract',
        ])->assertOk();

        $item->refresh();

        // Previously a line-level loss went into an activity description and
        // nowhere else - readable, but impossible to count.
        $this->assertSame('in_contract', $item->lost_reason_code);
        $this->assertSame('Tied into a contract', $item->lost_reason);
    }

    public function test_closing_a_product_line_lost_without_a_reason_is_refused(): void
    {
        $lead = $this->makeLead();
        $product = Product::create(['name' => 'ePOS', 'is_active' => true]);

        $item = LeadItem::create([
            'lead_id' => $lead->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 0,
            'status' => LeadItem::STATUS_PENDING,
        ]);

        $this->postJson("/api/leads/{$lead->id}/items/{$item->id}/close", ['status' => 'lost'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lost_reason_code');

        $this->assertSame(LeadItem::STATUS_PENDING, $item->fresh()->status);
    }

    public function test_the_funnel_groups_losses_by_reason_and_owns_up_to_the_uncoded_ones(): void
    {
        foreach (['price', 'price', 'no_response'] as $code) {
            $lead = $this->makeLead();
            $lead->update([
                'stage' => 'lost',
                'lost_reason_code' => $code,
                'lost_reason' => LostReasons::compose($code, null),
            ]);
        }

        // A loss from before the picker existed.
        $this->makeLead()->update(['stage' => 'lost', 'lost_reason' => 'they went quiet']);

        $report = app(ReportingService::class)->getFunnelReport();

        $this->assertSame(['Price' => 2, 'Could not reach them' => 1], $report['lost_reasons']);
        $this->assertSame(1, $report['lost_reasons_uncoded']);
    }

    public function test_the_php_and_javascript_reason_lists_have_not_drifted(): void
    {
        // The picker reads a JS constant rather than fetching, so a dialog the
        // user is waiting on does not show a spinner. That only stays safe while
        // the two lists agree.
        $js = file_get_contents(resource_path('js/constants/lostReasons.js'));

        preg_match('/export const LOST_REASONS = \[(.*?)\n\];/s', $js, $m);
        $this->assertNotEmpty($m, 'Could not find LOST_REASONS in the JS constants file.');

        preg_match_all(
            "/\{ code: '([a-z_]+)', label: '([^']+)', hint: '([^']+)', detail_required: (true|false) \}/",
            $m[1],
            $rows,
            PREG_SET_ORDER
        );

        $fromJs = array_map(fn ($r) => [
            'code' => $r[1],
            'label' => $r[2],
            'hint' => $r[3],
            'detail_required' => $r[4] === 'true',
        ], $rows);

        $this->assertSame(LostReasons::forPicker(), $fromJs);
    }
}
