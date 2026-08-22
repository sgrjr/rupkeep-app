<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One named, ad-hoc charge against a driver log (TASK-330).
 *
 * These are the descriptive replacement for the log's old single unlabeled
 * `extra_charge` amount: "Equipment rental — $340" rather than an anonymous
 * lump. Each one becomes its own invoice line item.
 */
class LogExtraCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_log_id',
        'organization_id',
        'description',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'float',
        'sort_order' => 'integer',
    ];

    public function userLog(): BelongsTo
    {
        return $this->belongsTo(UserLog::class);
    }
}
