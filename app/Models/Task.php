<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['bug', 'feature', 'chore', 'debt', 'verify'];
    public const PRIORITIES = ['blocker', 'high', 'medium', 'low'];
    public const STATUSES = ['triage', 'open', 'in_progress', 'verifying', 'done', 'declined'];

    protected $fillable = [
        'code',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'is_public',
        'organization_id',
        'customer_id',
        'submitter_user_id',
        'assignee_user_id',
        'promoted_from_user_event_id',
        'position',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'position' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function promotedFromUserEvent(): BelongsTo
    {
        return $this->belongsTo(UserEvent::class, 'promoted_from_user_event_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_label')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /**
     * Generate the next TASK-### code for new rows.
     */
    public static function nextCode(): string
    {
        $latest = static::query()
            ->where('code', 'like', 'TASK-%')
            ->orderByRaw('CAST(SUBSTR(code, 6) AS INTEGER) DESC')
            ->value('code');

        $n = $latest ? ((int) substr($latest, 5)) + 1 : 1;

        return 'TASK-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Record a system event in the comments timeline (no body).
     */
    public function recordEvent(string $eventType, ?int $userId, array $meta = [], ?string $body = null): TaskComment
    {
        return $this->comments()->create([
            'user_id' => $userId,
            'body' => $body ?? '',
            'event_type' => $eventType,
            'meta' => $meta ?: null,
            'is_internal' => false,
        ]);
    }
}
