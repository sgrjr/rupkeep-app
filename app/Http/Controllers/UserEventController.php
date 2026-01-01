<?php

namespace App\Http\Controllers;

use App\Models\UserEvent;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class UserEventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of user events.
     */
    public function index(Request $request)
    {
        // Only super users can view all events
        if (! Auth::user()->is_super) {
            abort(403);
        }

        $query = UserEvent::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by severity
        if ($request->has('severity') && in_array($request->severity, ['info', 'warning', 'error'])) {
            $query->where('severity', $request->severity);
        }

        // Filter by type
        if ($request->has('type') && in_array($request->type, ['error', 'warning', 'info', 'action', 'feedback'])) {
            $query->where('type', $request->type);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $events = $query->paginate(50);

        return view('user-events.index', compact('events'));
    }

    /**
     * Display the specified user event.
     */
    public function show(UserEvent $userEvent)
    {
        // Only super users can view events
        if (! Auth::user()->is_super) {
            abort(403);
        }

        $userEvent->load('user');

        return view('user-events.show', compact('userEvent'));
    }
}
