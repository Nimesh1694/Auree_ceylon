<!DOCTYPE html>
<html lang="{{ $isSinhala ? 'si' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura Luxe — Confirm Your Back-in-Stock Alert Request</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #fdfbf7; color: #1c1917; margin: 0; padding: 24px; }
        .container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e7e5e4; padding: 32px; }
        .header { text-align: center; border-bottom: 1px solid #f5f5f4; padding-bottom: 24px; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 700; letter-spacing: 0.1em; color: #78350f; text-transform: uppercase; }
        .badge { display: inline-block; background-color: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 9999px; margin-top: 8px; }
        .content { font-size: 15px; line-height: 1.6; color: #44403c; }
        .product-card { background: #fafaf9; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; border: 1px solid #f5f5f4; }
        .product-name { font-size: 18px; font-weight: 600; color: #1c1917; margin-bottom: 8px; }
        .btn { display: inline-block; background-color: #78350f; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 28px; border-radius: 8px; }
        .notice { font-size: 13px; color: #78716c; margin-top: 16px; line-height: 1.5; }
        .footer { margin-top: 32px; padding-top: 20px; border-top: 1px solid #f5f5f4; font-size: 12px; color: #78716c; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Aura Luxe</div>
            <div class="badge">{{ $isSinhala ? 'තහවුරු කිරීමේ ඉල්ලීම' : 'Verification Request' }}</div>
        </div>
        <div class="content">
            @if($isSinhala)
                <p>ආයුබෝවන්,</p>
                <p>ඔබ පහත නිෂ්පාදනය නැවත තොග ලැබුණු විට දැනුම්දීම් ලබා ගැනීමට ඉල්ලුම් කර ඇත. ඔබගේ විද්‍යුත් තැපැල් ලිපිනය තහවුරු කිරීමට කරුණාකර පහත බොත්තම ක්ලික් කරන්න:</p>
            @else
                <p>Hello,</p>
                <p>You requested to receive an email alert when the following item is back in stock at Aura Luxe. Please confirm your email address by clicking the button below:</p>
            @endif

            <div class="product-card">
                <div class="product-name">{{ $isSinhala && $product->name_si ? $product->name_si : $product->name_en }}</div>
                <a href="{{ $verificationUrl }}" class="btn">{{ $isSinhala ? 'දැනුම්දීම් ඉල්ලීම තහවුරු කරන්න' : 'Confirm Alert Request' }}</a>
            </div>

            <div class="notice">
                @if($isSinhala)
                    <p>• මෙම සබැඳිය පැය 24ක් සඳහා වලංගු වේ.<br>
                    • මෙම දැනුම්දීම මඟින් භාණ්ඩය වෙන්කර තැබීමක් සිදු නොවේ.<br>
                    • ඔබ මෙම ඉල්ලීම සිදු නොකළේ නම්, මෙම පණිවිඩය නොසලකා හරින්න.</p>
                @else
                    <p>• This verification link is valid for 24 hours.<br>
                    • Back-in-stock alerts do not reserve inventory and do not guarantee availability.<br>
                    • If you did not request this alert, you can safely ignore this email.</p>
                @endif
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Aura Luxe Cosmetics. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
