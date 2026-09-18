<?php

namespace Tests\Feature;

use App\Mail\AppointmentAssignedNotification;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Booking an appointment told the person it was assigned to, and told nobody at
 * all when it was booked without one - while the morning list falls back to the
 * lead's owner. So an appointment with nobody named reached its first human on
 * the day itself, hours before it.
 *
 * Both now answer "whose is this?" the same way.
 */
class AppointmentBookingEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function user(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'email' => $email]);
    }

    private function book(User $actor, Lead $lead, ?User $assignee): void
    {
        $payload = [
            'type' => 'appointment',
            'description' => 'EPOS demo',
            'meta' => [
                'appointment_date' => now()->addDays(2)->toDateString(),
                'appointment_time' => '14:00',
            ],
        ];

        if ($assignee) {
            $payload['assigned_user_id'] = $assignee->id;
        }

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/leads/{$lead->id}/activity", $payload)
            ->assertCreated();
    }

    private function lead(User $owner): Lead
    {
        $customer = Customer::create([
            'name' => 'Kaleem',
            'business_name' => 'Corner Shop',
            'phone' => '07700900123',
            'email' => 'kaleem@example.com',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $owner->id,
        ]);
    }

    public function test_the_person_it_is_assigned_to_is_told(): void
    {
        $owner = $this->user('Sales', 'owner@example.com');
        $attending = $this->user('Sales', 'attending@example.com');

        $this->book($owner, $this->lead($owner), $attending);

        Mail::assertSent(AppointmentAssignedNotification::class, fn ($m) => $m->hasTo('attending@example.com'));
        Mail::assertNotSent(AppointmentAssignedNotification::class, fn ($m) => $m->hasTo('owner@example.com'));
    }

    public function test_with_nobody_assigned_the_lead_owner_is_told(): void
    {
        $owner = $this->user('Sales', 'owner@example.com');
        $booker = $this->user('Admin', 'booker@example.com');

        $this->book($booker, $this->lead($owner), null);

        Mail::assertSent(AppointmentAssignedNotification::class, fn ($m) => $m->hasTo('owner@example.com'));
    }

    public function test_the_stand_in_is_told_why_it_reached_them(): void
    {
        $owner = $this->user('Sales', 'owner@example.com');

        $this->book($owner, $this->lead($owner), null);

        Mail::assertSent(AppointmentAssignedNotification::class, function ($m) {
            return $m->recipient?->email === 'owner@example.com'
                && $m->activity->assigned_user_id === null;
        });
    }

    public function test_the_admin_copy_still_goes_out(): void
    {
        \App\Modules\Settings\Models\Setting::updateOrCreate(
            ['key' => 'admin_notification_email'],
            ['value' => 'boss@example.com'],
        );

        $owner = $this->user('Sales', 'owner@example.com');
        $this->book($owner, $this->lead($owner), $owner);

        Mail::assertSent(\App\Mail\AppointmentNotification::class, fn ($m) => $m->hasTo('boss@example.com'));
    }

    public function test_the_customer_is_still_confirmed(): void
    {
        $owner = $this->user('Sales', 'owner@example.com');
        $this->book($owner, $this->lead($owner), $owner);

        Mail::assertSent(\App\Mail\AppointmentNotification::class, fn ($m) => $m->hasTo('kaleem@example.com'));
    }
}
