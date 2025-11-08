<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginCodeController extends Controller
{
    public function __construct(
        protected LoginCodeService $service
    ) {
    }

    public function create()
    {
        return view('auth.login-code-request');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !$user->isCustomer()) {
            throw ValidationException::withMessages([
                'email' => __('We were unable to find a customer account with that email.'),
            ]);
        }

        if (!$user->customer_id) {
            throw ValidationException::withMessages([
                'email' => __('This account is not connected to a customer record yet.'),
            ]);
        }

        $code = $this->service->generate($user, meta: [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // TODO: Send notification via email

        return back()->with('status', __('We sent a login code to :email', ['email' => $user->email]))
            ->with('code_preview', app()->environment('local') ? $code->code : null);
    }

    public function verifyForm()
    {
        return view('auth.login-code-verify');
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:64'],
        ]);

        $user = $this->service->consume($data['code']);

        if (!$user) {
            throw ValidationException::withMessages([
                'code' => __('That login code is invalid or expired.'),
            ]);
        }

        if (!$user->isCustomer() && !$user->isAdmin()) {
            throw ValidationException::withMessages([
                'code' => __('This account is not allowed to sign in with a code.'),
            ]);
        }

        Auth::login($user, remember: false);

        $request->session()->regenerate();

        return redirect()->intended(route('customer.invoices.index'));
    }
}

