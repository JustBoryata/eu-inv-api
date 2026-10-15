<?php

use App\Models\Invoice;
use App\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = signIn();
    $this->organisation = $this->user->organisation;
});

it('marks an issued invoice as paid', function () {
    $invoice = draftInvoice($this->organisation);
    $this->postJson("/api/invoices/{$invoice->id}/issue")->assertOk();
    $this->travelTo(Carbon::parse('2026-10-25 12:00:00', 'UTC'));

    $this->postJson("/api/invoices/{$invoice->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.paid_at', '2026-10-25T12:00:00+00:00')
        ->assertJsonPath('data.number', '0000000001');
});

it('refuses a draft', function () {
    $invoice = draftInvoice($this->organisation);

    $this->postJson("/api/invoices/{$invoice->id}/mark-paid")
        ->assertConflict()
        ->assertJsonPath('message', 'Only issued invoices can be marked as paid.');

    expect($invoice->refresh()->paid_at)->toBeNull();
});

it('refuses an invoice that is already paid, and keeps the first payment date', function () {
    $invoice = draftInvoice($this->organisation);
    $this->postJson("/api/invoices/{$invoice->id}/issue");
    $paidAt = $this->postJson("/api/invoices/{$invoice->id}/mark-paid")->json('data.paid_at');
    $this->travel(1)->days();

    $this->postJson("/api/invoices/{$invoice->id}/mark-paid")->assertConflict();

    expect($invoice->refresh()->paid_at?->toIso8601String())->toBe($paidAt);
});

it('cannot be changed once paid', function () {
    $invoice = draftInvoice($this->organisation);
    $this->postJson("/api/invoices/{$invoice->id}/issue");
    $this->postJson("/api/invoices/{$invoice->id}/mark-paid");

    $this->patchJson("/api/invoices/{$invoice->id}", ['notes' => 'Changed'])->assertConflict();
    $this->deleteJson("/api/invoices/{$invoice->id}")->assertConflict();
});

it("answers 404 for another tenant's invoice", function () {
    $theirs = draftInvoice(Organisation::factory()->create());

    $this->postJson("/api/invoices/{$theirs->id}/mark-paid")->assertNotFound();
});

it('can be filtered by status', function () {
    $paid = draftInvoice($this->organisation);
    $this->postJson("/api/invoices/{$paid->id}/issue");
    $this->postJson("/api/invoices/{$paid->id}/mark-paid");
    draftInvoice($this->organisation);

    $this->getJson('/api/invoices?status=paid')->assertJsonPath('data.*.id', [$paid->id]);
});

it('rejects a paid invoice without a payment date in the database', function () {
    $invoice = draftInvoice($this->organisation);
    $this->postJson("/api/invoices/{$invoice->id}/issue");

    Invoice::query()->whereKey($invoice->id)->update(['status' => 'paid']);
})->throws(QueryException::class, 'invoices_paid_fields');
