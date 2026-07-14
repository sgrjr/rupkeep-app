<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
    public $fromName;

    /**
     * Create a new message instance.
     *
     * @param  string|null  $fromName  Display name for the From header. In this
     *   multi-tenant app the sender should read as the tenant organization
     *   (e.g. "Casco Bay Pilot Car"), not the parent platform. The From
     *   address stays the verified sending identity; only the label changes.
     */
    public function __construct($message, $subject = 'User Notification', $view = 'mail.notification-text', $viewData = [], $fromName = null)
    {
        $this->subject = $subject;
        $this->message = $message;
        $this->view = $view;
        $this->viewData = $viewData;
        $this->fromName = $fromName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->fromName ?: config('mail.from.name')),
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
