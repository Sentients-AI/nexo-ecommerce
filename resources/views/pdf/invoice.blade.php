<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 13px; color: #1e293b; background: #fff; }

        .page { padding: 40px 48px; max-width: 800px; margin: 0 auto; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #6366f1; padding-bottom: 24px; margin-bottom: 32px; }
        .brand { font-size: 22px; font-weight: 700; color: #6366f1; }
        .brand-sub { font-size: 12px; color: #64748b; margin-top: 4px; }
        .invoice-meta { text-align: right; }
        .invoice-meta h1 { font-size: 28px; font-weight: 700; color: #1e293b; letter-spacing: 1px; }
        .invoice-meta .number { font-size: 13px; color: #64748b; margin-top: 4px; }
        .invoice-meta .date { font-size: 12px; color: #94a3b8; margin-top: 2px; }

        /* Parties */
        .parties { display: flex; justify-content: space-between; margin-bottom: 32px; gap: 24px; }
        .party { flex: 1; }
        .party h3 { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 8px; }
        .party p { font-size: 13px; color: #1e293b; line-height: 1.6; }
        .party .name { font-weight: 600; font-size: 14px; }

        /* Status badge */
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 24px; }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-other { background: #f1f5f9; color: #475569; }

        /* Items table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead tr { background: #f8fafc; }
        thead th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        thead th.right { text-align: right; }
        tbody td { padding: 12px; font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        tbody td.right { text-align: right; }
        tbody td.sku { font-size: 11px; color: #94a3b8; }
        tbody tr:last-child td { border-bottom: none; }

        /* Totals */
        .totals { width: 280px; margin-left: auto; }
        .totals table { margin-bottom: 0; }
        .totals tr td { padding: 6px 12px; border: none; }
        .totals tr td:first-child { color: #64748b; }
        .totals tr td:last-child { text-align: right; font-weight: 500; }
        .totals .discount td { color: #16a34a; }
        .totals .total-row { border-top: 2px solid #e2e8f0; }
        .totals .total-row td { padding-top: 12px; font-size: 15px; font-weight: 700; color: #1e293b; }

        /* Footer */
        .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="brand">{{ $tenant?->name ?? config('app.name') }}</div>
            @if($tenant?->email)
                <div class="brand-sub">{{ $tenant->email }}</div>
            @endif
        </div>
        <div class="invoice-meta">
            <h1>INVOICE</h1>
            <div class="number">#{{ $order->order_number }}</div>
            <div class="date">{{ $order->created_at->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Status --}}
    @php
        $statusClass = match($order->status->value) {
            'paid', 'fulfilled', 'shipped' => 'status-paid',
            'pending' => 'status-pending',
            default => 'status-other',
        };
    @endphp
    <span class="status-badge {{ $statusClass }}">{{ ucfirst($order->status->value) }}</span>

    {{-- Parties --}}
    <div class="parties">
        <div class="party">
            <h3>From</h3>
            <p class="name">{{ $tenant?->name ?? config('app.name') }}</p>
            @if($tenant?->email)
                <p>{{ $tenant->email }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Bill To</h3>
            <p class="name">{{ $order->user->name }}</p>
            <p>{{ $order->user->email }}</p>
            @if($shippingAddress)
                <p style="margin-top:6px;">
                    {{ $shippingAddress['address_line_1'] ?? '' }}<br>
                    @if(!empty($shippingAddress['address_line_2'])){{ $shippingAddress['address_line_2'] }}<br>@endif
                    {{ $shippingAddress['city'] ?? '' }}@if(!empty($shippingAddress['state'])), {{ $shippingAddress['state'] }}@endif {{ $shippingAddress['postal_code'] ?? '' }}<br>
                    {{ $shippingAddress['country'] ?? '' }}
                </p>
            @endif
        </div>
    </div>

    {{-- Line items --}}
    <table>
        <thead>
            <tr>
                <th style="width:45%">Item</th>
                <th>SKU</th>
                <th class="right">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product?->name ?? 'Unknown Product' }}</td>
                <td class="sku">{{ $item->product?->sku ?? '—' }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ $formatPrice($item->price_cents_snapshot) }}</td>
                <td class="right">{{ $formatPrice($item->price_cents_snapshot * $item->quantity) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td>{{ $formatPrice($order->subtotal_cents) }}</td>
            </tr>
            @if(($order->discount_cents ?? 0) > 0)
            <tr class="discount">
                <td>Discount</td>
                <td>−{{ $formatPrice($order->discount_cents) }}</td>
            </tr>
            @endif
            @if(($order->loyalty_discount_cents ?? 0) > 0)
            <tr class="discount">
                <td>Loyalty Points</td>
                <td>−{{ $formatPrice($order->loyalty_discount_cents) }}</td>
            </tr>
            @endif
            <tr>
                <td>Tax</td>
                <td>{{ $formatPrice($order->tax_cents) }}</td>
            </tr>
            <tr>
                <td>Shipping</td>
                <td>{{ $formatPrice($order->shipping_cost_cents) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td>{{ $formatPrice($order->total_cents) }}</td>
            </tr>
            @if(($order->refunded_amount_cents ?? 0) > 0)
            <tr class="discount">
                <td>Refunded</td>
                <td>−{{ $formatPrice($order->refunded_amount_cents) }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Thank you for your order · {{ $order->order_number }} · Generated {{ now()->format('d M Y H:i') }} UTC
    </div>

</div>
</body>
</html>
