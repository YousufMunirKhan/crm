<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\HR\Models\EmployeeTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The board the whole team reads: who has a target, and how far through it
 * they are.
 *
 * What is worth pinning down is who is on it (a target, not a role), that
 * anybody can open it, that it carries no money, and that a day is a UK day.
 */
class SalesLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday in British Summer Time: Monday and Tuesday are over.
        $this->travelTo('2026-10-07 11:00:00');
    }

    private function user(string $roleName, string $name, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(array_merge(
            ['role_id' => $role->id, 'is_active' => true, 'name' => $name],
            $attributes
        ));
    }

    private function target(User $user, int $dailyLeads = 0, int $appointments = 0, int $sales = 0): void
    {
        EmployeeTarget::updateOrCreate(
            ['user_id' => $user->id, 'month' => '2026-10'],
            ['target_daily_leads' => $dailyLeads, 'target_appointments' => $appointments, 'target_sales' => $sales],
        );
    }

    private function lead(User $user, string $at): Lead
    {
        $customer = Customer::create([
            'name' => 'Shop '.random_int(1, 999999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        $lead = Lead::create(['customer_id' => $customer->id, 'stage' => 'lead', 'assigned_to' => $user->id]);
        $lead->forceFill(['created_at' => $at])->saveQuietly();

        return $lead;
    }

    private function leads(User $user, int $count, string $at): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->lead($user, $at);
        }
    }

    private function appointment(User $bookedBy, string $at, ?User $for = null): void
    {
        $activity = LeadActivity::create([
            'lead_id' => $this->lead($bookedBy, '2026-09-01 10:00:00')->id,
            'user_id' => $bookedBy->id,
            'assigned_user_id' => $for?->id,
            'type' => 'appointment',
        ]);
        $activity->forceFill(['created_at' => $at])->saveQuietly();
    }

    private function sale(User $user, string $at): void
    {
        $product = Product::firstOrCreate(['name' => 'Card machine'], ['is_active' => true]);

        LeadItem::create([
            'lead_id' => $this->lead($user, '2026-09-01 10:00:00')->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => LeadItem::STATUS_WON,
            'closed_at' => $at,
        ]);
    }

    private function board(User $as, string $period = 'week'): array
    {
        return $this->actingAs($as, 'sanctum')
            ->getJson('/api/leaderboard?period='.$period)
            ->assertOk()
            ->json();
    }

    public function test_only_people_with_a_target_are_on_the_board(): void
    {
        $rep = $this->user('Sales', 'Has Leads Target');
        $manager = $this->user('Manager', 'Has Appointments Target');
        $noTarget = $this->user('Sales', 'No Target');
        $leaver = $this->user('Sales', 'Left Last Month', ['is_active' => false]);
        $salesOnly = $this->user('Sales', 'Has Sales Target');

        $this->target($rep, dailyLeads: 5);
        $this->target($manager, appointments: 10);
        $this->target($leaver, dailyLeads: 5);
        $this->target($salesOnly, sales: 4);
        // Busy, but nobody has given them a number.
        $this->leads($noTarget, 9, '2026-10-07 09:00:00');

        $board = $this->board($rep);
        $names = array_column($board['rows'], 'name');

        $this->assertEqualsCanonicalizing(['Has Leads Target', 'Has Appointments Target', 'Has Sales Target'], $names);
        $this->assertSame(3, $board['summary']['members']);
    }

    public function test_anybody_on_the_staff_can_read_it(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);

        // No target of their own, and not management.
        $support = $this->user('Support', 'Support Person');

        $board = $this->board($support);

        $this->assertSame('A Rep', $board['rows'][0]['name']);
        $this->assertFalse($board['rows'][0]['is_me']);
        $this->assertTrue($this->board($rep)['rows'][0]['is_me']);
    }

    public function test_it_is_not_open_to_somebody_signed_out(): void
    {
        $this->getJson('/api/leaderboard')->assertUnauthorized();
    }

    public function test_it_carries_no_money_and_no_personal_details(): void
    {
        $rep = $this->user('Sales', 'A Rep', ['email' => 'rep@example.test', 'bank_account_number' => '12345678']);
        EmployeeTarget::create([
            'user_id' => $rep->id, 'month' => '2026-10',
            'target_daily_leads' => 5, 'target_sales' => 4, 'target_revenue' => 9000,
        ]);

        $encoded = json_encode($this->board($this->user('Support', 'Somebody Else')));

        // A sale is a count of lines won; what it was worth is not on the board.
        foreach (['revenue', 'price', '9000', 'rep@example.test', '12345678'] as $absent) {
            $this->assertStringNotContainsString($absent, $encoded);
        }
    }

    public function test_it_opens_on_today_and_ranks_on_todays_leads(): void
    {
        $done = $this->user('Sales', 'Done For The Day');
        $going = $this->user('Sales', 'Getting There');
        $quiet = $this->user('Sales', 'Not Started');

        foreach ([$done, $going, $quiet] as $person) {
            $this->target($person, dailyLeads: 5, appointments: 20);
        }

        $this->leads($done, 5, '2026-10-07 09:00:00');
        $this->leads($going, 2, '2026-10-07 09:00:00');
        // A big week so far and a full diary, but nothing yet today.
        $this->leads($quiet, 9, '2026-10-06 09:00:00');
        foreach (range(1, 12) as $n) {
            $this->appointment($quiet, '2026-10-02 10:00:00');
        }

        $board = $this->actingAs($done, 'sanctum')->getJson('/api/leaderboard')->assertOk()->json();

        $this->assertSame('today', $board['period']);
        $this->assertSame(['Done For The Day', 'Getting There', 'Not Started'], array_column($board['rows'], 'name'));
        $this->assertSame(['achieved', 'in_progress', 'not_started'], array_column($board['rows'], 'status'));
        $this->assertSame([100, 40, 0], array_column($board['rows'], 'progress'));

        // One day of five, not a week of them.
        $this->assertSame(5, $board['rows'][1]['leads']['target']);
        $this->assertSame(2, $board['rows'][1]['leads']['done']);
        $this->assertSame(['done' => 7, 'target' => 15, 'percent' => 47], $board['summary']['leads_today']);
    }

    public function test_somebody_with_only_an_appointment_target_is_ranked_on_that_today(): void
    {
        $booker = $this->user('CallAgent', 'Booker');
        $this->target($booker, appointments: 10);
        $this->appointment($booker, '2026-10-07 09:30:00');
        $this->appointment($booker, '2026-10-02 09:30:00');

        $row = $this->board($booker, 'today')['rows'][0];

        $this->assertNull($row['leads']);
        $this->assertSame(1, $row['appointments']['today']);
        $this->assertSame(20, $row['progress']);
        $this->assertSame('in_progress', $row['status']);
    }

    public function test_leads_are_counted_for_the_working_week_against_the_daily_target(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);

        $this->leads($rep, 4, '2026-10-05 10:00:00'); // Monday
        $this->leads($rep, 3, '2026-10-07 09:00:00'); // today
        $this->leads($rep, 6, '2026-10-03 10:00:00'); // last week

        $row = $this->board($rep)['rows'][0];

        // Monday to Saturday is six days of five.
        $this->assertSame(30, $row['leads']['target']);
        $this->assertSame(7, $row['leads']['done']);
        $this->assertSame(3, $row['leads']['today']);
        $this->assertSame(23, $row['leads']['percent']);
        $this->assertNull($row['appointments']);
    }

    public function test_the_month_view_counts_the_whole_month(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);

        $this->leads($rep, 6, '2026-10-03 10:00:00');
        $this->leads($rep, 3, '2026-10-07 09:00:00');
        $this->leads($rep, 2, '2026-09-30 10:00:00');

        $row = $this->board($rep, 'month')['rows'][0];

        // October 2026 has 27 days that are not a Sunday.
        $this->assertSame(135, $row['leads']['target']);
        $this->assertSame(9, $row['leads']['done']);
    }

    public function test_a_day_is_a_uk_day(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);

        // Half past midnight on Wednesday by the clock on the wall; still
        // Tuesday in the UTC the row is stored in.
        $this->leads($rep, 1, '2026-10-06 23:30:00');

        $this->assertSame(1, $this->board($rep)['rows'][0]['leads']['today']);
    }

    public function test_the_furthest_through_their_target_is_first(): void
    {
        $ahead = $this->user('Sales', 'Ahead');
        $behind = $this->user('Sales', 'Behind');
        $this->target($ahead, dailyLeads: 5);
        $this->target($behind, dailyLeads: 5);

        $this->leads($ahead, 12, '2026-10-06 10:00:00');
        $this->leads($behind, 2, '2026-10-06 10:00:00');

        $rows = $this->board($ahead)['rows'];

        $this->assertSame(['Ahead', 'Behind'], array_column($rows, 'name'));
        $this->assertSame([1, 2], array_column($rows, 'rank'));
        // Two days are over: ten leads is the pace, twelve is on it, two is not.
        $this->assertSame('on_track', $rows[0]['status']);
        $this->assertSame('at_risk', $rows[1]['status']);
    }

    public function test_nobody_is_behind_on_a_week_that_has_only_just_started(): void
    {
        $this->travelTo('2026-10-05 08:00:00'); // Monday morning

        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);

        $this->assertSame('on_track', $this->board($rep)['rows'][0]['status']);
    }

    public function test_reaching_the_whole_target_says_so(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 1, appointments: 1);

        $this->leads($rep, 6, '2026-10-06 10:00:00');
        $this->appointment($rep, '2026-10-02 10:00:00');

        $row = $this->board($rep)['rows'][0];

        $this->assertSame('achieved', $row['status']);
        $this->assertSame(100, $row['progress']);
    }

    public function test_appointments_count_for_the_month_and_once_for_the_team(): void
    {
        $booker = $this->user('CallAgent', 'Booker');
        $closer = $this->user('Sales', 'Closer');
        $this->target($booker, appointments: 10);
        $this->target($closer, appointments: 4);

        // Booked by one for the other: it counts for both of them...
        $this->appointment($booker, '2026-10-02 10:00:00', for: $closer);
        $this->appointment($booker, '2026-10-07 09:30:00');
        $this->appointment($booker, '2026-09-29 10:00:00');

        $board = $this->board($booker);
        $rows = collect($board['rows'])->keyBy('name');

        $this->assertSame(2, $rows['Booker']['appointments']['done']);
        $this->assertSame(1, $rows['Closer']['appointments']['done']);

        // ...but it was one appointment, so the team booked two, not three.
        $this->assertSame(2, $board['summary']['appointments_month']['done']);
        $this->assertSame(14, $board['summary']['appointments_month']['target']);
        $this->assertSame(1, $board['summary']['appointments_today']);
    }

    public function test_sales_are_counted_as_lines_won_this_month_against_the_target(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, sales: 4);

        $this->sale($rep, '2026-10-02 10:00:00');
        $this->sale($rep, '2026-10-07 09:00:00');
        $this->sale($rep, '2026-09-28 10:00:00'); // last month

        $board = $this->board($rep, 'month');
        $row = $board['rows'][0];

        $this->assertSame(['target' => 4, 'today' => 1, 'done' => 2, 'percent' => 50], $row['sales']);
        $this->assertNull($row['leads']);
        $this->assertSame(50, $row['progress']);
        $this->assertSame(['done' => 2, 'target' => 4, 'percent' => 50], $board['summary']['sales_month']);
        $this->assertSame(1, $board['summary']['sales_today']);
    }

    public function test_somebody_with_no_sales_target_has_no_sales_figure(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);
        $this->sale($rep, '2026-10-02 10:00:00');

        $this->assertNull($this->board($rep)['rows'][0]['sales']);
    }

    public function test_the_chart_leaves_sunday_out(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5);
        $this->leads($rep, 2, '2026-10-06 10:00:00');

        $chart = collect($this->board($rep)['chart']);

        $this->assertNotContains('2026-10-04', $chart->pluck('date')->all());
        $this->assertCount(6, $chart);
        $this->assertSame(2, $chart->firstWhere('date', '2026-10-06')['leads']);
        $this->assertSame(5, $chart->firstWhere('date', '2026-10-06')['lead_target']);
        $this->assertTrue($chart->last()['is_today']);
    }

    public function test_it_downloads_as_a_pdf_anybody_can_send_round(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->target($rep, dailyLeads: 5, appointments: 10);
        $this->leads($rep, 4, '2026-10-06 10:00:00');

        $response = $this->actingAs($this->user('Support', 'Support Person'), 'sanctum')
            ->get('/api/leaderboard/pdf?period=week')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('sales-leaderboard-week-2026-10-07.pdf', $response->headers->get('content-disposition'));
        // Every PDF starts with the %PDF- header.
        $this->assertSame('%PDF-', substr($response->getContent(), 0, 5));
    }

    public function test_the_pdf_renders_with_nobody_on_the_board(): void
    {
        $response = $this->actingAs($this->user('Sales', 'A Rep'), 'sanctum')
            ->get('/api/leaderboard/pdf?period=month')
            ->assertOk();

        $this->assertSame('%PDF-', substr($response->getContent(), 0, 5));
    }

    public function test_an_unknown_period_is_refused(): void
    {
        $this->actingAs($this->user('Sales', 'A Rep'), 'sanctum')
            ->getJson('/api/leaderboard?period=decade')
            ->assertStatus(422);
    }
}
