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

        if(auth()->user()->isSuper()){
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
            'organization_role' => User::ROLE_EMPLOYEE_MANAGER,
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

        // A stale or hand-typed id used to reach the failure branch below and
        // read ->name off null, which is a 500 for a signed-in admin (TASK-373).
        if (! $user) {
            abort(404);
        }

        if(auth()->user()->can('impersonate', $user)){
            $this->becomeUser($user);

            session()->flash('message','Success. Logged in as ' . $user->name);
            // put(), not session($key, $default) -- the two-argument helper is
            // the GETTER, so this trail was silently never recorded and the
            // impersonation banner in the nav menu never appeared.
            if($impersonator) session()->put('impersonate', $impersonator->id);
            return redirect()->route('my.profile',);
        }
        session()->flash('message','You cannot impersonate ' . $user->name.'.');
        return redirect('/');
    }

    /**
     * Hand an impersonator back their own account.
     *
     * Until now there was no way out at all: impersonating replaced your
     * session with somebody else's and the only exit was to log out and back
     * in. The banner could not offer one either, because the trail it reads was
     * never written (see impersonate() above).
     *
     * Authorization is the session itself. `impersonate` is only ever written
     * by impersonate() after a policy check, so holding it IS the proof that
     * this person legitimately became someone else and may become themselves
     * again. Nothing here grants access that was not already granted.
     */
    public function stopImpersonating(Request $request)
    {
        $impersonatorId = session('impersonate');

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonator = User::find($impersonatorId);

        // Deleted or suspended mid-impersonation. There is no account to go
        // back to, so end the session rather than leave them wearing somebody
        // else's for lack of anywhere to put them.
        if (! $impersonator) {
            auth()->guard('web')->logoutCurrentDevice();
            session()->flush();

            return redirect()->route('login');
        }

        $this->becomeUser($impersonator);

        session()->flash('message', 'Welcome back, ' . $impersonator->name . '.');

        return redirect()->route('dashboard');
    }

    /**
     * Swap the signed-in user for the rest of this request and every request
     * after it.
     *
     * Both halves of impersonation go through here because the sequence is
     * unobvious enough to have broken twice (TASK-373), and a second hand-rolled
     * copy in stopImpersonating() would have broken the same two ways:
     *
     * 1. Name the SESSION guard. Inside the auth:sanctum group that middleware
     *    calls Auth::shouldUse('sanctum'), so auth()->guard() hands back a
     *    RequestGuard -- which has no logoutCurrentDevice() and no session to
     *    log into. Those live on SessionGuard.
     *
     * 2. Then tell the DEFAULT guard as well, or the rest of the request still
     *    believes the previous user is signed in: Sanctum's RequestGuard
     *    memoised them when auth:sanctum ran, and $request->user() reads
     *    through it. Jetstream's AuthenticateSession makes that fatal rather
     *    than untidy -- it re-stores the signed-in password hash from
     *    $request->user() AFTER the response, so the session would be logged in
     *    as one user while carrying another's hash, and the next request
     *    discards it and redirects to /login.
     *
     * session()->flush() wipes everything including the `impersonate` trail, so
     * callers that need it must read it before calling and write it after.
     */
    private function becomeUser(User $user): void
    {
        $session = auth()->guard('web');

        $session->logoutCurrentDevice();
        session()->flush();
        $session->login($user);

        auth()->guard()->setUser($user);
    }
}
