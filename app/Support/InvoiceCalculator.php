<?php

namespace App\Support;

use BcMath\Number;
use InvalidArgumentException;
use RoundingMode;

/**
 * Invoice arithmetic. Money is integer cents; quantities and rates are
 * decimal strings, multiplied with BcMath so no float is ever involved.
 *
 * Rounding is half-up (half away from zero, which is the same thing for the
 * non-negative amounts v1 allows) and happens exactly twice: once per line
 * for its net amount, and once per VAT rate group for the VAT.
 */
final class InvoiceCalculator
{
    /**
     * Net amount of one line in cents.
     */
    public static function lineNet(string $quantity, int $unitPrice): int
    {
        return self::toCents(self::number($quantity)->mul($unitPrice));
    }

    /**
     * Sum the lines per VAT rate, then compute each group's VAT once.
     *
     * Rounding VAT per group rather than per line is what produces the per-rate
     * breakdown an invoice has to show, and stops per-line rounding errors from
     * adding up (three lines of 5 cents at 9% give 1 cent of VAT, not 0).
     *
     * @param  iterable<array{rate: string, net: int}>  $lines
     */
    public static function totals(iterable $lines): InvoiceTotals
    {
        /** @var array<string, array{rate: Number, net: int}> $groups */
        $groups = [];

        foreach ($lines as $line) {
            $rate = self::number($line['rate']);
            // Two decimal places, so "20" and "20.00" fall into the same group.
            $key = $rate->add(0, 2)->value;

            $groups[$key] ??= ['rate' => $rate, 'net' => 0];
            $groups[$key]['net'] += $line['net'];
        }

        uasort($groups, fn (array $a, array $b): int => $b['rate']->compare($a['rate']));

        $breakdown = [];
        $netTotal = 0;
        $vatTotal = 0;

        foreach ($groups as $key => $group) {
            $vat = self::toCents((new Number($group['net']))->mul($group['rate'])->div(100));

            $breakdown[] = ['rate' => (string) $key, 'net' => $group['net'], 'vat' => $vat];
            $netTotal += $group['net'];
            $vatTotal += $vat;
        }

        return new InvoiceTotals($netTotal, $vatTotal, $breakdown);
    }

    private static function number(string $value): Number
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Expected a decimal number, got \"{$value}\".");
        }

        return new Number($value);
    }

    private static function toCents(Number $amount): int
    {
        return (int) $amount->round(0, RoundingMode::HalfAwayFromZero)->value;
    }
}
