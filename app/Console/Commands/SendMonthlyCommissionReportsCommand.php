<?php

namespace App\Console\Commands;

use App\Mail\CommissionMonthlyAdminMail;
use App\Mail\CommissionMonthlyUserMail;
use App\Modules\Commission\Services\CommissionMonthlyReportService;
use App\Modules\Settings\Models\Setting;
use App\Services\MailConfigFromDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMonthlyCommissionReportsCommand extends Command
{
    protected $signature = 'commission:send-monthly-reports
                            {--month= : Calendar month YYYY-MM (defaults to previous calendar month) }
                            {--dry-run : Show who would be emailed; do not send }';

    protected $description = 'Send monthly commission summary emails (with PDF) to each credited user and to admins';

    public function handle(CommissionMonthlyReportService $service): int
    {
        $yearMonth = $this->option('month') ?: now()->subMonth()->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $yearMonth)) {
            $this->error('Invalid month. Use YYYY-MM, e.g. 2026-04');

            return self::INVALID;
        }

        $dry = (bool) $this->option('dry-run');
        $companyName = Setting::query()->where('key', 'company_name')->value('value')
            ?? config('app.name', 'CRM');

        $monthLabel = $service->monthLabel($yearMonth);
        $sales = $service->salesForMonth($yearMonth);

        $userBlocks = [];
        foreach ($sales->groupBy('credited_user_id') as $uid => $userSales) {
            $first = $userSales->first();
            $userBlocks[] = [
                'user_id' => (int) $uid,
                'name' => $first?->creditedUser?->name ?? 'User #'.$uid,
                'product_totals' => $service->productWiseTotals($userSales)->all(),
                'lines' => $service->tableRows($userSales),
            ];
        }

        usort($userBlocks, fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        $overallTotals = $service->currencyTotals($sales);
        $distinctPeople = $sales->pluck('credited_user_id')->unique()->count();

        $intro = $distinctPeople === 0
            ? "No commission allocations were recorded in {$monthLabel}."
            : "Commission allocations were recorded for {$distinctPeople} user(s). Per-person detail is below — the PDF attachment is the full consolidated report for records.";

        $pdfFileName = 'commission_report_'.$yearMonth.'.pdf';

        if ($dry) {
            $this->info("Month {$yearMonth} ({$monthLabel}) — Dry run.");
            foreach ($sales->groupBy('credited_user_id') as $uid => $group) {
                $u = $group->first()?->creditedUser;
                $this->line('Staff: '.(($u && $u->email) ? "{$u->name} <{$u->email}>" : "(user #{$uid} — no usable email, would skip)"));
            }
            $admins = $service->adminEmailAddresses();
            foreach ($admins as $mail) {
                $this->line('Admin: '.$mail);
            }
            $this->line('Full PDF attachment name: '.$pdfFileName);

            return self::SUCCESS;
        }

        MailConfigFromDatabase::apply();

        $userMailsSent = 0;
        foreach ($sales->groupBy('credited_user_id') as $uid => $userSales) {
            $userModel = $userSales->first()?->creditedUser;
            if (! $userModel || empty($userModel->email) || ! filter_var($userModel->email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Skipping user #{$uid}: no valid email.");

                continue;
            }

            $detailRows = $service->tableRows($userSales);
            $productTotals = $service->productWiseTotals($userSales)->all();
            $currencyTotals = $service->currencyTotals($userSales);

            Mail::to($userModel->email)->send(new CommissionMonthlyUserMail(
                companyName: $companyName,
                monthLabel: $monthLabel,
                yearMonth: $yearMonth,
                userName: $userModel->name,
                detailRows: $detailRows,
                productTotals: $productTotals,
                currencyTotals: $currencyTotals,
            ));
            $userMailsSent++;
        }

        $addrList = $service->adminEmailAddresses();
        if ($addrList->isEmpty()) {
            $this->warn('No admin / manager email addresses found (check active users and roles, or COMMISSION_REPORT_EXTRA_EMAILS).');
        } else {
            $to = $addrList->first();
            $bcc = $addrList->slice(1)->values()->all();
            $adminMessage = new CommissionMonthlyAdminMail(
                companyName: $companyName,
                monthLabel: $monthLabel,
                introduction: $intro,
                userBlocks: $userBlocks,
                overallTotals: $overallTotals,
                pdfFileName: $pdfFileName,
            );
            $send = Mail::to($to);
            if ($bcc !== []) {
                $send->bcc($bcc);
            }
            $send->send($adminMessage);
        }

        $this->info("Commission monthly reports sent for {$monthLabel}: {$userMailsSent} staff email(s), admin(s) ".($addrList->isEmpty() ? '0' : $addrList->count()).'.');

        return self::SUCCESS;
    }
}
