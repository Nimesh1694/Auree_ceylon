<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendCustomerOrderEmailJob;
use App\Mail\CustomerOrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotification;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerEmailNotificationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Product $product;
    private CustomerEmailNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = app(CustomerEmailNotificationService::class);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Nadeesha Fernando',
            'email' => 'nadeesha@example.com',
            'phone' => '0771234567',
        ]);

        $this->product = Product::create([
            'name_en' => 'Rose Gold Elixir',
            'name_si' => 'රෝස රන් එලික්සර්',
            'slug' => 'rose-gold-elixir',
            'price' => 4500.00,
            'stock_quantity' => 25,
            'is_active' => true,
        ]);
    }

    private function createOrder(array $overrides = []): Order
    {
        static $seq = 200;
        $seq++;

        $order = Order::create(array_merge([
            'order_number' => "ORD-20261111-{$seq}",
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => '0771234567',
            'customer_email' => 'nadeesha@example.com',
            'shipping_address' => 'No 12, Flower Road',
            'district' => 'Colombo',
            'city' => 'Colombo 07',
            'postal_code' => '00700',
            'subtotal' => 4500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4850.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TRK_' . uniqid(),
            'notes' => 'Leave with security',
            'placed_at' => now(),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => 'SKU-RGE-01',
            'quantity' => 1,
            'unit_price' => 4500.00,
            'subtotal' => 4500.00,
        ]);

        return $order;
    }

    public function test_notification_record_created_and_job_dispatched_for_valid_order(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $notification = $this->notificationService->sendOrderPlacedNotification($order);

        $this->assertNotNull($notification);
        $this->assertEquals($order->id, $notification->order_id);
        $this->assertEquals($order->order_number, $notification->order_number);
        $this->assertEquals(OrderNotification::TYPE_ORDER_PLACED, $notification->notification_type);
        $this->assertEquals('nadeesha@example.com', $notification->recipient_email);
        $this->assertEquals(OrderNotification::STATUS_PENDING, $notification->status);
        $this->assertEquals(0, $notification->attempts);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id;
        });
    }

    public function test_job_sends_email_and_updates_notification_status_to_sent(): void
    {
        Mail::fake();

        $order = $this->createOrder();
        $notification = OrderNotification::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'notification_type' => OrderNotification::TYPE_ORDER_PLACED,
            'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
            'recipient_email' => 'nadeesha@example.com',
            'status' => OrderNotification::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $job = new SendCustomerOrderEmailJob($notification->id, new CustomerOrderPlacedMail($order));
        $job->handle();

        Mail::assertSent(CustomerOrderPlacedMail::class, function ($mail) use ($order) {
            return $mail->hasTo('nadeesha@example.com') &&
                   $mail->order->id === $order->id;
        });

        $notification->refresh();
        $this->assertEquals(OrderNotification::STATUS_SENT, $notification->status);
        $this->assertEquals(1, $notification->attempts);
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->error_details);
    }

    public function test_missing_or_invalid_customer_email_is_skipped_without_queueing_job(): void
    {
        Queue::fake();
        Mail::fake();

        $order = $this->createOrder(['customer_email' => '']);

        $notification = $this->notificationService->sendNotification(
            order: $order,
            notificationType: OrderNotification::TYPE_ORDER_PLACED,
            recipientEmail: ''
        );

        $this->assertNotNull($notification);
        $this->assertEquals(OrderNotification::STATUS_SKIPPED, $notification->status);
        $this->assertStringContainsString('missing or invalid', $notification->error_details);

        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_duplicate_notification_dispatch_is_prevented_and_idempotent(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        // First call
        $first = $this->notificationService->sendOrderPlacedNotification($order);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);

        // Second call while pending: suppresses duplicate queue push
        $second = $this->notificationService->sendOrderPlacedNotification($order);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
        $this->assertEquals($first->id, $second->id);

        // Third call after already marked sent: suppresses duplicate
        $first->update([
            'status' => OrderNotification::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $third = $this->notificationService->sendOrderPlacedNotification($order);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
        $this->assertEquals($first->id, $third->id);

        // Exactly one notification row in DB
        $this->assertEquals(1, OrderNotification::where('order_number', $order->order_number)->count());
    }

    public function test_job_handle_is_idempotent_if_already_marked_sent(): void
    {
        Mail::fake();

        $order = $this->createOrder();
        $notification = OrderNotification::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'notification_type' => OrderNotification::TYPE_ORDER_PLACED,
            'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
            'recipient_email' => 'nadeesha@example.com',
            'status' => OrderNotification::STATUS_SENT,
            'sent_at' => now()->subMinutes(10),
            'attempts' => 1,
        ]);

        $job = new SendCustomerOrderEmailJob($notification->id, new CustomerOrderPlacedMail($order));
        $job->handle();

        // No new mail sent because already sent
        Mail::assertNothingSent();
        $this->assertEquals(1, $notification->fresh()->attempts);
    }

    public function test_failed_email_delivery_records_error_and_rethrows(): void
    {
        $order = $this->createOrder();
        $notification = OrderNotification::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'notification_type' => OrderNotification::TYPE_ORDER_PLACED,
            'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
            'recipient_email' => 'nadeesha@example.com',
            'status' => OrderNotification::STATUS_PENDING,
            'attempts' => 0,
        ]);

        // Mock Mail to throw exception
        Mail::shouldReceive('to->send')->once()->andThrow(new Exception('Connection to smtp.mailtrap.io:2525 timed out'));

        $job = new SendCustomerOrderEmailJob($notification->id, new CustomerOrderPlacedMail($order));

        $exceptionCaught = false;
        try {
            $job->handle();
        } catch (Exception $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('smtp.mailtrap.io', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught);

        $notification->refresh();
        $this->assertEquals(OrderNotification::STATUS_FAILED, $notification->status);
        $this->assertEquals(1, $notification->attempts);
        $this->assertStringContainsString('Connection to smtp.mailtrap.io', $notification->error_details);

        // Invariant: Order state and financial amounts remain completely untouched
        $order->refresh();
        $this->assertEquals('placed', $order->status);
        $this->assertEquals(4850.00, (float) $order->total_amount);
    }

    public function test_order_relationship_and_safe_retention_on_order_deletion(): void
    {
        $order = $this->createOrder();
        $notification = OrderNotification::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'notification_type' => OrderNotification::TYPE_ORDER_PLACED,
            'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
            'recipient_email' => 'nadeesha@example.com',
            'status' => OrderNotification::STATUS_SENT,
            'sent_at' => now(),
            'attempts' => 1,
        ]);

        // Test relationship
        $this->assertTrue($order->notifications->contains($notification));
        $this->assertEquals($order->id, $notification->order->id);

        // Delete order (no commissions, so deletion allowed)
        $orderNumber = $order->order_number;
        $order->delete();

        // Notification record survives with order_id = null and order_number preserved
        $notification->refresh();
        $this->assertNull($notification->order_id);
        $this->assertEquals($orderNumber, $notification->order_number);
        $this->assertEquals(OrderNotification::STATUS_SENT, $notification->status);
    }

    public function test_mailable_renders_luxury_template_with_order_details(): void
    {
        $order = $this->createOrder();
        $mailable = new CustomerOrderPlacedMail($order);

        $rendered = $mailable->render();

        $this->assertStringContainsString('Aura Luxe', $rendered);
        $this->assertStringContainsString($order->order_number, $rendered);
        $this->assertStringContainsString('Rose Gold Elixir', $rendered);
        $this->assertStringContainsString('4,850.00', $rendered);
        $this->assertStringContainsString('Cash on Delivery', $rendered);
        $this->assertStringContainsString('Colombo 07', $rendered);
    }
}

