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
}
