<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * Extrato paginado da conta autenticada, com filtros de tipo e data.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['credit', 'debit', 'reversal'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $transactions = $request->user()->account->transactions()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json($transactions);
    }

    public function credit(Request $request): JsonResponse
    {
        return $this->record($request, 'credit');
    }

    public function debit(Request $request): JsonResponse
    {
        return $this->record($request, 'debit');
    }

    /**
     * Cria um estorno vinculado à movimentação original.
     */
    public function reverse(Request $request, int $id): JsonResponse
    {
        $accountId = $request->user()->account->id;

        $transaction = DB::transaction(function () use ($accountId, $id) {
            // Trava a conta para impedir operações simultâneas sobre o mesmo saldo
            $account = Account::whereKey($accountId)->lockForUpdate()->firstOrFail();

            // Buscar pela conta garante que só movimentações do próprio usuário sejam encontradas (404)
            $original = $account->transactions()->findOrFail($id);

            abort_if($original->type === 'reversal', 422, 'Um estorno não pode ser estornado.');
            abort_if($original->reversal()->exists(), 409, 'Movimentação já estornada.');

            $newBalance = $original->type === 'credit'
                ? $account->balance_cents - $original->amount_cents
                : $account->balance_cents + $original->amount_cents;

            abort_if($newBalance < 0, 422, 'Saldo insuficiente para estornar.');

            $account->balance_cents = $newBalance;
            $account->save();

            return Transaction::create([
                'account_id' => $account->id,
                'type' => 'reversal',
                'amount_cents' => $original->amount_cents,
                'balance_after_cents' => $newBalance,
                'reverses_transaction_id' => $original->id,
            ]);
        });

        return response()->json($transaction, 201);
    }

    /**
     * Registra crédito ou débito, de forma idempotente e sem permitir saldo negativo.
     */
    private function record(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $key = $request->header('Idempotency-Key');
        abort_if(
            blank($key) || strlen($key) > 100,
            422,
            'O cabeçalho Idempotency-Key é obrigatório (máx. 100 caracteres).'
        );

        $accountId = $request->user()->account->id;
        $amount = $data['amount_cents'];

        [$transaction, $created] = DB::transaction(function () use ($accountId, $type, $amount, $key) {
            // Trava a conta: requisições concorrentes esperam esta terminar
            $account = Account::whereKey($accountId)->lockForUpdate()->firstOrFail();

            // Idempotência: chave já usada devolve o resultado original
            $existing = $account->transactions()->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_if(
                    $existing->type !== $type || $existing->amount_cents !== $amount,
                    409,
                    'Idempotency-Key já usada com outros dados.'
                );

                return [$existing, false];
            }

            $newBalance = $type === 'credit'
                ? $account->balance_cents + $amount
                : $account->balance_cents - $amount;

            abort_if($newBalance < 0, 422, 'Saldo insuficiente.');

            // Atribuição direta: balance_cents não é fillable de propósito
            $account->balance_cents = $newBalance;
            $account->save();

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'type' => $type,
                'amount_cents' => $amount,
                'balance_after_cents' => $newBalance,
                'idempotency_key' => $key,
            ]);

            return [$transaction, true];
        });

        return response()->json($transaction, $created ? 201 : 200);
    }
}
