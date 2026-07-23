<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    /**
     * Restrict a route to pilot-car staff (admins, managers and drivers).
     *
     * Customer-portal and guest accounts share the pilot-car company's
     * organization_id, so org scoping alone does not keep them off staff
     * surfaces such as the job list, vehicle fleet or customer roster
     * (TASK-358). This gate blocks any account without a staff role.
     *
     * A customer is bounced to their portal home rather than shown a bare
     * 403 (friendlier, and they have a place to go). Everyone else without a
     * staff role — e.g. guest accounts — is refused outright.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isEmployee()) {
            if ($user && $user->isCustomer()) {
                return redirect()->route('customer.invoices.index');
            }

            abort(403);
        }

        return $next($request);
    }
}
