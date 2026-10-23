<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyAndReversalTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithAccount(): User
    {
        $user = User::factory()->create();
        Account::create(['user_id' => $user->id]);
        return $user;
    }

    public function test_repeated_idempotent_request_does_not_duplicate_financial_effect(): void
    {
        $user = $this->createUserWithAccount();

        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'idemp-1')
            ->postJson('/api/transactions/credit', ['amount_cents' => 5000]);

        $first->assertStatus(201);
        $this->assertSame(5000, $user->account->fresh()->balance_cents);

        $second = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'idemp-1')
            ->postJson('/api/transactions/credit', ['amount_cents' => 5000]);

        $second->assertStatus(200)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.balance_after_cents', 5000);

        $this->assertSame(5000, $user->account->fresh()->balance_cents);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $user = $this->createUserWithAccount();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'chave-conflito')
            ->postJson('/api/transactions/credit', ['amount_cents' => 5000])
            ->assertStatus(201);

        $conflictAmount = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'chave-conflito')
            ->postJson('/api/transactions/credit', ['amount_cents' => 8000]);

        $conflictAmount->assertStatus(409)
            ->assertJson(['message' => 'Idempotency-Key já usada com outros dados.']);

        $conflictType = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'chave-conflito')
            ->postJson('/api/transactions/debit', ['amount_cents' => 5000]);

        $conflictType->assertStatus(409);
    }

    public function test_reversal_creates_compensatory_entry_and_cannot_be_repeated(): void
    {
        $user = $this->createUserWithAccount();

        $credit = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'cred-100')
            ->postJson('/api/transactions/credit', ['amount_cents' => 10000]);

        $creditId = $credit->json('data.id');
        $this->assertSame(10000, $user->account->fresh()->balance_cents);

        $reversal = $this->actingAs($user)
            ->postJson("/api/transactions/{$creditId}/reverse");

        $reversal->assertStatus(201)
            ->assertJson([
                'data' => [
                    'type' => 'reversal',
                    'amount_cents' => 10000,
                    'balance_after_cents' => 0,
                    'reverses_transaction_id' => $creditId,
                ],
            ]);

        $this->assertSame(0, $user->account->fresh()->balance_cents);

        $this->assertDatabaseHas('transactions', [
            'id' => $creditId,
            'type' => 'credit',
        ]);

        $duplicateReversal = $this->actingAs($user)
            ->postJson("/api/transactions/{$creditId}/reverse");

        $duplicateReversal->assertStatus(409)
            ->assertJson(['message' => 'Movimentação já estornada.']);
    }

    public function test_reversal_of_debit_restores_balance(): void
    {
        $user = $this->createUserWithAccount();

        $this->actingAs($user)->withHeader('Idempotency-Key', 'c')->postJson('/api/transactions/credit', ['amount_cents' => 10000]);
        $debit = $this->actingAs($user)->withHeader('Idempotency-Key', 'd')->postJson('/api/transactions/debit', ['amount_cents' => 4000]);
        $debitId = $debit->json('data.id');

        $this->assertSame(6000, $user->account->fresh()->balance_cents);

        $this->actingAs($user)->postJson("/api/transactions/{$debitId}/reverse")->assertStatus(201);
        $this->assertSame(10000, $user->account->fresh()->balance_cents);
    }

    public function test_cannot_reverse_a_reversal(): void
    {
        $user = $this->createUserWithAccount();

        $credit = $this->actingAs($user)->withHeader('Idempotency-Key', 'c')->postJson('/api/transactions/credit', ['amount_cents' => 10000]);
        $reversal = $this->actingAs($user)->postJson("/api/transactions/{$credit->json('data.id')}/reverse");

        $reversalId = $reversal->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/transactions/{$reversalId}/reverse")
            ->assertStatus(422)
            ->assertJson(['message' => 'Um estorno não pode ser estornado.']);
    }

    public function test_reversing_credit_fails_if_balance_would_become_negative(): void
    {
        $user = $this->createUserWithAccount();

        $credit = $this->actingAs($user)->withHeader('Idempotency-Key', 'c')->postJson('/api/transactions/credit', ['amount_cents' => 10000]);
        $this->actingAs($user)->withHeader('Idempotency-Key', 'd')->postJson('/api/transactions/debit', ['amount_cents' => 8000]);

        $this->assertSame(2000, $user->account->fresh()->balance_cents);

        $this->actingAs($user)
            ->postJson("/api/transactions/{$credit->json('data.id')}/reverse")
            ->assertStatus(422)
            ->assertJson(['message' => 'Saldo insuficiente para estornar.']);

        $this->assertSame(2000, $user->account->fresh()->balance_cents);
    }
}
