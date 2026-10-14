<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = signIn();
    $this->organisation = $this->user->organisation;
});

describe('issuing', function () {
    it('numbers, dates and snapshots the invoice', function () {
        $client = Client::factory()->for($this->organisation)->create();
        $invoice = draftInvoice($this->organisation, client: $client);
        $this->travelTo(Carbon::parse('2026-10-20 09:15:00', 'UTC'));

        $this->postJson("/api/invoices/{$invoice->id}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.number', '0000000001')
            ->assertJsonPath('data.issue_date', '2026-10-20')
            ->assertJsonPath('data.issued_at', '2026-10-20T09:15:00+00:00')
            ->assertJsonPath('data.seller.name', $this->organisation->name)
            ->assertJsonPath('data.seller.registration_number', $this->organisation->registration_number)
            ->assertJsonPath('data.buyer.name', $client->name)
            ->assertJsonPath('data.buyer.vat_number', $client->vat_number)
            ->assertJsonPath('data.gross_total', 60000);
    });

    it('numbers invoices consecutively per organisation', function () {
        $numbers = collect(range(1, 3))->map(function () {
            $invoice = draftInvoice($this->organisation);

            return $this->postJson("/api/invoices/{$invoice->id}/issue")->json('data.number');
        });

        expect($numbers->all())->toBe(['0000000001', '0000000002', '0000000003']);
    });

    it('keeps a separate sequence for each organisation', function () {
        $this->postJson('/api/invoices/'.draftInvoice($this->organisation)->id.'/issue');

        signIn($other = User::factory()->create());

        $this->postJson('/api/invoices/'.draftInvoice($other->organisation)->id.'/issue')
            ->assertJsonPath('data.number', '0000000001');
    });

    it('does not use up a number for a deleted draft', function () {
        $deleted = draftInvoice($this->organisation);
        $this->deleteJson("/api/invoices/{$deleted->id}")->assertNoContent();

        $this->postJson('/api/invoices/'.draftInvoice($this->organisation)->id.'/issue')
            ->assertJsonPath('data.number', '0000000001');
    });

    it('dates the invoice in Sofia time', function () {
        // 22:30 UTC on the 20th is already 01:30 on the 21st in Sofia (UTC+3 in summer time).
        $invoice = draftInvoice($this->organisation);
        $this->travelTo(Carbon::parse('2026-10-20 22:30:00', 'UTC'));

        $this->postJson("/api/invoices/{$invoice->id}/issue")
            ->assertJsonPath('data.issue_date', '2026-10-21');
    });

    it('applies the VAT rate in force on the day of issue', function () {
        $invoice = draftInvoice($this->organisation, [
            ['description' => 'Hotel night', 'quantity' => '1', 'unit_price' => 10000, 'vat_rate_id' => vatRate('9.00')->id],
        ]);
        VatRate::query()->whereKey(vatRate('9.00')->id)->update(['rate' => '10.00']);

        $this->postJson("/api/invoices/{$invoice->id}/issue")
            ->assertJsonPath('data.lines.0.vat_rate', '10.00')
            ->assertJsonPath('data.vat_breakdown', [['rate' => '10.00', 'net' => 10000, 'vat' => 1000]])
            ->assertJsonPath('data.gross_total', 11000);
    });

    it('needs the seller details first, and then uses no number', function () {
        $this->organisation->update(['registration_number' => null]);
        $invoice = draftInvoice($this->organisation);

        $this->postJson("/api/invoices/{$invoice->id}/issue")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organisation');

        expect($invoice->refresh()->number)->toBeNull()
            ->and(InvoiceSequence::query()->find($this->organisation->id)?->last_number ?? 0)->toBe(0);
    });

    it("answers 404 for another tenant's invoice", function () {
        $theirs = draftInvoice(Organisation::factory()->create());

        $this->postJson("/api/invoices/{$theirs->id}/issue")->assertNotFound();
    });
});

describe('after issue', function () {
    beforeEach(function () {
        $this->client = Client::factory()->for($this->organisation)->create(['name' => 'Acme EOOD']);
        $this->invoice = draftInvoice($this->organisation, client: $this->client);
        $this->postJson("/api/invoices/{$this->invoice->id}/issue")->assertOk();
    });

    it('cannot be issued again', function () {
        $this->postJson("/api/invoices/{$this->invoice->id}/issue")
            ->assertConflict()
            ->assertJsonPath('message', 'Only draft invoices can be changed. An issued invoice is corrected with a credit note.');
    });

    it('cannot be updated', function () {
        $this->patchJson("/api/invoices/{$this->invoice->id}", ['notes' => 'Changed'])->assertConflict();

        expect($this->invoice->refresh()->notes)->toBeNull();
    });

    it('cannot be deleted', function () {
        $this->deleteJson("/api/invoices/{$this->invoice->id}")->assertConflict();

        expect(Invoice::query()->find($this->invoice->id))->not->toBeNull();
    });

    it('keeps the buyer details it was issued with', function () {
        $this->patchJson("/api/clients/{$this->client->id}", ['name' => 'Acme Renamed EOOD'])->assertOk();

        $this->getJson("/api/invoices/{$this->invoice->id}")
            ->assertJsonPath('data.buyer.name', 'Acme EOOD');
    });

    it('keeps the seller details it was issued with', function () {
        $original = $this->organisation->address;
        $this->patchJson('/api/organisation', ['address' => 'New address'])->assertOk();

        $this->getJson("/api/invoices/{$this->invoice->id}")
            ->assertJsonPath('data.seller.address', $original);
    });

    it('can be filtered by issue date', function () {
        $issueDate = $this->invoice->refresh()->issue_date?->toDateString();
        draftInvoice($this->organisation);

        $this->getJson("/api/invoices?issued_from={$issueDate}&issued_to={$issueDate}")
            ->assertJsonPath('data.*.id', [$this->invoice->id]);

        $this->getJson('/api/invoices?issued_from=2026-10-20&issued_to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issued_to');
    });
});

describe('database constraints', function () {
    // The application never does these; the constraints catch it if a bug ever does.

    it('rejects a second invoice with the same number', function () {
        $first = draftInvoice($this->organisation);
        $this->postJson("/api/invoices/{$first->id}/issue");

        $second = draftInvoice($this->organisation);
        $this->postJson("/api/invoices/{$second->id}/issue");

        Invoice::query()->whereKey($second->id)->update(['number' => '0000000001']);
    })->throws(UniqueConstraintViolationException::class);

    it('rejects an issued invoice without a number', function () {
        $invoice = draftInvoice($this->organisation);

        Invoice::query()->whereKey($invoice->id)->update(['status' => 'issued']);
    })->throws(QueryException::class, 'invoices_issued_fields');
});
