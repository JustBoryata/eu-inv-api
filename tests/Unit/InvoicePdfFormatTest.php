<?php

use App\Support\InvoicePdf;

it('formats cents the Bulgarian way', function (int $cents, string $expected) {
    expect(InvoicePdf::money($cents))->toBe($expected);
})->with([
    [0, '0,00'],
    [5, '0,05'],
    [123456, '1 234,56'],
    [100000000, '1 000 000,00'],
    [-150, '-1,50'],
]);

it('drops trailing decimal zeros from quantities and rates', function () {
    expect(InvoicePdf::quantity('2.500'))->toBe('2,5')
        ->and(InvoicePdf::quantity('10.000'))->toBe('10')
        ->and(InvoicePdf::quantity('0.125'))->toBe('0,125')
        ->and(InvoicePdf::percent('20.00'))->toBe('20%')
        ->and(InvoicePdf::percent('0.00'))->toBe('0%')
        ->and(InvoicePdf::percent('9.50'))->toBe('9,5%')
        ->and(InvoicePdf::percent('20'))->toBe('20%');
});
