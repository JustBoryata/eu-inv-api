<?php

namespace App\Support;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;

final class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $options = new Options([
            // Bundled with dompdf and covers Cyrillic, so Bulgarian names print.
            'defaultFont' => 'DejaVu Sans',
            // The view is self-contained. Never let invoice text fetch a URL or read a file.
            'isRemoteEnabled' => false,
            'chroot' => resource_path('views/invoices'),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('invoices.pdf', ['invoice' => $invoice])->render(), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Integer cents as "1 234,56", the Bulgarian convention.
     */
    public static function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.number_format(intdiv($cents, 100), 0, ',', ' ').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * "2.500" as "2,5" and "10.000" as "10".
     */
    public static function quantity(string $quantity): string
    {
        return str_replace('.', ',', self::trimDecimals($quantity));
    }

    /**
     * "20.00" as "20%" and "9.50" as "9,5%".
     */
    public static function percent(string $rate): string
    {
        return str_replace('.', ',', self::trimDecimals($rate)).'%';
    }

    /**
     * Drop trailing zeros after the decimal point. The point stops rtrim, so
     * the integer part is never touched: "0.00" becomes "0", "20" stays "20".
     */
    private static function trimDecimals(string $decimal): string
    {
        return str_contains($decimal, '.') ? rtrim(rtrim($decimal, '0'), '.') : $decimal;
    }
}
