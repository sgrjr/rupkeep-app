<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class TrackNavigationHistory
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only track for authenticated users and GET requests (to avoid forms, etc.)
        // Also, exclude Livewire internal requests
        if (Auth::check() && $request->isMethod('get') && !$request->ajax() && !$request->hasHeader('X-Livewire')) {

            $currentUrl = $request->fullUrl();
            $currentTitle = $this->getPageTitle($request); // Helper to get a title

            $history = Session::get('navigation_history', []);

            // Remove current URL if it's already in history (to prevent duplicates and move to top)
            $history = array_filter($history, function($item) use ($currentUrl) {
                return $item['url'] !== $currentUrl;
            });

            // Add current page to the beginning of the history
            array_unshift($history, [
                'url' => $currentUrl,
                'title' => $currentTitle,
            ]);

            // Keep only the last N items
            $maxHistoryItems = 5; // You can make this configurable
            $history = array_slice($history, 0, $maxHistoryItems);

            Session::put('navigation_history', $history);
        }

        return $next($request);
    }

    /**
     * Attempts to get a sensible title for the page.
     * You might need to customize this based on your routing or page structure.
     *
     * @param Request $request
     * @return string
     */
    protected function getPageTitle(Request $request): string
    {
        // Option 1: Use Route Name (if you consistently name your routes)
        if ($request->route()) {
            $routeName = $request->route()->getName();
            if ($routeName) {
                // You might map route names to more friendly titles
                return ucfirst(str_replace(['.', '-'], ' ', $routeName));
            }
        }

        // Option 2: Basic title from URL segment
        $path = $request->path();
        if ($path === '/') {
            return 'Home';
        }
        return ucfirst(str_replace(['/', '-', '_'], ' ', $path));
    }
}