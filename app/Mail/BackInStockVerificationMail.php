<?php

namespace App\Mail;

use App\Models\BackInStockSubscription;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStockVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BackInStockSubscription $subscription,
        public readonly Product $product,
        public readonly string $rawVerificationToken
    ) {}

    public function envelope(): Envelope
    {
        $isSinhala = $this->subscription->locale === 'si';
        $productName = $isSinhala && $this->product->name_si ? $this->product->name_si : $this->product->name_en;

        $subject = $isSinhala
            ? "ඔබගේ Back-in-Stock ඉල්ලීම තහවුරු කරන්න — Aura Luxe"
            : "Confirm your Back-in-Stock Alert Request — Aura Luxe";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $verificationUrl = route('back-in-stock.verify', [
            'token' => $this->rawVerificationToken,
        ]);

        return new Content(
            view: 'emails.alerts.back_in_stock_verification',
            with: [
                'subscription' => $this->subscription,
                'product' => $this->product,
                'verificationUrl' => $verificationUrl,
                'isSinhala' => $this->subscription->locale === 'si',
                'expiresIn' => '24 hours',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
