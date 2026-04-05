<?php

namespace App\Support;

/**
 * Valid keys for users.nav_permissions and roles.nav_permissions (JSON).
 * When set, only sections with true are visible (whitelist).
 * Admin/System Admin users always see all. null/empty = no restriction at that level.
 */
final class NavSections
{
    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'appointments' => 'Appointments',
            'followups' => 'Follow-ups',
            'prospects' => 'Prospects',
            'customers' => 'Customers',
            'all_leads' => 'All Leads',
            'lead_pipeline' => 'Lead Pipeline',
            'products' => 'Products',
            'tickets' => 'Tickets',
            'pos_support' => 'POS Support',
            'invoices' => 'Invoices',
            'today_activity' => "Today's Activity",
            'report' => 'Report',
            'todays_report' => "Today's Report",
            'marketing' => 'Marketing',
            'employees' => 'Employees',
            'hr' => 'HR',
            'expenses' => 'Expenses',
            'salary_slips' => 'Salary Slips',
            'salary_reports' => 'Salary Reports',
            'settings' => 'Settings',
            'access_manager' => 'Access Manager',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public static function sanitize(array $input): array
    {
        $allowed = self::keys();
        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $out[$key] = (bool) $input[$key];
        }

        return $out;
    }
}
