<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * TASK-351 — Notification URLs must be rooted at config('app.url').
 *
 * The links in queued SMS/email notifications were rendered as
 * "http://localhost/..." because they are generated inside queued listeners,
 * which have no HTTP request to infer the host from. AppServiceProvider now
 * explicitly roots the URL generator at config('app.url') at boot so every
 * context (web, queue, scheduler, artisan) generates the same, correct host.
 */
class AppUrlForcingTest extends TestCase
{
    /**
     * Re-apply the provider's URL-forcing logic. The provider reads
     * config('app.url') at boot; calling it directly after overriding config
     * exercises the exact code path the boot() cycle runs.
     */
    private function applyUrlForcing(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'forceApplicationUrl');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    public function test_generated_urls_are_rooted_at_configured_app_url(): void
    {
        config(['app.url' => 'https://pilotcar.example']);

        $this->applyUrlForcing();

        $this->assertStringStartsWith('https://pilotcar.example', route('dashboard'));
        $this->assertStringStartsWith('https://pilotcar.example', url('/logs/1023'));
    }

    public function test_https_app_url_forces_https_scheme(): void
    {
        config(['app.url' => 'https://pilotcar.example']);

        $this->applyUrlForcing();

        $this->assertStringStartsWith('https://', route('dashboard'));
    }

    public function test_empty_app_url_leaves_url_generation_untouched(): void
    {
        // An empty/misconfigured app.url must not blow up or force a bad root.
        config(['app.url' => '']);

        $this->applyUrlForcing();

        // route() still generates an absolute URL (falls back to request host).
        $this->assertStringStartsWith('http', route('dashboard'));
    }
}
