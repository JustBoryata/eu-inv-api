<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type LineData from SyncInvoiceLines
 */
final class CreateDraftInvoice
{
    public function __construct(private SyncInvoiceLines $syncLines) {}

    /**
     * A draft has no number yet. Numbers are only assigned when the invoice
     * is issued, so deleting a draft never leaves a gap in the sequence.
     *
     * @param  array{client_id: int, due_date?: ?string, notes?: ?string}  $attributes
     * @param  list<LineData>  $lines
     */
    public function handle(Organisation $organisation, array $attributes, array $lines): Invoice
    {
        return DB::transaction(function () use ($organisation, $attributes, $lines): Invoice {
            $invoice = new Invoice($attributes);
            $invoice->organisation_id = $organisation->id;
            $invoice->save();

            $this->syncLines->handle($invoice, $lines);

            return $invoice;
        });
    }
}
