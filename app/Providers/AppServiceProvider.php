<?php

namespace App\Providers;

use App\Events\InvoiceFlagged;
use App\Events\InvoiceReady;
use App\Events\JobAssigned;
use App\Events\JobStatusChanged;
use App\Events\JobWasCanceled;
use App\Events\JobWasUncanceled;
use App\Listeners\NotifyAssignedDriversOfJobCancellation;
use App\Listeners\NotifyAssignedDriversOfJobUncancellation;
use App\Listeners\NotifyDriversOfJobStatusChange;
use App\Listeners\SendInvoiceFlaggedNotification;
use App\Listeners\SendInvoiceReadyNotification;
use App\Listeners\SendJobAssignedNotification;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Observers\InvoiceObserver;
use App\Observers\PilotCarJobObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        $this->forceApplicationUrl();

        Event::listen(JobAssigned::class, SendJobAssignedNotification::class);
        Event::listen(JobWasCanceled::class, NotifyAssignedDriversOfJobCancellation::class);
        Event::listen(JobWasUncanceled::class, NotifyAssignedDriversOfJobUncancellation::class);
        Event::listen(JobStatusChanged::class, NotifyDriversOfJobStatusChange::class);
        Event::listen(InvoiceReady::class, SendInvoiceReadyNotification::class);
        Event::listen(InvoiceFlagged::class, SendInvoiceFlaggedNotification::class);

        // Register model observers for payment status synchronization
        Invoice::observe(InvoiceObserver::class);
        PilotCarJob::observe(PilotCarJobObserver::class);

        $this->suppressMinishlinkGmpBcmathWarning();
    }

    /**
     * Defensive fix for TASK-351.
     *
     * Notification links (e.g. "http://localhost/logs/1023") were being sent in
     * real SMS/email because they are generated inside QUEUED listeners. A queue
     * worker (or any CLI/console context) has no inbound HTTP request to infer
     * the host from, so route()/url() fall back entirely to config('app.url').
     * When the worker's APP_URL is stale or wrong, every generated link is wrong.
     *
     * The proper fix is operational — set APP_URL correctly in the production
     * environment and restart the queue worker. This is belt-and-suspenders: by
     * explicitly rooting the URL generator at config('app.url') at boot, every
     * context (web, queue, scheduler, artisan) generates links against the same
     * configured host, and an https app URL always produces https links.
     */
    protected function forceApplicationUrl(): void
    {
        $appUrl = config('app.url');

        if (! is_string($appUrl) || $appUrl === '') {
            return;
        }

        URL::forceRootUrl($appUrl);

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Defensive fix for TASK-007.
     *
     * The minishlink/web-push library calls trigger_error(..., E_USER_WARNING) at
     * construction time when neither the `gmp` nor `bcmath` PHP extension is loaded.
     * Laravel's HandleExceptions::handleError promotes that to a thrown
     * ErrorException, which can 500 any request path that touches the WebPush
     * notification channel — even a guest landing-page hit on a server missing
     * the extension.
     *
     * The library still works without the extension (it falls back to a slower
     * pure-PHP calculator), so the warning is purely informational. This handler
     * swallows that single specific warning and lets every other warning continue
     * to the previous handler unchanged.
     *
     * Proper fix is to install php-gmp or php-bcmath on the server; this is
     * belt-and-suspenders so a stripped-down deploy doesn't 500.
     */
    protected function suppressMinishlinkGmpBcmathWarning(): void
    {
        // Capture whichever handler Laravel installed during framework bootstrap.
        $previous = set_error_handler(static fn () => null);
        restore_error_handler();

        set_error_handler(static function (int $level, string $message, string $file = '', int $line = 0) use ($previous) {
            if ($level === E_USER_WARNING && str_contains($message, 'GMP or BCMath')) {
                if (function_exists('logger')) {
                    logger()->info('Suppressed minishlink/web-push GMP/BCMath warning', [
                        'message' => $message,
                        'file' => $file,
                        'line' => $line,
                    ]);
                }
                return true;
            }

            return $previous ? ($previous)($level, $message, $file, $line) : false;
        });
    }
}
