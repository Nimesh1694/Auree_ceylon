<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $meta['document_title'] }} — {{ $order['order_number'] }}</title>
    <style>
        @page {
            margin: 12mm 15mm 12mm 15mm;
            size: a4 portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #1e293b;
            margin: 0;
            padding: 0;
            position: relative;
        }

        /* Watermark styling */
        .watermark {
            position: fixed;
            top: 35%;
            left: 5%;
            width: 90%;
            text-align: center;
            opacity: 0.08;
            font-size: 60px;
            font-weight: 800;
            letter-spacing: 6px;
            color: #0f172a;
            transform: rotate(-30deg);
            z-index: -1000;
            text-transform: uppercase;
        }

        .watermark-void {
            color: #dc2626 !important;
            opacity: 0.12 !important;
        }

        .watermark-paid {
            color: #059669 !important;
            opacity: 0.10 !important;
        }

        /* Header Table */
        table.full-width {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 3px;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
        }

        .brand-gold {
            color: #b45309;
        }

        .brand-tagline {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 1.5px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 3px;
            margin-bottom: 8px;
        }

        .company-info {
            font-size: 9.5px;
            color: #475569;
            line-height: 1.4;
        }

        .doc-title-block {
            text-align: right;
        }

        .doc-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 6px 0;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            border-radius: 4px;
        }

        .badge-paid {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-void {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .divider {
            height: 2px;
            background: #e2e8f0;
            margin: 16px 0 14px 0;
        }

        .divider-gold {
            height: 2px;
            background: #b45309;
            margin-bottom: 14px;
        }

        /* Two-Column Info Cards */
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .info-card-title {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .info-line {
            font-size: 10px;
            color: #1e293b;
            line-height: 1.5;
        }

        .info-label {
            color: #64748b;
            font-weight: 500;
        }

        .info-val {
            font-weight: 600;
            color: #0f172a;
        }

        /* Items Table */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        table.items-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 7px 10px;
            border: none;
        }

        table.items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            vertical-align: middle;
        }

        table.items-table tr.even {
            background-color: #f8fafc;
        }

        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Totals Table */
        .totals-table {
            width: 48%;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .totals-table td {
            padding: 5px 8px;
            font-size: 10px;
        }

        .totals-table tr.grand-total td {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            padding: 8px;
            background-color: #f8fafc;
        }

        /* Payment Status Block */
        .payment-box {
            margin-top: 14px;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 9.5px;
        }

        .payment-box-paid {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .payment-box-pending {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .payment-box-void {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .payment-box-title {
            font-weight: 700;
            font-size: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        /* Footer */
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8.5px;
            color: #94a3b8;
            line-height: 1.4;
        }
    </style>
</head>
<body>

@if(!empty($meta['watermark']))
    <div class="watermark {{ in_array($meta['watermark'], ['CANCELLED / VOID', 'RETURNED / VOID', 'REFUSED / VOID']) ? 'watermark-void' : 'watermark-paid' }}">
        {{ $meta['watermark'] }}
    </div>
@endif

    <!-- Header Section -->
    <table class="full-width header-table">
        <tr>
            <td style="width: 55%;">
                <h1 class="brand-title">AURA <span class="brand-gold">LUXE</span></h1>
                <div class="brand-tagline">{{ $company['tagline'] }}</div>
                <div class="company-info">
                    {{ $company['address_line_1'] }}<br/>
                    {{ $company['address_line_2'] }}, {{ $company['country'] }}<br/>
                    Tel: {{ $company['phone'] }} &bull; Email: {{ $company['email'] }}<br/>
                    Web: {{ $company['web'] }}
                </div>
            </td>
            <td style="width: 45%;" class="doc-title-block">
                <div class="doc-title">{{ $meta['document_title'] }}</div>
                <div style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    REF: {{ $order['order_number'] }}
                </div>
                <div>
                    @if($meta['is_paid'])
                        <span class="badge badge-paid">&#10003; {{ $meta['status_badge'] }}</span>
                    @elseif(in_array($order['status'], ['cancelled', 'returned', 'refused']))
                        <span class="badge badge-void">&#10007; {{ $meta['status_badge'] }}</span>
                    @else
                        <span class="badge badge-pending">&#9679; {{ $meta['status_badge'] }}</span>
                    @endif
                </div>
                <div style="margin-top: 8px; font-size: 9.5px; color: #64748b;">
                    <strong>Date:</strong> {{ $order['placed_at_formatted'] }}<br/>
                    <strong>Order Status:</strong> {{ $order['status_label'] }}
                </div>
            </td>
        </tr>
    </table>

    <div class="divider-gold"></div>

    <!-- Customer & Order Meta Information -->
    <table class="full-width" style="margin-bottom: 8px;">
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <div class="info-card">
                    <div class="info-card-title">Customer & Delivery Information</div>
                    <div class="info-line"><span class="info-label">Customer Name:</span> <span class="info-val">{{ $customer['name'] }}</span></div>
                    <div class="info-line"><span class="info-label">Contact Phone:</span> <span class="info-val">{{ $customer['phone'] }}</span></div>
                    @if(!empty($customer['email']) && $customer['email'] !== 'N/A')
                        <div class="info-line"><span class="info-label">Email Address:</span> <span class="info-val">{{ $customer['email'] }}</span></div>
                    @endif
                    <div class="info-line" style="margin-top: 4px;">
                        <span class="info-label">Delivery Address:</span><br/>
                        <span class="info-val">
                            {{ $customer['shipping_address'] }}<br/>
                            {{ $customer['city'] }}, {{ $customer['district'] }} {{ $customer['postal_code'] }}
                        </span>
                    </div>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <div class="info-card">
                    <div class="info-card-title">Order & Payment Summary</div>
                    <div class="info-line"><span class="info-label">Order Number:</span> <span class="info-val">{{ $order['order_number'] }}</span></div>
                    <div class="info-line"><span class="info-label">Order Date:</span> <span class="info-val">{{ $order['placed_at_formatted'] }}</span></div>
                    <div class="info-line"><span class="info-label">Payment Method:</span> <span class="info-val">{{ $order['payment_method_label'] }}</span></div>
                    @if($delivery && !empty($delivery['courier_name']))
                        <div class="info-line" style="margin-top: 4px;">
                            <span class="info-label">Courier Partner:</span> <span class="info-val">{{ $delivery['courier_name'] }}</span>
                            @if(!empty($delivery['tracking_number']))
                                <br/><span class="info-label">Tracking Number:</span> <span class="info-val">{{ $delivery['tracking_number'] }}</span>
                            @endif
                        </div>
                    @endif
                    @if($meta['is_paid'] && !empty($payment['collected_at']))
                        <div class="info-line" style="margin-top: 4px; color: #15803d;">
                            <span class="info-label">Settlement Date:</span> <span class="info-val">{{ $payment['collected_at'] }}</span>
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 6%;">#</th>
                <th class="text-left" style="width: 48%;">Item Description</th>
                <th class="text-left" style="width: 14%;">SKU</th>
                <th class="text-right" style="width: 14%;">Unit Price</th>
                <th class="text-center" style="width: 6%;">Qty</th>
                <th class="text-right" style="width: 12%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    <td class="text-center" style="color: #64748b;">{{ $item['index'] }}</td>
                    <td class="text-left">
                        <strong style="color: #0f172a;">{{ $item['name'] }}</strong>
                    </td>
                    <td class="text-left" style="font-family: monospace; font-size: 9px; color: #475569;">
                        {{ $item['sku'] }}
                    </td>
                    <td class="text-right">{{ $item['unit_price_formatted'] }}</td>
                    <td class="text-center font-bold">{{ $item['quantity'] }}</td>
                    <td class="text-right" style="font-weight: 600;">{{ $item['subtotal_formatted'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Financial Totals -->
    <table class="totals-table">
        <tr>
            <td class="text-left" style="color: #64748b;">Items Subtotal ({{ $order['total_quantity'] }} {{ $order['total_quantity'] === 1 ? 'item' : 'items' }}):</td>
            <td class="text-right" style="font-weight: 600;">{{ $order['subtotal_formatted'] }}</td>
        </tr>
        <tr>
            <td class="text-left" style="color: #64748b;">Standard Delivery:</td>
            <td class="text-right" style="font-weight: 600;">
                @if($order['delivery_charge'] > 0)
                    {{ $order['delivery_charge_formatted'] }}
                @else
                    FREE
                @endif
            </td>
        </tr>
        <tr class="grand-total">
            <td class="text-left">TOTAL AMOUNT:</td>
            <td class="text-right brand-gold">{{ $order['total_amount_formatted'] }}</td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    <!-- Payment & Settlement Notice -->
    @if($meta['is_paid'])
        <div class="payment-box payment-box-paid">
            <div class="payment-box-title">&#10003; Payment Settlement Confirmation</div>
            <div>
                Payment has been verified and settled in full via Cash on Delivery (COD).
                @if(!empty($payment['courier_name']))
                    Collected by authorized courier partner <strong>{{ $payment['courier_name'] }}</strong>
                    @if(!empty($payment['courier_reference'])) (Ref: <code>{{ $payment['courier_reference'] }}</code>)@endif
                    on {{ $payment['collected_at'] ?? 'Delivery' }}.
                @endif
                Official receipt issued for <strong>{{ $payment['collected_amount_formatted'] ?? $order['total_amount_formatted'] }}</strong>.
            </div>
        </div>
    @elseif(in_array($order['status'], ['cancelled', 'returned', 'refused']))
        <div class="payment-box payment-box-void">
            <div class="payment-box-title">&#10007; Order Terminated &mdash; Document Void</div>
            <div>
                This order was {{ strtoupper($order['status']) }}. No payment is due or collected for this order reference. This document is retained for accounting and audit records only.
            </div>
        </div>
    @else
        <div class="payment-box payment-box-pending">
            <div class="payment-box-title">&#9679; Payment Terms &mdash; Cash on Delivery</div>
            <div>
                Total amount payable is <strong>{{ $order['total_amount_formatted'] }}</strong> in cash upon physical package delivery by our authorized courier partner. Please inspect package seals prior to payment.
            </div>
        </div>
    @endif

    <!-- Footer Section -->
    <div class="footer">
        <div>Thank you for choosing <strong>Aura Luxe</strong> &mdash; The Pinnacle of Pure Beauty &amp; Luxury.</div>
        <div style="margin-top: 3px;">
            This is a computer-generated commercial document. For inquiries or customer support, please contact support@auraluxe.lk or call +94 11 234 5678.
        </div>
        <div style="margin-top: 3px; font-size: 7.5px; color: #cbd5e1;">
            Generated: {{ $generated_at }} &bull; Aura Luxe (Pvt) Ltd. All Rights Reserved.
        </div>
    </div>

</body>
</html>

