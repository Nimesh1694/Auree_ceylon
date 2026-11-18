<!DOCTYPE html>
<html lang="{{ $isSinhala ? 'si' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura Luxe — Back in Stock Alert</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #fdfbf7; color: #1c1917; margin: 0; padding: 24px; }
        .container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e7e5e4; padding: 32px; }
        .header { text-align: center; border-bottom: 1px solid #f5f5f4; padding-bottom: 24px; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 700; letter-spacing: 0.1em; color: #78350f; text-transform: uppercase; }
        .badge { display: inline-block; background-color: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 9999px; margin-top: 8px; }
        .content { font-size: 15px; line-height: 1.6; color: #44403c; }
        .product-card { background: #fafaf9; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; border: 1px solid #f5f5f4; }
        .product-name { font-size: 18px; font-weight: 600; color: #1c1917; margin-bottom: 8px; }
        .product-price { font-size: 20px; font-weight: 700; color: #78350f; margin-bottom: 16px; }
        .btn { display: inline-block; background-color: #78350f; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 28px; border-radius: 8px; }
        .footer { margin-top: 32px; padding-top: 20px; border-top: 1px solid #f5f5f4; font-size: 12px; color: #78716c; text-align: center; }
        .footer a { color: #78350f; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Aura Luxe</div>
            <div class="badge">{{ $isSinhala ? 'නැවත ගබඩාවේ ඇත' : 'Back in Stock' }}</div>
        </div>
        <div class="content">
            @if($isSinhala)
                <p>ආයුබෝවන්,</p>
                <p>ඔබ බලාපොරොත්තුවෙන් සිටි නිෂ්පාදනය දැන් නැවත Aura Luxe හි මිලදී ගැනීමට ඇත!</p>
            @else
                <p>Hello,</p>
                <p>The item you've been waiting for is officially back in stock and ready to order!</p>
            @endif

            <div class="product-card">
                <div class="product-name">{{ $isSinhala && $product->name_si ? $product->name_si : $product->name_en }}</div>
                <div class="product-price">LKR {{ number_format($product->price, 2) }}</div>
                <a href="{{ $productUrl }}" class="btn">{{ $isSinhala ? 'දැන්ම මිලදී ගන්න' : 'Shop Now' }}</a>
            </div>

            @if($isSinhala)
                <p style="font-size: 13px; color: #78716c;">තොග සීමිත බැවින් ඉක්මනින් ඇණවුම් කරන්න.</p>
            @else
                <p style="font-size: 13px; color: #78716c;">Stock is limited and available on a first-come, first-served basis.</p>
            @endif
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Aura Luxe Cosmetics. All rights reserved.</p>
            @if(!empty($unsubscribeUrl))
                <p>{{ $isSinhala ? 'මෙම දැනුම්දීම් වලින් ඉවත් වීමට:' : 'To unsubscribe from back-in-stock alerts for this item:' }} <a href="{{ $unsubscribeUrl }}">{{ $isSinhala ? 'මෙහි ක්ලික් කරන්න' : 'Unsubscribe' }}</a></p>
            @endif
        </div>
    </div>
</body>
</html>

