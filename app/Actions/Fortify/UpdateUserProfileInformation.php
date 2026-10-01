<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * Only the fields validated here are ever written, and always by
     * forceFill of named keys: $input is client data and must never reach
     * fill() (TASK-428).
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'theme' => ['nullable', 'string', Rule::in(array_column(User::themes(), 'value'))],
            // Either a plain email or a carrier SMS gateway address
            // (2075551234@mms.uscc.net); both are email-shaped.
            'notification_address' => ['nullable', 'string', 'max:255', new \App\Rules\NotificationAddress],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $attributes = [
            'name' => $input['name'],
            'email' => $input['email'],
            'theme' => array_key_exists('theme', $input) ? $input['theme'] : $user->theme,
            'notification_address' => array_key_exists('notification_address', $input)
                ? ($input['notification_address'] ?: null)
                : $user->notification_address,
        ];

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $attributes);
        } else {
            $user->forceFill($attributes)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, mixed>  $attributes  already whitelisted
     */
    protected function updateVerifiedUser(User $user, array $attributes): void
    {
        $user->forceFill($attributes + ['email_verified_at' => null])->save();

        $user->sendEmailVerificationNotification();
    }
}
