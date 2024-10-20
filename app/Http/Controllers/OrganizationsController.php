<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Jetstream;

class OrganizationsController extends Controller
{

    use AuthorizesRequests;

    public function store(Request $request){
        if($request->has('owner_email')){
            $user = User::where('email', $request->owner_email)->first();
            if(!$user) $user = User::superUser();
            $request->merge(['user_id' => $user->id]);
        }

        Organization::create($request->except('owner_email'));
        return redirect()->route('organizations.index');
    }

    public function update(Request $request, $organization){

        $organization = Organization::find($organization);

        if($organization && $this->authorize('update', $organization)){

            if($request->has('owner_email') && !empty($request->owner_email) && $request->owner_email != $organization->owner->email){
                $user = User::where('email', $request->owner_email)->first();
                if($user){
                    $request->merge(['user_id' => $user->id]);
                }
            }
           $organization->update($request->except('_method','owner_email'));
        }

        return redirect()->route('organizations.index');
    }

    public function delete(Request $request, $organization){

        $organization = Organization::find($organization);

        if($organization && $this->authorize('delete', $organization)){
           $organization->delete();
        }

        return redirect()->route('organizations.index');
    }

    public function createUser(Request $request, $organization){

        $organization = Organization::find($organization);
        
        if($organization && $this->authorize('createUser', $organization)){

            Validator::make($request->except('_method'), [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
                'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
            ])->validate();

          $organization->createUser($request->except('_method'));
        }

        return back();
    }
}
