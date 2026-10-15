<?php

namespace App\Http\Requests\Invoices;

use App\Actions\SyncInvoiceLines;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and updating a draft.
 *
 * @phpstan-import-type LineData from SyncInvoiceLines
 */
abstract class InvoiceRequest extends FormRequest
{
    /**
     * Rule::exists runs a plain query that skips Eloquent's global scopes, so
     * both exists rules repeat the tenant filter. Without it a draft could
     * point at another organisation's client.
     *
     * The caps on quantity and unit_price keep quantity × unit_price far below
     * PHP_INT_MAX.
     *
     * The comments on the rules are API docs: Scramble publishes them as the
     * field descriptions.
     *
     * @return array<string, list<mixed>>
     */
    protected function invoiceRules(): array
    {
        $organisation = $this->organisation();

        return [
            // One of your organisation's clients.
            'client_id' => ['integer', Rule::exists(Client::class, 'id')->where('organisation_id', $organisation?->id)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Replaces all of the draft's lines.
            'lines' => ['array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            // Up to 3 decimal places.
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:1000000'],
            // Net price per unit, in cents.
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:10000000000'],
            // A rate for your organisation's country, from GET /vat-rates.
            'lines.*.vat_rate_id' => ['required', 'integer', Rule::exists(VatRate::class, 'id')->where('country_code', $organisation?->country_code)],
        ];
    }

    /**
     * @return array{client_id?: int, due_date?: ?string, notes?: ?string}
     */
    public function invoiceAttributes(): array
    {
        /** @var array{client_id?: int|string, due_date?: ?string, notes?: ?string} $attributes */
        $attributes = Arr::only($this->validated(), ['client_id', 'due_date', 'notes']);

        if (isset($attributes['client_id'])) {
            $attributes['client_id'] = (int) $attributes['client_id'];
        }

        return $attributes;
    }

    /**
     * @return list<LineData>
     */
    public function linesData(): array
    {
        /** @var array<array-key, array{description: string, quantity: int|float|string, unit_price: int|string, vat_rate_id: int|string}> $lines */
        $lines = $this->validated('lines', []);

        return array_map(fn (array $line): array => [
            'description' => $line['description'],
            // JSON numbers arrive as int or float. Turn them into a decimal
            // string straight away; the calculator never sees a float.
            'quantity' => (string) $line['quantity'],
            'unit_price' => (int) $line['unit_price'],
            'vat_rate_id' => (int) $line['vat_rate_id'],
        ], array_values($lines));
    }

    /**
     * Every invoice route sits behind auth:sanctum, so this is null only when
     * rules() runs outside a request, as when Scramble reads it to build the
     * API docs. The exists rules then filter on null and match nothing, so
     * they fail closed.
     */
    protected function organisation(): ?Organisation
    {
        $user = $this->user();

        return $user instanceof User ? $user->organisation : null;
    }
}
