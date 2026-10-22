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

    /**
     * Seed the application's database com dados de demonstração locais.
     */
    public function run(): void
    {
        // 1. Usuário principal de demonstração
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@creditflow.local'],
            [
                'name' => 'Usuário Demo',
                'password' => 'Demo1234!',
            ]
        );

        $demoAccount = Account::firstOrCreate(
            ['user_id' => $demoUser->id],
            ['balance_cents' => 57950] // R$ 579,50
        );

        // Se ainda não tiver histórico, cria lançamentos de demonstração
        if ($demoAccount->transactions()->doesntExist()) {
            // Depósito inicial de R$ 500,00
            $t1 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'credit',
                'amount_cents' => 50000,
                'balance_after_cents' => 50000,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(5),
            ]);

            // Pagamento (débito) de R$ 120,50
            $t2 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'debit',
                'amount_cents' => 12050,
                'balance_after_cents' => 37950,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(4),
            ]);

            // Novo crédito de R$ 200,00
            $t3 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'credit',
                'amount_cents' => 20000,
                'balance_after_cents' => 57950,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(3),
            ]);

            // Débito de R$ 50,00 que foi estornado
            $t4 = Transaction::create([
                'account_id' => $demoAccount->id,
                'type' => 'debit',
                'amount_cents' => 5000,
                'balance_after_cents' => 52950,
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now()->subDays(2),
            ]);

            // Estorno compensatório do débito acima (devolve R$ 50,00)
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

        // 2. Segundo usuário para testes de isolamento local
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
