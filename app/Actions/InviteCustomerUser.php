<?php

namespace App\Actions;

use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    /**
     * The welcome went out as one run-on paragraph of plain text, titled and
     * signed as the platform (TASK-506). It is now the branded HTML layout the
     * sign-in email uses, in the organization's name, sent from that name.
     * It goes to the email address directly: the portal is an email sign-in,
     * so an SMS gateway on the account is the wrong place for it.
     */
    protected function sendWelcome(User $user, bool $created): void
    {
        $loginUrl = route('login-code.create');
        $brand = $user->organization?->name ?: config('app.name');

        $intro = $created
            ? __(':brand has set up an account for you to see your invoices online.', ['brand' => $brand])
            : __('Here is a reminder of how to see the invoices :brand has sent you.', ['brand' => $brand]);

        $plain = sprintf(
            "Hello %s,\n\n%s\n\nThere is no password. Open the sign-in page, enter your email address (%s), and we will email you a link that signs you in with one click:\n%s",
            $user->name,
            $intro,
            $user->email,
            $loginUrl
        );

        $subject = $created
            ? __('Your :brand customer portal', ['brand' => $brand])
            : __('Signing in to your :brand customer portal', ['brand' => $brand]);

        try {
            Mail::to($user->email)->send(new UserNotification(
                $plain,
                $subject,
                'mail.customer-welcome',
                [
                    'user' => $user,
                    'brand' => $brand,
                    'created' => $created,
                    'loginUrl' => $loginUrl,
                ],
                $brand
            ));
        } catch (\Throwable $e) {
            // The account exists either way; the customer can still request a
            // sign-in link themselves. Log rather than fail the invite.
            Log::error('InviteCustomerUser: welcome email could not be sent', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
