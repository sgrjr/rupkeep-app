<?php

namespace App\Providers;

use App\Events\InvoiceFlagged;
use App\Events\InvoiceReady;
use App\Events\JobAssigned;
use App\Listeners\SendInvoiceFlaggedNotification;
use App\Listeners\SendInvoiceReadyNotification;
use App\Listeners\SendJobAssignedNotification;
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
        Event::listen(InvoiceReady::class, SendInvoiceReadyNotification::class);
        Event::listen(InvoiceFlagged::class, SendInvoiceFlaggedNotification::class);
    }
}
