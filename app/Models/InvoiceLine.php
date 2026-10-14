<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lines have no tenant scope of their own: they're only ever reached through
 * an invoice, which has one.
 *
 * @property string $quantity Decimal with three places, e.g. "2.500".
 * @property string $vat_rate Percentage with two places, e.g. "20.00".
 */
#[Fillable(['description', 'quantity', 'unit_price', 'vat_rate_id'])]
class InvoiceLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<VatRate, $this>
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'net_amount' => 'integer',
        ];
    }
}
