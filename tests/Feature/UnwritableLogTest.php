<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 2026-09-30 outage: an unwritable log file turned every request into a 500,
 * because Laravel reports exceptions through the log and Monolog throws when
 * it cannot open the file. The stack channel now ignores handler failures.
 */
class UnwritableLogTest extends TestCase
{
    public function test_a_request_that_logs_still_succeeds_when_the_log_file_cannot_be_opened(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.channels.stack.channels', ['single']);
        config()->set('logging.channels.single.path', '/nonexistent-dir-'.uniqid().'/laravel.log');

        Route::get('/_log-probe', function () {
            Log::error('this write has nowhere to go');
            report(new \RuntimeException('reported but unloggable'));

            return 'still here';
        });

        $this->get('/_log-probe')->assertOk()->assertSee('still here');
    }
}
