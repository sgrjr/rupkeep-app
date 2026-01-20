<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedByAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $admin;

    public function __construct(User $admin)
    {
        $this->admin = $admin;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Password Has Been Changed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your password was changed by an administrator.')
            ->line('Changed by: ' . $this->admin->name)
            ->line('Date: ' . now()->format('F j, Y g:i A'))
            ->line('If you did not request this change or have concerns, please contact your administrator immediately.')
            ->action('Log In', url('/login'))
            ->line('Thank you for using our application!');
    }
}
