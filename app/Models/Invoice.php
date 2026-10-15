<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotDraft;
use App\Models\Concerns\BelongsToOrganisation;
use App\Support\InvoiceCalculator;
use App\Support\InvoiceTotals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status, totals and organisation_id are not fillable: only the actions in
 * App\Actions set them.
 *
 * @property InvoiceStatus $status
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $issue_date
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $paid_at
 * @property-read Organisation $organisation
 * @property-read Client $client
 * @property list<array{rate: string, net: int, vat: int}> $vat_breakdown
 */
#[Fillable(['client_id', 'due_date', 'notes'])]
class Invoice extends Model
{
    use BelongsToOrganisation;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'EUR',
        'vat_breakdown' => '[]',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /**
     * Re-read this invoice with a row lock and make sure it is still a draft.
     *
     * Call it inside a transaction. The lock makes a concurrent edit and issue
     * of the same invoice run one after the other, so an edit can't land on an
     * invoice that was issued a moment earlier.
     *
     * @throws InvoiceNotDraft
     */
    public function lockDraft(): self
    {
        $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->status !== InvoiceStatus::Draft) {
            throw new InvoiceNotDraft;
        }

        return $locked;
    }

    /**
     * Recompute the totals and the VAT breakdown from the loaded lines.
     */
    public function recalculate(): void
    {
        $totals = InvoiceCalculator::totals($this->lines->map(fn (InvoiceLine $line): array => [
            'rate' => $line->vat_rate,
            'net' => $line->net_amount,
        ]));

        $this->applyTotals($totals);
    }

    private function applyTotals(InvoiceTotals $totals): void
    {
        $this->net_total = $totals->net;
        $this->vat_total = $totals->vat;
        $this->gross_total = $totals->gross();
        $this->vat_breakdown = $totals->breakdown;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'due_date' => 'immutable_date',
            'issue_date' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'net_total' => 'integer',
            'vat_total' => 'integer',
            'gross_total' => 'integer',
            'vat_breakdown' => 'array',
        ];
    }
}
