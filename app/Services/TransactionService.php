<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function record(Account $account, string $type, int $amount, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($account, $type, $amount, $idempotencyKey) {
            $lockedAccount = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $existing = $lockedAccount->transactions()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_if(
                    $existing->type !== $type || $existing->amount_cents !== $amount,
                    409,
                    'Idempotency-Key já usada com outros dados.'
                );

                return ['transaction' => $existing, 'created' => false];
            }

            $newBalance = $type === 'credit'
                ? $lockedAccount->balance_cents + $amount
                : $lockedAccount->balance_cents - $amount;

            abort_if($newBalance < 0, 422, 'Saldo insuficiente.');

            $lockedAccount->balance_cents = $newBalance;
            $lockedAccount->save();

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

    public function reverse(Account $account, int $transactionId): Transaction
    {
        return DB::transaction(function () use ($account, $transactionId) {
            $lockedAccount = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $original = $lockedAccount->transactions()->findOrFail($transactionId);

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
