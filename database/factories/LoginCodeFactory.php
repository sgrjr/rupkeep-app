<?php

namespace Database\Factories;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginCode>
 */
class LoginCodeFactory extends Factory
{
    protected $model = LoginCode::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => strtoupper(Str::random(8)),
            'expires_at' => now()->addMinutes(30),
            'used_at' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'used_at' => now()->subMinute(),
        ]);
    }
}

