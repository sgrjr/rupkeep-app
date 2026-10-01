<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * Jetstream's account-deletion hook. The feature is off
 * (config/jetstream.php), and Teams never existed in this app, so the
 * team teardown that used to live here is gone (TASK-474).
 */
class DeleteUser implements DeletesUsers
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }
}
