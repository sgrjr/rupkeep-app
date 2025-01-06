<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyUsersController extends Controller
{

    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        $users = User::where('organization_id', auth()->user()->organization_id)->get();

        if(auth()->user()->is_super){
            $all_users = User::all();
        }else{
            $all_users = false;

        }
        return view('users.index', compact('users','all_users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = (new User([
            'organization_id' => auth()->user()->organization_id,
            'organization_role' => 'editor',
            'theme'=> 'default-theme',
            'name'=>'',
            'password'=>'',
            'email'=>''
        ]))->toArray();

        $user['password'] = '';
        $user['email'] = '';
        $user = (Object)$user;
        $themes = User::themes();
        $roles = User::roles();

        return view('users.create', compact('user', 'themes','roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$request->has('organization_id')){
            $request->merge([
                'organization_id' => auth()->user()->organization_id
            ]);
        }
        $user = new User($request->except('_method'));

        $this->authorize('create', $user);
        $user->save();
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //christina@cascobaypilotcar.com
        $user = User::find($id);

        if(auth()->user()->can('delete', $user)){
            $user->delete();
        }

        return back();
        
    }

    public function delete(string $id)
    {
        $user = User::find($id);

        if(auth()->user()->can('forceDelete', $user)){
            $user->forceDelete();
        }

        return back();
        
    }

    public function restore(string $id)
    {
        $user = User::find($id);

        if(auth()->user()->can('restore', $user)){
            $user->restore();
        }

        return back();
        
    }

    public function impersonate(Request $request, string $id)
    {
        $impersonator = auth()->user();
        $user = User::find($id);
        if(auth()->user()->can('impersonate', $user)){
            auth()->guard()->logoutCurrentDevice();
            session()->flush();
            auth()->guard()->login($user);
            session()->flash('message','Success. Logged in as ' . $user->name);
            if($impersonator) session('impersonate', $impersonator->id);
            return redirect()->route('my.profile',);
        }
        session()->flash('message','You cannot impersonate ' . $user->name.'.');
        return redirect('/');
    }
}
