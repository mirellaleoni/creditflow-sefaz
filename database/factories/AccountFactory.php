<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance_cents' => 0,
        ];
    }

    /**
     * Define um saldo específico para a conta.
     */
    public function withBalance(int $cents): static
    {
        return $this->state(fn () => [
            'balance_cents' => $cents,
        ]);
    }
}
