<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three restricted roles as they are configured on the live system, doing
 * the things their people actually do all day.
 *
 * Turning on server-side permission checks is the kind of change that locks
 * real staff out of their own job on a Monday morning. These are the calls each
 * role's screens make on load, copied from the views, so a gate that is too
 * tight fails here rather than in front of somebody trying to work.
 */
class RealRolesStillWorkTest extends TestCase
{
    use RefreshDatabase;

    /** Copied from the live roles table. */
    private const LIVE = [
        'Manager' => [
            'dashboard', 'appointments', 'followups', 'prospects', 'customers', 'products',
            'tickets', 'pos_support', 'invoices', 'today_activity', 'marketing',
            'all_leads', 'lead_pipeline',
        ],
        'Sales' => [
            'dashboard', 'appointments', 'followups', 'prospects', 'customers', 'all_leads',
            'lead_pipeline', 'products', 'tickets', 'invoices', 'today_activity',
            'todays_report', 'marketing',
        ],
        'Support' => ['dashboard', 'tickets', 'pos_support', 'today_activity'],
    ];

    private function liveUser(string $roleName): User
    {
        $role = Role::create([
            'name' => $roleName,
            'nav_permissions' => array_fill_keys(self::LIVE[$roleName], true),
        ]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function dailyWork(): array
    {
        return [
            // Every dashboard calls this on load. It is outside the Reports
            // gate on purpose - Sales and Support have no Reports section, and
            // gating it would have emptied their home screen.
            'Manager' => [
                '/api/auth/me',
                '/api/dashboard',
                '/api/reporting/agents',
                '/api/customers',
                '/api/leads',
                '/api/appointments',
                '/api/followups',
                '/api/tickets',
                '/api/invoices',
                '/api/products',
                '/api/hr/attendance/today',
            ],
            'Sales' => [
                '/api/auth/me',
                '/api/dashboard/sales-agent',
                '/api/reporting/agents',
                '/api/customers',
                '/api/leads',
                '/api/appointments',
                '/api/followups',
                '/api/invoices',
                '/api/hr/attendance/today',
            ],
            'Support' => [
                '/api/auth/me',
                '/api/dashboard',
                '/api/reporting/agents',
                '/api/tickets',
                // Raising a ticket loads the customer list.
                '/api/customers',
                '/api/pos-support-tickets',
                '/api/hr/attendance/today',
            ],
        ];
    }

    public function test_manager_can_still_do_their_job(): void
    {
        $this->assertRoleCanReach('Manager');
    }

    public function test_sales_can_still_do_their_job(): void
    {
        $this->assertRoleCanReach('Sales');
    }

    public function test_support_can_still_do_their_job(): void
    {
        $this->assertRoleCanReach('Support');
    }

    private function assertRoleCanReach(string $roleName): void
    {
        $user = $this->liveUser($roleName);

        foreach (self::dailyWork()[$roleName] as $endpoint) {
            $response = $this->actingAs($user, 'sanctum')->getJson($endpoint);

            $this->assertNotSame(
                403,
                $response->status(),
                "{$roleName} was locked out of {$endpoint}, which their screens call on load."
            );
        }
    }

    public function test_and_they_are_still_kept_out_of_what_is_not_theirs(): void
    {
        // The other half: the gate has to actually bite, or none of this was
        // worth doing.
        $support = $this->liveUser('Support');

        foreach ([
            '/api/invoices',
            '/api/hr/salaries',
            '/api/hr/expenses',
            '/api/reporting/executive',
            '/api/commission-management/summary',
            '/api/email-management/smtp-status',
        ] as $endpoint) {
            $this->actingAs($support, 'sanctum')->getJson($endpoint)->assertStatus(403);
        }

        $sales = $this->liveUser('Sales');

        // Sales keeps invoices - it is in their role - but not payroll or the
        // reports board.
        $this->actingAs($sales, 'sanctum')->getJson('/api/invoices')->assertOk();
        $this->actingAs($sales, 'sanctum')->getJson('/api/hr/salaries')->assertStatus(403);
        $this->actingAs($sales, 'sanctum')->getJson('/api/reporting/executive')->assertStatus(403);
    }

    public function test_the_legacy_marketing_key_still_covers_what_it_was_split_into(): void
    {
        // Manager and Sales both carry the old single "marketing" key rather
        // than the six it became. That alias has to keep working or both roles
        // lose every marketing screen the moment the gate goes on.
        $manager = $this->liveUser('Manager');

        $this->actingAs($manager, 'sanctum')->getJson('/api/email-management/smtp-status')->assertOk();
        $this->actingAs($manager, 'sanctum')->getJson('/api/marketing/agent/plans')->assertOk();
    }
}
