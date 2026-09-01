<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Reporting\Services\BookHealthService;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner's dashboard was built entirely out of date ranges, so a quiet week
 * rendered a screen of zeroes. Two of its headline figures could never work
 * here at all: prices are deliberately never recorded, so revenue is £0 by
 * design, and 391 leads are marked won against 3 lost, which measures filing
 * rather than selling.
 *
 * What survives is timestamps and ownership. These cover the numbers built from
 * them - and in particular the two mistakes that would make the screen lie.
 */
class BookHealthTest extends TestCase
{
    use RefreshDatabase;

    private function lead(?User $owner, array $attributes = []): Lead
    {
        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
            'email' => 'c'.random_int(1, 99999).'@example.test',
        ]);

        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $lead = Lead::create(array_merge([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $owner?->id,
            'source' => 'referral',
        ], $attributes));

        if ($createdAt) {
            $lead->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $lead->refresh();
    }

    private function contact(Lead $lead, string $type, string $when): void
    {
        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $lead->assigned_to,
            'type' => $type,
            'description' => 'Recorded.',
        ]);

        $activity->forceFill(['created_at' => $when])->saveQuietly();
    }

    private function snapshot(): array
    {
        return app(BookHealthService::class)->snapshot();
    }

    private function rep(): User
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    public function test_quiet_is_measured_from_contact_not_from_edits(): void
    {
        $rep = $this->rep();

        $old = $this->lead($rep, ['created_at' => now()->subDays(60)]);
        $worked = $this->lead($rep, ['created_at' => now()->subDays(60)]);

        $this->contact($worked, 'call', now()->subDays(2));

        // updated_at moves when somebody corrects a postcode and stands still
        // through a twenty minute phone call, so it answers "who edited this".
        $old->touch();

        $leads = $this->snapshot()['leads'];

        $this->assertSame(2, $leads['open']);
        $this->assertSame(1, $leads['quiet_30'], 'Touching a record is not contacting anybody.');
    }

    public function test_dragging_a_card_between_stages_is_not_contact(): void
    {
        $rep = $this->rep();
        $lead = $this->lead($rep, ['created_at' => now()->subDays(60)]);

        // The system writes stage_change itself, so counting it would let a
        // lead look worked by being dragged back and forth.
        $this->contact($lead, 'stage_change', now()->subDay());
        $this->contact($lead, 'note', now()->subDay());

        $snapshot = $this->snapshot();

        $this->assertSame(1, $snapshot['leads']['quiet_30']);
        $this->assertSame(1, $snapshot['leads']['never_contacted']);
    }

    public function test_open_tickets_do_not_include_the_resolved_pile(): void
    {
        $customer = Customer::create(['name' => 'Shop', 'phone' => '07700900123']);

        foreach (['open', 'in_progress', 'resolved', 'resolved', 'closed'] as $i => $status) {
            Ticket::create([
                'ticket_number' => 'T-'.$i,
                'customer_id' => $customer->id,
                'subject' => 'Issue',
                'status' => $status,
                'priority' => 'high',
            ]);
        }

        $tickets = $this->snapshot()['tickets'];

        // Counting anything not `closed` as open swept in the resolved pile and
        // reported 136 where the truth was 28 - a mistake this screen made in
        // an earlier form, and the reason it is asserted here.
        $this->assertSame(2, $tickets['open']);
        $this->assertSame(2, $tickets['resolved_not_closed']);
    }

    public function test_the_worklist_is_oldest_first_and_names_the_owner(): void
    {
        $rep = $this->rep();

        $recent = $this->lead($rep, ['created_at' => now()->subDays(10)]);
        $ancient = $this->lead($rep, ['created_at' => now()->subDays(200)]);

        $stalest = $this->snapshot()['stalest'];

        $this->assertSame($ancient->id, $stalest[0]['id']);
        $this->assertSame($recent->id, $stalest[1]['id']);
        $this->assertSame($rep->name, $stalest[0]['owner']);
        $this->assertGreaterThan(150, $stalest[0]['days_since_contact']);
    }

    public function test_each_person_is_measured_against_their_own_book(): void
    {
        $big = $this->rep();
        $small = $this->rep();

        // One rep holds 191 open leads here and another holds 1. A league table
        // of totals would only ever say who was handed the most.
        foreach (range(1, 10) as $i) {
            $this->lead($big, ['created_at' => now()->subDays(60)]);
        }

        $worked = $this->lead($big, ['created_at' => now()->subDays(60)]);
        $this->contact($worked, 'call', now()->subDay());

        $this->lead($small, ['created_at' => now()->subDays(60)]);

        $byOwner = collect($this->snapshot()['by_owner'])->keyBy('name');

        $this->assertSame(11, $byOwner[$big->name]['book']);
        $this->assertSame(10, $byOwner[$big->name]['quiet']);
        $this->assertSame(91, $byOwner[$big->name]['quiet_pct']);

        $this->assertSame(1, $byOwner[$small->name]['book']);
        $this->assertSame(100, $byOwner[$small->name]['quiet_pct']);
    }

    public function test_won_and_lost_leads_are_not_part_of_the_open_book(): void
    {
        $rep = $this->rep();

        $this->lead($rep, ['stage' => 'won', 'created_at' => now()->subDays(200)]);
        $this->lead($rep, ['stage' => 'lost', 'created_at' => now()->subDays(200)]);

        $snapshot = $this->snapshot();

        $this->assertSame(0, $snapshot['leads']['open']);
        $this->assertSame(0, $snapshot['leads']['quiet_30']);
        $this->assertCount(0, $snapshot['stalest']);
    }

    public function test_a_salesperson_sees_only_their_own_book(): void
    {
        $mine = $this->rep();
        $theirs = $this->rep();

        $this->lead($mine, ['created_at' => now()->subDays(60)]);
        $this->lead($mine, ['created_at' => now()->subDays(60)]);
        $this->lead($theirs, ['created_at' => now()->subDays(60)]);

        $response = $this->actingAs($mine, 'sanctum')->getJson('/api/dashboard/my-work')->assertOk();

        // Being told the company has 169 neglected leads is a complaint. Being
        // told which two of them are yours is a morning's work.
        $this->assertSame(2, $response->json('leads.open'));
        $this->assertSame(2, $response->json('leads.quiet_30'));
        $this->assertCount(2, $response->json('stalest'));

        // A per-person view of "who owns the neglect" is a list of one.
        $this->assertSame([], $response->json('by_owner'));
    }

    public function test_the_rep_and_their_manager_see_the_same_numbers(): void
    {
        $rep = $this->rep();

        foreach (range(1, 3) as $i) {
            $this->lead($rep, ['created_at' => now()->subDays(60)]);
        }

        $mine = $this->actingAs($rep, 'sanctum')->getJson('/api/dashboard/my-work')->json('leads.quiet_30');

        $adminRole = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $company = collect(
            $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/attention')->json('by_owner')
        )->firstWhere('name', $rep->name);

        // One set of definitions with a scope, not two implementations that
        // drift into giving different answers to the same question.
        $this->assertSame($mine, $company['quiet']);
    }

    public function test_only_management_can_see_the_whole_company(): void
    {
        $rep = $this->rep();

        $this->actingAs($rep, 'sanctum')->getJson('/api/dashboard/attention')->assertStatus(403);

        $adminRole = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/attention')
            ->assertOk()
            ->assertJsonStructure(['leads', 'follow_ups', 'appointments', 'tickets', 'data_quality', 'stalest', 'by_owner']);
    }
}
