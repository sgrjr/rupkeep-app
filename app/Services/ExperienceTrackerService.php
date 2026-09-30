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
     * user_events.context is a TEXT column (64 KiB). A nested failure (log
     * write fails -> tracker logs that -> log write fails again) builds a
     * message that quotes the previous one, and it outgrew the column on
     * production on 2026-09-30. Everything is capped before the insert.
     */
    private const MAX_CONTEXT_BYTES = 60000;
    private const MAX_STRING_CHARS = 4000;

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
                'context' => self::capContext($context),
                'ip' => $ip ?? $currentIp,
            ]);
        } catch (Throwable $e) {
            // Don't let tracking failures break the app -- and don't let the
            // attempt to say so break it either. This runs inside the
            // exception reporter; if the log file is unwritable, an exception
            // escaping here turns every request into a 500.
            try {
                Log::warning('Failed to track user event', [
                    'error' => mb_substr($e->getMessage(), 0, self::MAX_STRING_CHARS),
                    'type' => $type,
                    'severity' => $severity,
                ]);
            } catch (Throwable) {
                // Nothing left to report to.
            }

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
     * Fit the context into the TEXT column: cap every string, then, if the
     * JSON is still too big, keep only the (capped) message.
     */
    public static function capContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $capped = [];
        foreach ($context as $key => $value) {
            $capped[$key] = is_string($value) ? mb_substr($value, 0, self::MAX_STRING_CHARS) : $value;
        }

        if (strlen((string) json_encode($capped)) <= self::MAX_CONTEXT_BYTES) {
            return $capped;
        }

        return [
            'message' => mb_substr((string) ($capped['message'] ?? ''), 0, self::MAX_STRING_CHARS),
            'truncated' => true,
        ];
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

