<?php

namespace Tests\Feature\Notifications;

use App\Events\InvoiceFlagged;
use App\Events\InvoiceReady;
use App\Events\JobAssigned;
use App\Events\JobStatusChanged;
use App\Events\JobUnassigned;
use App\Events\JobWasCanceled;
use App\Events\JobWasUncanceled;
use App\Events\LogCompleted;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * TASK-459. AppServiceProvider registers every notification listener by
 * hand, and Laravel 11's event discovery registered each of them again, so
 * every event ran its listener twice: two assignment texts, two cancellation
 * notices, two invoice emails. Discovery is off now; this keeps it off.
 */
class ListenerRegistrationTest extends TestCase
{
    public function test_every_notification_event_has_exactly_one_listener(): void
    {
        foreach ([
            JobAssigned::class,
            JobUnassigned::class,
            JobWasCanceled::class,
            JobWasUncanceled::class,
            JobStatusChanged::class,
            InvoiceReady::class,
            InvoiceFlagged::class,
            LogCompleted::class,
        ] as $event) {
            $this->assertCount(1, Event::getListeners($event), "{$event} must have exactly one listener");
        }
    }
}
