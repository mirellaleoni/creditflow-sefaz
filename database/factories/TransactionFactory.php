<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'type' => 'credit',
            'amount_cents' => 1000,
            'balance_after_cents' => 1000,
            'idempotency_key' => (string) Str::uuid(),
            'reverses_transaction_id' => null,
        ];
    }

    public function credit(int $amountCents = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'amount_cents' => $amountCents,
        ]);
    }

    public function debit(int $amountCents = 2000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'amount_cents' => $amountCents,
        ]);
    }

    public function reversal(Transaction $original): static
    {
        return $this->state(fn () => [
            'account_id' => $original->account_id,
            'type' => 'reversal',
            'amount_cents' => $original->amount_cents,
            'idempotency_key' => null,
            'reverses_transaction_id' => $original->id,
        ]);
    }
}
