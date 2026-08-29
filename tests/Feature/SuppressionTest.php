<?php

namespace Tests\Feature;

use App\Models\ContactConsent;
use App\Services\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuppressionTest extends TestCase
{
    use RefreshDatabase;

    private SuppressionService $suppression;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppression = app(SuppressionService::class);
    }

    public function test_phone_numbers_match_regardless_of_formatting(): void
    {
        $this->suppression->optOut('+44 7700 900123', ContactConsent::CHANNEL_SMS, 'test');

        // A STOP reply is worthless if the stored number does not match the
        // number the next campaign sends to.
        $this->assertTrue($this->suppression->isSuppressed('+44 7700 900123', ContactConsent::CHANNEL_SMS));
        $this->assertTrue($this->suppression->isSuppressed('447700900123', ContactConsent::CHANNEL_SMS));
        $this->assertTrue($this->suppression->isSuppressed('+44-7700-900123', ContactConsent::CHANNEL_SMS));
    }

    public function test_emails_match_case_insensitively(): void
    {
        $this->suppression->optOut('  Bob@Example.COM ', ContactConsent::CHANNEL_EMAIL, 'test');

        $this->assertTrue($this->suppression->isSuppressed('bob@example.com', ContactConsent::CHANNEL_EMAIL));
    }

    public function test_opt_out_is_per_channel(): void
    {
        $this->suppression->optOut('447700900123', ContactConsent::CHANNEL_SMS, 'test');

        $this->assertTrue($this->suppression->isSuppressed('447700900123', ContactConsent::CHANNEL_SMS));
        $this->assertFalse($this->suppression->isSuppressed('447700900123', ContactConsent::CHANNEL_WHATSAPP));
    }

    public function test_blank_identifier_is_treated_as_suppressed(): void
    {
        // Nothing to send to - callers must skip rather than attempt a send.
        $this->assertTrue($this->suppression->isSuppressed(null, ContactConsent::CHANNEL_EMAIL));
        $this->assertTrue($this->suppression->isSuppressed('   ', ContactConsent::CHANNEL_SMS));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('optOutKeywords')]
    public function test_recognises_opt_out_keywords(string $body, bool $expected): void
    {
        $this->assertSame($expected, $this->suppression->isOptOutKeyword($body));
    }

    public static function optOutKeywords(): array
    {
        return [
            'plain stop' => ['STOP', true],
            'trailing full stop' => ['stop.', true],
            'exclamation' => ['Unsubscribe!', true],
            'two words' => ['opt out', true],
            'hyphenated' => ['opt-out', true],
            'ordinary message' => ['hello there', false],
            'contains stop' => ['please stop sending me things', false],
            'empty' => ['', false],
        ];
    }

    public function test_suppressed_set_returns_only_opted_out_identifiers(): void
    {
        $this->suppression->optOut('a@example.com', ContactConsent::CHANNEL_EMAIL, 'test');

        $set = $this->suppression->suppressedSet(
            ['a@example.com', 'b@example.com'],
            ContactConsent::CHANNEL_EMAIL
        );

        $this->assertArrayHasKey('a@example.com', $set);
        $this->assertArrayNotHasKey('b@example.com', $set);
    }

    public function test_opt_in_clears_a_previous_opt_out(): void
    {
        $this->suppression->optOut('a@example.com', ContactConsent::CHANNEL_EMAIL, 'test');
        $this->assertTrue($this->suppression->isSuppressed('a@example.com', ContactConsent::CHANNEL_EMAIL));

        $this->suppression->optIn('a@example.com', ContactConsent::CHANNEL_EMAIL, 'test', 'consent');
        $this->assertFalse($this->suppression->isSuppressed('a@example.com', ContactConsent::CHANNEL_EMAIL));
    }

    public function test_unsubscribe_token_is_recipient_and_channel_specific(): void
    {
        $token = $this->suppression->token('a@example.com', ContactConsent::CHANNEL_EMAIL);

        $this->assertTrue($this->suppression->tokenMatches($token, 'A@Example.com', ContactConsent::CHANNEL_EMAIL));
        $this->assertFalse($this->suppression->tokenMatches($token, 'b@example.com', ContactConsent::CHANNEL_EMAIL));
        $this->assertFalse($this->suppression->tokenMatches('nonsense', 'a@example.com', ContactConsent::CHANNEL_EMAIL));
    }

    public function test_unsubscribe_endpoint_rejects_a_missing_or_wrong_token(): void
    {
        // Without this, anyone could unsubscribe anyone by posting their address.
        $this->postJson('/api/unsubscribe', [
            'email' => 'victim@example.com',
            'token' => 'wrong-token',
        ])->assertStatus(403);

        $this->assertFalse(
            $this->suppression->isSuppressed('victim@example.com', ContactConsent::CHANNEL_EMAIL)
        );
    }

    public function test_unsubscribe_endpoint_accepts_a_valid_token(): void
    {
        $token = $this->suppression->token('real@example.com', ContactConsent::CHANNEL_EMAIL);

        $this->postJson('/api/unsubscribe', [
            'email' => 'real@example.com',
            'token' => $token,
        ])->assertOk();

        $this->assertTrue(
            $this->suppression->isSuppressed('real@example.com', ContactConsent::CHANNEL_EMAIL)
        );
    }

    public function test_check_endpoint_does_not_unsubscribe_on_its_own(): void
    {
        // The page used to opt people out on load, so mail gateways that
        // pre-fetch links unsubscribed recipients who never clicked.
        $token = $this->suppression->token('reader@example.com', ContactConsent::CHANNEL_EMAIL);

        $this->getJson('/api/unsubscribe/check?email=reader@example.com&channel=email&token='.$token)
            ->assertOk()
            ->assertJson(['valid' => true, 'unsubscribed' => false]);

        $this->assertFalse(
            $this->suppression->isSuppressed('reader@example.com', ContactConsent::CHANNEL_EMAIL)
        );
    }
}
