<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * sla_due_at was computed on creation and shown on the ticket, but nothing
 * ever read it again - a breach was only noticed by a human on the right
 * screen at the right moment.
 */
class TicketSlaBreachTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(array $attributes = []): Ticket
    {
        $customer = Customer::create([
            'name' => 'Acme Ltd',
            'phone' => '4477009010'.random_int(10, 99),
        ]);

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-'.uniqid(),
            'customer_id' => $customer->id,
            'subject' => 'Terminal offline',
            'description' => 'Card machine will not connect',
            'priority' => 'high',
            'status' => 'open',
            'sla_due_at' => now()->subHours(2),
        ], $attributes));
    }

    public function test_a_breached_ticket_is_flagged(): void
    {
        $ticket = $this->ticket();

        $this->artisan('tickets:check-sla')->assertSuccessful();

        $this->assertNotNull($ticket->fresh()->sla_breached_at);
    }

    public function test_a_ticket_within_its_sla_is_untouched(): void
    {
        $ticket = $this->ticket(['sla_due_at' => now()->addHours(4)]);

        $this->artisan('tickets:check-sla')->assertSuccessful();

        $this->assertNull($ticket->fresh()->sla_breached_at);
    }

    public function test_a_resolved_ticket_is_not_escalated(): void
    {
        $ticket = $this->ticket(['status' => 'resolved']);

        $this->artisan('tickets:check-sla')->assertSuccessful();

        $this->assertNull($ticket->fresh()->sla_breached_at);
    }

    public function test_escalation_happens_once_not_on_every_run(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Support'], ['description' => 'Support']);
        $agent = User::factory()->create(['role_id' => $role->id]);

        $this->ticket(['assigned_to' => $agent->id]);

        $this->artisan('tickets:check-sla')->assertSuccessful();
        $this->artisan('tickets:check-sla')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', 'ticket_sla_breach')->count());
    }

    public function test_the_assignee_is_notified(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Support'], ['description' => 'Support']);
        $agent = User::factory()->create(['role_id' => $role->id]);

        $ticket = $this->ticket(['assigned_to' => $agent->id]);

        $this->artisan('tickets:check-sla')->assertSuccessful();

        $notification = Notification::first();

        $this->assertNotNull($notification);
        $this->assertSame($agent->id, $notification->notifiable_id);
        $this->assertStringContainsString($ticket->ticket_number, $notification->title);
        $this->assertSame($ticket->id, $notification->data['ticket_id']);
    }

    public function test_notification_endpoints_work_now_that_the_table_has_columns(): void
    {
        // All four endpoints previously failed on missing columns rather than
        // returning an empty list.
        $role = Role::query()->firstOrCreate(['name' => 'Support'], ['description' => 'Support']);
        $agent = User::factory()->create(['role_id' => $role->id]);

        Notification::notifyUser($agent->id, 'test', 'Hello', 'A message');

        Sanctum::actingAs($agent);

        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJson(['count' => 1]);
        $this->putJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJson(['count' => 0]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $ticket = $this->ticket();

        $this->artisan('tickets:check-sla', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($ticket->fresh()->sla_breached_at);
    }
}
