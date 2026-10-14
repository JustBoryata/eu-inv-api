<?php

namespace App\Actions;

use App\Exceptions\InvoiceNotDraft;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type LineData from SyncInvoiceLines
 */
final class UpdateDraftInvoice
{
    public function __construct(private SyncInvoiceLines $syncLines) {}

    /**
     * @param  array{client_id?: int, due_date?: ?string, notes?: ?string}  $attributes
     * @param  list<LineData>|null  $lines  null keeps the current lines.
     *
     * @throws InvoiceNotDraft
     */
    public function handle(Invoice $invoice, array $attributes, ?array $lines): Invoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $lines): Invoice {
            $invoice = $invoice->lockDraft();
            $invoice->update($attributes);

            if ($lines !== null) {
                $this->syncLines->handle($invoice, $lines);
            }

            return $invoice;
        });
    }
}
