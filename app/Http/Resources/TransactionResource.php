<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'type' => $this->type,
            'amount_cents' => $this->amount_cents,
            'amount' => Money::format($this->amount_cents),
            'balance_after_cents' => $this->balance_after_cents,
            'balance_after' => Money::format($this->balance_after_cents),
            'idempotency_key' => $this->idempotency_key,
            'reverses_transaction_id' => $this->reverses_transaction_id,
            'created_at' => $this->created_at,
        ];
    }
}
