<?php

use App\Jobs\RenderInvoicePdf;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organisation;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = signIn();
    $this->organisation = $this->user->organisation;
});

/**
 * Issue a draft through the API and return it fresh from the database.
 */
function issued(Invoice $invoice): Invoice
{
    test()->postJson("/api/invoices/{$invoice->id}/issue")->assertOk();

    return $invoice->refresh();
}

describe('rendering', function () {
    it('renders the PDF when the invoice is issued', function () {
        $invoice = issued(draftInvoice($this->organisation));

        expect($invoice->pdf_path)->toBe("invoices/{$this->organisation->id}/0000000001.pdf");
        expect(Storage::disk('local')->get($invoice->pdf_path))->toStartWith('%PDF-');
    });

    it('queues the job once the issue has committed', function () {
        Queue::fake();

        $invoice = issued(draftInvoice($this->organisation));

        Queue::assertPushed(RenderInvoicePdf::class, fn (RenderInvoicePdf $job) => $job->invoice->is($invoice));
        expect($invoice->pdf_path)->toBeNull();
    });

    it('queues nothing when the issue fails', function () {
        Queue::fake();
        $this->organisation->update(['address' => null]);
        $invoice = draftInvoice($this->organisation);

        $this->postJson("/api/invoices/{$invoice->id}/issue")->assertUnprocessable();

        Queue::assertNothingPushed();
    });

    it('does nothing when run again for a rendered invoice', function () {
        $invoice = issued(draftInvoice($this->organisation));
        Storage::disk('local')->put($invoice->pdf_path, 'already rendered');

        RenderInvoicePdf::dispatchSync($invoice);

        expect(Storage::disk('local')->get($invoice->pdf_path))->toBe('already rendered');
    });

    it('renders again when the file has gone missing', function () {
        $invoice = issued(draftInvoice($this->organisation));
        Storage::disk('local')->delete($invoice->pdf_path);

        RenderInvoicePdf::dispatchSync($invoice);

        expect(Storage::disk('local')->get($invoice->pdf_path))->toStartWith('%PDF-');
    });

    it('does not undo a payment recorded while it was queued', function () {
        Queue::fake();
        $invoice = issued(draftInvoice($this->organisation));
        $this->postJson("/api/invoices/{$invoice->id}/mark-paid")->assertOk();

        // The job still holds the model as it was at issue time.
        app()->call([new RenderInvoicePdf($invoice), 'handle']);

        expect($invoice->refresh())
            ->status->value->toBe('paid')
            ->pdf_path->not->toBeNull();
    });

    it('prints the frozen details, the VAT breakdown and Cyrillic text', function () {
        $client = Client::factory()->for($this->organisation)->create(['name' => 'Акме ЕООД']);
        $invoice = issued(draftInvoice($this->organisation, [
            ['description' => 'Уеб разработка', 'quantity' => '2.5', 'unit_price' => 123456, 'vat_rate_id' => vatRate('20.00')->id],
            ['description' => 'Hotel night', 'quantity' => '1', 'unit_price' => 10000, 'vat_rate_id' => vatRate('9.00')->id],
        ], $client));
        $client->update(['name' => 'Renamed Ltd']);

        $html = view('invoices.pdf', ['invoice' => $invoice->load('lines')])->render();

        expect($html)
            ->toContain('0000000001', 'Акме ЕООД', 'Уеб разработка', '2,5', '1 234,56', '3 086,40')
            ->toContain('Данъчна основа 20%', '617,28', 'ДДС 9%', '9,00')
            ->not->toContain('Renamed Ltd');
    });
});

describe('downloading', function () {
    it('streams the PDF', function () {
        $invoice = issued(draftInvoice($this->organisation));

        $response = $this->get("/api/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->headers->get('Content-Disposition'))->toContain('invoice-0000000001.pdf');
        expect($response->streamedContent())->toStartWith('%PDF-');
    });

    it('answers 202 while the PDF is not ready, without queueing a second copy', function () {
        Queue::fake();
        $invoice = issued(draftInvoice($this->organisation));

        $this->getJson("/api/invoices/{$invoice->id}/pdf")
            ->assertAccepted()
            ->assertHeader('Retry-After', '5');
        $this->getJson("/api/invoices/{$invoice->id}/pdf")->assertAccepted();

        // The job from the issue still holds the unique lock, so the polls don't add more.
        Queue::assertPushed(RenderInvoicePdf::class, 1);
    });

    it('answers 202 and renders again when the file has gone missing', function () {
        $invoice = issued(draftInvoice($this->organisation));
        Storage::disk('local')->delete($invoice->pdf_path);

        // The queue is sync in tests, so the job runs during this request.
        $this->getJson("/api/invoices/{$invoice->id}/pdf")->assertAccepted();
        $this->get("/api/invoices/{$invoice->id}/pdf")->assertOk();
    });

    it('answers 409 for a draft', function () {
        $invoice = draftInvoice($this->organisation);

        $this->getJson("/api/invoices/{$invoice->id}/pdf")
            ->assertConflict()
            ->assertJsonPath('message', 'A draft has no PDF. Issue the invoice first.');
    });

    it("answers 404 for another tenant's invoice", function () {
        $theirs = draftInvoice(Organisation::factory()->create());

        $this->getJson("/api/invoices/{$theirs->id}/pdf")->assertNotFound();
    });

    it('reports readiness on the invoice', function () {
        Queue::fake();
        $invoice = issued(draftInvoice($this->organisation));

        $this->getJson("/api/invoices/{$invoice->id}")->assertJsonPath('data.pdf_ready', false);

        // Queue::fake() fakes dispatchSync too, so run the job by hand.
        app()->call([new RenderInvoicePdf($invoice), 'handle']);

        $this->getJson("/api/invoices/{$invoice->id}")->assertJsonPath('data.pdf_ready', true);
    });
});
