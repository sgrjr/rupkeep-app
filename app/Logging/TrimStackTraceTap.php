<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Throwable;

class TrimStackTraceTap
{
    /**
     * Customize the given logger instance.
     */
    public function __invoke(Logger $logger): void
    {
        $limit = (int) config('logging.stacktrace_limit', 12);

        $logger->pushProcessor(function (LogRecord|array $record) use ($limit) {
            // Handle Monolog 3.x LogRecord objects
            if ($record instanceof LogRecord) {
                $context = $record->context;
                $extra = $record->extra;
                
                if (
                    isset($context['exception']) &&
                    $context['exception'] instanceof Throwable
                ) {
                    /** @var \Throwable $exception */
                    $exception = $context['exception'];
                    $trace = collect($exception->getTrace())->take($limit);

                    $extra['trace_preview'] = $trace
                        ->map(function (array $frame, int $index) {
                            $file = $frame['file'] ?? '[internal]';
                            $line = $frame['line'] ?? '?';
                            $function = $frame['function'] ?? null;
                            $class = $frame['class'] ?? null;

                            $location = $class
                                ? $class.'::'.$function
                                : ($function ?? '(closure)');

                            return sprintf('#%d %s:%s -> %s', $index, $file, $line, $location);
                        })
                        ->implode(PHP_EOL);

                    $extra['trace_preview_count'] = $trace->count();

                    if (count($exception->getTrace()) > $trace->count()) {
                        $extra['trace_preview_note'] = sprintf(
                            'stack trace trimmed to first %d frames',
                            $trace->count()
                        );
                    }

                    // Replace the original exception string to avoid the full trace output.
                    $context['exception'] = $this->exceptionSummary($exception);
                    
                    // Update the LogRecord with modified context and extra
                    // LogRecord::with() requires all parameters, so we pass the original values
                    return $record->with(
                        message: $record->message,
                        level: $record->level,
                        datetime: $record->datetime,
                        context: $context,
                        extra: $extra,
                        channel: $record->channel
                    );
                }
                
                return $record;
            }
            
            // Handle Monolog 2.x array format (for backward compatibility)
            if (
                is_array($record) &&
                isset($record['context']['exception']) &&
                $record['context']['exception'] instanceof Throwable
            ) {
                /** @var \Throwable $exception */
                $exception = $record['context']['exception'];
                $trace = collect($exception->getTrace())->take($limit);

                $record['extra']['trace_preview'] = $trace
                    ->map(function (array $frame, int $index) {
                        $file = $frame['file'] ?? '[internal]';
                        $line = $frame['line'] ?? '?';
                        $function = $frame['function'] ?? null;
                        $class = $frame['class'] ?? null;

                        $location = $class
                            ? $class.'::'.$function
                            : ($function ?? '(closure)');

                        return sprintf('#%d %s:%s -> %s', $index, $file, $line, $location);
                    })
                    ->implode(PHP_EOL);

                $record['extra']['trace_preview_count'] = $trace->count();

                if (count($exception->getTrace()) > $trace->count()) {
                    $record['extra']['trace_preview_note'] = sprintf(
                        'stack trace trimmed to first %d frames',
                        $trace->count()
                    );
                }

                // Replace the original exception string to avoid the full trace output.
                $record['context']['exception'] = $this->exceptionSummary($exception);
            }

            return $record;
        });
    }

    protected function exceptionSummary(Throwable $exception): string
    {
        return sprintf(
            '%s: %s in %s:%s',
            $exception::class,
            Str::limit($exception->getMessage(), 500),
            $exception->getFile(),
            $exception->getLine()
        );
    }
}

