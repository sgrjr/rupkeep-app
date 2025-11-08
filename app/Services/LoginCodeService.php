<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginCodeService
{
    public function __construct(
        protected int $defaultExpiryMinutes = 0,
        protected int $codeLength = 8
    ) {
        $this->defaultExpiryMinutes = config('login-codes.expires_after_minutes', 60 * 24);
        $this->codeLength = config('login-codes.code_length', 8);
    }

    /**
     * Generate and persist a login code for the given user.
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
                'expires_at' => $expiresAt,
                'ip_address' => $meta['ip'] ?? null,
                'user_agent' => $meta['user_agent'] ?? null,
            ]);
        });
    }

    /**
     * Attempt to consume a code and return the associated user.
     */
    public function consume(string $code): ?User
    {
        /** @var LoginCode|null $loginCode */
        $loginCode = LoginCode::query()
            ->where('code', strtoupper($code))
            ->first();

        if (!$loginCode || $loginCode->isUsed() || $loginCode->isExpired()) {
            return null;
        }

        $loginCode->markUsed();

        return $loginCode->user;
    }

    protected function generateUniqueCode(): string
    {
        $length = max(4, $this->codeLength);

        do {
            $code = $this->formatCode(Str::upper(Str::random($length)));
        } while (LoginCode::where('code', $code)->exists());

        return $code;
    }

    protected function formatCode(string $raw): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $raw);
    }
}

