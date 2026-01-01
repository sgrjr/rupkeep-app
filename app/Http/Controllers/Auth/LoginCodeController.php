<?php

namespace App\Http\Controllers\Auth;

use App\Actions\SendUserNotification;
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

    public function create(Request $request)
    {
        if ($redirect = $request->query('redirect')) {
            $request->session()->put('customer_portal.redirect', $redirect);
        }

        return view('auth.login-code-request', [
            'redirect' => $request->query('redirect', $request->session()->get('customer_portal.redirect')),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'redirect' => ['nullable', 'string'],
        ]);

        if (! empty($data['redirect'])) {
            $request->session()->put('customer_portal.redirect', $data['redirect']);
        }

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

        // Send login code via email
        $message = sprintf(
            "Hello %s,\n\nYour login code for Rupkeep is: %s\n\nThis code will expire in 24 hours. If you did not request this code, please ignore this email.",
            $user->name,
            $code->code
        );

        $subject = __('Your Rupkeep Login Code');

        SendUserNotification::to($user, $message, $subject);

        return back()->with('status', __('We sent a login code to :email', ['email' => $user->email]))
            ->with('code_preview', app()->environment('local') ? $code->code : null);
    }

    public function verifyForm(Request $request)
    {
        if ($redirect = $request->query('redirect')) {
            $request->session()->put('customer_portal.redirect', $redirect);
        }

        return view('auth.login-code-verify', [
            'redirect' => $request->query('redirect', $request->session()->get('customer_portal.redirect')),
        ]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:64'],
            'redirect' => ['nullable', 'string'],
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

        $redirect = $data['redirect'] ?? $request->session()->pull('customer_portal.redirect');

        if ($redirect) {
            return redirect()->to($redirect);
        }

        return redirect()->intended(route('customer.invoices.index'));
    }
}

