<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->user() || !auth()->user()->isSuper()) {
            abort(403);
        }

        // The in-app deploy runs composer, npm and a build in one request.
        // PHP's own limit is raised here; nginx and PHP-FPM have their own
        // (fastcgi_read_timeout, request_terminate_timeout), see
        // docs/DEPLOYMENT.md (TASK-470).
        set_time_limit(600);

        return $next($request);
    }
}
