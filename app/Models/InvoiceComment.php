<?php

namespace App\Models;

use App\Events\InvoiceFlagged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'body',
        'is_flagged',
        'flagged_at',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'flagged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $comment): void {
            if ($comment->is_flagged) {
                event(new InvoiceFlagged($comment));
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flag(): void
    {
        $this->forceFill([
            'is_flagged' => true,
            'flagged_at' => now(),
        ])->save();

        if ($fresh = $this->fresh()) {
            event(new InvoiceFlagged($fresh));
        }
    }

    public function unflag(): void
    {
        $this->forceFill([
            'is_flagged' => false,
            'flagged_at' => null,
        ])->save();
    }
}

