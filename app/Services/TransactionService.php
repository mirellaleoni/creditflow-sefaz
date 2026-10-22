<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Registra crédito ou débito na conta com suporte a idempotência e trava de concorrência.
     *
     * @return array{transaction: Transaction, created: bool}
     */
    public function record(Account $account, string $type, int $amount, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($account, $type, $amount, $idempotencyKey) {
            // Trava a linha da conta no PostgreSQL (SELECT ... FOR UPDATE)
            // Evita race condition: duas requisições concorrentes não gastam o mesmo saldo
            $lockedAccount = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            // Idempotência: se já existe para esta conta com a mesma chave
            $existing = $lockedAccount->transactions()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_if(
                    $existing->type !== $type || $existing->amount_cents !== $amount,
                    409,
                    'Idempotency-Key já usada com outros dados.'
                );

                return ['transaction' => $existing, 'created' => false];
            }

            // Calcula novo saldo
            $newBalance = $type === 'credit'
                ? $lockedAccount->balance_cents + $amount
                : $lockedAccount->balance_cents - $amount;

            // Regra R2: débito nunca pode deixar o saldo negativo
            abort_if($newBalance < 0, 422, 'Saldo insuficiente.');

            // Atualiza saldo da conta
            $lockedAccount->balance_cents = $newBalance;
            $lockedAccount->save();

            // Registra a movimentação no histórico
            $transaction = Transaction::create([
                'account_id' => $lockedAccount->id,
                'type' => $type,
                'amount_cents' => $amount,
                'balance_after_cents' => $newBalance,
                'idempotency_key' => $idempotencyKey,
            ]);

            return ['transaction' => $transaction, 'created' => true];
        });
    }

    /**
     * Realiza o estorno compensatório de uma movimentação.
     */
    public function reverse(Account $account, int $transactionId): Transaction
    {
        return DB::transaction(function () use ($account, $transactionId) {
            // Trava a linha da conta para garantir saldo consistente
            $lockedAccount = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            // Garante que a transação pertence à conta do usuário autenticado
            $original = $lockedAccount->transactions()->findOrFail($transactionId);

            // Regra R4: Estorno cria compensatória e não pode ser estornado novamente
            abort_if($original->type === 'reversal', 422, 'Um estorno não pode ser estornado.');
            abort_if($original->reversal()->exists(), 409, 'Movimentação já estornada.');

            $newBalance = $original->type === 'credit'
                ? $lockedAccount->balance_cents - $original->amount_cents
                : $lockedAccount->balance_cents + $original->amount_cents;

            abort_if($newBalance < 0, 422, 'Saldo insuficiente para estornar.');

            $lockedAccount->balance_cents = $newBalance;
            $lockedAccount->save();

            return Transaction::create([
                'account_id' => $lockedAccount->id,
                'type' => 'reversal',
                'amount_cents' => $original->amount_cents,
                'balance_after_cents' => $newBalance,
                'reverses_transaction_id' => $original->id,
            ]);
        });
    }
}
