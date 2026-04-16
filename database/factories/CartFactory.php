<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_token' => Str::random(40),
            'currency' => 'gbp',
            'expires_at' => now()->addDays(30),
            'meta' => null,
        ];
    }

    public function forUser(int $userId): self
    {
        return $this->state(fn () => [
            'user_id' => $userId,
            'session_token' => null,
        ]);
    }
}
