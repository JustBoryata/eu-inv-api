<?php

/*
 * Issues one invoice in a PHP process of its own, so ConcurrentIssueTest can
 * run several issues on separate database connections at the same time.
 *
 * Usage: php tests/Concurrency/issue-invoice.php <invoice-id>
 */

use App\Actions\IssueInvoice;
use App\Models\Invoice;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// This test is about numbering. Don't render (and leave behind) a PDF per worker.
config(['queue.default' => 'null', 'queue.connections.null' => ['driver' => 'null']]);

// Laravel's exception handler would print an uncaught error and still exit
// with 0, which would make a crashed worker look successful to the test.
try {
    // No signed-in user here, so the tenant scope doesn't apply.
    $invoice = Invoice::query()->findOrFail((int) ($argv[1] ?? 0));

    echo $app->make(IssueInvoice::class)->handle($invoice)->number, PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);
    exit(1);
}
