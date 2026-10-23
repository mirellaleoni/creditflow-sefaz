<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Teste de concorrência requer PostgreSQL (SQLite ignora FOR UPDATE).');
        }
    }

    private function runInParallel(Account $account, array $jobs): array
    {
        $startAt = microtime(true) + 3;
        $processes = [];

        foreach ($jobs as $i => $job) {
            $cmd = [PHP_BINARY, base_path('tests/Support/debit_worker.php'),
                (string) $account->id, (string) $job['amount'], $job['key'], (string) $startAt];

            $processes[$i] = proc_open(
                $cmd,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
            );
            $processes[$i] = ['proc' => $processes[$i], 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $i => $p) {
            $stdout = stream_get_contents($p['pipes'][1]);
            $stderr = stream_get_contents($p['pipes'][2]);
            proc_close($p['proc']);

            $this->assertStringContainsString('STATUS:', $stdout, "Worker $i falhou: $stderr $stdout");
            $results[$i] = trim(str_replace('STATUS:', '', $stdout));
        }

        return $results;
    }

    public function test_concurrent_debits_cannot_spend_the_same_balance(): void
    {
        $account = Account::factory()->withBalance(10_000)->create();

        $jobs = [];
        for ($i = 1; $i <= 5; $i++) {
            $jobs[] = ['key' => "race-$i", 'amount' => 8_000];
        }

        $results = $this->runInParallel($account, $jobs);

        $this->assertSame(1, count(array_keys($results, '201')), 'Exatamente um débito deve ser criado: '.json_encode($results));
        $this->assertSame(4, count(array_keys($results, '422')), 'Os demais devem falhar por saldo insuficiente: '.json_encode($results));

        $this->assertSame(2_000, $account->fresh()->balance_cents);
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_concurrent_requests_with_same_idempotency_key_apply_effect_once(): void
    {
        $account = Account::factory()->withBalance(100_000)->create();

        $jobs = array_fill(0, 5, ['key' => 'same-key', 'amount' => 1_000]);

        $results = $this->runInParallel($account, $jobs);

        $this->assertSame(1, count(array_keys($results, '201')), json_encode($results));
        $this->assertSame(4, count(array_keys($results, '200')), json_encode($results));

        $this->assertSame(99_000, $account->fresh()->balance_cents);
        $this->assertSame(1, $account->transactions()->count());
    }
}
