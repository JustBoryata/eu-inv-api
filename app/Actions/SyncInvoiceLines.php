<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\VatRate;
use App\Support\InvoiceCalculator;
use LogicException;

/**
 * Replace a draft's lines and recompute its totals.
 *
 * Replacing every line, rather than diffing by id, keeps the API simple: the
 * client always sends the full list in the order it should print.
 *
 * @phpstan-type LineData array{description: string, quantity: string, unit_price: int, vat_rate_id: int}
 */
final class SyncInvoiceLines
{
    /**
     * Expects to run inside the caller's transaction, on a draft it has locked.
     *
     * @param  list<LineData>  $lines
     */
    public function handle(Invoice $invoice, array $lines): void
    {
        $rates = VatRate::query()
            ->whereKey(array_column($lines, 'vat_rate_id'))
            ->get()
            ->keyBy('id');

        $invoice->lines()->delete();

        $models = [];

        foreach ($lines as $index => $line) {
            $model = new InvoiceLine($line);
            $model->position = $index + 1;
            // The form request has already checked that every rate exists.
            $model->vat_rate = $rates->get($line['vat_rate_id'])->rate
                ?? throw new LogicException("Unknown VAT rate {$line['vat_rate_id']}.");
            $model->net_amount = InvoiceCalculator::lineNet($line['quantity'], $line['unit_price']);
            $models[] = $model;
        }

        $invoice->lines()->saveMany($models);
        $invoice->load('lines');
        $invoice->recalculate();
        $invoice->save();
    }
}
