/** Must match App\Support\NavSections::labels() keys (dashboard is always on for everyone). */
export const NAV_SECTION_OPTIONS = [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'appointments', label: 'Appointments' },
    { key: 'followups', label: 'Follow-ups' },
    { key: 'prospects', label: 'Prospects' },
    { key: 'customers', label: 'Customers' },
    { key: 'all_leads', label: 'All Leads' },
    { key: 'lead_pipeline', label: 'Lead Pipeline' },
    { key: 'products', label: 'Products' },
    { key: 'tickets', label: 'Tickets' },
    { key: 'pos_support', label: 'POS Support' },
    { key: 'invoices', label: 'Invoices' },
    { key: 'today_activity', label: "Today's Activity" },
    { key: 'report', label: 'Report (submenu)' },
    { key: 'todays_report', label: "Today's Report" },
    { key: 'marketing_email', label: 'Marketing — Email' },
    { key: 'marketing_sms', label: 'Marketing — SMS' },
    { key: 'marketing_whatsapp', label: 'Marketing — WhatsApp' },
    { key: 'marketing_templates', label: 'Marketing — Templates' },
    { key: 'marketing_cold_calling', label: 'Marketing — Cold calling' },
    { key: 'employees', label: 'Employees' },
    { key: 'hr_records', label: 'HR — Employee records' },
    { key: 'hr_attendance', label: 'HR — Attendance' },
    { key: 'bank_documents', label: 'My bank & documents' },
    { key: 'expenses', label: 'Expenses' },
    { key: 'salary_slips', label: 'Salary Slips' },
    { key: 'salary_reports', label: 'Salary Reports' },
    { key: 'commission_management', label: 'Commission Management' },
    { key: 'settings', label: 'Settings' },
    { key: 'access_manager', label: 'Access Manager' },
];

/**
 * Legacy whitelist keys that still grant the finer-grained keys they were split
 * into. Mirrors App\Support\NavSections::legacyAliases() so already-stored
 * nav_permissions rows keep working until they are re-saved.
 */
export const NAV_SECTION_LEGACY_ALIASES = {
    leads_pipeline: ['all_leads', 'lead_pipeline'],
    marketing: [
        'marketing_email',
        'marketing_sms',
        'marketing_whatsapp',
        'marketing_templates',
        'marketing_cold_calling',
    ],
    hr: ['hr_records', 'hr_attendance'],
};

/** True when a legacy key present in the whitelist covers `key`. */
export function navLegacyGrants(permissions, key) {
    if (!permissions) return false;
    for (const [legacy, covers] of Object.entries(NAV_SECTION_LEGACY_ALIASES)) {
        if (covers.includes(key) && permissions[legacy]) return true;
    }
    return false;
}
