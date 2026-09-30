<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Profile editor, mounted at /my/profile for everyone and, via
 * <livewire:profile.update-profile-information-form :profile="$user">, by an
 * admin editing someone else.
 *
 * TASK-428: this used to do User::find($state['id'])->update($state), with
 * $state a client-writable array and password / organization_id /
 * organization_role all fillable. Any signed-in user could rewrite any
 * account. Now the target is the mounted $user (part of the signed Livewire
 * snapshot, so the client cannot swap it), every action authorizes against
 * UserPolicy, and only whitelisted fields are ever written.
 */
class UpdateProfileInformationForm extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    /** The editable fields. Nothing else in $state is ever written. */
    private const EDITABLE = ['name', 'email', 'theme', 'notification_address'];

    /**
     * The component's state.
     *
     * @var array
     */
    public array $state = [];

    public array $themes = [];

    /**
     * The new avatar for the user.
     *
     * @var mixed
     */
    public $photo;

    /**
     * Determine if the verification email was sent.
     *
     * @var bool
     */
    public bool $verificationLinkSent = false;

    public User $user;

    public array $roles = [];

    /**
     * Prepare the component.
     *
     * @return void
     */
    public function mount($profile = null)
    {
        $this->user = $profile ?: Auth::user();

        $this->authorize('update', $this->user);

        $this->state = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'theme' => $this->user->theme,
            'notification_address' => $this->user->notification_address,
            'organization_role' => $this->user->organization_role,
        ];

        $this->themes = User::themes();
        $this->roles = User::roles();
    }

    /**
     * Update the user's profile information.
     *
     * @param  \Laravel\Fortify\Contracts\UpdatesUserProfileInformation  $updater
     * @return \Illuminate\Http\RedirectResponse|null
     */
    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $this->authorize('update', $this->user);

        $this->resetErrorBag();

        $input = array_intersect_key($this->state, array_flip(self::EDITABLE));

        if ($this->photo) {
            $input['photo'] = $this->photo;
        }

        // Validates name / email / theme / notification_address / photo and
        // handles re-verification on an email change.
        $updater->update($this->user, $input);

        $this->updateRole();

        if (isset($this->photo)) {
            return redirect()->route('my.profile');
        }

        $this->dispatch('saved');

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * The role is the one field with its own policy. Only an org admin (same
     * organization) or a super user may change it, and only to a known role.
     * Anyone else's submitted value is dropped, not applied.
     */
    private function updateRole(): void
    {
        $role = $this->state['organization_role'] ?? null;

        if ($role === null || $role === $this->user->organization_role) {
            return;
        }

        if (! Auth::user()->can('updateRole', $this->user)) {
            $this->state['organization_role'] = $this->user->organization_role;

            return;
        }

        $this->validate([
            'state.organization_role' => ['required', Rule::in(array_column(User::roles(), 'id'))],
        ]);

        $this->user->forceFill(['organization_role' => $role])->save();
    }

    /**
     * Delete user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
        $this->authorize('update', $this->user);

        $this->user->deleteProfilePhoto();

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Sent the email verification.
     *
     * @return void
     */
    public function sendEmailVerification()
    {
        $this->authorize('update', $this->user);

        $this->user->sendEmailVerificationNotification();

        $this->verificationLinkSent = true;
    }

    /**
     * Get the current user of the application.
     *
     * @return mixed
     */
    public function getUserProperty()
    {
        return $this->user;
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('profile.update-profile-information-form', [
            'themes' => $this->themes,
            'roles' => $this->roles,
            'smsGateways' => config('sms_gateways.providers', []),
        ]);
    }
}
