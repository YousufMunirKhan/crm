<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Throwable;

/**
 * The email sent when the server breaks.
 *
 * Note what this does not implement: ShouldQueue. Every other mailable in the
 * app queues, which is right for them. This one cannot - there is no worker
 * running, so a queued alert would be written to the jobs table and never read.
 * An alert that waits for someone to notice it is not an alert.
 */
class ServerErrorAlert extends Mailable
{
    public string $errorClass;
    public string $errorMessage;
    public string $location;
    public string $trace;
    public string $appUrl;

    /**
     * @param  array<string, string>  $context
     */
    public function __construct(Throwable $e, public array $context = [])
    {
        $this->errorClass = $e::class;
        $this->errorMessage = $e->getMessage() !== '' ? $e->getMessage() : '(no message)';
        $this->location = $e->getFile().':'.$e->getLine();
        $this->appUrl = (string) config('app.url');

        // Enough frames to see where it came from. The whole trace turns the
        // email into something nobody scrolls through.
        $this->trace = implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 15));
    }

    public function envelope(): Envelope
    {
        // The class and location go in the subject so a mailbox thread groups
        // repeats of one fault, and two different faults never look alike.
        $short = class_basename($this->errorClass);

        return new Envelope(
            subject: '['.config('app.name').'] Server error: '.$short.' — '.basename($this->location),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.server-error-alert',
        );
    }
}
