<?php

namespace App\Providers;

use App\Events\InvoiceFlagged;
use App\Events\InvoiceReady;
use App\Events\JobAssigned;
use App\Events\JobWasCanceled;
use App\Events\JobWasUncanceled;
use App\Listeners\NotifyAssignedDriversOfJobCancellation;
use App\Listeners\NotifyAssignedDriversOfJobUncancellation;
use App\Listeners\SendInvoiceFlaggedNotification;
use App\Listeners\SendInvoiceReadyNotification;
use App\Listeners\SendJobAssignedNotification;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Observers\InvoiceObserver;
use App\Observers\PilotCarJobObserver;
use Illuminate\Support\Facades\Event;
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
        Event::listen(JobAssigned::class, SendJobAssignedNotification::class);
        Event::listen(JobWasCanceled::class, NotifyAssignedDriversOfJobCancellation::class);
        Event::listen(JobWasUncanceled::class, NotifyAssignedDriversOfJobUncancellation::class);
        Event::listen(InvoiceReady::class, SendInvoiceReadyNotification::class);
        Event::listen(InvoiceFlagged::class, SendInvoiceFlaggedNotification::class);

        // Register model observers for payment status synchronization
        Invoice::observe(InvoiceObserver::class);
        PilotCarJob::observe(PilotCarJobObserver::class);
    }
}
