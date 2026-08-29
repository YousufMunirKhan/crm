<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Appointments could be listed, viewed and updated but never created or
 * cancelled through the API.
 */
class AppointmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $roleName = 'Sales'): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function leadFor(User $user): Lead
    {
        $customer = Customer::create([
            'name' => 'Acme Ltd',
            'phone' => '4477009011'.random_int(10, 99),
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $user->id,
        ]);
    }

    public function test_an_agent_can_book_an_appointment_on_their_lead(): void
    {
        $agent = $this->agent();
        $lead = $this->leadFor($agent);

        Sanctum::actingAs($agent);

        $response = $this->postJson('/api/appointments', [
            'lead_id' => $lead->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'appointment_time' => '14:30',
            'description' => 'Site visit',
        ])->assertCreated();

        $this->assertDatabaseHas('lead_activities', [
            'id' => $response->json('id'),
            'lead_id' => $lead->id,
            'type' => 'appointment',
            'appointment_status' => 'pending',
        ]);
    }

    public function test_the_booked_appointment_appears_in_the_list(): void
    {
        $agent = $this->agent();
        $lead = $this->leadFor($agent);
        $date = now()->addDays(2)->toDateString();

        Sanctum::actingAs($agent);

        $this->postJson('/api/appointments', [
            'lead_id' => $lead->id,
            'appointment_date' => $date,
            'appointment_time' => '09:00',
        ])->assertCreated();

        $this->getJson('/api/appointments?date='.$date)
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_an_agent_cannot_book_on_someone_elses_lead(): void
    {
        $owner = $this->agent();
        $other = $this->agent();
        $lead = $this->leadFor($owner);

        Sanctum::actingAs($other);

        $this->postJson('/api/appointments', [
            'lead_id' => $lead->id,
            'appointment_date' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    public function test_cancelling_soft_deletes_and_marks_it_cancelled(): void
    {
        $agent = $this->agent();
        $lead = $this->leadFor($agent);

        Sanctum::actingAs($agent);

        $id = $this->postJson('/api/appointments', [
            'lead_id' => $lead->id,
            'appointment_date' => now()->addDay()->toDateString(),
        ])->json('id');

        $this->deleteJson("/api/appointments/{$id}")->assertNoContent();

        // History is preserved: the row survives with a cancelled status.
        $this->assertSoftDeleted('lead_activities', ['id' => $id]);
        $this->assertSame(
            'cancelled',
            LeadActivity::withTrashed()->find($id)->appointment_status
        );
    }

    public function test_booking_requires_a_valid_lead_and_date(): void
    {
        Sanctum::actingAs($this->agent());

        $this->postJson('/api/appointments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lead_id', 'appointment_date']);
    }
}
