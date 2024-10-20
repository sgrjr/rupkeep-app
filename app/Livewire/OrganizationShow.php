<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Auth;

class NewUserForm extends Form
{
    #[Validate('required|string|max:255')]
    public $name = '';
 
    #[Validate('required|string|email|max:255|unique:users')]
    public $email = '';

    #[Validate('required|string|min:6')]
    public $password = '';

    #[Validate('same:password')]
    public $password_confirmation = '';

    #[Validate('string|min:3')]
    public $role = 'administrator';
}

class OrganizationShow extends Component
{
    public $organization;
    public $roles;

    public NewUserForm $form;

    public function mount(Int $organization){
        $organization = Organization::with('users','owner')->find($organization);

        if($this->authorize('view', $organization)){
            $this->organization = $organization;
        }

        $this->roles = User::roles();
    }

    public function render()
    {
        return view('livewire.organization-show');
    }

    public function createUser(){
        $this->organization->createUser($this->form->all());
        $this->form->reset();
        $this->dispatch('saved');
        return back();
    }

    public function impersonate($user_id){
        $impersonator = auth()->user();
        $impersonator->currentAccessToken()->delete();
        $user = User::find($user_id);
        if(auth()->user()->can('impersonate', $user)){
            session()->put('impersonate',$impersonator->id);
            //auth()->login($user);
            auth()->setUser($user);

            
            session()->flash('message','Success. Logged in as ' . $user->name);
            return redirect()->route('dashboard');
        }else{
            session()->flash('message','You are not allowed to do this ' . auth()->user()->name . ' :/');
        }
    }
}
