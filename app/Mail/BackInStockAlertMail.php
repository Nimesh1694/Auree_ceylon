<?php

namespace App\Mail;

use App\Models\BackInStockSubscription;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class BackInStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BackInStockSubscription $subscription,
        public readonly Product $product,
        public readonly ?string $rawUnsubscribeToken = null
    ) {}

    public function envelope(): Envelope
    {
        $isSinhala = $this->subscription->locale === 'si';
        $productName = $isSinhala && $this->product->name_si ? $this->product->name_si : $this->product->name_en;

        $subject = $isSinhala
            ? "සුභ ආරංචියක්! {$productName} නැවත Aura Luxe හි ඇත"
            : "Good News! {$productName} is back in stock at Aura Luxe";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $productUrl = url("/products/{$this->product->slug}");
        $unsubscribeUrl = URL::temporarySignedRoute(
            'back-in-stock.unsubscribe',
            now()->addDays(30),
            ['subscription' => $this->subscription->id]
        );

        return new Content(
            view: 'emails.alerts.back_in_stock',
            with: [
                'subscription' => $this->subscription,
                'product' => $this->product,
                'productUrl' => $productUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
                'isSinhala' => $this->subscription->locale === 'si',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

