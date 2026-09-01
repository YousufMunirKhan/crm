<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nothing in this CRM has ever told a rep to chase anybody: a notifications
 * table, four working endpoints and a scheduler running every morning, with the
 * sales side wired to none of it. 169 of 177 open leads have had no contact in
 * a month and 33 promised follow-ups have gone past their date unnoticed.
 */
class DailyWorklistTest extends TestCase
{
    use RefreshDatabase;

    private function rep(): User
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'Manager'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function lead(?User $owner, array $attributes = []): Lead
    {
        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'business_name' => 'Shop '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        // created_at is not mass-assignable, so it has to be forced - otherwise
        // every "sixty days old" lead in here is created today and the quiet
        // tests pass by finding nothing.
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $lead = Lead::create(array_merge([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $owner?->id,
        ], $attributes));

        if ($createdAt) {
            $lead->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $lead->refresh();
    }

    private function activity(Lead $lead, User $user, string $type, string $when): void
    {
        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'type' => $type,
            'description' => 'Recorded.',
        ]);

        $activity->forceFill(['created_at' => $when])->saveQuietly();
    }

    public function test_a_rep_is_told_about_their_own_overdue_follow_ups(): void
    {
        $rep = $this->rep();
        $other = $this->rep();

        $this->lead($rep, ['next_follow_up_at' => now()->subDays(9)]);
        $this->lead($rep, ['next_follow_up_at' => now()->subDays(2)]);
        $this->lead($other, ['next_follow_up_at' => now()->subDays(4)]);

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $mine = Notification::where('notifiable_id', $rep->id)->where('type', 'follow_ups.overdue')->first();

        $this->assertNotNull($mine);
        $this->assertStringContainsString('2 follow-ups are overdue', $mine->title);
        $this->assertStringContainsString('9 days', $mine->message);
        $this->assertSame('/follow-ups?overdue=1', $mine->data['route']);

        // Somebody else's overdue work is not this rep's problem.
        $this->assertSame(
            1,
            Notification::where('notifiable_id', $other->id)->where('type', 'follow_ups.overdue')->count()
        );
    }

    public function test_a_follow_up_still_in_the_future_is_not_raised(): void
    {
        $rep = $this->rep();
        $this->lead($rep, ['next_follow_up_at' => now()->addDays(3)]);

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'follow_ups.overdue')->count());
    }

    public function test_one_line_per_person_not_one_per_lead(): void
    {
        $rep = $this->rep();

        // A rep with sixty neglected leads gets one notification saying sixty.
        // Sixty rows would be swiped away and would teach them to ignore the bell.
        foreach (range(1, 12) as $i) {
            $this->lead($rep, ['created_at' => now()->subDays(60)]);
        }

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $quiet = Notification::where('notifiable_id', $rep->id)->where('type', 'leads.quiet')->get();

        $this->assertCount(1, $quiet);
        $this->assertStringContainsString('12 leads have gone quiet', $quiet->first()->title);
    }

    public function test_a_recorded_conversation_keeps_a_lead_off_the_quiet_list(): void
    {
        $rep = $this->rep();
        $lead = $this->lead($rep, ['created_at' => now()->subDays(60)]);

        $this->activity($lead, $rep, 'call', now()->subDays(3));

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'leads.quiet')->count());
    }

    public function test_dragging_a_card_between_stages_does_not_count_as_contact(): void
    {
        $rep = $this->rep();
        $lead = $this->lead($rep, ['created_at' => now()->subDays(60)]);

        // The system writes stage_change by itself, so counting it would let a
        // lead look worked by being dragged back and forth.
        $this->activity($lead, $rep, 'stage_change', now()->subDay());

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', 'leads.quiet')->count());
    }

    public function test_unowned_leads_go_to_managers_not_to_reps(): void
    {
        $manager = $this->manager();
        $rep = $this->rep();

        $this->lead(null);
        $this->lead(null);

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $raised = Notification::where('type', 'leads.unassigned')->get();

        $this->assertCount(1, $raised);
        $this->assertSame($manager->id, $raised->first()->notifiable_id);
        $this->assertStringContainsString('2 leads have no owner', $raised->first()->title);
        $this->assertSame(0, Notification::where('notifiable_id', $rep->id)->where('type', 'leads.unassigned')->count());
    }

    public function test_running_it_twice_in_a_morning_does_not_double_up(): void
    {
        $rep = $this->rep();
        $this->lead($rep, ['next_follow_up_at' => now()->subDays(5)]);

        // A per-minute cron makes an accidental second run easy.
        $this->artisan('crm:daily-worklist')->assertSuccessful();
        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', 'follow_ups.overdue')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $rep = $this->rep();
        $this->lead($rep, ['next_follow_up_at' => now()->subDays(5)]);

        $this->artisan('crm:daily-worklist', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_the_stale_filter_lists_exactly_what_the_notification_counted(): void
    {
        $rep = $this->rep();

        $quiet = $this->lead($rep, ['created_at' => now()->subDays(60)]);
        $worked = $this->lead($rep, ['created_at' => now()->subDays(60)]);

        $this->activity($worked, $rep, 'call', now()->subDay());

        // A notification that counts differently from the list it links to is
        // worse than no notification.
        $response = $this->actingAs($rep, 'sanctum')->getJson('/api/leads?stale_days=30');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($quiet->id, $ids);
        $this->assertNotContains($worked->id, $ids);
    }
}
