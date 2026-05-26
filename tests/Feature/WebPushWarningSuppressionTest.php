<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Locks the defensive fix from TASK-007: the minishlink/web-push library
 * triggers an E_USER_WARNING when neither gmp nor bcmath PHP extension is
 * loaded, and Laravel's HandleExceptions promotes that warning to a thrown
 * ErrorException. AppServiceProvider::suppressMinishlinkGmpBcmathWarning()
 * installs an error handler that swallows that single specific warning
 * and lets every other warning continue to the previous handler.
 */
class WebPushWarningSuppressionTest extends TestCase
{
    public function test_gmp_or_bcmath_warning_does_not_throw(): void
    {
        // The exact phrase the upstream library uses
        // (vendor/minishlink/web-push/src/Utils.php).
        $message = 'It is highly recommended to install the GMP or BCMath extension to speed up calculations.';

        $threw = false;
        try {
            @trigger_error($message, E_USER_WARNING);
            trigger_error($message, E_USER_WARNING); // un-suppressed, full path
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertFalse($threw, 'AppServiceProvider should swallow the GMP/BCMath warning so it does not become a fatal exception.');
    }

    public function test_unrelated_warning_still_bubbles_to_previous_handler(): void
    {
        // Pre-fix behaviour: any E_USER_WARNING that reaches Laravel's
        // HandleExceptions becomes a thrown ErrorException. Our wrapper must
        // preserve that for everything except the one GMP/BCMath message.

        $threw = false;
        try {
            trigger_error('Some unrelated user warning', E_USER_WARNING);
        } catch (\ErrorException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Non-GMP user warnings must continue to be promoted to exceptions by Laravel.');
    }
}
