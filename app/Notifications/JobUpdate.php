<?php

namespace App\Notifications;

use App\Models\PilotCarJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class JobUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    protected ?PilotCarJob $job;
    protected string $title;
    protected string $body;

    /**
     * Create a new notification instance.
     */
    public function __construct(?PilotCarJob $job = null, ?string $title = null, ?string $body = null)
    {
        $this->job = $job;
        $this->title = $title ?? 'New Job Update';
        $this->body = $body ?? 'You have been assigned a new job!';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_id' => $this->job?->id,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        $url = $this->job
            ? route('my.jobs.show', ['job' => $this->job->id])
            : route('dashboard');

        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/assets/favicon.ico')
            ->body($this->body)
            ->action('View Job', $url)
            ->data(['url' => $url]);
    }
}
