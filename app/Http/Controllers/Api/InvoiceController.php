<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateDraftInvoice;
use App\Actions\DeleteDraftInvoice;
use App\Actions\IssueInvoice;
use App\Actions\MarkInvoicePaid;
use App\Actions\UpdateDraftInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\ListInvoicesRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function destroy(Invoice $invoice, DeleteDraftInvoice $deleteDraft): Response
    {
        $deleteDraft->handle($invoice);

        return response()->noContent();
    }
}
