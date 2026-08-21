<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;

class LoginCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'link_token',
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

    /**
     * How long is left, in words: "2 hours", "45 minutes".
     *
     * Rounds UP rather than using diffForHumans() directly, which truncates:
     * a code minted two hours ago-minus-a-second reads as "1 hour", which
     * understates the window every single time and reads as a bug to the user.
     */
    public function expiresInWords(): ?string
    {
        if (!$this->expires_at instanceof Carbon) {
            return null;
        }

        $minutes = (int) ceil(now()->diffInMinutes($this->expires_at, false));

        if ($minutes <= 0) {
            return null;
        }

        return CarbonInterval::minutes($minutes)->cascade()->forHumans(['parts' => 1]);
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

