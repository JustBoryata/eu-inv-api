<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotDraft;
use App\Jobs\RenderInvoicePdf;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Organisation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turn a draft into a legal document: give it the organisation's next
 * number, freeze its VAT rates and both parties' details, date it, and queue
 * its PDF.
 */
final class IssueInvoice
{
    /**
     * Everything happens in one transaction. If any step fails, the number is
     * rolled back with it, so the sequence never gets a gap.
     *
     * Locks are always taken in the same order (the invoice, then the
     * organisation's sequence row), so two issues can't deadlock each other.
     *
     * @throws InvoiceNotDraft
     * @throws ValidationException
     */
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = $invoice->lockDraft();
            $invoice->load(['organisation', 'client', 'lines.vatRate']);

            $this->ensureSellerDetails($invoice->organisation);
            $this->refreshVatRates($invoice);

            $issuedAt = CarbonImmutable::now();

            $invoice->status = InvoiceStatus::Issued;
            $invoice->number = $this->nextNumber($invoice->organisation);
            $invoice->issue_date = $issuedAt->setTimezone(config()->string('invoicing.timezone'))->startOfDay();
            $invoice->issued_at = $issuedAt;
            $this->snapshotParties($invoice);
            $invoice->save();

            // afterCommit: the worker must never pick up an invoice that this
            // transaction then rolls back. That holds even when the caller has
            // wrapped this action in a transaction of its own.
            RenderInvoicePdf::dispatch($invoice)->afterCommit();

            return $invoice;
        });
    }

    private function ensureSellerDetails(Organisation $organisation): void
    {
        if ($organisation->registration_number === null || $organisation->address === null) {
            throw ValidationException::withMessages([
                'organisation' => 'Add your registration number and address (PATCH /api/organisation) before issuing an invoice.',
            ]);
        }
    }

    /**
     * A draft can sit for weeks. The rate that applies is the one in force on
     * the day of issue, so copy it again now. After this it never changes.
     */
    private function refreshVatRates(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            $line->vat_rate = $line->vatRate->rate;
            $line->save();
        }

        $invoice->recalculate();
    }

    private function nextNumber(Organisation $organisation): string
    {
        // The first issue creates the counter. ON CONFLICT DO NOTHING makes it
        // safe for two "first" issues to race: the loser waits for the winner's
        // row and then simply uses it.
        InvoiceSequence::query()->insertOrIgnore(['organisation_id' => $organisation->id]);

        // FOR UPDATE: a concurrent issue blocks here until this transaction
        // commits, then reads the number this one wrote.
        $sequence = InvoiceSequence::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();
        $sequence->last_number++;
        $sequence->save();

        return str_pad((string) $sequence->last_number, 10, '0', STR_PAD_LEFT);
    }

    private function snapshotParties(Invoice $invoice): void
    {
        $seller = $invoice->organisation;
        $buyer = $invoice->client;

        $invoice->forceFill([
            'seller_name' => $seller->name,
            'seller_registration_number' => $seller->registration_number,
            'seller_vat_number' => $seller->vat_number,
            'seller_address' => $seller->address,
            'seller_country_code' => $seller->country_code,
            'buyer_name' => $buyer->name,
            'buyer_registration_number' => $buyer->registration_number,
            'buyer_vat_number' => $buyer->vat_number,
            'buyer_address' => $buyer->address,
            'buyer_country_code' => $buyer->country_code,
        ]);
    }
}
