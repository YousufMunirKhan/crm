<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The POS endpoints were completely anonymous: anyone on the internet could
 * overwrite a customer by knowing their phone number, or inject a paid invoice
 * straight into reported revenue. These tests pin the guard in place.
 */
class IntegrationKeyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-pos-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.api_key' => self::KEY]);
    }

    public function test_pos_endpoints_reject_a_missing_key(): void
    {
        $this->postJson('/api/pos/customer', [
            'external_id' => 'X1',
            'name' => 'Intruder',
            'phone' => '447700900999',
        ])->assertStatus(401);

        $this->assertDatabaseMissing('customers', ['phone' => '447700900999']);
    }

    public function test_pos_endpoints_reject_a_wrong_key(): void
    {
        $this->withHeaders(['X-Api-Key' => 'not-the-key'])
            ->postJson('/api/pos/customer', [
                'external_id' => 'X2',
                'name' => 'Intruder',
                'phone' => '447700900998',
            ])->assertStatus(401);

        $this->assertDatabaseMissing('customers', ['phone' => '447700900998']);
    }

    public function test_pos_endpoints_accept_the_configured_key(): void
    {
        $this->withHeaders(['X-Api-Key' => self::KEY])
            ->postJson('/api/pos/customer', [
                'external_id' => 'X3',
                'name' => 'Real Shop',
                'phone' => '447700900997',
            ])->assertCreated();

        $this->assertDatabaseHas('customers', ['phone' => '447700900997']);
    }

    public function test_an_unset_key_closes_the_endpoint_rather_than_opening_it(): void
    {
        // An unset key must never mean "accept anonymous writes".
        config(['pos.api_key' => '']);

        $this->postJson('/api/pos/customer', [
            'external_id' => 'X4',
            'name' => 'Intruder',
            'phone' => '447700900996',
        ])->assertStatus(503);
    }

    public function test_a_replayed_pos_request_does_not_create_a_duplicate(): void
    {
        $payload = [
            'external_id' => 'IDEMPOTENT-1',
            'name' => 'Retry Shop',
            'phone' => '447700900995',
        ];

        $this->withHeaders(['X-Api-Key' => self::KEY])
            ->postJson('/api/pos/customer', $payload)->assertCreated();

        // The POS retries after a timeout; it must get the original record back.
        $this->withHeaders(['X-Api-Key' => self::KEY])
            ->postJson('/api/pos/customer', $payload)->assertOk();

        $this->assertSame(1, Customer::where('phone', '447700900995')->count());
    }

    public function test_webhook_endpoints_require_their_own_key(): void
    {
        config(['webhooks.api_key' => 'test-webhook-key']);

        $this->postJson('/api/webhooks/delivery', ['email' => 'a@example.com', 'event' => 'bounced'])
            ->assertStatus(401);

        $this->withHeaders(['X-Webhook-Key' => 'test-webhook-key'])
            ->postJson('/api/webhooks/delivery', ['email' => 'a@example.com', 'event' => 'bounced'])
            ->assertOk();
    }
}
