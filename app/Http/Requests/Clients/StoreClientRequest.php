<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            // Any country, not just supported ones: an organisation can sell abroad.
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Only touch keys that were sent. Merging a missing key as null would
        // make a partial update (PATCH) clear fields it never mentioned.
        if ($this->filled('country_code')) {
            $this->merge(['country_code' => mb_strtoupper(trim($this->string('country_code')->value()))]);
        }

        if ($this->filled('vat_number')) {
            // VAT numbers are often written with spaces ("BG 123 456 789").
            $this->merge(['vat_number' => mb_strtoupper((string) preg_replace('/\s+/', '', $this->string('vat_number')->value()))]);
        }
    }
}
