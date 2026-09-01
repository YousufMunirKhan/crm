<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The expenses screen printed "Total (PKR): Rs118,600" from the ten rows it
 * happened to be showing, with nothing on screen to say so. The real total
 * across the filter was Rs639,896 - under a fifth of the spend, labelled as the
 * spend, on the one screen in the product that is entirely about money.
 */
class ExpenseSummaryTest extends TestCase
{
    use RefreshDatabase;

    private ?User $bookkeeper = null;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);

        return $this->bookkeeper ??= User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function expense(array $attributes = []): Expense
    {
        return Expense::create(array_merge([
            'created_by' => $this->admin()->id,
            'reason' => 'Office',
            'amount' => 1000,
            'currency' => 'PKR',
            'category' => 'Office',
            'date' => now()->toDateString(),
            'status' => 'open',
        ], $attributes));
    }

    public function test_the_total_covers_the_whole_filter_not_the_page(): void
    {
        // Fifteen expenses, ten to a page.
        foreach (range(1, 15) as $i) {
            $this->expense(['amount' => 100]);
        }

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/hr/expenses')
            ->assertOk();

        $this->assertCount(10, $response->json('data'), 'The page is still a page.');

        $pkr = collect($response->json('summary.by_currency'))->firstWhere('currency', 'PKR');

        $this->assertSame(15, $pkr['count']);
        $this->assertEqualsWithDelta(1500, $pkr['total'], 0.01, 'The total must cover every matching row.');
    }

    public function test_the_total_follows_the_filter(): void
    {
        $this->expense(['amount' => 500, 'category' => 'Office']);
        $this->expense(['amount' => 900, 'category' => 'Utilities']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/hr/expenses?category=Utilities')
            ->assertOk();

        $pkr = collect($response->json('summary.by_currency'))->firstWhere('currency', 'PKR');

        $this->assertSame(1, $pkr['count']);
        $this->assertEqualsWithDelta(900, $pkr['total'], 0.01);
    }

    public function test_currencies_are_never_added_together(): void
    {
        $this->expense(['amount' => 1000, 'currency' => 'PKR']);
        $this->expense(['amount' => 20, 'currency' => 'GBP']);

        $rows = collect(
            $this->actingAs($this->admin(), 'sanctum')->getJson('/api/hr/expenses')->json('summary.by_currency')
        )->keyBy('currency');

        // There is no exchange rate anywhere in this product, so 1000 rupees
        // and 20 pounds must never become 1020 of anything.
        $this->assertEqualsWithDelta(1000, $rows['PKR']['total'], 0.01);
        $this->assertEqualsWithDelta(20, $rows['GBP']['total'], 0.01);
    }

    public function test_open_is_reported_separately_from_the_total(): void
    {
        $this->expense(['amount' => 300, 'status' => 'open']);
        $this->expense(['amount' => 700, 'status' => 'closed']);

        $pkr = collect(
            $this->actingAs($this->admin(), 'sanctum')->getJson('/api/hr/expenses')->json('summary.by_currency')
        )->firstWhere('currency', 'PKR');

        $this->assertEqualsWithDelta(1000, $pkr['total'], 0.01);
        $this->assertEqualsWithDelta(300, $pkr['open_total'], 0.01);
        $this->assertSame(1, $pkr['open_count']);
    }

    public function test_the_category_breakdown_is_biggest_first(): void
    {
        $this->expense(['amount' => 100, 'category' => 'Utilities']);
        $this->expense(['amount' => 900, 'category' => 'Equipment']);
        $this->expense(['amount' => 400, 'category' => 'Office']);

        $rows = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/hr/expenses')->json('summary.by_category');

        $this->assertSame(['Equipment', 'Office', 'Utilities'], array_column($rows, 'category'));
    }

    public function test_an_expense_with_no_category_is_still_counted(): void
    {
        $this->expense(['amount' => 250, 'category' => null]);

        $rows = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/hr/expenses')->json('summary.by_category');

        // Dropping it would make the breakdown quietly disagree with the total.
        $this->assertSame('Uncategorised', $rows[0]['category']);
        $this->assertEqualsWithDelta(250, $rows[0]['total'], 0.01);
    }

    public function test_only_management_sees_the_expense_book(): void
    {
        $salesRole = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);
        $rep = User::factory()->create(['role_id' => $salesRole->id, 'is_active' => true]);

        $this->actingAs($rep, 'sanctum')->getJson('/api/hr/expenses')->assertStatus(403);
    }
}
