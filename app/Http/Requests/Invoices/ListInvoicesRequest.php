<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Requests\PaginatedRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends PaginatedRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', Rule::enum(InvoiceStatus::class)],
            'client_id' => ['sometimes', 'integer'],
            // Drafts have no issue date, so either filter leaves them out.
            'issued_from' => ['sometimes', 'date_format:Y-m-d'],
            'issued_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:issued_from'],
        ];
    }

    public function status(): ?InvoiceStatus
    {
        return $this->enum('status', InvoiceStatus::class);
    }

    public function clientId(): ?int
    {
        return $this->filled('client_id') ? $this->integer('client_id') : null;
    }

    public function issuedFrom(): ?string
    {
        return $this->filled('issued_from') ? $this->string('issued_from')->value() : null;
    }

    public function issuedTo(): ?string
    {
        return $this->filled('issued_to') ? $this->string('issued_to')->value() : null;
    }
}
