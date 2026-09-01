<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tickets list only ever said "116 Total", which counts the resolved pile
 * and the closed ones alongside the handful that are genuinely open - so the
 * one number on the page answered a question nobody asks. And 108 tickets sat
 * in `resolved` where the status filter did not even offer that state, so they
 * were unreachable except by asking for everything.
 */
class TicketQueueSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function ticket(array $attributes = []): Ticket
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Shop '.$n, 'phone' => '07700900'.(100 + $n)]);

        $ticket = Ticket::create(array_merge([
            'ticket_number' => 'TKT-'.$n,
            'customer_id' => $customer->id,
            'subject' => 'Issue '.$n,
            'description' => 'Something broke',
            'status' => 'open',
            'priority' => 'medium',
            'source' => 'crm',
        ], collect($attributes)->except('created_at')->all()));

        if (isset($attributes['created_at'])) {
            $ticket->forceFill(['created_at' => $attributes['created_at']])->saveQuietly();
        }

        return $ticket->refresh();
    }

    private function summary(): array
    {
        return $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/tickets')
            ->assertOk()
            ->json('summary');
    }

    public function test_open_does_not_include_the_resolved_pile(): void
    {
        $this->ticket(['status' => 'open']);
        $this->ticket(['status' => 'in_progress']);
        $this->ticket(['status' => 'resolved']);
        $this->ticket(['status' => 'resolved']);
        $this->ticket(['status' => 'closed']);

        $s = $this->summary();

        // Counting everything that is not `closed` reports four where the truth
        // is two - the same mistake that turned 28 open tickets into "136".
        $this->assertSame(2, $s['open']);
        $this->assertSame(2, $s['resolved_not_closed']);
    }

    public function test_unassigned_counts_only_open_ones(): void
    {
        $owner = $this->admin();

        $this->ticket(['status' => 'open', 'assigned_to' => null]);
        $this->ticket(['status' => 'open', 'assigned_to' => $owner->id]);
        // A resolved ticket with nobody on it is not work waiting for anybody.
        $this->ticket(['status' => 'resolved', 'assigned_to' => null]);

        $this->assertSame(1, $this->summary()['unassigned']);
    }

    public function test_ageing_counts_open_tickets_past_a_week(): void
    {
        $this->ticket(['status' => 'open', 'created_at' => now()->subDays(30)]);
        $this->ticket(['status' => 'open', 'created_at' => now()->subDays(2)]);
        $this->ticket(['status' => 'resolved', 'created_at' => now()->subDays(60)]);

        $this->assertSame(1, $this->summary()['over_a_week']);
    }

    public function test_breaching_needs_a_deadline_to_breach(): void
    {
        $this->ticket(['status' => 'open', 'sla_due_at' => now()->subHour()]);
        $this->ticket(['status' => 'open', 'sla_due_at' => now()->addHour()]);
        // Null sla_due_at was the state of every POS ticket until recently: it
        // cannot be late, whatever happens to it.
        $this->ticket(['status' => 'open', 'sla_due_at' => null]);

        $this->assertSame(1, $this->summary()['breaching']);
    }

    public function test_the_summary_ignores_the_status_filter(): void
    {
        $this->ticket(['status' => 'open']);
        $this->ticket(['status' => 'resolved']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/tickets?status=resolved')
            ->assertOk();

        // The list narrows; the chips still say what is outstanding, or they
        // would go blank the moment somebody used them.
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('summary.open'));
    }

    public function test_the_resolved_state_is_now_filterable(): void
    {
        $this->ticket(['status' => 'open']);
        $this->ticket(['status' => 'resolved']);
        $this->ticket(['status' => 'resolved']);

        $rows = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/tickets?status=resolved')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $rows);
    }

    public function test_a_rep_sees_a_summary_of_their_own_queue(): void
    {
        $salesRole = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);
        $rep = User::factory()->create(['role_id' => $salesRole->id, 'is_active' => true]);

        $this->ticket(['status' => 'open', 'assigned_to' => $rep->id]);
        $this->ticket(['status' => 'open']);

        $s = $this->actingAs($rep, 'sanctum')->getJson('/api/tickets')->assertOk()->json('summary');

        $this->assertSame(1, $s['open']);
    }
}
