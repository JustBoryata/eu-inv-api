<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;

/**
 * For tests where several database connections work at the same time.
 *
 * RefreshDatabase wraps each test in a transaction that is never committed,
 * so other connections can't see its rows. These tests commit for real and
 * truncate the tables afterwards instead.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use DatabaseTruncation;

    /**
     * Reference data inserted by a migration. Truncating it would break every
     * test that runs after this suite in the same process.
     *
     * @var list<string>
     */
    protected array $exceptTables = ['vat_rates'];

    protected function tearDown(): void
    {
        // DatabaseTruncation only cleans up before each test. Clean up after
        // as well, so the Feature suite never finds committed rows.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }
}
