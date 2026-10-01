<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * The browser's web-push subscription for the signed-in person.
 *
 * store() is called on every page load that has a VAPID key and is an
 * upsert by endpoint: the package moves an endpoint owned by someone else
 * to the current user, which is what a shared phone needs (TASK-467).
 * destroy() is called from the logout form so the device stops receiving
 * the previous person's pushes.
 */
class SubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $this->validate($request, [
            'endpoint' => 'required|string|max:1000',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $endpoint = $request->endpoint;
        $token = $request->keys['auth'];
        $key = $request->keys['p256dh'];

        // The HasPushSubscriptions trait provides this method
        $request->user()->updatePushSubscription($endpoint, $key, $token);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $this->validate($request, [
            'endpoint' => 'required|string|max:1000',
        ]);

        // Only this person's row for the endpoint: another account on the
        // same device is not ours to touch.
        $request->user()->deletePushSubscription($request->endpoint);

        return response()->json(['success' => true]);
    }
}
