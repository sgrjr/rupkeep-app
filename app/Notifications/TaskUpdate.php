<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public TaskComment $comment,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $statusLabel = str_replace('_', ' ', $this->task->status);
        $url = url(route('portal.tasks.show', $this->task));

        return (new MailMessage)
            ->subject("[{$this->task->code}] {$this->task->title}")
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line("There's an update on your request: **{$this->task->title}** ({$this->task->code}).")
            ->line("Current status: **{$statusLabel}**")
            ->line('---')
            ->line($this->comment->body)
            ->action('View request', $url)
            ->line('Thanks for using Rupkeep.');
    }
}
