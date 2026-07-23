<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    /**
     * Send the user to their role-aware landing page after login.
     *
     * Managers land on the Jobs index; every other role keeps Fortify's
     * default destination (config('fortify.home'), i.e. /dashboard). A
     * genuine intended URL — e.g. a deep link the user was bounced from to
     * sign in — still wins for all roles via redirect()->intended(). This
     * only changes the *default* landing, so managers can still visit
     * /dashboard directly.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user && $user->isManager()) {
            return redirect()->intended(route('my.jobs.index'));
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
