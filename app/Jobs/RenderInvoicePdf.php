<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\InvoicePdf;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an issued invoice to PDF and records where the file is.
 *
 * Safe to run any number of times: the path is derived from the invoice
 * number, so a retry overwrites the same file, and a run that finds the file
 * already there does nothing. ShouldBeUnique keeps at most one copy per
 * invoice in the queue, so GET /pdf can dispatch it again on every poll.
 */
final class RenderInvoicePdf implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30];

    /**
     * Release the lock eventually even if a worker dies mid-job.
     */
    public int $uniqueFor = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Invoice $invoice) {}

    public function uniqueId(): string
    {
        return (string) $this->invoice->id;
    }

    public function handle(InvoicePdf $pdf): void
    {
        $invoice = $this->invoice;
        $disk = Storage::disk('local');

        if ($invoice->status === InvoiceStatus::Draft) {
            return;
        }

        if ($invoice->pdf_path !== null && $disk->exists($invoice->pdf_path)) {
            return;
        }

        $path = sprintf('invoices/%d/%s.pdf', $invoice->organisation_id, $invoice->number);
        $disk->put($path, $pdf->render($invoice->load('lines')));

        // A plain update: the status may have moved on to "paid" since the
        // job was queued, and saving the stale model would undo that.
        Invoice::query()->whereKey($invoice->getKey())->update(['pdf_path' => $path]);
    }
}
