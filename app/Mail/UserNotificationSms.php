<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserNotificationSms extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    private $message;

    /**
     * Create a new message instance.
     *
     * SMS gateway messages are sent subject-less on purpose (see envelope()) so
     * the carrier treats the payload as plain text, not a titled email.
     */
    public function __construct($message)
    {
        $this->subject = '';
        $this->message = $message;
    }

    /**
     * The composed SMS body. Exposed for assertions/tests; the body is rendered
     * into the text view for delivery.
     */
    public function body(): string
    {
        return (string) $this->message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.notification-text',
            with: [
                'message_body' => $this->message,
            ],
        );
    }
}
