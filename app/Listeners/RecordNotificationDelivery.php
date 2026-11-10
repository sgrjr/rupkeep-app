<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Notifications\JobUpdate;
use App\Notifications\PasswordChangedByAdmin;
use App\Notifications\TaskUpdate;
use App\Support\DeliveryContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the notification timeline (TASK-498): one row per email, text or
 * push the app hands to a transport.
 *
 * Every email passes through MessageSent, whichever code sent it, so that is
 * where emails and gateway texts are recorded; DeliveryContext supplies what
 * the sender knew (recipient, kind, job or invoice). Laravel notifications are
 * recorded from NotificationSent / NotificationFailed instead, which carry the
 * notifiable and the notification itself; their mail is skipped in
 * MessageSent so it is not counted twice.
 *
 * Recording must never break a send: any failure here is logged and dropped.
 */
class RecordNotificationDelivery
{
    public function handleMessageSent(MessageSent $event): void
    {
        $this->safely(function () use ($event) {
            // Sent by the notification system: recorded from NotificationSent.
            if (isset($event->data['__laravel_notification'])) {
                return;
            }

            $message = $event->message;
            $context = DeliveryContext::current();
            $recipients = array_map(fn ($address) => strtolower($address->getAddress()), $message->getTo());

            foreach ($recipients as $address) {
                $user = $context['user'] ?? $this->userForAddress($address);
                $kind = $context['kind'] ?? null;

                $this->record([
                    'user' => $user,
                    'related' => $context['related'] ?? null,
                    'recipient' => $address,
                    'channel' => $user && $user->usesSmsGateway() && strtolower((string) $user->getSmsGatewayAddress()) === $address
                        ? NotificationDelivery::CHANNEL_SMS
                        : NotificationDelivery::CHANNEL_EMAIL,
                    'kind' => $kind,
                    'subject' => $message->getSubject(),
                    'excerpt' => $this->excerpt($kind, $message->getTextBody() ?? strip_tags((string) $message->getHtmlBody())),
                    // The log mailer is the last resort after every real
                    // attempt failed: the person was not reached.
                    'status' => ($event->data['mailer'] ?? null) === 'log'
                        ? NotificationDelivery::STATUS_NOT_DELIVERED
                        : NotificationDelivery::STATUS_SENT,
                ]);
            }
        });
    }

    public function handleNotificationSent(NotificationSent $event): void
    {
        $this->safely(fn () => $this->recordNotification($event->notifiable, $event->notification, $event->channel, NotificationDelivery::STATUS_SENT));
    }

    public function handleNotificationFailed(NotificationFailed $event): void
    {
        $this->safely(fn () => $this->recordNotification($event->notifiable, $event->notification, $event->channel, NotificationDelivery::STATUS_NOT_DELIVERED));
    }

    private function recordNotification($notifiable, $notification, $channel, string $status): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $isPush = is_string($channel) && str_contains(strtolower($channel), 'webpush');

        // The push channel reports success for someone with no subscribed
        // device, having sent nothing. Recording that as "sent" would claim a
        // notice reached a phone it never reached.
        if ($isPush && ! $notifiable->pushSubscriptions()->exists()) {
            return;
        }

        [$kind, $related, $subject] = match (true) {
            $notification instanceof JobUpdate => ['job_update', $notification->job(), $notification->title()],
            $notification instanceof TaskUpdate => ['task_update', null, null],
            $notification instanceof PasswordChangedByAdmin => ['password_changed', null, null],
            default => [null, null, null],
        };

        $this->record([
            'user' => $notifiable,
            'related' => $related,
            'recipient' => $isPush ? null : $notifiable->email,
            'channel' => $isPush ? NotificationDelivery::CHANNEL_PUSH : NotificationDelivery::CHANNEL_EMAIL,
            'kind' => $kind,
            'subject' => $subject ?? Str::headline(class_basename($notification)),
            'excerpt' => null,
            'status' => $status,
        ]);
    }

    private function record(array $row): void
    {
        /** @var ?User $user */
        $user = $row['user'];
        /** @var ?Model $related */
        $related = $row['related'];

        $organizationId = $user?->organization_id ?? ($related?->organization_id ?? null);

        NotificationDelivery::create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'recipient' => $row['recipient'] ? Str::limit($row['recipient'], 250, '') : null,
            'channel' => $row['channel'],
            'kind' => $row['kind'],
            'subject' => $row['subject'] ? Str::limit($row['subject'], 250) : null,
            'excerpt' => $row['excerpt'],
            'status' => $row['status'],
            'related_type' => $related ? $related->getMorphClass() : null,
            'related_id' => $related?->getKey(),
        ]);
    }

    /** Text is kept only for kinds that carry no secret, and kept short. */
    private function excerpt(?string $kind, ?string $body): ?string
    {
        if ($kind === null || ! in_array($kind, NotificationDelivery::EXCERPT_KINDS, true) || $body === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $body));

        return $text === '' ? null : Str::limit($text, 500);
    }

    private function userForAddress(string $address): ?User
    {
        return User::where('email', $address)->first()
            ?? User::where('notification_address', $address)->first();
    }

    private function safely(callable $record): void
    {
        try {
            $record();
        } catch (Throwable $e) {
            Log::warning('RecordNotificationDelivery: could not record a delivery', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
