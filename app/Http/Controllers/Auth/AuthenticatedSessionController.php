<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;

// This controller extends Fortify's AuthenticatedSessionController.
// By doing so, you can override its methods.
class AuthenticatedSessionController extends \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController
{
    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function destroy(Request $request): LogoutResponse
    {
        // Call the parent's destroy method first to perform the actual logout
        // This ensures Fortify's core logout logic (e.g., event dispatching) runs.
        $response = parent::destroy($request);

        // --- ADD YOUR CUSTOM LOGIC HERE ---
        Session::forget('navigation_history'); // Clear the navigation history from the session

        return $response; // Return the response from the parent method
    }

    /**
     * This is a standard property for Fortify,
     * specifying where to redirect after logout.
     * You can adjust this if you need a different default.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;
}