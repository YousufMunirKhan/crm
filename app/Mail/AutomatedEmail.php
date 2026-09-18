<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One mailable for the mail nobody writes: reminders, chases and digests that
 * a scheduled command sends on its own.
 *
 * The rest of app/Mail is one class per message because each carries its own
 * dozen constructor arguments. These do not - they differ only in a subject, a
 * view and what that view is given - so five near-identical classes would say
 * nothing the views do not already say.
 */
class AutomatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $mailData
     * @param  array<int, \Illuminate\Mail\Mailables\Attachment>  $mailFiles
     */
    public function __construct(
        public string $mailSubject,
        public string $mailView,
        public array $mailData = [],
        public array $mailFiles = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: $this->mailView, with: $this->mailData);
    }

    public function attachments(): array
    {
        return $this->mailFiles;
    }
}
