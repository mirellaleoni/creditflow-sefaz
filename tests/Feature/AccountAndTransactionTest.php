<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAndTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithAccount(): User
    {
        $user = User::factory()->create();
        Account::create(['user_id' => $user->id]);
        return $user;
    }

    public function test_initial_account_balance_is_zero(): void
    {
        $user = $this->createUserWithAccount();

        $response = $this->actingAs($user)
            ->getJson('/api/account');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->account->id,
                    'balance_cents' => 0,
                    'balance' => '0.00',
                ],
            ]);
    }

    public function test_can_perform_credit_operation(): void
    {
        $user = $this->createUserWithAccount();

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'cred-100')
            ->postJson('/api/transactions/credit', [
                'amount_cents' => 10000,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'type' => 'credit',
                    'amount_cents' => 10000,
                    'amount' => '100.00',
                    'balance_after_cents' => 10000,
                    'balance_after' => '100.00',
                ],
            ]);

        $this->assertSame(10000, $user->account->fresh()->balance_cents);
    }

    public function test_can_perform_debit_operation_when_balance_is_sufficient(): void
    {
        $user = $this->createUserWithAccount();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'cred-init')
            ->postJson('/api/transactions/credit', ['amount_cents' => 10000]);

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'deb-3550')
            ->postJson('/api/transactions/debit', ['amount_cents' => 3550]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'type' => 'debit',
                    'amount_cents' => 3550,
                    'amount' => '35.50',
                    'balance_after_cents' => 6450,
                    'balance_after' => '64.50',
                ],
            ]);

        $this->assertSame(6450, $user->account->fresh()->balance_cents);
    }

    public function test_debit_greater_than_balance_is_rejected_without_negative_balance(): void
    {
        $user = $this->createUserWithAccount();

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'deb-sem-saldo')
            ->postJson('/api/transactions/debit', [
                'amount_cents' => 5000,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Saldo insuficiente.']);

        $this->assertSame(0, $user->account->fresh()->balance_cents);
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        $user = $this->createUserWithAccount();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'k1')
            ->postJson('/api/transactions/credit', ['amount_cents' => 0])
            ->assertStatus(422);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'k2')
            ->postJson('/api/transactions/credit', ['amount_cents' => -100])
            ->assertStatus(422);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'k3')
            ->postJson('/api/transactions/credit', ['amount_cents' => 10.5])
            ->assertStatus(422);

        $this->flushHeaders();
        $this->actingAs($user)
            ->postJson('/api/transactions/credit', ['amount_cents' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_transactions_history_is_paginated_and_filters_work(): void
    {
        $user = $this->createUserWithAccount();

        $this->actingAs($user)->withHeader('Idempotency-Key', 't1')->postJson('/api/transactions/credit', ['amount_cents' => 1000]);
        $this->actingAs($user)->withHeader('Idempotency-Key', 't2')->postJson('/api/transactions/credit', ['amount_cents' => 2000]);
        $this->actingAs($user)->withHeader('Idempotency-Key', 't3')->postJson('/api/transactions/debit', ['amount_cents' => 500]);

        $response = $this->actingAs($user)
            ->getJson('/api/transactions?per_page=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);

        $filtered = $this->actingAs($user)
            ->getJson('/api/transactions?type=debit');

        $filtered->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'debit');
    }

    public function test_data_isolation_between_different_users(): void
    {
        $userA = $this->createUserWithAccount();
        $userB = $this->createUserWithAccount();

        $creditResponse = $this->actingAs($userA)
            ->withHeader('Idempotency-Key', 'userA-cred')
            ->postJson('/api/transactions/credit', ['amount_cents' => 10000]);

        $txId = $creditResponse->json('data.id');

        $responseAccountB = $this->actingAs($userB)->getJson('/api/account');
        $responseAccountB->assertJsonPath('data.balance_cents', 0);

        $responseTxB = $this->actingAs($userB)->getJson('/api/transactions');
        $responseTxB->assertJsonCount(0, 'data');

        $responseReverse = $this->actingAs($userB)->postJson("/api/transactions/{$txId}/reverse");
        $responseReverse->assertStatus(422);
    }
}
