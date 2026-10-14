<?php

namespace App\Http\Requests\Invoices;

use App\Actions\SyncInvoiceLines;

/**
 * @phpstan-import-type LineData from SyncInvoiceLines
 */
class UpdateInvoiceRequest extends InvoiceRequest
{
    /**
     * Every field is optional. If "lines" is sent, it replaces all lines.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->invoiceRules();
        $rules['client_id'] = ['sometimes', ...$rules['client_id']];
        $rules['lines'] = ['sometimes', ...$rules['lines']];

        return $rules;
    }

    /**
     * @return list<LineData>|null
     */
    public function linesOrNull(): ?array
    {
        return $this->has('lines') ? $this->linesData() : null;
    }
}
