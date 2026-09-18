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
 * People leave and their pipeline stays exactly where it was - assigned to
 * somebody who no longer logs in, and so absent from every list that starts
 * from an active user. One person here left holding fourteen hot leads and
 * three quotations nobody had looked at since.
 *
 * The email matters as much as the move: a book that lands silently in
 * somebody's account is a book nobody goes through.
 */
class LeadHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function user(string $name, string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);

        return User::factory()->create([
            'role_id' => $role->id, 'is_active' => true, 'name' => $name, 'email' => $email,
        ]);
    }

    private function lead(User $owner, string $stage, float $value = 0): Lead
    {
        $customer = Customer::create([
            'name' => 'Shop '.random_int(1, 999999),
            'business_name' => 'Biz '.random_int(1, 999999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => $stage,
            'assigned_to' => $owner->id,
            'pipeline_value' => $value,
        ]);
    }

    public function test_only_the_named_stages_move(): void
    {
        $gone = $this->user('Haris', 'haris@example.com');
        $taking = $this->user('Ebad', 'ebad@example.com');

        $hot = $this->lead($gone, 'hot_lead');
        $quote = $this->lead($gone, 'quotation');
        $plain = $this->lead($gone, 'lead');
        $won = $this->lead($gone, 'won');

        $this->artisan('crm:hand-over-leads', [
            'from' => 'Haris', 'to' => 'Ebad', '--stages' => 'hot_lead,quotation',
        ])->assertSuccessful();

        $this->assertSame($taking->id, $hot->refresh()->assigned_to);
        $this->assertSame($taking->id, $quote->refresh()->assigned_to);
        $this->assertSame($gone->id, $plain->refresh()->assigned_to);
        $this->assertSame($gone->id, $won->refresh()->assigned_to);
    }

    public function test_the_person_taking_them_on_is_told_what_they_got(): void
    {
        $this->user('Haris', 'haris@example.com');
        $this->user('Ebad', 'ebad@example.com');
        $gone = User::where('name', 'Haris')->first();

        $this->lead($gone, 'hot_lead', 1200);
        $this->lead($gone, 'quotation', 800);

        $this->artisan('crm:hand-over-leads', [
            'from' => 'Haris', 'to' => 'Ebad', '--stages' => 'hot_lead,quotation',
        ])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->hasTo('ebad@example.com')
                && $m->mailData['total'] === 2
                && (float) $m->mailData['totalValue'] === 2000.0
                && $m->mailData['fromName'] === 'Haris';
        });
    }

    public function test_the_closest_to_closing_are_listed_first(): void
    {
        $gone = $this->user('Haris', 'haris@example.com');
        $this->user('Ebad', 'ebad@example.com');

        $this->lead($gone, 'hot_lead');
        $this->lead($gone, 'quotation');

        $this->artisan('crm:hand-over-leads', [
            'from' => 'Haris', 'to' => 'Ebad', '--stages' => 'hot_lead,quotation',
        ])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->mailData['rows'][0]['stage'] === 'Quotation'
                && $m->mailData['rows'][1]['stage'] === 'Hot Lead';
        });
    }

    public function test_a_dry_run_moves_nothing_and_sends_nothing(): void
    {
        $gone = $this->user('Haris', 'haris@example.com');
        $this->user('Ebad', 'ebad@example.com');
        $hot = $this->lead($gone, 'hot_lead');

        $this->artisan('crm:hand-over-leads', [
            'from' => 'Haris', 'to' => 'Ebad', '--stages' => 'hot_lead', '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame($gone->id, $hot->refresh()->assigned_to);
        Mail::assertNothingSent();
    }

    public function test_with_nothing_to_move_nobody_is_emailed(): void
    {
        $this->user('Haris', 'haris@example.com');
        $this->user('Ebad', 'ebad@example.com');

        $this->artisan('crm:hand-over-leads', [
            'from' => 'Haris', 'to' => 'Ebad', '--stages' => 'hot_lead',
        ])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_lead_with_no_follow_up_date_is_flagged_rather_than_left_blank(): void
    {
        $gone = $this->user('Haris', 'haris@example.com');
        $this->user('Ebad', 'ebad@example.com');
        $this->lead($gone, 'hot_lead');

        $this->artisan('crm:hand-over-leads', [
            'from' => 'Haris', 'to' => 'Ebad', '--stages' => 'hot_lead',
        ])->assertSuccessful();

        $mail = Mail::sent(AutomatedEmail::class)->first();

        $level = ob_get_level();
        $html = view($mail->mailView, $mail->mailData)->render();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        $this->assertStringContainsString('none set — decide a date or close it', $html);
        $this->assertStringContainsString('close anything that is not real', $html);
    }
}
