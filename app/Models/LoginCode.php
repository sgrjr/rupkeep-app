<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LoginCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('used_at')
            ->where(function ($inner) {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon
            ? $this->expires_at->isPast()
            : false;
    }

    public function markUsed(): void
    {
        $this->forceFill(['used_at' => now()])->save();
    }

    protected function remainingMinutes(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->expires_at instanceof Carbon) {
                return null;
            }

            return now()->diffInMinutes($this->expires_at, false);
        });
    }
}

