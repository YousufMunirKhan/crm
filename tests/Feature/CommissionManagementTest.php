<?php

namespace Tests\Feature;

use App\Mail\CommissionMonthlyAdminMail;
use App\Mail\CommissionMonthlyUserMail;
use App\Models\CommissionSale;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_eligibility(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $target = $this->makeUserWithRole('Sales', false);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/commission-management/users/{$target->id}/eligibility", [
            'commission_eligible' => true,
        ])->assertOk();

        $this->assertTrue((bool) $target->fresh()->commission_eligible);
    }

    public function test_non_admin_cannot_toggle_eligibility(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $target = $this->makeUserWithRole('CallAgent', false);
        Sanctum::actingAs($sales);

        $this->patchJson("/api/commission-management/users/{$target->id}/eligibility", [
            'commission_eligible' => true,
        ])->assertStatus(403);
    }

    public function test_admin_can_assign_single_commission(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $credited = $this->makeUserWithRole('Sales', true);
        $sale = $this->makeWonSale($credited);

        Sanctum::actingAs($admin);
        $this->postJson('/api/commission-management/allocations', [
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'allocations' => [
                [
                    'credited_user_id' => $credited->id,
                    'commission_amount' => 120.50,
                    'commission_currency' => 'GBP',
                    'commission_role' => 'single_owner',
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('commission_sales', [
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'credited_user_id' => $credited->id,
            'commission_currency' => 'GBP',
            'commission_role' => 'single_owner',
        ]);
    }

    public function test_admin_can_split_commission_into_two_entries(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $appointmentCreator = $this->makeUserWithRole('Sales', true);
        $closer = $this->makeUserWithRole('Sales', true);
        $sale = $this->makeWonSale($closer);

        Sanctum::actingAs($admin);
        $this->postJson('/api/commission-management/allocations', [
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'allocations' => [
                [
                    'credited_user_id' => $appointmentCreator->id,
                    'commission_amount' => 75,
                    'commission_currency' => 'PKR',
                    'commission_role' => 'appointment_creator',
                ],
                [
                    'credited_user_id' => $closer->id,
                    'commission_amount' => 75,
                    'commission_currency' => 'PKR',
                    'commission_role' => 'closer',
                ],
            ],
        ])->assertCreated();

        $this->assertSame(2, CommissionSale::query()->count());
    }

    public function test_ineligible_user_cannot_receive_commission_entry(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $credited = $this->makeUserWithRole('Sales', false);
        $sale = $this->makeWonSale($credited);
        Sanctum::actingAs($admin);

        $this->postJson('/api/commission-management/allocations', [
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'allocations' => [
                [
                    'credited_user_id' => $credited->id,
                    'commission_amount' => 25,
                    'commission_currency' => 'GBP',
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_reassignment_writes_activity_log(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $creditedA = $this->makeUserWithRole('Sales', true);
        $creditedB = $this->makeUserWithRole('Sales', true);
        $sale = $this->makeWonSale($creditedA);

        $entry = CommissionSale::create([
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'customer_id' => $sale['customer']->id,
            'credited_user_id' => $creditedA->id,
            'assigned_by_user_id' => $admin->id,
            'commission_amount' => 100,
            'commission_currency' => 'GBP',
            'commission_role' => 'single_owner',
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/commission-management/allocations/{$entry->id}/reassign", [
            'credited_user_id' => $creditedB->id,
        ])->assertOk();

        $this->assertDatabaseHas('commission_sales', [
            'id' => $entry->id,
            'credited_user_id' => $creditedB->id,
        ]);
        $this->assertTrue(
            LeadActivity::query()
                ->where('lead_id', $sale['lead']->id)
                ->where('description', 'like', 'Commission reassigned%')
                ->exists()
        );
    }

    public function test_admin_can_email_commission_report_to_single_user(): void
    {
        Mail::fake();
        $admin = $this->makeUserWithRole('Admin');
        $credited = $this->makeUserWithRole('Sales', true);
        $sale = $this->makeWonSale($credited);
        CommissionSale::query()->create([
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'customer_id' => $sale['customer']->id,
            'credited_user_id' => $credited->id,
            'assigned_by_user_id' => $admin->id,
            'commission_amount' => 50,
            'commission_currency' => 'GBP',
            'commission_role' => 'single_owner',
        ]);

        Sanctum::actingAs($admin);
        $today = now()->toDateString();
        $this->postJson('/api/commission-management/report/send-to-user', [
            'from' => $today,
            'to' => $today,
            'credited_user_id' => $credited->id,
        ])->assertOk()->assertJsonFragment(['sent_to' => $credited->email]);

        Mail::assertSent(CommissionMonthlyUserMail::class, function (CommissionMonthlyUserMail $mail) use ($credited) {
            return $mail->hasTo($credited->email);
        });
    }

    public function test_admin_can_email_internal_commission_report(): void
    {
        Mail::fake();
        $admin = $this->makeUserWithRole('Admin');
        $credited = $this->makeUserWithRole('Sales', true);
        $sale = $this->makeWonSale($credited);
        CommissionSale::query()->create([
            'lead_id' => $sale['lead']->id,
            'lead_item_id' => $sale['lead_item']->id,
            'customer_id' => $sale['customer']->id,
            'credited_user_id' => $credited->id,
            'assigned_by_user_id' => $admin->id,
            'commission_amount' => 40,
            'commission_currency' => 'GBP',
            'commission_role' => 'single_owner',
        ]);

        Sanctum::actingAs($admin);
        $today = now()->toDateString();
        $this->postJson('/api/commission-management/report/send-internal', [
            'from' => $today,
            'to' => $today,
        ])->assertOk();

        Mail::assertSent(CommissionMonthlyAdminMail::class);
    }

    public function test_send_commission_report_to_user_returns_422_when_no_rows(): void
    {
        Mail::fake();
        $admin = $this->makeUserWithRole('Admin');
        $credited = $this->makeUserWithRole('Sales', true);
        Sanctum::actingAs($admin);
        $future = now()->addYear()->toDateString();
        $this->postJson('/api/commission-management/report/send-to-user', [
            'from' => $future,
            'to' => $future,
            'credited_user_id' => $credited->id,
        ])->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_sales_user_cannot_send_commission_report_emails(): void
    {
        Mail::fake();
        $sales = $this->makeUserWithRole('Sales', true);
        $other = $this->makeUserWithRole('Sales', true);
        Sanctum::actingAs($sales);
        $today = now()->toDateString();
        $this->postJson('/api/commission-management/report/send-to-user', [
            'from' => $today,
            'to' => $today,
            'credited_user_id' => $other->id,
        ])->assertStatus(403);
        $this->postJson('/api/commission-management/report/send-internal', [
            'from' => $today,
            'to' => $today,
        ])->assertStatus(403);
        Mail::assertNothingSent();
    }

    private function makeUserWithRole(string $roleName, bool $eligible = true): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'commission_eligible' => $eligible,
        ]);
    }

    /**
     * @return array{customer: Customer, lead: Lead, lead_item: LeadItem}
     */
    private function makeWonSale(User $owner): array
    {
        $customer = Customer::query()->create([
            'name' => 'Commission Customer',
            'phone' => '12345',
            'type' => Customer::TYPE_CUSTOMER,
        ]);

        $lead = Lead::query()->create([
            'customer_id' => $customer->id,
            'stage' => 'won',
            'assigned_to' => $owner->id,
            'pipeline_value' => 100,
        ]);

        $product = Product::query()->create([
            'name' => 'EPOS',
            'category' => 'Software',
        ]);

        $item = LeadItem::query()->create([
            'lead_id' => $lead->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'status' => LeadItem::STATUS_WON,
            'closed_at' => now(),
        ]);

        return ['customer' => $customer, 'lead' => $lead, 'lead_item' => $item];
    }
}
