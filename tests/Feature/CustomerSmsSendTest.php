<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sending an SMS from the customer panel reported success no matter what the
 * provider said, so a rejected sender name ("BAD REQUEST Originator") looked
 * exactly like a delivered message - the composer cleared itself and said
 * nothing while every send was being refused.
 */
class CustomerSmsSendTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Manager'], ['description' => 'Manager']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Aamir',
            'phone' => '07340529757',
        ]);
    }

    private function configureSms(): void
    {
        Setting::updateOrCreate(['key' => 'sms_api_key'], ['value' => 'uid-123']);
        Setting::updateOrCreate(['key' => 'sms_secret_key'], ['value' => 'pass-123']);
        Setting::updateOrCreate(['key' => 'sms_sender_name'], ['value' => 'SWITCH&SAVE']);
    }

    public function test_a_rejected_sender_name_is_reported_instead_of_a_silent_success(): void
    {
        $this->configureSms();
        Http::fake([
            '*voodoosms.com*' => Http::response(['result' => 400, 'resultText' => 'BAD REQUEST Originator']),
        ]);

        $response = $this->actingAs($this->staff())->postJson('/api/communications', [
            'customer_id' => $this->customer()->id,
            'channel' => 'sms',
            'message' => 'You are eligible for funding.',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('sender name', $response->json('message'));
        $this->assertStringContainsString('BAD REQUEST Originator', $response->json('message'));
        $this->assertSame('failed', $response->json('communication.status'));
    }

    public function test_a_provider_rejection_of_any_kind_is_passed_back(): void
    {
        $this->configureSms();
        Http::fake([
            '*voodoosms.com*' => Http::response(['result' => 402, 'resultText' => 'BAD REQUEST Low Credit 402']),
        ]);

        $response = $this->actingAs($this->staff())->postJson('/api/communications', [
            'customer_id' => $this->customer()->id,
            'channel' => 'sms',
            'message' => 'You are eligible for funding.',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Low Credit', $response->json('message'));
    }

    public function test_a_delivered_sms_is_still_created(): void
    {
        $this->configureSms();
        Http::fake([
            '*voodoosms.com*' => Http::response(['result' => 200, 'resultText' => '200 OK', 'reference_id' => ['abc']]),
        ]);

        $this->actingAs($this->staff())->postJson('/api/communications', [
            'customer_id' => $this->customer()->id,
            'channel' => 'sms',
            'message' => 'You are eligible for funding.',
        ])->assertCreated()->assertJsonPath('status', 'sent');
    }

    public function test_a_sender_name_the_provider_cannot_use_is_refused_at_the_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/settings/sms', ['sms_sender_name' => 'S&S/Youlend'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sms_sender_name');

        $this->assertNull(Setting::where('key', 'sms_sender_name')->first());
    }
}
