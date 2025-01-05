<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\User;

class UpdateProfileInformationForm extends Component
{
    use WithFileUploads;

    /**
     * The component's state.
     *
     * @var array
     */
    public $state = [];
    public $themes = [];

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
    public $verificationLinkSent = false;

    public $user;
    public $roles;

    /**
     * Prepare the component.
     *
     * @return void
     */
    public function mount($profile = null)
    {
        if($profile){
            $this->user = $profile;
        }else{
            $this->user = Auth::user();
        }
        
        $this->state = array_merge([
            'email' => $this->user->email,
        ], $this->user->withoutRelations()->toArray());

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

        $this->resetErrorBag();
        $user = User::find($this->state['id']);
        
        //I commented this out because it wont save changes to organization_role
        /*$updater->update(
            $user,
            $this->photo
                ? array_merge($this->state, ['photo' => $this->photo])
                : $this->state
        );*/

        $user->update(
            $this->photo
                ? array_merge($this->state, ['photo' => $this->photo])
                : $this->state
        );

        if (isset($this->photo)) {
            return redirect()->route('my.profile');
        }

        $this->dispatch('saved');

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Delete user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
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
        return view('profile.update-profile-information-form');
    }
}
