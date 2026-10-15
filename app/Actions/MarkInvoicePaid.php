<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotIssued;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class MarkInvoicePaid
{
    /**
     * The row lock makes two concurrent calls run one after the other, so the
     * second one sees "paid" and gets a 409 instead of overwriting paid_at.
     *
     * @throws InvoiceNotIssued
     */
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($invoice->status !== InvoiceStatus::Issued) {
                throw InvoiceNotIssued::cannotBePaid();
            }

            $invoice->status = InvoiceStatus::Paid;
            $invoice->paid_at = CarbonImmutable::now();
            $invoice->save();

            return $invoice;
        });
    }
}
