<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BrandedErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_renders_the_branded_page(): void
    {
        $response = $this->get('/a-route-that-does-not-exist-' . uniqid());

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee("This road doesn't exist");
        $response->assertSee('Casco Bay Pilot Car');
        $response->assertSee('images/logo.svg');
    }

    public function test_403_renders_the_branded_page_with_the_abort_message(): void
    {
        Route::get('/__forbidden_test', fn () => abort(403, 'You may not enter the yard'));

        $response = $this->get('/__forbidden_test');

        $response->assertStatus(403);
        $response->assertSee('403');
        $response->assertSee('You may not enter the yard');
        $response->assertSee('Casco Bay Pilot Car');
    }

    public function test_500_renders_the_branded_page_without_leaking_the_message(): void
    {
        // Error views only render when debug is off; otherwise the whoops page shows.
        config(['app.debug' => false]);

        Route::get('/__boom_test', fn () => throw new RuntimeException('secret internal detail'));

        $response = $this->get('/__boom_test');

        $response->assertStatus(500);
        $response->assertSee('Something went wrong on our end');
        $response->assertSee('Casco Bay Pilot Car');
        $response->assertDontSee('secret internal detail');
    }

    /**
     * The friendlier codes are hard to trigger over HTTP in a test, so assert
     * the views compile and carry the right copy and actions.
     */
    #[DataProvider('brandedViewProvider')]
    public function test_error_views_render_with_branded_copy(string $view, string $code, string $heading): void
    {
        $html = view("errors.$view")->render();

        $this->assertStringContainsString($code, $html);
        $this->assertStringContainsString($heading, $html);
        $this->assertStringContainsString('Casco Bay Pilot Car', $html);
    }

    public static function brandedViewProvider(): array
    {
        return [
            '419 expired' => ['419', '419', 'Your session expired'],
            '429 throttled' => ['429', '429', 'Slow down a moment'],
            '503 maintenance' => ['503', '503', 'Down for a quick tune-up'],
        ];
    }

    public function test_expired_and_maintenance_pages_offer_a_refresh_action(): void
    {
        $this->assertStringContainsString('window.location.reload()', view('errors.419')->render());
        $this->assertStringContainsString('window.location.reload()', view('errors.503')->render());
    }
}
