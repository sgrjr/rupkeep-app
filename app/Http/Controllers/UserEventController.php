<?php

namespace App\Http\Controllers;

use App\Models\UserEvent;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Use 20 per page for feedback, 50 for other types
        $perPage = ($request->has('type') && $request->type === 'feedback') ? 20 : 50;
        $events = $query->paginate($perPage);

        // Get daily event counts (cached for 24 hours)
        try {
            $dailyEventCounts = $this->getDailyEventCounts();
        } catch (\Exception $e) {
            \Log::error('Error getting daily event counts', ['error' => $e->getMessage()]);
            $dailyEventCounts = [];
        }

        // Get total event count (not filtered by pagination)
        $totalEvents = UserEvent::count();

        return view('user-events.index', compact('events', 'dailyEventCounts', 'totalEvents'));
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

    /**
     * Display a listing of feedback submissions.
     */
    public function feedback(Request $request)
    {
        // Only super users can view feedback
        if (! Auth::user()->is_super) {
            abort(403);
        }

        $feedback = UserEvent::with('user')
            ->where('type', UserEvent::TYPE_FEEDBACK)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Calculate stats
        $totalFeedback = UserEvent::where('type', UserEvent::TYPE_FEEDBACK)->count();
        $infoFeedback = UserEvent::where('type', UserEvent::TYPE_FEEDBACK)
            ->where('severity', UserEvent::SEVERITY_INFO)
            ->count();
        $errorFeedback = UserEvent::where('type', UserEvent::TYPE_FEEDBACK)
            ->where('severity', UserEvent::SEVERITY_ERROR)
            ->count();

        return view('admin.feedback.index', compact('feedback', 'totalFeedback', 'infoFeedback', 'errorFeedback'));
    }

    /**
     * Get daily event counts, cached for 24 hours.
     */
    private function getDailyEventCounts(): array
    {
        $cacheKey = 'user_events_daily_counts';
        
        return Cache::remember($cacheKey, now()->endOfDay(), function () {
            try {
                // Get last 30 days of data
                $startDate = now()->subDays(29)->startOfDay();
                $endDate = now()->endOfDay();
                
                $counts = UserEvent::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'asc')
                ->pluck('count', 'date')
                ->toArray();
                
                // Fill in missing days with 0
                $result = [];
                $currentDate = $startDate->copy();
                
                while ($currentDate->lte($endDate)) {
                    $dateKey = $currentDate->format('Y-m-d');
                    $result[$dateKey] = isset($counts[$dateKey]) ? (int)$counts[$dateKey] : 0;
                    $currentDate->addDay();
                }
                
                return $result;
            } catch (\Exception $e) {
                // If query fails, return empty array with today's date
                \Log::warning('Failed to get daily event counts', ['error' => $e->getMessage()]);
                return [now()->format('Y-m-d') => 0];
            }
        });
    }

    /**
     * Prune old user events.
     */
    public function prune(Request $request)
    {
        // Only super users can prune events
        if (! Auth::user()->is_super) {
            abort(403);
        }

        $request->validate([
            'keep_days' => 'required|integer|min:1|max:365',
        ]);

        $keepDays = (int) $request->input('keep_days');
        $cutoffDate = now()->subDays($keepDays)->startOfDay();

        $deletedCount = UserEvent::where('created_at', '<', $cutoffDate)->delete();

        // Clear the daily counts cache since data has changed
        Cache::forget('user_events_daily_counts');

        Log::info('User events pruned', [
            'user_id' => Auth::id(),
            'keep_days' => $keepDays,
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return redirect()->route('user-events.index')
            ->with('success', __('Pruned :count old user events (kept last :days days).', [
                'count' => number_format($deletedCount),
                'days' => $keepDays,
            ]));
    }

    /**
     * Clear all user events.
     */
    public function clearAll(Request $request)
    {
        // Only super users can clear all events
        if (! Auth::user()->is_super) {
            abort(403);
        }

        $totalCount = UserEvent::count();
        
        UserEvent::truncate();

        // Clear the daily counts cache
        Cache::forget('user_events_daily_counts');

        Log::warning('All user events cleared', [
            'user_id' => Auth::id(),
            'deleted_count' => $totalCount,
        ]);

        return redirect()->route('user-events.index')
            ->with('success', __('Cleared all :count user events. This action cannot be undone.', [
                'count' => number_format($totalCount),
            ]));
    }
}
