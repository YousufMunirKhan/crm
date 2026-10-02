<?php

namespace Tests\Feature;

use App\Models\ColdCallingContact;
use App\Models\ColdCallingRun;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sweeping the areas we already have customers in.
 *
 * The scraper runs on an office PC, so the CRM's half is a queue: staff pick
 * areas, the runner claims them with a shared key and posts listings back.
 * These pin where the areas come from, that the runner cannot be anonymous,
 * and that a listing is never saved twice or offered as a cold call when it is
 * already a customer.
 */
class ColdCallingAreaSweepTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-sweep-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cold_calling.sweep.runner_key' => self::KEY]);

        Http::fake([
            'api.postcodes.io/outcodes/*' => Http::response(['status' => 200, 'result' => ['latitude' => 53.4486, 'longitude' => -2.2247]]),
            'api.postcodes.io/places*' => Http::response(['status' => 200, 'result' => [
                ['name_1' => 'Leeds', 'local_type' => 'Village', 'latitude' => 51.25, 'longitude' => 0.61],
                ['name_1' => 'Leeds', 'local_type' => 'City', 'latitude' => 53.7997, 'longitude' => -1.5492],
            ]]),
        ]);
    }

    private function staff(string $role = 'Admin'): User
    {
        $role = Role::query()->firstOrCreate(['name' => $role], ['description' => $role]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function customer(array $attributes): Customer
    {
        static $n = 0;
        $n++;

        return Customer::create(array_merge([
            'name' => 'Shop '.$n,
            'phone' => '0161700'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'type' => Customer::TYPE_CUSTOMER,
        ], $attributes));
    }

    private function prospectWithLead(array $attributes): Customer
    {
        $prospect = $this->customer(array_merge(['type' => Customer::TYPE_PROSPECT], $attributes));
        Lead::create(['customer_id' => $prospect->id, 'stage' => 'lead']);

        return $prospect;
    }

    private function runner(): static
    {
        return $this->withHeaders(['X-Api-Key' => self::KEY]);
    }

    /**
     * @return array<string, string>
     */
    private function listing(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Rusholme Grill',
            'category' => 'Restaurant',
            'address' => '12 Wilmslow Rd, Manchester M14 5TP, United Kingdom',
            'website' => 'https://rusholmegrill.example',
            'phone' => '0161 224 0000',
            'review_count' => '120',
            'review_rating' => '4.4',
            'latitude' => '53.4551',
            'longitude' => '-2.2256',
            'place_id' => 'ChIJ-rusholme-grill',
            'link' => 'https://www.google.com/maps/place/rusholme-grill',
            'emails' => 'hello@rusholmegrill.example',
            'complete_address' => '{"street":"12 Wilmslow Rd","city":"Manchester","postal_code":"M14 5TP","country":"GB"}',
        ], $overrides);
    }

    public function test_areas_come_from_customers_and_from_prospects_a_lead_was_raised_for(): void
    {
        $this->customer(['postcode' => 'M14 5TP', 'city' => 'Manchester']);
        $this->customer(['postcode' => 'm145ab', 'city' => 'Manchester']);
        $this->prospectWithLead(['postcode' => 'M14 6XX', 'city' => 'Manchester']);
        $this->prospectWithLead(['postcode' => 'LS1 4AP', 'city' => 'Leeds']);
        // No postcode: falls back to the town, however it was typed.
        $this->customer(['postcode' => null, 'city' => '  bradford ']);
        $this->customer(['postcode' => 'n/a', 'city' => 'BRADFORD']);
        // A prospect nobody raised a lead for is not somewhere "leads come from".
        $this->customer(['type' => Customer::TYPE_PROSPECT, 'postcode' => 'B11 1AA', 'city' => 'Birmingham']);
        // Nothing to place it by.
        $this->customer(['postcode' => null, 'city' => null]);

        $response = $this->actingAs($this->staff())->getJson('/api/cold-calling/areas')->assertOk();

        $areas = collect($response->json('data'))->keyBy('key');

        $this->assertSame(['M14', 'TOWN:BRADFORD', 'LS1'], collect($response->json('data'))->pluck('key')->all());
        $this->assertSame(2, $areas['M14']['customers']);
        $this->assertSame(1, $areas['M14']['leads']);
        $this->assertSame('Manchester', $areas['M14']['town']);
        $this->assertSame('Bradford', $areas['TOWN:BRADFORD']['label']);
        $this->assertSame(2, $areas['TOWN:BRADFORD']['customers']);
        $this->assertSame(1, $response->json('unlocated'));
    }

    public function test_queueing_an_area_leaves_a_pending_sweep_for_the_runner(): void
    {
        $this->customer(['postcode' => 'M14 5TP', 'city' => 'Manchester']);

        $this->actingAs($this->staff())
            ->postJson('/api/cold-calling/sweeps', [
                'area_keys' => ['M14', 'ZZ99'],
                'business_types' => ['restaurant', 'takeaway'],
                'depth' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('queued', 1);

        $run = ColdCallingRun::query()->sole();
        $this->assertSame('pending', $run->status);
        $this->assertSame(ColdCallingRun::ENGINE_GMAPS_SCRAPER, $run->engine);
        $this->assertSame('M14', $run->area_key);
        $this->assertSame(['restaurant in M14 Manchester, UK', 'takeaway in M14 Manchester, UK'], $run->meta['keywords']);
        $this->assertSame(53.4486, $run->meta['lat']);

        // Asking again while it is still waiting does not queue it twice.
        $this->postJson('/api/cold-calling/sweeps', ['area_keys' => ['M14']])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'already_queued');
        $this->assertSame(1, ColdCallingRun::query()->count());
    }

    public function test_a_town_is_centred_on_our_own_customers_there(): void
    {
        $this->customer(['postcode' => null, 'city' => 'Leeds', 'latitude' => 53.80, 'longitude' => -1.55]);

        $this->actingAs($this->staff())
            ->postJson('/api/cold-calling/sweeps', ['area_keys' => ['TOWN:LEEDS']])
            ->assertCreated();

        $run = ColdCallingRun::query()->sole();
        $this->assertSame('customers', $run->meta['geocode_source']);
        $this->assertSame('restaurant in Leeds, UK', $run->meta['keywords'][0]);
    }

    public function test_sales_staff_cannot_queue_sweeps(): void
    {
        $this->customer(['postcode' => 'M14 5TP']);

        $this->actingAs($this->staff('Sales Agent'))
            ->postJson('/api/cold-calling/sweeps', ['area_keys' => ['M14']])
            ->assertForbidden();

        $this->assertSame(0, ColdCallingRun::query()->count());
    }

    public function test_the_runner_endpoints_are_closed_without_the_key(): void
    {
        $this->postJson('/api/cold-calling-runner/claim')->assertStatus(401);
        $this->withHeaders(['X-Api-Key' => 'not-the-key'])->postJson('/api/cold-calling-runner/claim')->assertStatus(401);

        // An unset key must never mean "accept anonymous writes".
        config(['cold_calling.sweep.runner_key' => '']);
        $this->runner()->postJson('/api/cold-calling-runner/claim')->assertStatus(503);
    }

    public function test_the_runner_claims_a_sweep_posts_listings_and_finishes_it(): void
    {
        $this->customer(['postcode' => 'M14 5TP', 'city' => 'Manchester']);
        $this->actingAs($this->staff())->postJson('/api/cold-calling/sweeps', ['area_keys' => ['M14']])->assertCreated();
        $this->app['auth']->forgetGuards();

        $claim = $this->runner()->postJson('/api/cold-calling-runner/claim')->assertOk();
        $id = $claim->json('run.id');
        $this->assertSame('53.4486', $claim->json('run.lat'));
        $this->assertNotEmpty($claim->json('run.keywords'));
        $this->assertSame('processing', ColdCallingRun::find($id)->status);

        // Nothing else is waiting.
        $this->runner()->postJson('/api/cold-calling-runner/claim')->assertOk()->assertJsonPath('run', null);

        $this->runner()->postJson("/api/cold-calling-runner/runs/{$id}/rows", ['rows' => [
            $this->listing(),
            $this->listing(['title' => 'Curry Mile Cafe', 'place_id' => 'ChIJ-curry-mile', 'phone' => '', 'emails' => '']),
            $this->listing(['title' => 'Gone Bakery', 'place_id' => 'ChIJ-gone', 'status' => 'Permanently closed']),
        ]])->assertOk()->assertJsonPath('counts.new', 2)->assertJsonPath('counts.skipped', 1);

        // The same listing again - a second keyword found it too.
        $this->runner()->postJson("/api/cold-calling-runner/runs/{$id}/rows", ['rows' => [$this->listing()]])
            ->assertOk()->assertJsonPath('counts.new', 0)->assertJsonPath('counts.duplicate', 1);

        $this->runner()->postJson("/api/cold-calling-runner/runs/{$id}/finish", ['status' => 'completed', 'rows_scraped' => 4])
            ->assertOk();

        $run = ColdCallingRun::find($id);
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->new_count);
        $this->assertSame(1, $run->duplicate_count);

        $contact = ColdCallingContact::where('place_id', 'ChIJ-rusholme-grill')->sole();
        $this->assertSame('Rusholme Grill', $contact->name);
        $this->assertSame('M145TP', $contact->postcode_extracted);
        $this->assertSame('hello@rusholmegrill.example', $contact->email);
        $this->assertSame('gmaps_scraper', $contact->source);
        $this->assertSame(['Restaurant'], $contact->types);
        // Found by the M14 sweep, so the Saved contacts postcode filter finds it under M14.
        $this->assertSame(2, ColdCallingContact::query()->forPostcode('M14')->count());

        // A finished sweep takes no more rows.
        $this->runner()->postJson("/api/cold-calling-runner/runs/{$id}/rows", ['rows' => [$this->listing()]])->assertStatus(409);
    }

    public function test_a_listing_that_is_already_a_customer_is_linked_not_offered_as_a_cold_call(): void
    {
        $customer = $this->customer(['postcode' => 'M14 5TP', 'phone' => '+44 161 224 0000']);
        $this->actingAs($this->staff())->postJson('/api/cold-calling/sweeps', ['area_keys' => ['M14']])->assertCreated();
        $this->app['auth']->forgetGuards();

        $id = $this->runner()->postJson('/api/cold-calling-runner/claim')->json('run.id');
        $this->runner()->postJson("/api/cold-calling-runner/runs/{$id}/rows", ['rows' => [$this->listing()]])->assertOk();

        $this->assertSame($customer->id, ColdCallingContact::query()->sole()->crm_customer_id);
    }

    public function test_a_sweep_the_runner_abandoned_goes_back_on_the_queue(): void
    {
        $this->customer(['postcode' => 'M14 5TP']);
        $this->actingAs($this->staff())->postJson('/api/cold-calling/sweeps', ['area_keys' => ['M14']])->assertCreated();
        $this->app['auth']->forgetGuards();

        $id = $this->runner()->postJson('/api/cold-calling-runner/claim')->json('run.id');

        $this->travel(3)->hours();

        $this->runner()->postJson('/api/cold-calling-runner/claim')->assertOk()->assertJsonPath('run.id', $id);
    }

    public function test_a_scraper_csv_can_be_uploaded_by_hand(): void
    {
        $csv = "title,phone,emails,website,category,address,review_rating,review_count\n"
            ."Rusholme Grill,0161 224 0000,hello@rusholmegrill.example,rusholmegrill.example,Restaurant,\"12 Wilmslow Rd, Manchester M14 5TP\",4.4,120\n"
            ."Curry Mile Cafe,,,,Cafe,\"40 Wilmslow Rd, Manchester M14 5TQ\",4.1,33\n";

        $upload = fn () => $this->actingAs($this->staff())->post('/api/cold-calling/sweeps/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('results-ab12cd34.csv', $csv),
            'area_key' => 'M14',
        ], ['Accept' => 'application/json']);

        $upload()->assertCreated()->assertJsonPath('counts.new', 2);

        $contact = ColdCallingContact::where('name', 'Rusholme Grill')->sole();
        $this->assertSame('http://rusholmegrill.example', $contact->website);
        $this->assertSame('M145TP', $contact->postcode_extracted);

        // The trimmed file has no place id; the same file twice is still the same businesses.
        $upload()->assertCreated()->assertJsonPath('counts.new', 0)->assertJsonPath('counts.duplicate', 2);
        $this->assertSame(2, ColdCallingContact::query()->count());
    }

    public function test_a_pending_sweep_can_be_cancelled_but_a_started_one_cannot(): void
    {
        $this->customer(['postcode' => 'M14 5TP']);
        $admin = $this->staff();
        $this->actingAs($admin)->postJson('/api/cold-calling/sweeps', ['area_keys' => ['M14']])->assertCreated();
        $run = ColdCallingRun::query()->sole();

        $this->deleteJson("/api/cold-calling/sweeps/{$run->id}")->assertOk();
        $this->assertSame('cancelled', $run->fresh()->status);

        $run->update(['status' => 'processing']);
        $this->deleteJson("/api/cold-calling/sweeps/{$run->id}")->assertStatus(422);
    }
}
