<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation #{{ $order->order_number }}</title>
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
            background-color: #f5ebe0;
            color: #8c5828;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #fbf8f5;
            border: 1px solid #ebd9c8;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 25px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .summary-row.total {
            border-top: 1px solid #ebd9c8;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 16px;
            font-weight: 700;
            color: #8c5828;
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
            <p>Thank you for shopping with Aura Luxe! We are delighted to confirm that your order has been received and is being prepared with the utmost care.</p>
            
            <div class="order-badge">Order Reference: #{{ $order->order_number }}</div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
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

            <div class="summary-card">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>LKR {{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span>Delivery Charge:</span>
                    <span>LKR {{ number_format((float) $order->delivery_charge, 2) }}</span>
                </div>
                <div class="summary-row total">
                    <span>Total Amount (Cash on Delivery):</span>
                    <span>LKR {{ number_format((float) $order->total_amount, 2) }}</span>
                </div>
            </div>

            <div style="background-color: #fff9f0; border-left: 4px solid #c59b6d; padding: 12px 15px; margin-bottom: 25px; font-size: 13px;">
                <strong>Delivery Notice:</strong> Your package will be delivered to <em>{{ $order->shipping_address }}, {{ $order->city }} ({{ $order->district }} District)</em>. Please prepare exact cash of <strong>LKR {{ number_format((float) $order->total_amount, 2) }}</strong> for our courier partner upon arrival.
            </div>

            <div style="text-align: center;">
                <a href="{{ config('app.url') }}/track" class="btn-track">Track Your Order</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Aura Luxe Cosmetics (Pvt) Ltd. All rights reserved.</p>
            <p>Need assistance? Contact our concierge team at <a href="mailto:care@auraluxe.lk" style="color: #8c5828;">care@auraluxe.lk</a></p>
        </div>
    </div>
</body>
</html>

