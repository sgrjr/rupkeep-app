<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invite a customer to the portal: find-or-create a customer-role User linked
 * to the given customer record, then send them a welcome email explaining how
 * to sign in with a one-time login code.
 *
 * This is the single place where customer portal accounts come into existence,
 * so the welcome notification is wired here (TASK-018 / TASK-052).
 */
class InviteCustomerUser
{
    /**
     * @return array{user: User, created: bool}
     */
    public function invite(Customer $customer, string $email, ?string $name = null): array
    {
        $email = trim(mb_strtolower($email));

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->guardExistingUser($existing, $customer);

            // Idempotent re-invite of the same customer account: just re-send.
            $this->sendWelcome($existing, created: false);

            return ['user' => $existing, 'created' => false];
        }

        $user = User::create([
            'name' => $name !== null && trim($name) !== '' ? trim($name) : $email,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'organization_role' => User::ROLE_CUSTOMER,
        ]);

        $this->sendWelcome($user, created: true);

        return ['user' => $user, 'created' => true];
    }

    /**
     * Refuse to repurpose an existing account we shouldn't touch — a staff
     * member, or a customer user already tied to a different customer.
     */
    protected function guardExistingUser(User $existing, Customer $customer): void
    {
        if (! $existing->isCustomer()) {
            throw ValidationException::withMessages([
                'email' => __('That email already belongs to a staff account and cannot be used for a customer portal login.'),
            ]);
        }

        if ($existing->customer_id && $existing->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'email' => __('That email is already connected to a different customer.'),
            ]);
        }
    }

    protected function sendWelcome(User $user, bool $created): void
    {
        $loginUrl = route('login-code.create');

        $intro = $created
            ? __('An account has been created for you to view your invoices and jobs online.')
            : __('Here is a reminder of how to access your invoices and jobs online.');

        $message = sprintf(
            "Hello %s,\n\n%s\n\nTo sign in, request a one-time login code here:\n%s\n\nEnter your email (%s) and we will send you a code that signs you in — no password required.",
            $user->name,
            $intro,
            $loginUrl,
            $user->email
        );

        SendUserNotification::to($user, $message, __('Welcome to the Rupkeep Customer Portal'));
    }
}
