<?php

namespace App\Logging;

use Illuminate\Support\Str;
use Monolog\Logger;
use Throwable;

class TrimStackTraceTap
{
    /**
     * Customize the given Monolog instance.
     */
    public function __invoke(Logger $logger): void
    {
        $limit = (int) config('logging.stacktrace_limit', 12);

        $logger->pushProcessor(function (array $record) use ($limit) {
            if (
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

