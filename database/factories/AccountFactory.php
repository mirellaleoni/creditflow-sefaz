<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance_cents' => 0,
        ];
    }

    public function withBalance(int $cents): static
    {
        return $this->state(fn () => [
            'balance_cents' => $cents,
        ]);
    }
}
