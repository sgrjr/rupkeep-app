<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Passwordless sign-in.
 *
 * One request form (`/login-code`) issues one row that can be redeemed two
 * ways: click the link in the email, or type the code into the verify form.
 * Open to every role — staff, manager, admin and customer alike (TASK-319);
 * it used to be customer-only and code-only.
 */
class LoginCodeController extends Controller
{
    use SendsNotificationMail;

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

        $code = $user ? $this->issueAndSend($request, $user) : null;

        // Deliberately identical whether or not the account exists. This is an
        // unauthenticated public endpoint, and now that every role can request
        // a link, a "no such account" error would turn it into a staff-account
        // enumeration oracle.
        return back()
            ->with('status', __('If an account exists for that email, we just sent a sign-in link.'))
            ->with('code_preview', $code && app()->environment('local') ? $code->code : null);
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

        return $this->signIn($request, $user, $data['redirect'] ?? null);
    }

    /**
     * Show the sign-in link confirmation page.
     *
     * Deliberately does NOT spend the token. Mail scanners (Outlook Safe
     * Links and friends) follow every URL in an email, and a GET that logged
     * the user straight in would let a scanner burn a single-use token before
     * the human ever clicked - the standard magic-link failure mode. The token
     * is only spent by the POST below, which needs a real form submission and
     * a valid CSRF token.
     */
    public function link(Request $request, string $token)
    {
        $loginCode = $this->service->findLiveLinkToken($token);

        if (!$loginCode) {
            return $this->deadLink();
        }

        return view('auth.login-link-confirm', [
            'token' => $token,
            'user' => $loginCode->user,
            'expirySentence' => $this->expirySentence($loginCode),
        ]);
    }

    /**
     * Spend the token and sign the user in. Reached only from the button on
     * the confirmation page above.
     */
    public function confirm(Request $request, string $token)
    {
        $user = $this->service->consumeLinkToken($token);

        if (!$user) {
            return $this->deadLink();
        }

        return $this->signIn($request, $user);
    }

    /**
     * A spent or stale link would otherwise be a dead end - send them back to
     * the request form with a way out rather than an error page.
     *
     * Flashed as a warning, not a status: this is a soft failure, and the
     * green success styling would read as "it worked".
     */
    protected function deadLink()
    {
        return redirect()->route('login-code.create')->with(
            'warning',
            __('That sign-in link has already been used or has expired. Enter your email below and we will send a new one.')
        );
    }

    /**
     * Issue a code for the user and email them both ways to redeem it.
     *
     * Sent to $user->email directly rather than through SendUserNotification,
     * which prefers a configured SMS-gateway address — a sign-in URL pushed
     * through a carrier gateway arrives truncated.
     */
    protected function issueAndSend(Request $request, User $user): LoginCode
    {
        $code = $this->service->generate($user, meta: [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $url = route('login-link', $code->link_token);
        $subject = __('Your Rupkeep sign-in link');

        $plain = sprintf(
            "Hello %s,\n\nSign in to Rupkeep with this link:\n%s\n\nOr enter this code at %s:\n%s\n\n%s\n\nIf you did not request this, you can ignore this email.",
            $user->name,
            $url,
            route('login-code.verify-form'),
            $code->code,
            $this->expirySentence($code)
        );

        $this->mailSafely($user->email, new UserNotification(
            $plain,
            $subject,
            'mail.login-link',
            [
                'user' => $user,
                'code' => $code->code,
                'url' => $url,
                'verifyUrl' => route('login-code.verify-form'),
                'expirySentence' => $this->expirySentence($code),
            ],
            $user->organization?->name
        ));

        return $code;
    }

    /**
     * Authenticate and send the user to their landing page.
     */
    protected function signIn(Request $request, User $user, ?string $redirect = null)
    {
        Auth::login($user, remember: false);

        $request->session()->regenerate();

        $redirect = $redirect ?? $request->session()->pull('customer_portal.redirect');

        if ($redirect) {
            return redirect()->to($redirect);
        }

        if ($user->isCustomer()) {
            return redirect()->intended(route('customer.invoices.index'));
        }

        // Staff inherit the same role-aware landing that password login uses
        // (managers to the Jobs index, everyone else to the dashboard).
        return app(LoginResponseContract::class)->toResponse($request);
    }

    protected function expirySentence(LoginCode $code): string
    {
        $words = $code->expiresInWords();

        return $words
            ? __('This link expires in :time.', ['time' => $words])
            : __('This link does not expire.');
    }
}
