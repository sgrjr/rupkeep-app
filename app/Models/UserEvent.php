<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEvent extends Model
{
    protected $fillable = [
        'user_id',
        'url',
        'type',
        'severity',
        'context',
        'ip',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';

    public const TYPE_ERROR = 'error';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';
    public const TYPE_ACTION = 'action';
    public const TYPE_FEEDBACK = 'feedback';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeErrors($query)
    {
        return $query->where('severity', self::SEVERITY_ERROR);
    }

    public function scopeWarnings($query)
    {
        return $query->where('severity', self::SEVERITY_WARNING);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
