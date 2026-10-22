<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->unique()
                ->constrained('transactions')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'idempotency_key']);
            $table->index(['account_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_valid CHECK (type IN ('credit', 'debit', 'reversal'))");
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount_cents > 0)');
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_balance_after_non_negative CHECK (balance_after_cents >= 0)');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_reversal_link CHECK (
                (type = 'reversal' AND reverses_transaction_id IS NOT NULL)
                OR (type <> 'reversal' AND reverses_transaction_id IS NULL)
            )");
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_idempotency_required CHECK (
                type = 'reversal' OR idempotency_key IS NOT NULL
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};