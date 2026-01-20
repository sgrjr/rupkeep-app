<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\TrackNavigationHistory;
use Illuminate\View\ViewException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'customer' => EnsureCustomer::class,
            'super' => IsSuperAdmin::class,
        ]);

        $middleware->web(append: [
            TrackNavigationHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Track all exceptions in the experience tracker
        $exceptions->report(function (\Throwable $e) {
            \App\Services\ExperienceTrackerService::trackError($e);
        });

        $exceptions->render(function(ViewException $e, $request){
            // Handle Vite manifest errors (deprecated ViteManifestNotFoundException replaced with RuntimeException)
            if (str_contains($e->getMessage(), 'Vite manifest') || str_contains($e->getMessage(), 'manifest.json')) {
                return response()->view('errors.missing-assets', ['message'=>$e->getMessage(), 'code'=>$e->getCode() ?: 500], $e->getCode() ?: 500);
            }
        });

    })->create();
