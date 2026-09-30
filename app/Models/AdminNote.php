<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sticky note shared by every super user, keyed by what it is about.
 * Holds pointers ("credentials are in Bitwarden under X"), never secrets.
 */
class AdminNote extends Model
{
    public const SSH_CREDENTIALS = 'ssh-credentials';

    protected $fillable = ['key', 'body', 'updated_by_user_id'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
