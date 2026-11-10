<?php

namespace App\Jobs;

use App\Actions\SendUserNotification;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Support\DeliveryContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One message to one person, as its own queued job (TASK-465).
 *
 * The cancel, uncancel and status-change listeners used to loop over every
 * assigned driver inside one job, so a timeout on the third driver re-sent
 * the first two on retry -- and with sends that never threw, there was no
 * retry anyway. One job per recipient makes a retry safe and a failure
 * specific.
 */
class SendUserMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> seconds before the second and third attempts */
    public array $backoff = [30, 120];

    /** A recipient deleted before the worker got to it is not a failure. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  ?string  $kind  what the notice is, for the notification timeline (TASK-498)
     * @param  ?PilotCarJob  $about  the job it concerns
     */
    public function __construct(
        public User $user,
        public string $message,
        public ?string $subject = null,
        public ?string $kind = null,
        public ?PilotCarJob $about = null,
    ) {
        // Never before the row that caused it is committed.
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        $context = ['kind' => $this->kind, 'user' => $this->user, 'related' => $this->about];

        try {
            DeliveryContext::run($context, fn () => SendUserNotification::deliver($this->user, $this->message, $this->subject));
        } catch (Throwable $e) {
            if ($this->shouldRetry()) {
                Log::warning('SendUserMessage: send failed; the queue will retry it', [
                    'user_id' => $this->user->id,
                    'subject' => $this->subject,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]);

                throw $e;
            }

            DeliveryContext::run($context, fn () => SendUserNotification::recordUndeliverable($this->user, $this->message, $this->subject, $e));
        }
    }

    /**
     * Only a real queue can retry; the sync driver would surface the throw to
     * whoever fired the event.
     */
    protected function shouldRetry(): bool
    {
        if (! $this->job || $this->job instanceof SyncJob) {
            return false;
        }

        return $this->attempts() < $this->tries;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendUserMessage: gave up after every attempt', [
            'user_id' => $this->user->id ?? null,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]);
    }
}
