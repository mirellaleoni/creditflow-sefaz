<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTransactionsRequest;
use App\Http\Requests\ReverseTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Extrato paginado da conta autenticada, com filtros de tipo e data.
     */
    public function index(IndexTransactionsRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $transactions = $request->user()->account->transactions()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);

        return TransactionResource::collection($transactions)->response();
    }

    /**
     * Registra crédito na conta autenticada.
     */
    public function credit(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->transactionService->record(
            account: $request->user()->account,
            type: 'credit',
            amount: (int) $data['amount_cents'],
            idempotencyKey: $data['idempotency_key']
        );

        return TransactionResource::make($result['transaction'])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    /**
     * Registra débito na conta autenticada.
     */
    public function debit(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->transactionService->record(
            account: $request->user()->account,
            type: 'debit',
            amount: (int) $data['amount_cents'],
            idempotencyKey: $data['idempotency_key']
        );

        return TransactionResource::make($result['transaction'])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    /**
     * Cria um estorno vinculado à movimentação original.
     */
    public function reverse(ReverseTransactionRequest $request, int $id): JsonResponse
    {
        $transaction = $this->transactionService->reverse(
            account: $request->user()->account,
            transactionId: $id
        );

        return TransactionResource::make($transaction)
            ->response()
            ->setStatusCode(201);
    }
}
