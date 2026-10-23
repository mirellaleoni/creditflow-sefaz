<?php

/**
 * Processo auxiliar do ConcurrencyTest: tenta debitar uma conta em um processo PHP separado.
 *
 * Uso: php debit_worker.php <account_id> <amount_cents> <idempotency_key> <start_at_microtime>
 *
 * Imprime "STATUS:<codigo>" (201 criado, 200 repetição, 422 saldo insuficiente, ...).
 */

use App\Models\Account;
use App\Services\TransactionService;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $accountId, $amount, $key, $startAt] = $argv;

$account = Account::findOrFail((int) $accountId);

// Barreira: todos os processos esperam o mesmo instante para disputar o saldo ao mesmo tempo
while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $result = app(TransactionService::class)->record($account, 'debit', (int) $amount, $key);
    echo 'STATUS:'.($result['created'] ? 201 : 200);
} catch (HttpException $e) {
    echo 'STATUS:'.$e->getStatusCode();
}
