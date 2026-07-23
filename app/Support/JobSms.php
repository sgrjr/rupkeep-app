<?php

namespace App\Support;

use App\Models\PilotCarJob;
use Illuminate\Support\Carbon;

/**
 * The driver-facing SMS bodies for pilot-car job notifications (TASK-352 /
 * TASK-312). Every string produced here is composed through {@see SmsMessage}
 * so it is guaranteed to fit in a single 160-character SMS with the action URL
 * left intact.
 *
 * These are deliberately terse and ASCII-only (GSM-7) so carriers deliver them
 * as a plain text message rather than converting the overflow into an
 * unreadable attachment.
 */
class JobSms
{
    /**
     * "New assignment" text sent when a driver is put on a job.
     */
    public static function assigned(PilotCarJob $job, string $actionUrl, bool $needsConfirmation): string
    {
        return SmsMessage::make()
            ->fixed('Job ')
            ->flexible(self::jobRef($job))
            ->fixed(' assigned. Pickup: ')
            ->flexible($job->pickup_address, 'TBD')
            ->fixed(self::when($job))
            ->fixed($needsConfirmation ? '. Open & Confirm: ' : '. Details: ')
            ->url($actionUrl)
            ->build();
    }

    /**
     * Sent when a job the driver is on is canceled.
     */
    public static function canceled(PilotCarJob $job, string $actionUrl, ?string $reason = null): string
    {
        return SmsMessage::make()
            ->fixed('Job ')
            ->flexible(self::jobRef($job))
            ->fixed(' CANCELED')
            ->fixed($reason ? ': ' : '. ')
            ->flexible($reason)
            ->fixed($reason ? '. Do not proceed. Details: ' : 'Do not proceed. Details: ')
            ->url($actionUrl)
            ->build();
    }

    /**
     * Sent when a previously canceled job is reactivated.
     */
    public static function reactivated(PilotCarJob $job, string $actionUrl): string
    {
        return SmsMessage::make()
            ->fixed('Job ')
            ->flexible(self::jobRef($job))
            ->fixed(' REACTIVATED')
            ->fixed(self::when($job))
            ->fixed('. Proceed as scheduled. Details: ')
            ->url($actionUrl)
            ->build();
    }

    /**
     * Generic status-transition notice (e.g. active -> completed).
     */
    public static function statusChanged(PilotCarJob $job, string $toStatus, string $actionUrl): string
    {
        return SmsMessage::make()
            ->fixed('Job ')
            ->flexible(self::jobRef($job))
            ->fixed(' is now '.self::statusPhrase($toStatus).'. Details: ')
            ->url($actionUrl)
            ->build();
    }

    /**
     * Status update aimed at a customer contact (truck driver). These recipients
     * have no app link, so no URL is included.
     */
    public static function customerStatus(PilotCarJob $job, string $statusLabel): string
    {
        return SmsMessage::make()
            ->fixed('Job ')
            ->flexible(self::jobRef($job))
            ->fixed(' status: ')
            ->flexible($statusLabel, 'updated')
            ->fixed('. Pickup: ')
            ->flexible($job->pickup_address, 'TBD')
            ->fixed(self::when($job))
            ->fixed('. Contact your dispatcher.')
            ->build();
    }

    /**
     * A short human phrase for a derived job status.
     */
    public static function statusPhrase(string $status): string
    {
        return match ($status) {
            'ACTIVE' => 'active',
            'COMPLETED' => 'completed & invoiced',
            'CANCELLED' => 'cancelled (show/no-go)',
            'CANCELLED_NO_GO' => 'cancelled (no-go)',
            default => 'updated',
        };
    }

    private static function jobRef(PilotCarJob $job): string
    {
        return $job->job_no ?: ('#'.$job->id);
    }

    /**
     * A compact " @<date/time>" fragment, or an empty string when the job has no
     * scheduled pickup. Kept as fixed text because its length is bounded.
     */
    private static function when(PilotCarJob $job): string
    {
        if (! $job->scheduled_pickup_at) {
            return '';
        }

        return ' @'.Carbon::parse($job->scheduled_pickup_at)->format('n/j g:i A');
    }
}
