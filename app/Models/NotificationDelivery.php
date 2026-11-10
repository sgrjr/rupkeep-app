<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * A text, email or push the app handed to a transport (TASK-498).
 *
 * Status is what the app knows, not what the phone did: "sent" means the
 * provider accepted it; "not_delivered" means every attempt failed and it was
 * written to the log instead. Whether a person read a text or an email cannot
 * be measured, so the timeline shows what they did next instead (confirmed the
 * job, for example).
 */
class NotificationDelivery extends Model
{
    use Prunable;

    public const STATUS_SENT = 'sent';

    public const STATUS_NOT_DELIVERED = 'not_delivered';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_PUSH = 'push';

    public const KIND_LABELS = [
        'job_assigned' => 'Job assigned',
        'job_unassigned' => 'Job reassigned',
        'job_canceled' => 'Job canceled',
        'job_uncanceled' => 'Job back on',
        'job_status' => 'Job update',
        'job_update' => 'Job update',
        'log_completed' => 'Log completed',
        'invoice_ready' => 'Invoice ready',
        'invoice_flagged' => 'Invoice flagged',
        'invoice_email' => 'Invoice emailed',
        'customer_welcome' => 'Portal welcome',
        'login_link' => 'Sign-in link',
        'task_update' => 'Request update',
        'password_changed' => 'Password changed',
        'direct_message' => 'Message',
    ];

    /**
     * Kinds whose text may be kept. Anything else (a sign-in link, a password
     * notice, an unclassified email) records only that it was sent: a stored
     * sign-in link would let whoever reads the timeline sign in as the
     * recipient.
     */
    public const EXCERPT_KINDS = [
        'job_assigned', 'job_unassigned', 'job_canceled', 'job_uncanceled', 'job_status', 'job_update',
        'log_completed', 'invoice_ready', 'invoice_flagged', 'invoice_email', 'direct_message',
    ];

    protected $fillable = [
        'organization_id', 'user_id', 'recipient', 'channel', 'kind', 'subject',
        'excerpt', 'status', 'related_type', 'related_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function kindLabel(): string
    {
        return __(self::KIND_LABELS[$this->kind] ?? 'Message');
    }

    /** A year and a half is enough to answer "were they told?". */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(18));
    }
}
