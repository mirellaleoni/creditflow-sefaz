<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable([
    'account_id',
    'type',
    'amount_cents',
    'balance_after_cents',
    'idempotency_key',
    'reverses_transaction_id'
])]
class Transaction extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Movimentação original que este estorno reverte. */
    public function reversesTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reverses_transaction_id');
    }

    /** Estorno desta movimentação, se existir. */
    public function reversal(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reverses_transaction_id');
    }
}
