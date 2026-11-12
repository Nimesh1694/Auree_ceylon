<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Delivered #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #fdfbf7;
            color: #2d2a26;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ebd9c8;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #1f1d1d 0%, #383330 100%);
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid #c59b6d;
        }
        .header h1 {
            color: #f7e7ce;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .content {
            padding: 30px 25px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1f1d1d;
            margin-bottom: 10px;
        }
        .order-badge {
            display: inline-block;
            background-color: #f0f7f2;
            color: #1e592f;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #c3e0cb;
        }
        .delivered-card {
            background-color: #fbf8f5;
            border: 1px solid #ebd9c8;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 25px;
            text-align: center;
        }
        .delivered-card h3 {
            margin: 0 0 8px;
            color: #1e592f;
            font-size: 18px;
        }
        .delivered-card p {
            margin: 0;
            font-size: 14px;
            color: #594231;
        }
        .concierge-notice {
            background-color: #fff9f0;
            border-left: 4px solid #c59b6d;
            padding: 14px 16px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #594231;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        table.items-table th {
            text-align: left;
            background-color: #f5ebe0;
            color: #594231;
            padding: 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table.items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f0e6dc;
            font-size: 14px;
        }
        .btn-track {
            display: inline-block;
            background-color: #1f1d1d;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 15px 0;
            border: 1px solid #c59b6d;
        }
        .footer {
            background-color: #fbf8f5;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #7d756d;
            border-top: 1px solid #ebd9c8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Aura Luxe</h1>
            <p style="color: #c59b6d; margin: 5px 0 0; font-size: 12px; letter-spacing: 1px;">HAUTE BEAUTY & BOTANICAL LUXURY</p>
        </div>
        <div class="content">
            <div class="greeting">Hello {{ $order->customer_name }},</div>
            <p>Our courier partner has confirmed that your order has been successfully delivered to your address.</p>
            
            <div class="order-badge">Delivered: #{{ $order->order_number }}</div>

            <div class="delivered-card">
                <h3>Package Handed Over</h3>
                <p>
                    Delivered to <em>{{ $order->shipping_address }}, {{ $order->city }}</em>
                    @if($delivery?->delivered_at)
                        <br><span style="color: #7d756d; font-size: 12px;">Delivered at: {{ $delivery->delivered_at->format('M d, Y h:i A') }}</span>
                    @endif
                </p>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Items in Package</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product_name }}</strong>
                                @if($item->product_sku)
                                    <br><small style="color: #7d756d;">SKU: {{ $item->product_sku }}</small>
                                @endif
                            </td>
                            <td style="text-align: center;">{{ $item->quantity }}</td>
                            <td style="text-align: right;">LKR {{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="concierge-notice">
                <strong>Boutique Care & Guidance:</strong> We invite you to inspect your botanical formulations. Should you have any questions regarding your beauty regimen or package condition, our beauty concierge is at your service.
            </div>

            <div style="text-align: center;">
                <a href="{{ config('app.url') }}/track" class="btn-track">View Order History</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Aura Luxe Cosmetics (Pvt) Ltd. All rights reserved.</p>
            <p>Need assistance? Contact our concierge team at <a href="mailto:care@auraluxe.lk" style="color: #8c5828;">care@auraluxe.lk</a></p>
        </div>
    </div>
</body>
</html>

