<?php

namespace App\Http\Requests\Invoices;

class StoreInvoiceRequest extends InvoiceRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->invoiceRules();
        $rules['client_id'] = ['required', ...$rules['client_id']];
        $rules['lines'] = ['required', ...$rules['lines']];

        return $rules;
    }

    /**
     * @return array{client_id: int, due_date?: ?string, notes?: ?string}
     */
    public function invoiceAttributes(): array
    {
        $attributes = parent::invoiceAttributes();

        return ['client_id' => (int) ($attributes['client_id'] ?? 0)] + $attributes;
    }
}
