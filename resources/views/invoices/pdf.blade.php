<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @include('brand.document-styles')
        h1, h2, p { margin-bottom: 0; }
        .header { align-items: flex-start; display: flex; justify-content: space-between; margin-bottom: 44px; }
        .brand { color: #172554; font-size: 22px; font-weight: 700; }
        .muted { color: #64748b; }
        .invoice-title { font-size: 34px; font-weight: 700; text-align: right; }
        .grid { display: grid; gap: 24px; grid-template-columns: 1fr 1fr; margin-bottom: 32px; }
        .label { color: #475569; font-size: 11px; font-weight: 700; letter-spacing: .04em; margin-bottom: 6px; text-transform: uppercase; }
        .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; }
        table { border-collapse: collapse; margin-top: 8px; width: 100%; }
        .right { text-align: right; }
        .totals { margin-left: auto; margin-top: 24px; width: 280px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 0; }
        .grand-total { border-top: 2px solid #0f172a; font-size: 18px; font-weight: 700; margin-top: 6px; padding-top: 12px; }
        .notes { margin-top: 32px; }
        .status { border: 1px solid #cbd5e1; border-radius: 999px; display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    @php
        $money = fn (int $amount) => strtoupper($invoice->currency).' '.number_format($amount / 100, 2);
    @endphp

    <div class="header">
        <div>
            <div class="brand">{{ config('brand.product_name') }}</div>
            <p class="muted">{{ config('brand.website_url') }}</p>
        </div>
        <div>
            <div class="invoice-title">Invoice</div>
            <p>{{ $invoice->number }}</p>
            <p><span class="status">{{ $invoice->status }}</span></p>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="label">Bill To</div>
            <p><strong>{{ $invoice->customer_name }}</strong></p>
            @if ($invoice->customer_email)
                <p class="muted">{{ $invoice->customer_email }}</p>
            @endif
        </div>

        <div class="box">
            <div class="label">Invoice Details</div>
            <p>Issued: {{ $invoice->issued_at?->format('M j, Y') }}</p>
            @if ($invoice->due_at)
                <p>Due: {{ $invoice->due_at->format('M j, Y') }}</p>
            @endif
            @if ($invoice->provider)
                <p class="muted">Source: {{ ucfirst($invoice->provider) }} {{ $invoice->provider_type }} {{ $invoice->provider_id }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Tax</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">{{ $money($item->unit_price) }}</td>
                    <td class="right">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                    <td class="right">{{ $money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal</span>
            <span>{{ $money($invoice->subtotal) }}</span>
        </div>
        <div class="totals-row">
            <span>Tax</span>
            <span>{{ $money($invoice->tax_total) }}</span>
        </div>
        <div class="totals-row grand-total">
            <span>Total</span>
            <span>{{ $money($invoice->total) }}</span>
        </div>
    </div>

    @if ($invoice->notes)
        <div class="notes">
            <div class="label">Notes</div>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif
</body>
</html>
