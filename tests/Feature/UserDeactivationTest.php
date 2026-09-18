<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Switching somebody off and deciding where their pipeline goes were two acts,
 * and only the first was ever done. A person here was switched off months ago
 * still holding sixteen quotations and hot leads, invisible to every screen
 * because every screen starts from an active user.
 *
 * Now it is one act, and the screen asks the question at the only moment
 * anybody is thinking about it.
 */
class UserDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function user(string $roleName, string $name, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create([
            'role_id' => $role->id, 'is_active' => true, 'name' => $name, 'email' => $email,
        ]);
    }

    private function lead(User $owner, string $stage): Lead
    {
        $customer = Customer::create([
            'name' => 'Shop '.random_int(1, 999999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => $stage,
            'assigned_to' => $owner->id,
        ]);
    }

    public function test_the_screen_can_ask_what_they_are_holding(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');

        $this->lead($leaving, 'quotation');
        $this->lead($leaving, 'hot_lead');
        $this->lead($leaving, 'hot_lead');
        $this->lead($leaving, 'won');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/users/{$leaving->id}/open-work")
            ->assertOk();

        // Won is not open work; it does not need handing on.
        $this->assertSame(3, $response->json('open_work.total'));
        $this->assertSame(['quotation' => 1, 'hot_lead' => 2], $response->json('open_work.by_stage'));
    }

    public function test_one_person_can_take_the_lot(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');
        $taking = $this->user('Sales', 'Taking', 'taking@example.com');

        foreach (range(1, 3) as $n) {
            $this->lead($leaving, 'hot_lead');
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$leaving->id}/deactivate", ['recipients' => [$taking->id]])
            ->assertOk()
            ->assertJsonPath('handed_over.0.count', 3);

        $this->assertFalse((bool) $leaving->refresh()->is_active);
        $this->assertSame(3, Lead::where('assigned_to', $taking->id)->count());
        $this->assertSame(0, Lead::where('assigned_to', $leaving->id)->count());
    }

    public function test_two_people_get_half_each(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');
        $one = $this->user('Sales', 'One', 'one@example.com');
        $two = $this->user('Sales', 'Two', 'two@example.com');

        foreach (range(1, 4) as $n) {
            $this->lead($leaving, 'hot_lead');
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$leaving->id}/deactivate", ['recipients' => [$one->id, $two->id]])
            ->assertOk();

        $this->assertSame(2, Lead::where('assigned_to', $one->id)->count());
        $this->assertSame(2, Lead::where('assigned_to', $two->id)->count());
    }

    public function test_the_good_ones_are_dealt_out_rather_than_given_to_one_person(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');
        $one = $this->user('Sales', 'One', 'one@example.com');
        $two = $this->user('Sales', 'Two', 'two@example.com');

        // Two of each, so a split that cut the list in half rather than dealing
        // it would hand one person both quotations and the other both cold ones.
        $this->lead($leaving, 'quotation');
        $this->lead($leaving, 'quotation');
        $this->lead($leaving, 'lead');
        $this->lead($leaving, 'lead');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$leaving->id}/deactivate", ['recipients' => [$one->id, $two->id]])
            ->assertOk();

        foreach ([$one, $two] as $person) {
            $this->assertSame(1, Lead::where('assigned_to', $person->id)->where('stage', 'quotation')->count());
            $this->assertSame(1, Lead::where('assigned_to', $person->id)->where('stage', 'lead')->count());
        }
    }

    public function test_everybody_taking_some_is_emailed_their_own_list(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');
        $one = $this->user('Sales', 'One', 'one@example.com');
        $two = $this->user('Sales', 'Two', 'two@example.com');

        foreach (range(1, 4) as $n) {
            $this->lead($leaving, 'hot_lead');
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$leaving->id}/deactivate", ['recipients' => [$one->id, $two->id]])
            ->assertOk();

        foreach (['one@example.com', 'two@example.com'] as $address) {
            Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo($address)
                && $m->mailData['total'] === 2
                && $m->mailData['fromName'] === 'Leaving');
        }
    }

    public function test_choosing_nobody_still_switches_them_off(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');
        $lead = $this->lead($leaving, 'hot_lead');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$leaving->id}/deactivate", ['recipients' => []])
            ->assertOk()
            ->assertJsonCount(0, 'handed_over');

        $this->assertFalse((bool) $leaving->refresh()->is_active);
        $this->assertSame($leaving->id, $lead->refresh()->assigned_to);
        Mail::assertNothingSent();
    }

    public function test_somebody_who_has_already_left_cannot_be_handed_the_work(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');
        $leaving = $this->user('Sales', 'Leaving', 'leaving@example.com');
        $alsoGone = $this->user('Sales', 'Also Gone', 'gone@example.com');
        $alsoGone->update(['is_active' => false]);
        $this->lead($leaving, 'hot_lead');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$leaving->id}/deactivate", ['recipients' => [$alsoGone->id]])
            ->assertStatus(422);

        // Nothing half-done: they are still active and still hold their leads.
        $this->assertTrue((bool) $leaving->refresh()->is_active);
        $this->assertSame(1, Lead::where('assigned_to', $leaving->id)->count());
    }

    public function test_you_cannot_switch_yourself_off(): void
    {
        $admin = $this->user('Admin', 'Boss', 'boss@example.com');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$admin->id}/deactivate", ['recipients' => []])
            ->assertStatus(422);

        $this->assertTrue((bool) $admin->refresh()->is_active);
    }

    public function test_a_salesperson_cannot_switch_anybody_off(): void
    {
        $rep = $this->user('Sales', 'Rep', 'rep@example.com');
        $other = $this->user('Sales', 'Other', 'other@example.com');

        $this->actingAs($rep, 'sanctum')
            ->postJson("/api/users/{$other->id}/deactivate", ['recipients' => []])
            ->assertForbidden();

        $this->assertTrue((bool) $other->refresh()->is_active);
    }
}
