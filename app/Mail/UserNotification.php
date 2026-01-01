<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    private $message;
    public $view;
    public $viewData;

    /**
     * Create a new message instance.
     */
    public function __construct($message, $subject = 'User Notification', $view = 'mail.notification-text', $viewData = [])
    {
        $this->subject = $subject;
        $this->message = $message;
        $this->view = $view;
        $this->viewData = $viewData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $with = array_merge([
            'message_body' => $this->message,
            'subject' => $this->subject,
        ], $this->viewData);

        return new Content(
            view: $this->view,
            text: 'mail.notification-text',
            with: $with,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
