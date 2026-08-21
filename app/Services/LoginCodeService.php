<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginCodeService
{
    /**
     * Uppercase, unambiguous. O/0, I/1 and S/5 are omitted because people read
     * these codes off a screen and retype them into the verify form.
     */
    protected const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    public function __construct(
        protected int $defaultExpiryMinutes = 0,
        protected int $codeLength = 8
    ) {
        $this->defaultExpiryMinutes = config('login-codes.expires_after_minutes', 120);
        $this->codeLength = config('login-codes.code_length', 8);
    }

    /**
     * Generate and persist a login code for the given user.
     *
     * The row carries two secrets: `code` for the human to type, and
     * `link_token` for the one-click sign-in link (TASK-319). They share one
     * `used_at`, so redeeming either retires both.
     */
    public function generate(User $user, ?int $minutes = null, array $meta = []): LoginCode
    {
        $expiresAt = $minutes === 0
            ? null
            : now()->addMinutes($minutes ?? $this->defaultExpiryMinutes);

        return DB::transaction(function () use ($user, $expiresAt, $meta) {
            $user->loginCodes()->active()->update(['used_at' => now()]);

            return $user->loginCodes()->create([
                'code' => $this->generateUniqueCode(),
                'link_token' => $this->generateUniqueLinkToken(),
                'expires_at' => $expiresAt,
                'ip_address' => $meta['ip'] ?? null,
                'user_agent' => $meta['user_agent'] ?? null,
            ]);
        });
    }

    /**
     * Attempt to consume a typed code and return the associated user.
     */
    public function consume(string $code): ?User
    {
        /** @var LoginCode|null $loginCode */
        $loginCode = LoginCode::query()
            ->where('code', strtoupper($code))
            ->first();

        return $this->redeem($loginCode);
    }

    /**
     * Attempt to consume an emailed sign-in link token and return the user.
     *
     * Unlike a typed code this is matched case-sensitively — the token is
     * never retyped by hand, so there is nothing to be lenient about, and
     * folding case would throw away entropy.
     */
    public function consumeLinkToken(string $token): ?User
    {
        /** @var LoginCode|null $loginCode */
        $loginCode = LoginCode::query()
            ->where('link_token', $token)
            ->first();

        if ($loginCode && ! hash_equals((string) $loginCode->link_token, $token)) {
            return null;
        }

        return $this->redeem($loginCode);
    }

    /**
     * Shared redemption: reject spent or expired rows, otherwise burn the row
     * and hand back its user.
     */
    protected function redeem(?LoginCode $loginCode): ?User
    {
        if (!$loginCode || $loginCode->isUsed() || $loginCode->isExpired()) {
            return null;
        }

        $loginCode->markUsed();

        return $loginCode->user;
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = $this->randomCode(max(4, $this->codeLength));
        } while (LoginCode::where('code', $code)->exists());

        return $code;
    }

    protected function generateUniqueLinkToken(): string
    {
        do {
            $token = Str::random(64);
        } while (LoginCode::where('link_token', $token)->exists());

        return $token;
    }

    /**
     * Draw exactly $length characters from the unambiguous alphabet.
     *
     * The previous implementation ran Str::random() through a regex that
     * stripped everything outside [A-Z0-9], which silently produced codes
     * well short of the configured length — roughly half of them, since
     * Str::random() is mixed-case base64. Now that staff accounts can redeem
     * codes too, the length needs to actually be the length.
     */
    protected function randomCode(int $length): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
