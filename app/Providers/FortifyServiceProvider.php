<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Role-aware post-login landing: managers go to the Jobs index, every
        // other role keeps Fortify's default destination. Bound in boot() so it
        // overrides Fortify's own singleton binding (registered in register()).
        $this->app->singleton(LoginResponseContract::class, \App\Http\Responses\LoginResponse::class);

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Passwordless sign-in (TASK-319). Requesting a link sends real email
        // to a real inbox, so the request limit is the tight one — three per
        // hour per email, as the customer specified. The two redemption
        // endpoints are throttled per-IP instead, since they are guessable
        // secrets on unauthenticated routes rather than a send trigger.
        RateLimiter::for('login-code', function (Request $request) {
            // Keyed on the address alone (TASK-441): keyed on email+IP, a
            // caller rotating addresses could mail-bomb one inbox, and each
            // request retires the victim's live code.
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')));

            return Limit::perHour(3)->by($throttleKey);
        });

        RateLimiter::for('login-code-verify', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('login-link', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // The /setup console password (TASK-424). Its username is well known,
        // so the shared secret is the only thing left to guess.
        RateLimiter::for('setup-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
