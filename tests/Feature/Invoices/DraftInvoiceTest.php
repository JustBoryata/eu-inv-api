<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\VatRate;

beforeEach(function () {
    $this->user = signIn();
    $this->client = Client::factory()->for($this->user->organisation)->create();
});

it('creates a draft with lines, totals and a VAT breakdown', function () {
    $response = $this->postJson('/api/invoices', [
        'client_id' => $this->client->id,
        'due_date' => '2026-11-14',
        'notes' => 'Thank you for your business.',
        'lines' => [
            ['description' => 'Website development', 'quantity' => 12.5, 'unit_price' => 4000, 'vat_rate_id' => vatRate('20.00')->id],
            ['description' => 'Hosting, one year', 'quantity' => '1', 'unit_price' => 12000, 'vat_rate_id' => vatRate('20.00')->id],
            ['description' => 'Printed manual', 'quantity' => 2, 'unit_price' => 1550, 'vat_rate_id' => vatRate('9.00')->id],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.currency', 'EUR')
        ->assertJsonPath('data.client.id', $this->client->id)
        ->assertJsonPath('data.due_date', '2026-11-14')
        ->assertJsonPath('data.lines.*.position', [1, 2, 3])
        ->assertJsonPath('data.lines.0.quantity', '12.500')
        ->assertJsonPath('data.lines.0.vat_rate', '20.00')
        ->assertJsonPath('data.lines.0.net_amount', 50000)
        ->assertJsonPath('data.vat_breakdown', [
            ['rate' => '20.00', 'net' => 62000, 'vat' => 12400],
            ['rate' => '9.00', 'net' => 3100, 'vat' => 279],
        ])
        ->assertJsonPath('data.net_total', 65100)
        ->assertJsonPath('data.vat_total', 12679)
        ->assertJsonPath('data.gross_total', 77779);

    expect(Invoice::query()->findOrFail($response->json('data.id'))->organisation_id)
        ->toBe($this->user->organisation_id);
});

it('needs a client and at least one line', function () {
    $this->postJson('/api/invoices', ['lines' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id', 'lines']);
});

it('validates each line', function (array $line, string $field) {
    $this->postJson('/api/invoices', [
        'client_id' => $this->client->id,
        'lines' => [[
            'description' => 'Consulting',
            'quantity' => '1',
            'unit_price' => 1000,
            'vat_rate_id' => vatRate('20.00')->id,
            ...$line,
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors("lines.0.{$field}");
})->with([
    'zero quantity' => [['quantity' => '0'], 'quantity'],
    'negative quantity' => [['quantity' => '-1'], 'quantity'],
    'four decimal places' => [['quantity' => '1.2345'], 'quantity'],
    'exponent notation' => [['quantity' => '1e3'], 'quantity'],
    'fractional cents' => [['unit_price' => 10.5], 'unit_price'],
    'negative price' => [['unit_price' => -100], 'unit_price'],
    'unknown VAT rate' => [['vat_rate_id' => 999999], 'vat_rate_id'],
    'missing description' => [['description' => ''], 'description'],
]);

it("rejects another country's VAT rate", function () {
    $germanRate = VatRate::query()->insertGetId(['country_code' => 'DE', 'rate' => '19.00', 'name' => 'Standard rate']);

    $this->postJson('/api/invoices', [
        'client_id' => $this->client->id,
        'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_price' => 1000, 'vat_rate_id' => $germanRate]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.0.vat_rate_id');
});

it("rejects another tenant's client", function () {
    // Rule::exists skips the tenant scope, so the rule has to filter by organisation itself.
    $theirClient = Client::factory()->create();

    $this->postJson('/api/invoices', [
        'client_id' => $theirClient->id,
        'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_price' => 1000, 'vat_rate_id' => vatRate('20.00')->id]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('client_id');
});

it('shows a draft with its lines', function () {
    $invoice = draftInvoice($this->user->organisation, client: $this->client);

    $this->getJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonPath('data.lines.0.description', 'Website development')
        ->assertJsonPath('data.gross_total', 60000);
});

it('lists invoices newest first, filtered by status and client', function () {
    $first = draftInvoice($this->user->organisation, client: $this->client);
    $second = draftInvoice($this->user->organisation);

    $this->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$second->id, $first->id])
        ->assertJsonMissingPath('data.0.lines');

    $this->getJson("/api/invoices?client_id={$this->client->id}")
        ->assertJsonPath('data.*.id', [$first->id]);

    $this->getJson('/api/invoices?status=issued')
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/invoices?status=void')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('replaces the lines and recomputes the totals on update', function () {
    $invoice = draftInvoice($this->user->organisation, client: $this->client);
    $oldLineIds = $invoice->lines->modelKeys();

    $this->patchJson("/api/invoices/{$invoice->id}", [
        'lines' => [
            ['description' => 'Logo design', 'quantity' => '1', 'unit_price' => 30000, 'vat_rate_id' => vatRate('0.00')->id],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.description', 'Logo design')
        ->assertJsonPath('data.vat_breakdown', [['rate' => '0.00', 'net' => 30000, 'vat' => 0]])
        ->assertJsonPath('data.gross_total', 30000);

    expect(InvoiceLine::query()->whereKey($oldLineIds)->exists())->toBeFalse();
});

it('keeps the lines when an update leaves them out', function () {
    $invoice = draftInvoice($this->user->organisation, client: $this->client);

    $this->patchJson("/api/invoices/{$invoice->id}", ['notes' => 'Updated', 'due_date' => null])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Updated')
        ->assertJsonPath('data.due_date', null)
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.gross_total', 60000);
});

it('deletes a draft along with its lines', function () {
    $invoice = draftInvoice($this->user->organisation, client: $this->client);

    $this->deleteJson("/api/invoices/{$invoice->id}")->assertNoContent();

    expect(Invoice::query()->find($invoice->id))->toBeNull()
        ->and(InvoiceLine::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

it("answers 404 for another tenant's invoice", function (string $method) {
    $theirs = draftInvoice(Organisation::factory()->create());

    $this->json($method, "/api/invoices/{$theirs->id}", ['notes' => 'Hijacked'])->assertNotFound();
})->with(['GET', 'PATCH', 'DELETE']);

it('refuses to delete a client that has invoices', function () {
    draftInvoice($this->user->organisation, client: $this->client);

    $this->deleteJson("/api/clients/{$this->client->id}")
        ->assertConflict()
        ->assertJsonPath('message', "This client has invoices, so it can't be deleted.");
});
