<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Organisation;
use Illuminate\Process\InvokedProcessPool;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Environment for a worker process: the same database this test uses.
 *
 * PHPUnit sets its <env> values with putenv(), and Symfony Process doesn't
 * pass those on to child processes, so without this a worker would read
 * .env and connect to the development database.
 *
 * @return array<string, string>
 */
function workerEnvironment(): array
{
    $connection = config()->array('database.connections.'.config()->string('database.default'));

    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => config()->string('database.default'),
        'DB_URL' => '',
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
    ];
}

/**
 * Wait until $count other connections are blocked waiting for a lock.
 */
function waitForBlockedConnections(InvokedProcessPool $pool, int $count, float $timeoutSeconds = 15): void
{
    $deadline = microtime(true) + $timeoutSeconds;

    do {
        // A worker that exits before reaching the lock has failed; say why.
        foreach ($pool as $process) {
            if (! $process->running()) {
                throw new RuntimeException("A worker exited early:\n".$process->output().$process->errorOutput());
            }
        }

        // pg_stat_activity is snapshotted once per transaction, and this runs
        // inside the one holding the lock. Clear it, or every read sees the first.
        DB::statement('SELECT pg_stat_clear_snapshot()');

        $blocked = DB::scalar(
            "SELECT count(*) FROM pg_stat_activity
             WHERE datname = current_database() AND wait_event_type = 'Lock' AND pid <> pg_backend_pid()",
        );

        if ($blocked >= $count) {
            return;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException("Only {$blocked} of {$count} connections reached the lock in time.");
}

it('gives concurrent issues distinct, consecutive numbers', function () {
    $workers = 8;
    $organisation = Organisation::factory()->create();
    $invoices = collect(range(1, $workers))->map(fn () => draftInvoice($organisation));
    InvoiceSequence::query()->insert(['organisation_id' => $organisation->id]);

    // Hold the sequence row's lock while the workers start, so that all of
    // them reach it and queue up behind it. Releasing it then lets them race.
    // Without this the workers might simply run one after another, and the
    // test would pass even with the locking removed.
    $pool = DB::transaction(function () use ($organisation, $invoices, $workers) {
        InvoiceSequence::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();

        $pool = Process::pool(function (Pool $pool) use ($invoices) {
            foreach ($invoices as $invoice) {
                $pool->path(base_path())
                    ->env(workerEnvironment())
                    ->command(['php', 'tests/Concurrency/issue-invoice.php', (string) $invoice->id]);
            }
        })->start();

        waitForBlockedConnections($pool, $workers);

        return $pool;
    });

    $results = $pool->wait();

    foreach ($results as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput() ?: $result->output());
    }

    $ids = $invoices->pluck('id')->all();
    $numbers = Invoice::query()->whereKey($ids)->orderBy('number')->pluck('number')->all();

    expect($numbers)->toBe(array_map(fn (int $n) => sprintf('%010d', $n), range(1, $workers)))
        ->and(Invoice::query()->whereKey($ids)->where('status', InvoiceStatus::Issued)->count())->toBe($workers)
        ->and(InvoiceSequence::query()->findOrFail($organisation->id)->last_number)->toBe($workers);
});
