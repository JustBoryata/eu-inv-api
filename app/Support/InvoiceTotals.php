<?php

namespace App\Support;

/**
 * An invoice's totals in cents, with the VAT breakdown per rate.
 */
final readonly class InvoiceTotals
{
    /**
     * @param  list<array{rate: string, net: int, vat: int}>  $breakdown  Highest rate first.
     */
    public function __construct(
        public int $net,
        public int $vat,
        public array $breakdown,
    ) {}

    public function gross(): int
    {
        return $this->net + $this->vat;
    }
}
