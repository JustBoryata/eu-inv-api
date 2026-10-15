<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateDraftInvoice;
use App\Actions\DeleteDraftInvoice;
use App\Actions\IssueInvoice;
use App\Actions\MarkInvoicePaid;
use App\Actions\UpdateDraftInvoice;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotIssued;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\ListInvoicesRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Jobs\RenderInvoicePdf;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(ListInvoicesRequest $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with('client')
            ->when($request->status(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->clientId(), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($request->issuedFrom(), fn ($query, $from) => $query->where('issue_date', '>=', $from))
            ->when($request->issuedTo(), fn ($query, $to) => $query->where('issue_date', '<=', $to))
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request, CreateDraftInvoice $createDraft, #[CurrentUser] User $user): JsonResponse
    {
        $invoice = $createDraft->handle($user->organisation, $request->invoiceAttributes(), $request->linesData());

        return (new InvoiceResource($invoice->load('client')))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('client', 'lines'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateDraftInvoice $updateDraft): InvoiceResource
    {
        $invoice = $updateDraft->handle($invoice, $request->invoiceAttributes(), $request->linesOrNull());

        return new InvoiceResource($invoice->load('client', 'lines'));
    }

    public function issue(Invoice $invoice, IssueInvoice $issueInvoice): InvoiceResource
    {
        $invoice = $issueInvoice->handle($invoice);

        return new InvoiceResource($invoice->load('client', 'lines'));
    }

    public function markPaid(Invoice $invoice, MarkInvoicePaid $markPaid): InvoiceResource
    {
        $invoice = $markPaid->handle($invoice);

        return new InvoiceResource($invoice->load('client', 'lines'));
    }

    /**
     * The invoice as a PDF, or 202 Accepted while it's still being rendered.
     *
     * @response 202 array{message: string}
     */
    public function pdf(Invoice $invoice): StreamedResponse|JsonResponse
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            throw InvoiceNotIssued::hasNoPdf();
        }

        $disk = Storage::disk('local');

        if ($invoice->pdf_path === null || ! $disk->exists($invoice->pdf_path)) {
            // Covers a job that is still queued, one that failed for good, and
            // a file that went missing. The job is unique per invoice, so
            // dispatching it on every poll queues it at most once.
            RenderInvoicePdf::dispatch($invoice);

            return response()->json(['message' => 'The PDF is being rendered. Try again in a few seconds.'], 202, ['Retry-After' => '5']);
        }

        return $disk->response($invoice->pdf_path, "invoice-{$invoice->number}.pdf", ['Content-Type' => 'application/pdf']);
    }

    public function destroy(Invoice $invoice, DeleteDraftInvoice $deleteDraft): Response
    {
        $deleteDraft->handle($invoice);

        return response()->noContent();
    }
}
