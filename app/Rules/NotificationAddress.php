<?php

namespace App\Rules;

use App\Support\NotificationAddress as Address;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A mailbox, or a carrier gateway address with a 10-digit phone number in
 * front of the @ (TASK-468). Formatting in the phone part is allowed here
 * because the model mutator strips it on save.
 */
class NotificationAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        if (! is_string($value) || ! Address::isValid($value)) {
            $fail(__('Enter an email address, or a 10-digit phone number with its carrier gateway, like 2075551234@vtext.com.'));
        }
    }
}
