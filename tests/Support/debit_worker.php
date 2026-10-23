<?php

use App\Models\Account;
use App\Services\TransactionService;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $accountId, $amount, $key, $startAt] = $argv;

$account = Account::findOrFail((int) $accountId);

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $result = app(TransactionService::class)->record($account, 'debit', (int) $amount, $key);
    echo 'STATUS:'.($result['created'] ? 201 : 200);
} catch (HttpException $e) {
    echo 'STATUS:'.$e->getStatusCode();
}
