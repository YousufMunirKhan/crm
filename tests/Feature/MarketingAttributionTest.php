<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CommunicationClick;
use App\Models\ContactConsent;
use App\Models\SentCommunication;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Services\MarketingEmailClickTracker;
use App\Services\SuppressionService;
use App\Support\LeadSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Marketing could not be tied to revenue: no campaign identity, no click
 * signal, no attribution columns, and no public way for a lead to arrive.
 */
class MarketingAttributionTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- capture

    public function test_a_website_form_creates_a_prospect_and_a_lead(): void
    {
        $this->postJson('/api/public/leads', [
            'name' => 'Jane Smith',
            'phone' => '447700900321',
            'email' => 'jane@example.com',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring-epos',
            'gclid' => 'abc123',
            'landing_page' => 'https://example.com/epos',
        ])->assertCreated();

        $customer = Customer::where('phone', '447700900321')->first();
        $this->assertNotNull($customer);
        $this->assertSame('prospect', $customer->type);

        $lead = Lead::where('customer_id', $customer->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('spring-epos', $lead->utm_campaign);
        $this->assertSame('abc123', $lead->gclid);
    }

    public function test_utm_is_mapped_onto_a_canonical_source(): void
    {
        $this->assertSame('google_ads', LeadSources::fromUtm('google', 'cpc'));
        $this->assertSame('meta', LeadSources::fromUtm('facebook', 'paid'));
        $this->assertSame('tiktok', LeadSources::fromUtm('tiktok'));
        $this->assertSame('website', LeadSources::fromUtm(null));
    }

    public function test_the_honeypot_rejects_bots(): void
    {
        $this->postJson('/api/public/leads', [
            'name' => 'Bot',
            'phone' => '447700900322',
            'website_url' => 'http://spam.example',
        ])->assertStatus(422);
    }

    public function test_first_touch_attribution_is_not_overwritten(): void
    {
        $payload = [
            'name' => 'Jane Smith',
            'phone' => '447700900323',
            'utm_campaign' => 'first-campaign',
        ];

        $this->postJson('/api/public/leads', $payload)->assertCreated();
        $this->postJson('/api/public/leads', array_merge($payload, ['utm_campaign' => 'second-campaign']))
            ->assertCreated();

        // The customer keeps where they originally came from.
        $this->assertSame(
            'first-campaign',
            Customer::where('phone', '447700900323')->value('utm_campaign')
        );
    }

    public function test_marketing_consent_is_recorded_as_evidence(): void
    {
        $this->postJson('/api/public/leads', [
            'name' => 'Jane Smith',
            'phone' => '447700900324',
            'email' => 'consenting@example.com',
            'marketing_consent' => true,
            'landing_page' => 'https://example.com/epos',
        ])->assertCreated();

        $consent = ContactConsent::where('identifier', 'consenting@example.com')->first();

        $this->assertNotNull($consent);
        $this->assertSame(ContactConsent::STATUS_OPT_IN, $consent->status);
        $this->assertSame('consent', $consent->lawful_basis);
        $this->assertNotNull($consent->opt_in_at);
    }

    public function test_no_consent_means_no_opt_in_record(): void
    {
        $this->postJson('/api/public/leads', [
            'name' => 'Jane Smith',
            'phone' => '447700900325',
            'email' => 'quiet@example.com',
        ])->assertCreated();

        $this->assertNull(ContactConsent::where('identifier', 'quiet@example.com')->first());
    }

    // ----------------------------------------------------------------- clicks

    public function test_links_are_rewritten_for_tracking(): void
    {
        $html = '<a href="https://example.com/offer">Offer</a>';

        $out = MarketingEmailClickTracker::rewriteLinks($html, 123);

        $this->assertStringNotContainsString('href="https://example.com/offer"', $out);
        $this->assertStringContainsString('/email/track/click/123', $out);
    }

    public function test_unsubscribe_and_mailto_links_are_never_rewritten(): void
    {
        // Routing an opt-out through a tracker would log a "click" for someone
        // leaving, and adds a failure point to the one link that must work.
        $html = '<a href="https://crm.example/unsubscribe?token=x">Unsubscribe</a>'
            .'<a href="mailto:hi@example.com">Mail</a>'
            .'<a href="#top">Top</a>';

        $out = MarketingEmailClickTracker::rewriteLinks($html, 1);

        $this->assertStringContainsString('href="https://crm.example/unsubscribe?token=x"', $out);
        $this->assertStringContainsString('href="mailto:hi@example.com"', $out);
        $this->assertStringContainsString('href="#top"', $out);
    }

    public function test_a_click_is_recorded_and_redirects(): void
    {
        $send = SentCommunication::create([
            'type' => 'email',
            'recipient_email' => 'a@example.com',
            'subject' => 'Hello',
            'content' => '',
            'status' => 'sent',
        ]);

        $url = URL::signedRoute('email.track.click', [
            'id' => $send->id,
            'url' => rawurlencode('https://example.com/offer'),
        ]);

        $this->get($url)->assertRedirect('https://example.com/offer');

        $click = CommunicationClick::first();
        $this->assertNotNull($click);
        $this->assertSame(1, $click->click_count);
        $this->assertNotNull($send->fresh()->opened_at, 'A click implies an open');
    }

    public function test_an_unsigned_click_is_not_an_open_redirect(): void
    {
        $this->get('/email/track/click/1?url='.rawurlencode('https://evil.example'))
            ->assertRedirect('/');
    }

    // --------------------------------------------------------------- bounces

    public function test_a_hard_bounce_suppresses_the_address(): void
    {
        config(['webhooks.api_key' => 'test-key']);

        $this->withHeaders(['X-Webhook-Key' => 'test-key'])
            ->postJson('/api/webhooks/delivery', [
                'email' => 'bounced@example.com',
                'event' => 'bounced',
                'reason' => 'mailbox unavailable',
            ])->assertOk();

        $this->assertTrue(
            app(SuppressionService::class)->isSuppressed('bounced@example.com', ContactConsent::CHANNEL_EMAIL)
        );
    }

    public function test_a_soft_bounce_does_not_suppress(): void
    {
        // A full mailbox is not a person who should never be contacted again.
        config(['webhooks.api_key' => 'test-key']);

        $this->withHeaders(['X-Webhook-Key' => 'test-key'])
            ->postJson('/api/webhooks/delivery', [
                'event-data' => [
                    'recipient' => 'full@example.com',
                    'event' => 'failed',
                    'severity' => 'temporary',
                ],
            ])->assertOk();

        $this->assertFalse(
            app(SuppressionService::class)->isSuppressed('full@example.com', ContactConsent::CHANNEL_EMAIL)
        );
    }

    public function test_a_spam_complaint_suppresses(): void
    {
        config(['webhooks.api_key' => 'test-key']);

        $this->withHeaders(['X-Webhook-Key' => 'test-key'])
            ->postJson('/api/webhooks/delivery', [
                'RecordType' => 'SpamComplaint',
                'Email' => 'annoyed@example.com',
            ])->assertOk();

        $this->assertTrue(
            app(SuppressionService::class)->isSuppressed('annoyed@example.com', ContactConsent::CHANNEL_EMAIL)
        );
    }

    public function test_the_delivery_webhook_requires_its_key(): void
    {
        config(['webhooks.api_key' => 'test-key']);

        $this->postJson('/api/webhooks/delivery', ['email' => 'x@example.com', 'event' => 'bounced'])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------- campaigns

    public function test_a_campaign_reports_its_open_rate(): void
    {
        $campaign = Campaign::create([
            'name' => 'March push',
            'channel' => 'email',
            'status' => Campaign::STATUS_SENT,
            'sent_count' => 4,
        ]);

        foreach (range(1, 4) as $i) {
            SentCommunication::create([
                'campaign_id' => $campaign->id,
                'type' => 'email',
                'recipient_email' => "r{$i}@example.com",
                'subject' => 'Hi',
                'content' => '',
                'status' => 'sent',
                'opened_at' => $i <= 1 ? now() : null,
            ]);
        }

        $this->assertSame(25.0, $campaign->openRate());
    }
}
