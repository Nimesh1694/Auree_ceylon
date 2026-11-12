<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Dispatched #{{ $order->order_number }}</title>
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
            background-color: #e8f4fd;
            color: #155b96;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #b6daf7;
        }
        .logistics-card {
            background-color: #fbf8f5;
            border: 1px solid #ebd9c8;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 25px;
        }
        .logistics-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .logistics-row strong {
            color: #1f1d1d;
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
        .cod-reminder {
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
            <p>Your luxury package has been dispatched from our fulfillment center and is on its way to you.</p>
            
            <div class="order-badge">Dispatched: #{{ $order->order_number }}</div>

            <div class="logistics-card">
                <div class="logistics-row">
                    <span>Courier Partner:</span>
                    <strong>{{ $delivery?->courier_name ?? 'Designated Courier Service' }}</strong>
                </div>
                <div class="logistics-row">
                    <span>Tracking Number / Waybill:</span>
                    <strong>{{ $delivery?->tracking_number ?? 'Assigned' }}</strong>
                </div>
                <div class="logistics-row">
                    <span>Destination:</span>
                    <span>{{ $order->shipping_address }}, {{ $order->city }} ({{ $order->district }})</span>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Dispatched Items</th>
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

            <div class="cod-reminder">
                <strong>Cash on Delivery Reminder:</strong> Please prepare exact cash payment of <strong>LKR {{ number_format((float) $order->total_amount, 2) }}</strong> for our courier upon package arrival.
            </div>

            <div style="text-align: center;">
                <a href="{{ config('app.url') }}/track" class="btn-track">Track Courier Status</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Aura Luxe Cosmetics (Pvt) Ltd. All rights reserved.</p>
            <p>Need assistance? Contact our concierge team at <a href="mailto:care@auraluxe.lk" style="color: #8c5828;">care@auraluxe.lk</a></p>
        </div>
    </div>
</body>
</html>

