<?php

namespace App\Services;

use App\Models\CodCollection;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Http\Response;

class OrderInvoiceService
{
    /**
     * Determine whether the order has verified payment evidence.
     *
     * Strict rule: For COD, payment is verified ONLY if:
     * 1. Order status is 'cash_collected' or 'completed'
     * 2. A CodCollection record exists for this order
     * 3. CodCollection collected_amount >= order total_amount (within 0.01 tolerance)
     *    and reconciliation_status is 'matched' or 'mismatched_overpaid'.
     */
    public function isPaymentVerified(Order $order): bool
    {
        if (!in_array($order->status, ['cash_collected', 'completed'], true)) {
            return false;
        }

        $cod = $order->relationLoaded('codCollection')
            ? $order->codCollection
            : $order->codCollection()->first();

        if (!$cod) {
            return false;
        }

        $expected = (float) $order->total_amount;
        $collected = (float) $cod->collected_amount;

        if (($collected + 0.01) < $expected) {
            return false;
        }

        return in_array($cod->reconciliation_status, [
            CodCollection::STATUS_MATCHED,
            CodCollection::STATUS_MISMATCHED_OVERPAID,
        ], true);
    }

    /**
     * Resolve document title, badge label, and watermark status.
     *
     * @return array{
     *     document_title: string,
     *     document_type: string,
     *     status_badge: string,
     *     is_paid: bool,
     *     watermark: string|null,
     *     filename: string
     * }
     */
    public function resolveDocumentMeta(Order $order): array
    {
        $isPaid = $this->isPaymentVerified($order);

        // Watermark check for terminated or paid statuses
        $watermark = null;
        if ($order->status === 'cancelled') {
            $watermark = 'CANCELLED / VOID';
        } elseif ($order->status === 'returned') {
            $watermark = 'RETURNED / VOID';
        } elseif ($order->status === 'refused') {
            $watermark = 'REFUSED / VOID';
        } elseif ($isPaid) {
            $watermark = 'PAID';
        }

        if ($isPaid) {
            $documentTitle = 'OFFICIAL PAYMENT RECEIPT';
            $documentType = 'receipt';
            $statusBadge = 'PAID IN FULL';
            $filename = "Aura-Luxe-Receipt-{$order->order_number}.pdf";
        } else {
            $documentTitle = 'COMMERCIAL INVOICE';
            $documentType = 'invoice';
            $filename = "Aura-Luxe-Invoice-{$order->order_number}.pdf";

            $statusBadge = match ($order->status) {
                'cancelled' => 'CANCELLED',
                'returned' => 'RETURNED',
                'refused' => 'REFUSED',
                'delivered' => 'DELIVERED (PENDING RECONCILIATION)',
                default => 'PAYMENT DUE ON DELIVERY',
            };
        }

        return [
            'document_title' => $documentTitle,
            'document_type' => $documentType,
            'status_badge' => $statusBadge,
            'is_paid' => $isPaid,
            'watermark' => $watermark,
            'filename' => $filename,
        ];
    }

    /**
     * Build the sanitized, whitelisted data payload for the PDF view.
     * Strictly excludes: cost_price, actual_courier_fee, commission, agent_id, notes.
     */
    public function buildInvoiceData(Order $order): array
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }
        if (!$order->relationLoaded('delivery')) {
            $order->load('delivery');
        }
        if (!$order->relationLoaded('codCollection')) {
            $order->load('codCollection');
        }

        $meta = $this->resolveDocumentMeta($order);
        $cod = $order->codCollection;

        $items = $order->items->map(function ($item, $index) {
            $unitPrice = (float) $item->unit_price;
            $subtotal = (float) $item->subtotal;

            return [
                'index' => $index + 1,
                'name' => $item->product_name,
                'sku' => $item->product_sku ?: 'N/A',
                'unit_price' => $unitPrice,
                'unit_price_formatted' => 'LKR ' . number_format($unitPrice, 2),
                'quantity' => (int) $item->quantity,
                'subtotal' => $subtotal,
                'subtotal_formatted' => 'LKR ' . number_format($subtotal, 2),
            ];
        })->values()->all();

        return [
            'meta' => $meta,
            'company' => [
                'name' => 'AURA LUXE',
                'tagline' => 'Luxury Beauty & Premium Cosmetics',
                'address_line_1' => 'Level 12, West Tower, World Trade Center',
                'address_line_2' => 'Echelon Square, Colombo 00100',
                'country' => 'Sri Lanka',
                'phone' => '+94 11 234 5678',
                'email' => 'support@auraluxe.lk',
                'web' => 'www.auraluxe.lk',
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'placed_at' => $order->placed_at ?? $order->created_at,
                'placed_at_formatted' => ($order->placed_at ?? $order->created_at)->format('F d, Y h:i A'),
                'status' => $order->status,
                'status_label' => ucfirst(str_replace('_', ' ', $order->status)),
                'payment_method' => strtoupper($order->payment_method ?: 'COD'),
                'payment_method_label' => 'Cash on Delivery (COD)',
                'subtotal' => (float) $order->subtotal,
                'subtotal_formatted' => 'LKR ' . number_format((float) $order->subtotal, 2),
                'delivery_charge' => (float) $order->delivery_charge,
                'delivery_charge_formatted' => 'LKR ' . number_format((float) $order->delivery_charge, 2),
                'total_amount' => (float) $order->total_amount,
                'total_amount_formatted' => 'LKR ' . number_format((float) $order->total_amount, 2),
                'total_quantity' => $order->items->sum('quantity'),
            ],
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email ?: 'N/A',
                'phone' => $order->customer_phone,
                'shipping_address' => $order->shipping_address,
                'city' => $order->city,
                'district' => $order->district,
                'postal_code' => $order->postal_code ?: '',
            ],
            'delivery' => $order->delivery ? [
                'courier_name' => $order->delivery->courier_name ?: 'Standard Courier',
                'tracking_number' => $order->delivery->tracking_number,
                'dispatched_at' => $order->delivery->dispatched_at?->format('F d, Y'),
                'delivered_at' => $order->delivery->delivered_at?->format('F d, Y'),
            ] : null,
            'payment' => [
                'is_paid' => $meta['is_paid'],
                'collected_amount' => $cod ? (float) $cod->collected_amount : null,
                'collected_amount_formatted' => $cod ? ('LKR ' . number_format((float) $cod->collected_amount, 2)) : null,
                'collected_at' => $cod?->collected_at ? $cod->collected_at->format('F d, Y h:i A') : null,
                'courier_name' => $cod?->courier_name,
                'courier_reference' => $cod?->courier_reference,
            ],
            'items' => $items,
            'generated_at' => now()->format('F d, Y h:i:s A T'),
        ];
    }

    /**
     * Generate the DomPDF wrapper instance.
     */
    public function generatePdf(Order $order): DomPdfWrapper
    {
        $data = $this->buildInvoiceData($order);

        /** @var DomPdfWrapper $pdf */
        $pdf = Pdf::loadView('pdf.invoice', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Download or stream the invoice PDF as an HTTP download response.
     */
    public function download(Order $order): Response
    {
        $pdf = $this->generatePdf($order);
        $meta = $this->resolveDocumentMeta($order);

        return $pdf->download($meta['filename']);
    }

    /**
     * Stream inline in browser if requested.
     */
    public function stream(Order $order): Response
    {
        $pdf = $this->generatePdf($order);
        $meta = $this->resolveDocumentMeta($order);

        return $pdf->stream($meta['filename']);
    }
}

