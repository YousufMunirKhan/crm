<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Models\ContactConsent;
use App\Services\SuppressionService;
use App\Support\LeadSources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public lead capture for website forms and ad landing pages.
 *
 * There was no unauthenticated way to create a lead, so every inbound enquiry
 * had to be typed in by hand or scraped - which is why top-of-funnel was
 * entirely manual and paid spend could not be attributed.
 *
 * Rate limited and honeypot protected at the route.
 */
class PublicLeadCaptureController extends Controller
{
    public function __construct(private SuppressionService $suppression) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:2000'],
            'company' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:16'],

            // Consent must be explicit and is recorded as evidence.
            'marketing_consent' => ['nullable', 'boolean'],

            'utm_source' => ['nullable', 'string', 'max:191'],
            'utm_medium' => ['nullable', 'string', 'max:191'],
            'utm_campaign' => ['nullable', 'string', 'max:191'],
            'utm_term' => ['nullable', 'string', 'max:191'],
            'utm_content' => ['nullable', 'string', 'max:191'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'landing_page' => ['nullable', 'string', 'max:2000'],
            'gclid' => ['nullable', 'string', 'max:191'],
            'fbclid' => ['nullable', 'string', 'max:191'],

            // Honeypot: a real person never fills this in.
            'website_url' => ['nullable', 'string', 'max:0'],
        ]);

        $attribution = collect($data)->only([
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'referrer', 'landing_page', 'gclid', 'fbclid',
        ])->filter()->all();

        $source = LeadSources::fromUtm($data['utm_source'] ?? null, $data['utm_medium'] ?? null);

        $lead = DB::transaction(function () use ($data, $attribution, $source) {
            $customer = Customer::firstOrNew(['phone' => $data['phone']]);

            $customer->fill(array_filter([
                'name' => $customer->exists ? $customer->name : $data['name'],
                'email' => $data['email'] ?? $customer->email,
                'business_name' => $data['company'] ?? $customer->business_name,
                'postcode' => $data['postcode'] ?? $customer->postcode,
                'type' => $customer->exists ? $customer->type : 'prospect',
                'source' => $customer->exists ? $customer->source : $source,
            ], fn ($v) => $v !== null));

            // Attribution belongs to first touch; never overwrite it.
            foreach ($attribution as $key => $value) {
                if (blank($customer->{$key})) {
                    $customer->{$key} = $value;
                }
            }

            $customer->save();

            return Lead::create(array_merge([
                'customer_id' => $customer->id,
                'stage' => 'follow_up',
                'source' => $source,
            ], $attribution));
        });

        // Record consent, or its absence, per channel.
        if ($request->boolean('marketing_consent')) {
            foreach ([ContactConsent::CHANNEL_EMAIL, ContactConsent::CHANNEL_SMS] as $channel) {
                $identifier = $channel === ContactConsent::CHANNEL_EMAIL
                    ? ($data['email'] ?? null)
                    : $data['phone'];

                $this->suppression->optIn(
                    $identifier,
                    $channel,
                    'web_form',
                    'consent',
                    'Ticked marketing consent on '.($data['landing_page'] ?? 'the website form'),
                    $lead->customer_id
                );
            }
        }

        // Deliberately minimal: this endpoint is public, so it must not
        // disclose whether a customer already existed.
        return response()->json([
            'message' => 'Thank you. We will be in touch shortly.',
            'reference' => $lead->id,
        ], 201);
    }
}
