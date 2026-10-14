<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganisationRequest extends FormRequest
{
    /**
     * The seller details printed on invoices.
     *
     * country_code is left out on purpose: it decides which VAT rates apply,
     * so changing it would silently invalidate every draft.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('vat_number')) {
            $this->merge(['vat_number' => mb_strtoupper((string) preg_replace('/\s+/', '', $this->string('vat_number')->value()))]);
        }
    }
}
