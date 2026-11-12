<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Inquiry #{{ $inquiry->inquiry_number }}</title>
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
            max-width: 620px;
            margin: 25px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ebd9c8;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        }
        .header {
            background: linear-gradient(135deg, #1f1d1d 0%, #383330 100%);
            padding: 28px 24px;
            text-align: center;
            border-bottom: 3px solid #c59b6d;
        }
        .header h1 {
            color: #f7e7ce;
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .header p {
            color: #c59b6d;
            margin: 6px 0 0 0;
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .content {
            padding: 28px 25px;
        }
        .badge {
            display: inline-block;
            background-color: #f5ebe0;
            color: #8c5828;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .info-card {
            background-color: #fbf8f5;
            border: 1px solid #ebd9c8;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 22px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #ebd9c8;
            font-size: 14px;
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .info-label {
            color: #6d645d;
            font-weight: 500;
            width: 140px;
        }
        .info-value {
            color: #1f1d1d;
            font-weight: 600;
            text-align: right;
            word-break: break-word;
        }
        .message-section {
            background-color: #ffffff;
            border: 1px solid #ebd9c8;
            border-left: 4px solid #c59b6d;
            border-radius: 4px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }
        .message-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8c5828;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .message-body {
            font-size: 14px;
            color: #2d2a26;
            white-space: pre-wrap;
            line-height: 1.7;
        }
        .actions {
            text-align: center;
            margin: 25px 0 15px 0;
        }
        .reply-button {
            display: inline-block;
            background: linear-gradient(135deg, #c59b6d 0%, #8c5828 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 8px rgba(140, 88, 40, 0.25);
        }
        .footer {
            background-color: #f7f3ee;
            padding: 18px 20px;
            text-align: center;
            font-size: 12px;
            color: #8c827a;
            border-top: 1px solid #ebd9c8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Aura Luxe</h1>
            <p>Customer Support Notification</p>
        </div>

        <div class="content">
            <div class="badge">Reference: {{ $inquiry->inquiry_number }}</div>

            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Customer Name:</span>
                    <span class="info-value">{{ $inquiry->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer Email:</span>
                    <span class="info-value">{{ $inquiry->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number:</span>
                    <span class="info-value">{{ $inquiry->phone ?? 'Not provided' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Subject:</span>
                    <span class="info-value">{{ $inquiry->subject }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Submitted On:</span>
                    <span class="info-value">{{ $inquiry->created_at?->format('F d, Y \a\t h:i A') ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="message-section">
                <h4 class="message-title">Inquiry Message</h4>
                <div class="message-body">{!! nl2br(e($inquiry->message)) !!}</div>
            </div>

            <div class="actions">
                <a href="mailto:{{ $inquiry->email }}?subject={{ rawurlencode('Re: [Inquiry #' . $inquiry->inquiry_number . '] ' . $inquiry->subject) }}" class="reply-button">
                    Reply Directly to {{ $inquiry->name }}
                </a>
            </div>
        </div>

        <div class="footer">
            <p>This automated staff notification was dispatched by the Aura Luxe Customer Support System.</p>
            <p>Target Support Mailbox: <strong>care@auraluxe.lk</strong></p>
        </div>
    </div>
</body>
</html>

