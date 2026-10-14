<?php

use App\Actions\CreateDraftInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\ConcurrencyTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(ConcurrencyTestCase::class)
    ->in('Concurrency');

/**
 * Authenticate as a user (a fresh one by default) for the rest of the test.
 *
 * From here on the tenant scope also applies to queries in the test body,
 * not only to the requests the test makes.
 */
function signIn(?User $user = null): User
{
    $user ??= User::factory()->create();

    Sanctum::actingAs($user);

    return $user;
}

/**
 * One of the Bulgarian rates seeded by the migration, e.g. vatRate('20.00').
 */
function vatRate(string $rate): VatRate
{
    return VatRate::query()->where('country_code', 'BG')->where('rate', $rate)->firstOrFail();
}

/**
 * A draft for a new client of the organisation, created the way the API does it.
 *
 * @param  list<array{description: string, quantity: string, unit_price: int, vat_rate_id: int}>|null  $lines
 */
function draftInvoice(Organisation $organisation, ?array $lines = null, ?Client $client = null): Invoice
{
    $client ??= Client::factory()->for($organisation)->create();

    return app(CreateDraftInvoice::class)->handle($organisation, ['client_id' => $client->id], $lines ?? [
        ['description' => 'Website development', 'quantity' => '10', 'unit_price' => 5000, 'vat_rate_id' => vatRate('20.00')->id],
    ]);
}
