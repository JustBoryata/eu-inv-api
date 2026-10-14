<?php

namespace App\Actions;

use App\Exceptions\InvoiceNotDraft;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

final class DeleteDraftInvoice
{
    /**
     * Its lines go with it (ON DELETE CASCADE).
     *
     * @throws InvoiceNotDraft
     */
    public function handle(Invoice $invoice): void
    {
        DB::transaction(fn () => $invoice->lockDraft()->delete());
    }
}
