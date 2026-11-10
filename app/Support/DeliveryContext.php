<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * What the message being sent is about (TASK-498).
 *
 * The notification timeline records every email from one place, the
 * MessageSent listener, which sees only an address, a subject and a body.
 * A sender wraps its send in run() to say who it is for, what kind of notice
 * it is and which job or invoice it concerns. The stack allows nesting and is
 * always unwound, even when the send throws.
 */
class DeliveryContext
{
    /** @var array<int, array{kind?: ?string, user?: ?\App\Models\User, related?: ?Model}> */
    private static array $stack = [];

    /**
     * @template T
     *
     * @param  array{kind?: ?string, user?: ?\App\Models\User, related?: ?Model}  $context
     * @param  callable(): T  $send
     * @return T
     */
    public static function run(array $context, callable $send)
    {
        self::$stack[] = $context;

        try {
            return $send();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function current(): array
    {
        return self::$stack === [] ? [] : self::$stack[array_key_last(self::$stack)];
    }
}
