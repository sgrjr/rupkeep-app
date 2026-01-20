<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        $authenticatedUser = auth()->user();

        // Determine if this is a self-update or admin-initiated update
        $isSelfUpdate = $authenticatedUser->id === $user->id;

        // Build validation rules based on context
        $rules = [
            'password' => $this->passwordRules(),
        ];

        $messages = [];

        // Only require current password for self-updates
        if ($isSelfUpdate) {
            $rules['current_password'] = ['required', 'string', 'current_password:web'];
            $messages['current_password.current_password'] = __('The provided password does not match your current password.');
        }

        Validator::make($input, $rules, $messages)->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // Log admin password changes and notify user
        if (!$isSelfUpdate) {
            // Create audit log entry in user_events table
            \App\Models\UserEvent::create([
                'user_id' => $authenticatedUser->id,
                'url' => request()->fullUrl(),
                'type' => \App\Models\UserEvent::TYPE_ACTION,
                'severity' => \App\Models\UserEvent::SEVERITY_INFO,
                'context' => [
                    'action' => 'password_changed_by_admin',
                    'admin_id' => $authenticatedUser->id,
                    'admin_name' => $authenticatedUser->name,
                    'admin_email' => $authenticatedUser->email,
                    'target_user_id' => $user->id,
                    'target_user_name' => $user->name,
                    'target_user_email' => $user->email,
                    'organization_id' => $authenticatedUser->organization_id,
                ],
                'ip' => request()->ip(),
            ]);

            // Notify user their password was changed
            $user->notify(new \App\Notifications\PasswordChangedByAdmin($authenticatedUser));
        }
    }
}
