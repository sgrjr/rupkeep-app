<?php

namespace App\Services;

use App\Models\UserEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExperienceTrackerService
{
    /**
     * Track a user event.
     */
    public static function track(
        string $type,
        string $severity = UserEvent::SEVERITY_INFO,
        ?string $url = null,
        ?array $context = null,
        ?int $userId = null,
        ?string $ip = null
    ): UserEvent {
        try {
            $request = app('request');
            $currentUrl = $request && method_exists($request, 'fullUrl') ? $request->fullUrl() : null;
            $currentIp = $request && method_exists($request, 'ip') ? $request->ip() : null;
            
            return UserEvent::create([
                'user_id' => $userId ?? (Auth::check() ? Auth::id() : null),
                'url' => $url ?? $currentUrl,
                'type' => $type,
                'severity' => $severity,
                'context' => $context,
                'ip' => $ip ?? $currentIp,
            ]);
        } catch (Throwable $e) {
            // Don't let tracking failures break the app
            Log::warning('Failed to track user event', [
                'error' => $e->getMessage(),
                'type' => $type,
                'severity' => $severity,
            ]);
            
            // Return a dummy model to prevent null errors
            return new UserEvent();
        }
    }

    /**
     * Track an error/exception.
     */
    public static function trackError(
        Throwable $exception,
        ?string $url = null,
        ?int $userId = null,
        ?string $ip = null
    ): UserEvent {
        $context = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => self::abbreviateStackTrace($exception->getTraceAsString()),
        ];

        return self::track(
            type: UserEvent::TYPE_ERROR,
            severity: UserEvent::SEVERITY_ERROR,
            url: $url,
            context: $context,
            userId: $userId,
            ip: $ip
        );
    }

    /**
     * Track a warning.
     */
    public static function trackWarning(
        string $message,
        ?array $context = null,
        ?string $url = null,
        ?int $userId = null,
        ?string $ip = null
    ): UserEvent {
        $fullContext = array_merge($context ?? [], ['message' => $message]);

        return self::track(
            type: UserEvent::TYPE_WARNING,
            severity: UserEvent::SEVERITY_WARNING,
            url: $url,
            context: $fullContext,
            userId: $userId,
            ip: $ip
        );
    }

    /**
     * Track an info event.
     */
    public static function trackInfo(
        string $message,
        ?array $context = null,
        ?string $url = null,
        ?int $userId = null,
        ?string $ip = null
    ): UserEvent {
        $fullContext = array_merge($context ?? [], ['message' => $message]);

        return self::track(
            type: UserEvent::TYPE_INFO,
            severity: UserEvent::SEVERITY_INFO,
            url: $url,
            context: $fullContext,
            userId: $userId,
            ip: $ip
        );
    }

    /**
     * Track a user action.
     */
    public static function trackAction(
        string $action,
        ?array $context = null,
        ?string $url = null,
        ?int $userId = null,
        ?string $ip = null
    ): UserEvent {
        $fullContext = array_merge($context ?? [], ['action' => $action]);

        return self::track(
            type: UserEvent::TYPE_ACTION,
            severity: UserEvent::SEVERITY_INFO,
            url: $url,
            context: $fullContext,
            userId: $userId,
            ip: $ip
        );
    }

    /**
     * Abbreviate stack trace to first N frames.
     */
    protected static function abbreviateStackTrace(string $trace, int $limit = 12): string
    {
        $lines = explode("\n", $trace);
        $abbreviated = array_slice($lines, 0, $limit);
        
        if (count($lines) > $limit) {
            $abbreviated[] = "\n... (" . (count($lines) - $limit) . " more frames)";
        }
        
        return implode("\n", $abbreviated);
    }
}

