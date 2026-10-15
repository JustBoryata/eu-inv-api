@php
    use App\Support\InvoicePdf;
    /** @var \App\Models\Invoice $invoice */
@endphp
<!doctype html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Фактура {{ $invoice->number }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #1a1a1a; }
        h1 { font-size: 18pt; margin: 0; letter-spacing: 0.5pt; }
        .muted { color: #666; }
        .header td { vertical-align: top; }
        .meta { text-align: right; }
        .meta strong { font-size: 11pt; }
        .parties { margin-top: 10mm; width: 100%; }
        .parties td { width: 50%; vertical-align: top; padding-right: 6mm; }
        .label { font-size: 7pt; text-transform: uppercase; color: #666; letter-spacing: 0.4pt; margin-bottom: 1.5mm; }
        table { border-collapse: collapse; width: 100%; }
        .lines { margin-top: 10mm; }
        .lines th { font-size: 7pt; text-transform: uppercase; color: #666; text-align: left; border-bottom: 1px solid #1a1a1a; padding: 1.5mm 1mm; }
        .lines td { border-bottom: 1px solid #ddd; padding: 2mm 1mm; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 6mm; width: 60%; margin-left: 40%; }
        .totals td { padding: 1.2mm 1mm; }
        .totals .grand td { border-top: 1px solid #1a1a1a; font-weight: bold; font-size: 11pt; padding-top: 2mm; }
        .notes { margin-top: 10mm; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>ФАКТУРА</h1>
                <div class="muted">Invoice · Оригинал / Original</div>
            </td>
            <td class="meta">
                <div class="muted">№ / No.</div>
                <strong>{{ $invoice->number }}</strong>
                <div style="margin-top: 2mm;">
                    <span class="muted">Дата на издаване / Issue date:</span> {{ $invoice->issue_date?->format('d.m.Y') }}<br>
                    <span class="muted">Дата на данъчно събитие / Tax point:</span> {{ $invoice->issue_date?->format('d.m.Y') }}
                    @if ($invoice->due_date)
                        <br><span class="muted">Падеж / Due date:</span> {{ $invoice->due_date->format('d.m.Y') }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Доставчик / Supplier</div>
                <strong>{{ $invoice->seller_name }}</strong><br>
                {!! nl2br(e($invoice->seller_address)) !!}, {{ $invoice->seller_country_code }}<br>
                ЕИК / Reg. No.: {{ $invoice->seller_registration_number }}
                @if ($invoice->seller_vat_number)
                    <br>ДДС № / VAT No.: {{ $invoice->seller_vat_number }}
                @endif
            </td>
            <td>
                <div class="label">Получател / Customer</div>
                <strong>{{ $invoice->buyer_name }}</strong><br>
                {!! nl2br(e($invoice->buyer_address)) !!}, {{ $invoice->buyer_country_code }}
                @if ($invoice->buyer_registration_number)
                    <br>ЕИК / Reg. No.: {{ $invoice->buyer_registration_number }}
                @endif
                @if ($invoice->buyer_vat_number)
                    <br>ДДС № / VAT No.: {{ $invoice->buyer_vat_number }}
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 6%;">№</th>
                <th style="width: 38%;">Описание / Description</th>
                <th class="num">Кол. / Qty</th>
                <th class="num">Ед. цена / Unit price</th>
                <th class="num">ДДС / VAT</th>
                <th class="num">Стойност / Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ InvoicePdf::quantity($line->quantity) }}</td>
                    <td class="num">{{ InvoicePdf::money($line->unit_price) }}</td>
                    <td class="num">{{ InvoicePdf::percent($line->vat_rate) }}</td>
                    <td class="num">{{ InvoicePdf::money($line->net_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @foreach ($invoice->vat_breakdown as $group)
            <tr>
                <td>Данъчна основа {{ InvoicePdf::percent($group['rate']) }} / Taxable amount</td>
                <td class="num">{{ InvoicePdf::money($group['net']) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>ДДС {{ InvoicePdf::percent($group['rate']) }} / VAT</td>
                <td class="num">{{ InvoicePdf::money($group['vat']) }} {{ $invoice->currency }}</td>
            </tr>
        @endforeach
        <tr class="grand">
            <td>Сума за плащане / Total due</td>
            <td class="num">{{ InvoicePdf::money($invoice->gross_total) }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <div class="label">Бележки / Notes</div>
            {!! nl2br(e($invoice->notes)) !!}
        </div>
    @endif

    <div class="footer">{{ $invoice->seller_name }} · {{ $invoice->number }}</div>
</body>
</html>
