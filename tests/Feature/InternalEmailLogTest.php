<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SentCommunication;
use App\Models\User;
use App\Services\AutomatedEmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These emails go out on a schedule with nobody watching, so until now the only
 * evidence any of it worked was the absence of a complaint - a refused address
 * went to a log at a level production drops.
 *
 * It reads sent_communications, which every automated send already writes, so
 * an email added next month shows up here without anybody wiring it in.
 */
class InternalEmailLogTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function send(array $attributes = []): SentCommunication
    {
        return SentCommunication::create(array_merge([
            'type' => 'email',
            'template_type' => AutomatedEmailSender::TEMPLATE_TYPE,
            'reference' => 'invoice-overdue:1:'.now()->toDateString(),
            'recipient_email' => 'kaleem@example.com',
            'subject' => 'Overdue: invoice INV-1',
            'content' => 'emails.automated.invoice-overdue',
            'status' => 'sent',
            'sent_at' => now(),
        ], $attributes));
    }

    public function test_an_admin_sees_what_was_sent(): void
    {
        $this->send();

        $response = $this->actingAs($this->user('Admin'), 'sanctum')
            ->getJson('/api/internal-emails')
            ->assertOk();

        $this->assertSame(1, $response->json('summary.total'));
        $this->assertSame('Invoice overdue chase', $response->json('data.0.kind'));
        $this->assertSame('kaleem@example.com', $response->json('data.0.recipient'));
    }

    public function test_nobody_else_does(): void
    {
        $this->send();

        foreach (['Sales', 'CallAgent', 'Support', 'Manager'] as $role) {
            $this->actingAs($this->user($role), 'sanctum')
                ->getJson('/api/internal-emails')
                ->assertForbidden();
        }
    }

    public function test_a_failure_carries_its_reason(): void
    {
        $this->send(['status' => 'failed', 'error_message' => 'SMTP 550 mailbox unavailable']);

        $response = $this->actingAs($this->user('Admin'), 'sanctum')
            ->getJson('/api/internal-emails?status=failed')
            ->assertOk();

        $this->assertSame(1, $response->json('summary.failed'));
        $this->assertSame('SMTP 550 mailbox unavailable', $response->json('data.0.error'));
    }

    public function test_opens_are_reported_when_the_pixel_came_back(): void
    {
        $this->send(['opened_at' => now(), 'open_count' => 3]);
        $this->send(['recipient_email' => 'other@example.com']);

        $response = $this->actingAs($this->user('Admin'), 'sanctum')
            ->getJson('/api/internal-emails?opened=yes')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(3, $response->json('data.0.open_count'));
    }

    public function test_it_can_be_narrowed_to_one_kind_of_email(): void
    {
        $this->send(['reference' => 'invoice-overdue:1:x']);
        $this->send(['reference' => 'lead-target:2:x', 'subject' => '2 of 5 leads']);

        $response = $this->actingAs($this->user('Admin'), 'sanctum')
            ->getJson('/api/internal-emails?kind=lead-target')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Lead target (to the person)', $response->json('data.0.kind'));
    }

    public function test_a_lead_summary_is_not_mistaken_for_a_lead_target(): void
    {
        // The prefixes share a word, so a loose match would file one as the other.
        $this->send(['reference' => 'lead-summary:2:x']);

        $response = $this->actingAs($this->user('Admin'), 'sanctum')
            ->getJson('/api/internal-emails')
            ->assertOk();

        $this->assertSame('Lead summary (to management)', $response->json('data.0.kind'));
    }

    public function test_marketing_sends_are_left_out_unless_asked_for(): void
    {
        $this->send();
        $this->send(['template_type' => 'email_template', 'reference' => null, 'subject' => 'Spring offer']);

        $admin = $this->user('Admin');

        $this->assertSame(1, $this->actingAs($admin, 'sanctum')
            ->getJson('/api/internal-emails')->json('summary.total'));

        $this->assertSame(2, $this->actingAs($admin, 'sanctum')
            ->getJson('/api/internal-emails?automated_only=0')->json('summary.total'));
    }

    public function test_it_can_be_searched_by_address(): void
    {
        $this->send(['recipient_email' => 'wanted@example.com']);
        $this->send(['recipient_email' => 'other@example.com']);

        $response = $this->actingAs($this->user('Admin'), 'sanctum')
            ->getJson('/api/internal-emails?search=wanted')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }
}
