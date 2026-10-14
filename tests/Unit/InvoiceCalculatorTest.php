<?php

use App\Support\InvoiceCalculator;

describe('line net amount', function () {
    it('multiplies quantity by unit price', function () {
        expect(InvoiceCalculator::lineNet('3', 1250))->toBe(3750);
    });

    it('rounds half a cent up', function () {
        // 2.5 × 19.99 = 49.975
        expect(InvoiceCalculator::lineNet('2.5', 1999))->toBe(4998);
    });

    it('rounds below half a cent down', function () {
        // 0.333 × 1.00 = 0.333
        expect(InvoiceCalculator::lineNet('0.333', 100))->toBe(33);
    });

    it('is exact where floats are not', function () {
        // As floats, 0.285 × 100 is 28.499999999999996, and round() gives 28.
        expect(InvoiceCalculator::lineNet('0.285', 100))->toBe(29);
    });

    it('rejects a quantity that is not a number', function () {
        InvoiceCalculator::lineNet('two', 100);
    })->throws(InvalidArgumentException::class);
});

describe('totals', function () {
    it('groups lines by VAT rate, highest rate first', function () {
        $totals = InvoiceCalculator::totals([
            ['rate' => '9.00', 'net' => 10000],
            ['rate' => '20.00', 'net' => 5000],
            ['rate' => '20.00', 'net' => 2500],
            ['rate' => '0.00', 'net' => 1000],
        ]);

        expect($totals->breakdown)->toBe([
            ['rate' => '20.00', 'net' => 7500, 'vat' => 1500],
            ['rate' => '9.00', 'net' => 10000, 'vat' => 900],
            ['rate' => '0.00', 'net' => 1000, 'vat' => 0],
        ])
            ->and($totals->net)->toBe(18500)
            ->and($totals->vat)->toBe(2400)
            ->and($totals->gross())->toBe(20900);
    });

    it('rounds VAT once per rate group, not per line', function () {
        // Per line, 5 cents at 9% is 0.45 cents and rounds to 0, three times.
        // Per group, 15 cents at 9% is 1.35 cents and rounds to 1.
        $totals = InvoiceCalculator::totals([
            ['rate' => '9.00', 'net' => 5],
            ['rate' => '9.00', 'net' => 5],
            ['rate' => '9.00', 'net' => 5],
        ]);

        expect($totals->vat)->toBe(1);
    });

    it('rounds half a cent of VAT up', function () {
        // 2.50 at 9% = 0.225 → 0.23
        expect(InvoiceCalculator::totals([['rate' => '9.00', 'net' => 250]])->vat)->toBe(23);
    });

    it('treats "20" and "20.00" as the same rate', function () {
        $totals = InvoiceCalculator::totals([
            ['rate' => '20', 'net' => 100],
            ['rate' => '20.00', 'net' => 100],
        ]);

        expect($totals->breakdown)->toBe([['rate' => '20.00', 'net' => 200, 'vat' => 40]]);
    });

    it('is zero for no lines', function () {
        $totals = InvoiceCalculator::totals([]);

        expect($totals->breakdown)->toBe([])
            ->and($totals->gross())->toBe(0);
    });
});
