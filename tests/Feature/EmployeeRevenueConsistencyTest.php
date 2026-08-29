<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revenue was de-duplicated on the executive dashboard but not per employee,
 * so the leaderboard rows summed to more than the company headline.
 */
class EmployeeRevenueConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private array $range = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->range = [
            'from' => now()->subYear()->toDateString(),
            'to' => now()->addYear()->toDateString(),
        ];
    }

    private function agent(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function wonLead(User $agent, float $value): Lead
    {
        $customer = Customer::create([
            'name' => 'Acme',
            'phone' => '4477009013'.random_int(10, 99),
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'won',
            'assigned_to' => $agent->id,
        ]);

        $product = Product::create(['name' => 'Widget', 'is_active' => true]);

        LeadItem::create([
            'lead_id' => $lead->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $value,
            'total_price' => $value,
            'status' => LeadItem::STATUS_WON,
        ]);

        return $lead;
    }

    public function test_an_invoiced_won_deal_is_not_counted_twice_for_the_employee(): void
    {
        $agent = $this->agent();
        $lead = $this->wonLead($agent, 500.00);

        app(InvoiceService::class)->createFromLead($lead->fresh(), ['vat_rate' => 0], $agent->id);

        $rows = app(ReportingService::class)->getRevenueByEmployee($this->range);
        $row = collect($rows)->firstWhere('employee_id', $agent->id);

        $this->assertNotNull($row);
        // 500 invoiced, not 500 won + 500 invoiced.
        $this->assertSame(500.0, (float) $row['revenue']);
    }

    public function test_lead_revenue_still_reports_the_full_won_value(): void
    {
        // Targets and commission are settled on what the person closed,
        // whether or not it was invoiced.
        $agent = $this->agent();
        $lead = $this->wonLead($agent, 500.00);

        app(InvoiceService::class)->createFromLead($lead->fresh(), ['vat_rate' => 0], $agent->id);

        $rows = app(ReportingService::class)->getRevenueByEmployee($this->range);
        $row = collect($rows)->firstWhere('employee_id', $agent->id);

        $this->assertSame(500.0, (float) $row['lead_revenue']);
    }

    public function test_an_uninvoiced_won_deal_still_counts(): void
    {
        $agent = $this->agent();
        $this->wonLead($agent, 300.00);

        $rows = app(ReportingService::class)->getRevenueByEmployee($this->range);
        $row = collect($rows)->firstWhere('employee_id', $agent->id);

        $this->assertSame(300.0, (float) $row['revenue']);
    }

    public function test_employee_rows_do_not_exceed_the_company_headline(): void
    {
        $agent = $this->agent();
        $lead = $this->wonLead($agent, 500.00);
        app(InvoiceService::class)->createFromLead($lead->fresh(), ['vat_rate' => 0], $agent->id);
        $this->wonLead($agent, 200.00);

        $service = app(ReportingService::class);

        $perEmployee = collect($service->getRevenueByEmployee($this->range))->sum('revenue');
        $company = (float) $service->getExecutiveDashboard($this->range)['revenue'];

        $this->assertSame($company, (float) $perEmployee);
    }
}
