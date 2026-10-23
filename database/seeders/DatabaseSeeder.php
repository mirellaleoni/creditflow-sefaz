<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@creditflow.local'],
            [
                'name' => 'Usuário Demo',
                'password' => 'Demo1234!',
            ]
        );

        $demoAccount = Account::firstOrCreate(
            ['user_id' => $demoUser->id],
            ['balance_cents' => 57950]
        );

        if ($demoAccount->transactions()->doesntExist()) {
            $t1 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'credit',
                'amount_cents' => 50000,
                'balance_after_cents' => 50000,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(5),
            ]);

            $t2 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'debit',
                'amount_cents' => 12050,
                'balance_after_cents' => 37950,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(4),
            ]);

            $t3 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'credit',
                'amount_cents' => 20000,
                'balance_after_cents' => 57950,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(3),
            ]);

            $t4 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'debit',
                'amount_cents' => 5000,
                'balance_after_cents' => 52950,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(2),
            ]);

            Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'reversal',
                'amount_cents' => 5000,
                'balance_after_cents' => 57950,
                'idempotency_key' => null,
                'reverses_transaction_id' => $t4->id,
                'created_at' => now()->subDays(1),
            ]);
        }

        $secondUser = User::firstOrCreate(
            ['email' => 'outro@creditflow.local'],
            [
                'name' => 'Outro Usuário',
                'password' => 'Demo1234!',
            ]
        );

        Account::firstOrCreate(
            ['user_id' => $secondUser->id],
            ['balance_cents' => 0]
        );
    }
}
