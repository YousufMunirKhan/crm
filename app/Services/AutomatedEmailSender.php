<?php

namespace App\Services;

use App\Mail\AutomatedEmail;
use App\Models\SentCommunication;
use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the mail nobody is watching, once each.
 *
 * Everything scheduled runs again tomorrow, so the question every automated
 * send has to answer first is "have I already sent this one" - not "have I
 * emailed this customer". Each send names the thing it is about, and that name
 * is what stops a reminder arriving every morning until the appointment.
 *
 * Undeliverable addresses are refused here rather than in each command, and a
 * marketing opt-out deliberately is not: an unsubscribe from campaigns is not
 * an instruction to stop sending somebody their own invoice.
 */
class AutomatedEmailSender
{
    public const TEMPLATE_TYPE = 'automated';

    private ?string $companyName = null;

    public function __construct(private SuppressionService $suppression) {}

    public function companyName(): string
    {
        return $this->companyName ??= (string) (Setting::query()
            ->where('key', 'company_name')
            ->value('value') ?: config('app.name'));
    }

    /** Already sent, so a rerun of the same day's command does nothing. */
    public function alreadySent(string $reference): bool
    {
        return SentCommunication::query()
            ->where('reference', $reference)
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * @param  array{customer_id?: int|null, lead_id?: int|null}  $context
     * @return bool whether a message actually went out
     */
    public function send(string $reference, ?string $to, AutomatedEmail $mail, array $context = []): bool
    {
        $to = trim((string) $to);

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($this->alreadySent($reference)) {
            return false;
        }

        // Not recorded, so that fixing a bounced address makes it deliverable
        // again rather than leaving it permanently skipped by a stale row.
        if ($this->suppression->isUndeliverable($to)) {
            return false;
        }

        MailConfigFromDatabase::apply();

        try {
            Mail::to($to)->send($mail);

            $this->record($reference, $to, $mail, $context, 'sent');

            // The host answers a burst with 450 "too much mail", and a digest
            // run is a burst by nature.
            $delay = (int) config('email_send.between_message_delay_ms', 0);
            if ($delay > 0) {
                usleep($delay * 1000);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Automated email failed', [
                'reference' => $reference,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            $this->record($reference, $to, $mail, $context, 'failed', $e->getMessage());

            return false;
        }
    }

    /**
     * @param  array{customer_id?: int|null, lead_id?: int|null}  $context
     */
    private function record(
        string $reference,
        string $to,
        AutomatedEmail $mail,
        array $context,
        string $status,
        ?string $error = null,
    ): void {
        SentCommunication::create([
            'type' => 'email',
            'template_type' => self::TEMPLATE_TYPE,
            'reference' => $reference,
            'customer_id' => $context['customer_id'] ?? null,
            'lead_id' => $context['lead_id'] ?? null,
            'recipient_email' => $to,
            'subject' => $mail->mailSubject,
            'content' => $mail->mailView,
            'status' => $status,
            'error_message' => $error,
            'sent_at' => now(),
        ]);
    }
}
