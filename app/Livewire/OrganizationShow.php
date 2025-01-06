<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Auth;
use Illuminate\Support\Facades\DB;

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

    public $deleted_users = false;

    public NewUserForm $form;

    public function mount(Int $organization){
        $organization = Organization::with('users','owner')->find($organization);

        if(auth()->user()->can('restoreAny', new User)){
            $this->deleted_users = DB::table('users')->where('organization_id', $organization->id)->whereNotNull('deleted_at')->get();
        }

        if($this->authorize('view', $organization)){
            $this->organization = $organization;
        }

        $this->roles = User::roles();
    }

    public function render()
    {
        return view('livewire.organization-show', [
            'jobs_count' => $this->organization->jobs()->count(),
            'users_count' => $this->organization->users()->count(),
            'customers_count' => $this->organization->customers()->count(),
            'vehicles_count' => $this->organization->vehicles()->count(),
        ]);
    }

    public function createUser(){
        $this->organization->createUser($this->form->all());
        $this->form->reset();
        $this->dispatch('saved');
        return back();
    }

    
    public function deleteJobs(){
        $this->organization->jobs()->withTrashed()->get()->map(function($job){
            $job->logs()->delete(); //logs do not have softdeletes trait
            $job->delete();
        });
        return back();
    }

    public function deleteUsers(){
        $this->organization->users()->withTrashed()->where('organization_role','!=','administrator')->delete();
        return back();
    }

    public function deleteVehicles(){
        $this->organization->vehicles()->withTrashed()->delete();
        return back();
    }

    public function deleteCustomers(){
        $this->organization->customers->map(function($customer){ //customers do not have softdeletes trait
            $customer->contacts()->forceDelete(); //contacts does not have softdeletes trait
            
            $customer->jobs->map(function($job){
                $job->withTrashed()->logs()->forceDelete();
                $job->forceDelete();
            });

            $customer->forceDelete();
        });
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
