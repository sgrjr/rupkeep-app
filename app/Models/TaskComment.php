<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use HasFactory;

    public const EVENT_COMMENT = 'comment';
    public const EVENT_STATUS_CHANGE = 'status_change';
    public const EVENT_ASSIGNEE_CHANGE = 'assignee_change';
    public const EVENT_LABEL_ADDED = 'label_added';
    public const EVENT_LABEL_REMOVED = 'label_removed';
    public const EVENT_PUBLIC_TOGGLE = 'is_public_toggle';
    public const EVENT_PROMOTED = 'promoted';

    protected $fillable = [
        'task_id',
        'user_id',
        'body',
        'is_internal',
        'sent_to_customer',
        'event_type',
        'meta',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'sent_to_customer' => 'boolean',
        'meta' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSystem(): bool
    {
        return $this->event_type !== self::EVENT_COMMENT;
    }
}
