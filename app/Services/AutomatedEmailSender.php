<?php

namespace App\Services;

use App\Mail\AutomatedEmail;
use App\Models\SentCommunication;
use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

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

    /**
     * The logo, as something an email client will actually draw.
     *
     * Two things stop the stored value working in a mailbox. It is a relative
     * path, and an email has no page to be relative to; and it is a .webp,
     * which every browser renders and Outlook does not. A .png sitting beside
     * it is preferred when one has been made, so the picture survives the trip.
     */
    public function logoUrl(): ?string
    {
        $raw = trim((string) Setting::query()->where('key', 'logo_url')->value('value'));

        if ($raw === '') {
            return null;
        }

        $png = preg_replace('/\.(webp|avif)$/i', '.png', $raw);

        if ($png !== $raw && is_file(public_path(ltrim(parse_url($png, PHP_URL_PATH) ?: $png, '/')))) {
            $raw = $png;
        }

        return str_starts_with($raw, 'http')
            ? $raw
            : rtrim((string) config('app.url'), '/').'/'.ltrim($raw, '/');
    }

    /** Company details for an email footer, straight from Settings. */
    public function companyDetails(): array
    {
        $keys = ['company_website', 'company_phone', 'company_email', 'company_address'];
        $values = Setting::query()->whereIn('key', $keys)->pluck('value', 'key')->all();

        return [
            'website' => trim((string) ($values['company_website'] ?? '')),
            'phone' => trim((string) ($values['company_phone'] ?? '')),
            'email' => trim((string) ($values['company_email'] ?? '')),
            'address' => trim((string) ($values['company_address'] ?? '')),
        ];
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

        // Written before the send, not after, because the open pixel has to carry
        // this row's id and an afterwards would not know it until the message
        // had already gone.
        $record = $this->record($reference, $to, $mail, $context, 'pending');

        // Branding is the same on every one of these, so it is put on here
        // rather than repeated at each call site and drifting between them.
        // Anything the caller set already wins.
        $mail->mailData = array_merge([
            'companyName' => $this->companyName(),
            'logoUrl' => $this->logoUrl(),
            'company' => $this->companyDetails(),
            'trackingPixel' => URL::signedRoute('email.track.open', ['id' => $record->id]),
        ], $mail->mailData);

        MailConfigFromDatabase::apply();

        try {
            Mail::to($to)->send($mail);

            $record->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                // The message itself, so it can be looked at afterwards. The
                // view name alone cannot be replayed: the data that filled it
                // is gone the moment the command finishes.
                'content' => $this->renderedHtml($mail) ?? $mail->mailView,
            ])->save();

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

            $record->forceFill(['status' => 'failed', 'error_message' => $e->getMessage()])->save();

            return false;
        }
    }

    /**
     * The message as it went out, or null if it cannot be drawn twice.
     *
     * Never allowed to fail the send: by the time this runs the email has
     * already left, and losing a copy of it is not a reason to report the send
     * as failed.
     */
    private function renderedHtml(AutomatedEmail $mail): ?string
    {
        $level = ob_get_level();

        try {
            $html = view($mail->mailView, $mail->mailData)->render();

            // A 64KB column: a truncated copy is more use than none, and these
            // run about 15KB.
            return mb_strlen($html) > 60000 ? mb_substr($html, 0, 60000) : $html;
        } catch (\Throwable $e) {
            Log::warning('Could not keep a copy of an automated email', [
                'view' => $mail->mailView,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
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
    ): SentCommunication {
        return SentCommunication::create([
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
