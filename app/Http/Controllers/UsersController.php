<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UsersController extends Controller
{

    use AuthorizesRequests;

    public function restore(string $id)
    {
        $user = User::withTrashed()->find($id);

        if(auth()->user()->can('restore', $user)){
            $user->restore();
        }

        return back();
        
    }

    public function delete(string $id)
    {
        $user = User::withTrashed()->find($id);

        if(auth()->user()->can('forceDelete', $user)){
            $user->forceDelete();
        }

        return back();
        
    }


public function subscribeToNotifications(Request $request)
{
    /*
    test: 
    $user = User::find(1);
    $user->notify(new \App\Notifications\JobUpdate());

    */
    $this->validate($request, [
        'endpoint' => 'required',
        'keys.auth' => 'required',
        'keys.p256dh' => 'required'
    ]);

    $endpoint = $request->endpoint;
    $key = $request->keys['p256dh'];
    $token = $request->keys['auth'];

    // The trait 'HasPushSubscriptions' provides the updatePushSubscription method
    $request->user()->updatePushSubscription($endpoint, $key, $token);

    return response()->json(['success' => true]);
}
}
