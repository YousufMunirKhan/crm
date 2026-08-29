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
            'marketing_email' => 'Marketing — Email',
            'marketing_sms' => 'Marketing — SMS',
            'marketing_whatsapp' => 'Marketing — WhatsApp',
            'marketing_templates' => 'Marketing — Templates',
            'marketing_cold_calling' => 'Marketing — Cold calling',
            'employees' => 'Employees',
            'hr_records' => 'HR — Employee records',
            'hr_attendance' => 'HR — Attendance',
            'bank_documents' => 'My bank & documents',
            'expenses' => 'Expenses',
            'salary_slips' => 'Salary Slips',
            'salary_reports' => 'Salary Reports',
            'commission_management' => 'Commission Management',
            'settings' => 'Settings',
            'access_manager' => 'Access Manager',
        ];
    }

    /**
     * Legacy whitelist keys that still grant access to the finer-grained keys
     * they were split into. Keeps existing users.nav_permissions rows working.
     *
     * @return array<string, list<string>>
     */
    public static function legacyAliases(): array
    {
        return [
            'leads_pipeline' => ['all_leads', 'lead_pipeline'],
            'marketing' => [
                'marketing_email',
                'marketing_sms',
                'marketing_whatsapp',
                'marketing_templates',
                'marketing_cold_calling',
            ],
            'hr' => ['hr_records', 'hr_attendance'],
        ];
    }

    /**
     * True when a legacy key present in the whitelist covers $key.
     *
     * @param  array<string, mixed>  $permissions
     */
    public static function legacyGrants(array $permissions, string $key): bool
    {
        foreach (self::legacyAliases() as $legacy => $covers) {
            if (in_array($key, $covers, true) && ! empty($permissions[$legacy])) {
                return true;
            }
        }

        return false;
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
