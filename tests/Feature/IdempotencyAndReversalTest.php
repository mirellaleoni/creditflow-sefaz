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

        // 1ª requisição com a chave 'idemp-1'
        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'idemp-1')
            ->postJson('/api/transactions/credit', ['amount_cents' => 5000]);

        $first->assertStatus(201);
        $this->assertSame(5000, $user->account->fresh()->balance_cents);

        // 2ª requisição idêntica com a mesma chave 'idemp-1'
        $second = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'idemp-1')
            ->postJson('/api/transactions/credit', ['amount_cents' => 5000]);

        // Retorna 200 OK com os dados existentes sem creditar de novo
        $second->assertStatus(200)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.balance_after_cents', 5000);

        // O saldo continua exatamente 5000 (sem efeito adicional)
        $this->assertSame(5000, $user->account->fresh()->balance_cents);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $user = $this->createUserWithAccount();

        // 1ª requisição de R$ 50,00
        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'chave-conflito')
            ->postJson('/api/transactions/credit', ['amount_cents' => 5000])
            ->assertStatus(201);

        // Reutilizar a mesma chave com valor diferente (R$ 80,00) deve retornar 409
        $conflictAmount = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'chave-conflito')
            ->postJson('/api/transactions/credit', ['amount_cents' => 8000]);

        $conflictAmount->assertStatus(409)
            ->assertJson(['message' => 'Idempotency-Key já usada com outros dados.']);

        // Reutilizar a mesma chave com tipo diferente (debit) também deve retornar 409
        $conflictType = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'chave-conflito')
            ->postJson('/api/transactions/debit', ['amount_cents' => 5000]);

        $conflictType->assertStatus(409);
    }

    public function test_reversal_creates_compensatory_entry_and_cannot_be_repeated(): void
    {
        $user = $this->createUserWithAccount();

        // 1. Crédito de R$ 100,00
        $credit = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'cred-100')
            ->postJson('/api/transactions/credit', ['amount_cents' => 10000]);

        $creditId = $credit->json('data.id');
        $this->assertSame(10000, $user->account->fresh()->balance_cents);

        // 2. Estorna o crédito (reduz o saldo de volta para 0)
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

        // 3. O lançamento original continua intacto no banco
        $this->assertDatabaseHas('transactions', [
            'id' => $creditId,
            'type' => 'credit',
        ]);

        // 4. Tentar estornar novamente a mesma movimentação deve retornar 409 Conflict
        $duplicateReversal = $this->actingAs($user)
            ->postJson("/api/transactions/{$creditId}/reverse");

        $duplicateReversal->assertStatus(409)
            ->assertJson(['message' => 'Movimentação já estornada.']);
    }

    public function test_reversal_of_debit_restores_balance(): void
    {
        $user = $this->createUserWithAccount();

        // Crédito de R$ 100 e Débito de R$ 40 -> Saldo R$ 60
        $this->actingAs($user)->withHeader('Idempotency-Key', 'c')->postJson('/api/transactions/credit', ['amount_cents' => 10000]);
        $debit = $this->actingAs($user)->withHeader('Idempotency-Key', 'd')->postJson('/api/transactions/debit', ['amount_cents' => 4000]);
        $debitId = $debit->json('data.id');

        $this->assertSame(6000, $user->account->fresh()->balance_cents);

        // Estorno do débito -> Devolve os 4000 centavos -> Saldo 10000
        $this->actingAs($user)->postJson("/api/transactions/{$debitId}/reverse")->assertStatus(201);
        $this->assertSame(10000, $user->account->fresh()->balance_cents);
    }

    public function test_cannot_reverse_a_reversal(): void
    {
        $user = $this->createUserWithAccount();

        $credit = $this->actingAs($user)->withHeader('Idempotency-Key', 'c')->postJson('/api/transactions/credit', ['amount_cents' => 10000]);
        $reversal = $this->actingAs($user)->postJson("/api/transactions/{$credit->json('data.id')}/reverse");

        $reversalId = $reversal->json('data.id');

        // Tentativa de estornar o próprio estorno deve falhar com 422
        $this->actingAs($user)
            ->postJson("/api/transactions/{$reversalId}/reverse")
            ->assertStatus(422)
            ->assertJson(['message' => 'Um estorno não pode ser estornado.']);
    }

    public function test_reversing_credit_fails_if_balance_would_become_negative(): void
    {
        $user = $this->createUserWithAccount();

        // Credita 100 e gasta 80 (saldo restante 20)
        $credit = $this->actingAs($user)->withHeader('Idempotency-Key', 'c')->postJson('/api/transactions/credit', ['amount_cents' => 10000]);
        $this->actingAs($user)->withHeader('Idempotency-Key', 'd')->postJson('/api/transactions/debit', ['amount_cents' => 8000]);

        $this->assertSame(2000, $user->account->fresh()->balance_cents);

        // Tentar estornar o crédito de 100 deixaria o saldo em -80 -> deve falhar
        $this->actingAs($user)
            ->postJson("/api/transactions/{$credit->json('data.id')}/reverse")
            ->assertStatus(422)
            ->assertJson(['message' => 'Saldo insuficiente para estornar.']);

        // O saldo permanece intacto em 2000
        $this->assertSame(2000, $user->account->fresh()->balance_cents);
    }
}
