<?php

namespace App\Services;

use App\Jobs\SendCustomerOrderEmailJob;
use App\Mail\CustomerOrderCancelledMail;
use App\Mail\CustomerOrderCompletedMail;
use App\Mail\CustomerOrderConfirmedMail;
use App\Mail\CustomerOrderDeliveredMail;
use App\Mail\CustomerOrderPlacedMail;
use App\Mail\CustomerOrderRefusedMail;
use App\Mail\CustomerOrderReturnedMail;
use App\Mail\CustomerOrderShippedMail;
use App\Models\Order;
use App\Models\OrderNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerEmailNotificationService
{
    /**
     * Dispatch an order placed confirmation email to the customer.
     */
    public function sendOrderPlacedNotification(Order $order): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_PLACED,
            mailable: new CustomerOrderPlacedMail($order)
        );
    }

    /**
     * Dispatch an order confirmed notification email to the customer.
     */
    public function sendOrderConfirmedNotification(Order $order): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_CONFIRMED,
            mailable: new CustomerOrderConfirmedMail($order)
        );
    }

    /**
     * Dispatch an order shipped notification email to the customer.
     */
    public function sendOrderShippedNotification(Order $order): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_SHIPPED,
            mailable: new CustomerOrderShippedMail($order)
        );
    }

    /**
     * Dispatch an order delivered notification email to the customer.
     */
    public function sendOrderDeliveredNotification(Order $order): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_DELIVERED,
            mailable: new CustomerOrderDeliveredMail($order)
        );
    }

    /**
     * Dispatch an order completed / COD payment settled notification email to the customer.
     */
    public function sendOrderCompletedNotification(Order $order): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_COMPLETED,
            mailable: new CustomerOrderCompletedMail($order)
        );
    }

    /**
     * Dispatch an order cancelled notification email to the customer.
     */
    public function sendOrderCancelledNotification(Order $order, ?string $reason = null): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_CANCELLED,
            mailable: new CustomerOrderCancelledMail($order, $reason)
        );
    }

    /**
     * Dispatch an order returned notification email to the customer.
     */
    public function sendOrderReturnedNotification(Order $order, ?string $reason = null): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_RETURNED,
            mailable: new CustomerOrderReturnedMail($order, $reason)
        );
    }

    /**
     * Dispatch an order delivery refused notification email to the customer.
     */
    public function sendOrderRefusedNotification(Order $order, ?string $reason = null): ?OrderNotification
    {
        return $this->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_REFUSED,
            mailable: new CustomerOrderRefusedMail($order, $reason)
        );
    }

    /**
     * Safe centralized notification dispatcher.
     * Guaranteed never to throw an exception that interrupts caller/order workflow.
     */
    public function sendNotification(
        Order $order,
        string $notificationType,
        ?Mailable $mailable = null,
        ?string $recipientEmail = null
    ): ?OrderNotification {
        try {
            // Resolve recipient email (snapshot priority)
            $email = trim($recipientEmail ?? $order->customer_email ?? $order->customer?->email ?? '');

            // Fallback default mailable for known types if not explicitly provided
            if ($mailable === null) {
                $mailable = match ($notificationType) {
                    OrderNotification::TYPE_ORDER_PLACED => new CustomerOrderPlacedMail($order),
                    OrderNotification::TYPE_ORDER_CONFIRMED => new CustomerOrderConfirmedMail($order),
                    OrderNotification::TYPE_ORDER_SHIPPED => new CustomerOrderShippedMail($order),
                    OrderNotification::TYPE_ORDER_DELIVERED => new CustomerOrderDeliveredMail($order),
                    OrderNotification::TYPE_ORDER_COMPLETED => new CustomerOrderCompletedMail($order),
                    OrderNotification::TYPE_ORDER_CANCELLED => new CustomerOrderCancelledMail($order),
                    OrderNotification::TYPE_ORDER_RETURNED => new CustomerOrderReturnedMail($order),
                    OrderNotification::TYPE_ORDER_REFUSED => new CustomerOrderRefusedMail($order),
                    default => null,
                };
            }

            // Handle missing or invalid email: log audit trail as skipped without failing caller
            if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('CustomerEmailNotificationService: Skipping notification due to missing or invalid email.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'notification_type' => $notificationType,
                    'email' => $email,
                ]);

                return OrderNotification::firstOrCreate(
                    [
                        'order_number' => $order->order_number,
                        'notification_type' => $notificationType,
                        'recipient_email' => ! empty($email) ? $email : 'missing@aura-luxe.local',
                    ],
                    [
                        'order_id' => $order->id,
                        'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
                        'status' => OrderNotification::STATUS_SKIPPED,
                        'attempts' => 0,
                        'error_details' => 'Recipient email missing or invalid format: ' . ($email ?: 'empty'),
                    ]
                );
            }

            // Check for existing notification (Duplicate Prevention)
            $existing = OrderNotification::where('order_number', $order->order_number)
                ->where('notification_type', $notificationType)
                ->where('recipient_email', $email)
                ->first();

            if ($existing) {
                if ($existing->isSent()) {
                    Log::info('CustomerEmailNotificationService: Notification already sent, skipping duplicate dispatch.', [
                        'order_number' => $order->order_number,
                        'notification_type' => $notificationType,
                        'recipient_email' => $email,
                    ]);
                    return $existing;
                }

                if ($existing->status === OrderNotification::STATUS_PENDING) {
                    Log::info('CustomerEmailNotificationService: Notification already pending in queue, skipping duplicate dispatch.', [
                        'order_number' => $order->order_number,
                        'notification_type' => $notificationType,
                    ]);
                    return $existing;
                }

                // Failed Notification Recovery (OBS-04 remediation):
                // Reset status to pending and clear past error details before re-dispatching
                if ($existing->status === OrderNotification::STATUS_FAILED) {
                    $existing->update([
                        'status' => OrderNotification::STATUS_PENDING,
                        'error_details' => null,
                        'mailer' => config('mail.default'),
                    ]);

                    Log::info('CustomerEmailNotificationService: Re-dispatching previously failed notification.', [
                        'order_notification_id' => $existing->id,
                        'order_number' => $order->order_number,
                        'notification_type' => $notificationType,
                    ]);
                }
            }

            // Create notification record if not exists
            $notification = $existing ?? OrderNotification::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'notification_type' => $notificationType,
                'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
                'recipient_email' => $email,
                'mailer' => config('mail.default'),
                'status' => OrderNotification::STATUS_PENDING,
                'attempts' => 0,
            ]);

            if (! $mailable) {
                $notification->update([
                    'status' => OrderNotification::STATUS_SKIPPED,
                    'error_details' => "No mailable defined for notification type: {$notificationType}",
                ]);
                return $notification;
            }

            // Dispatch queued job (afterCommit ensures DB transaction commits first)
            SendCustomerOrderEmailJob::dispatch($notification->id, $mailable);

            Log::info('CustomerEmailNotificationService: Successfully queued order notification.', [
                'order_notification_id' => $notification->id,
                'order_number' => $order->order_number,
                'notification_type' => $notificationType,
                'recipient_email' => $email,
            ]);

            return $notification;
        } catch (Throwable $e) {
            // Absolute failure boundary: log error, NEVER rethrow to caller
            Log::critical('CustomerEmailNotificationService: Unexpected failure during notification dispatch.', [
                'order_id' => $order->id ?? null,
                'order_number' => $order->order_number ?? null,
                'notification_type' => $notificationType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
