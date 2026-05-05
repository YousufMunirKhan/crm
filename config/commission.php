<?php

return [
    /**
     * Day of month (1–31) when the scheduled monthly commission emails run.
     */
    'monthly_report_day' => (int) env('COMMISSION_MONTHLY_REPORT_DAY', 1),

    /**
     * Time (H:i) when the scheduled job runs (app timezone).
     */
    'monthly_report_time' => env('COMMISSION_MONTHLY_REPORT_TIME', '08:00'),

    /**
     * Roles that receive the admin summary email with the full PDF attachment.
     *
     * @var list<string>
     */
    'admin_role_names' => ['Admin', 'System Admin', 'Manager'],

    /**
     * Extra email addresses (comma-separated in .env) to BCC on the admin summary.
     *
     * @var list<string>
     */
    'extra_admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('COMMISSION_REPORT_EXTRA_EMAILS', ''))))),
];
