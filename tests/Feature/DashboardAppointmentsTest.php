<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Completing an appointment from the dashboard wrote the remark and changed
 * nothing else: the dashboard listed appointments by date alone, so the row
 * came straight back on the next load and the rep could not tell what was
 * still outstanding.
 */
class DashboardAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);
        $this->user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($this->user, 'sanctum');
    }

    private function lead(): Lead
    {
        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $this->user->id,
        ]);
    }

    private function appointment(Lead $lead, array $attributes = []): LeadActivity
    {
        return LeadActivity::create(array_merge([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'assigned_user_id' => $this->user->id,
            'type' => 'appointment',
            'description' => 'Site visit',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_status' => LeadActivity::APPOINTMENT_STATUS_PENDING,
        ], $attributes));
    }

    /** @return int[] */
    private function dashboardAppointmentIds(string $url = '/api/dashboard'): array
    {
        return collect($this->getJson($url)->assertOk()->json('today_appointments'))
            ->pluck('id')
            ->all();
    }

    public function test_a_pending_appointment_for_today_is_listed(): void
    {
        $appointment = $this->appointment($this->lead());

        $this->assertSame([$appointment->id], $this->dashboardAppointmentIds());
    }

    public function test_a_completed_appointment_leaves_todays_list(): void
    {
        $appointment = $this->appointment($this->lead());
        $appointment->update(['appointment_status' => LeadActivity::APPOINTMENT_STATUS_COMPLETED]);

        $this->assertSame([], $this->dashboardAppointmentIds());
    }

    public function test_a_cancelled_appointment_leaves_todays_list(): void
    {
        $appointment = $this->appointment($this->lead());
        $appointment->update(['appointment_status' => LeadActivity::APPOINTMENT_STATUS_CANCELLED]);

        $this->assertSame([], $this->dashboardAppointmentIds());
    }

    public function test_completing_it_from_the_dashboard_takes_it_off_the_list(): void
    {
        $lead = $this->lead();
        $appointment = $this->appointment($lead);

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Met the owner, sending a quote.',
            'appointment_activity_id' => $appointment->id,
        ])->assertOk();

        $appointment->refresh();
        $this->assertSame(LeadActivity::APPOINTMENT_STATUS_COMPLETED, $appointment->appointment_status);
        $this->assertSame('Met the owner, sending a quote.', $appointment->outcome_notes);
        $this->assertSame([], $this->dashboardAppointmentIds());
    }

    public function test_completing_a_plain_follow_up_leaves_the_appointment_alone(): void
    {
        $lead = $this->lead();
        $appointment = $this->appointment($lead);

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Rang them, no answer.',
        ])->assertOk();

        $this->assertSame(
            LeadActivity::APPOINTMENT_STATUS_PENDING,
            $appointment->refresh()->appointment_status
        );
        $this->assertSame([$appointment->id], $this->dashboardAppointmentIds());
    }

    public function test_one_appointment_completing_does_not_close_the_lead_s_others(): void
    {
        $lead = $this->lead();
        $morning = $this->appointment($lead, ['appointment_time' => '09:00']);
        $afternoon = $this->appointment($lead, ['appointment_time' => '15:00']);

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Done the morning one.',
            'appointment_activity_id' => $morning->id,
        ])->assertOk();

        $this->assertSame([$afternoon->id], $this->dashboardAppointmentIds());
    }

    public function test_an_appointment_that_only_ever_had_a_date_in_meta_is_still_listed(): void
    {
        $appointment = $this->appointment($this->lead(), [
            'appointment_date' => null,
            'appointment_time' => null,
            'meta' => ['appointment_date' => now()->toDateString(), 'appointment_time' => '11:30'],
        ]);

        $this->assertSame([$appointment->id], $this->dashboardAppointmentIds());
    }

    public function test_the_sales_dashboard_hides_completed_appointments_too(): void
    {
        $pending = $this->appointment($this->lead());
        $this->appointment($this->lead())
            ->update(['appointment_status' => LeadActivity::APPOINTMENT_STATUS_COMPLETED]);

        $this->assertSame([$pending->id], $this->dashboardAppointmentIds('/api/dashboard/sales-agent'));
    }

    public function test_appointments_are_ordered_by_their_time(): void
    {
        $lead = $this->lead();
        $late = $this->appointment($lead, ['appointment_time' => '16:00']);
        $early = $this->appointment($lead, ['appointment_time' => '08:30']);

        $this->assertSame([$early->id, $late->id], $this->dashboardAppointmentIds());
    }
}
