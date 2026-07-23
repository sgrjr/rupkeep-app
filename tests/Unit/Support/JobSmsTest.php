<?php

namespace Tests\Unit\Support;

use App\Models\PilotCarJob;
use App\Support\JobSms;
use App\Support\SmsMessage;
use PHPUnit\Framework\TestCase;

/**
 * Every driver-facing job SMS must fit one 160-char text (TASK-352), even with
 * worst-case inputs (very long customer job numbers / addresses, 6-digit IDs),
 * and the action URL must survive intact.
 */
class JobSmsTest extends TestCase
{
    private const LONG_URL = 'https://www.pilotcar.io/my/jobs/999999';

    private function worstCaseJob(): PilotCarJob
    {
        $job = new PilotCarJob();
        $job->id = 999999;
        $job->job_no = str_repeat('JOB-NUMBER-', 12);              // ~130 chars
        $job->load_no = str_repeat('LOAD', 20);
        $job->pickup_address = str_repeat('100 Very Long Commercial Street, Portland, ME ', 6);
        $job->delivery_address = str_repeat('900 Equally Long Delivery Avenue, Bangor, ME ', 6);
        $job->scheduled_pickup_at = '2026-07-15 08:00:00';

        return $job;
    }

    public function test_assigned_sms_fits_limit_and_keeps_url(): void
    {
        foreach ([true, false] as $needsConfirmation) {
            $sms = JobSms::assigned($this->worstCaseJob(), self::LONG_URL, $needsConfirmation);

            $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms));
            $this->assertStringContainsString(self::LONG_URL, $sms);
            $this->assertStringEndsWith(self::LONG_URL, $sms);
        }
    }

    public function test_canceled_sms_fits_limit_and_keeps_url(): void
    {
        $longReason = str_repeat('customer changed their mind and rescheduled ', 10);

        $sms = JobSms::canceled($this->worstCaseJob(), self::LONG_URL, $longReason);

        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms));
        $this->assertStringContainsString(self::LONG_URL, $sms);
        $this->assertStringContainsString('CANCELED', $sms);
    }

    public function test_canceled_sms_without_reason_fits_limit(): void
    {
        $sms = JobSms::canceled($this->worstCaseJob(), self::LONG_URL, null);

        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms));
        $this->assertStringContainsString(self::LONG_URL, $sms);
    }

    public function test_reactivated_sms_fits_limit_and_keeps_url(): void
    {
        $sms = JobSms::reactivated($this->worstCaseJob(), self::LONG_URL);

        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms));
        $this->assertStringContainsString(self::LONG_URL, $sms);
        $this->assertStringContainsString('REACTIVATED', $sms);
    }

    public function test_status_changed_sms_fits_limit_for_every_status(): void
    {
        foreach (['ACTIVE', 'COMPLETED', 'CANCELLED', 'CANCELLED_NO_GO', 'WHATEVER'] as $status) {
            $sms = JobSms::statusChanged($this->worstCaseJob(), $status, self::LONG_URL);

            $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms), "Overflow for status {$status}");
            $this->assertStringContainsString(self::LONG_URL, $sms);
        }
    }

    public function test_customer_status_sms_fits_limit(): void
    {
        $sms = JobSms::customerStatus($this->worstCaseJob(), 'Cancelled (Show But No-Go)');

        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms));
        $this->assertStringContainsString('status:', $sms);
        $this->assertStringContainsString('dispatcher', $sms);
    }

    public function test_falls_back_to_id_when_job_no_missing(): void
    {
        $job = new PilotCarJob();
        $job->id = 424242;
        $job->job_no = null;
        $job->pickup_address = 'Somewhere';

        $sms = JobSms::assigned($job, self::LONG_URL, true);

        $this->assertStringContainsString('#424242', $sms);
        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($sms));
    }
}
