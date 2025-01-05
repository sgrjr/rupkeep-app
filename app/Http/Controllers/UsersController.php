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
}
