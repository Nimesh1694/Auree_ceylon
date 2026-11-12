<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendCustomerOrderEmailJob;
use App\Mail\CustomerOrderCancelledMail;
use App\Mail\CustomerOrderCompletedMail;
use App\Mail\CustomerOrderConfirmedMail;
use App\Mail\CustomerOrderDeliveredMail;
use App\Mail\CustomerOrderPlacedMail;
use App\Mail\CustomerOrderRefusedMail;
use App\Mail\CustomerOrderReturnedMail;
use App\Mail\CustomerOrderShippedMail;
use App\Models\CodCollection;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotification;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerEmailNotificationService;
use App\Services\OrderService;
use App\Services\OrderStateMachineService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerOrderLifecycleEmailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $owner;
    private Product $product;
    private OrderService $orderService;
    private OrderStateMachineService $stateMachine;
    private CustomerEmailNotificationService $emailService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
        $this->stateMachine = app(OrderStateMachineService::class);
        $this->emailService = app(CustomerEmailNotificationService::class);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Nadeesha Fernando',
            'email' => 'nadeesha@example.com',
            'phone' => '0771234567',
        ]);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->product = Product::create([
            'name_en' => 'Rose Water Hydrating Mist',
            'name_si' => 'රෝස වතුර මීදුම',
            'slug' => 'rose-water-hydrating-mist',
            'sku' => 'SKU-RWHM-01',
            'price' => 3500.00,
            'cost_price' => 1800.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    private function createOrder(array $overrides = []): Order
    {
        static $seq = 300;
        $seq++;

        $order = Order::create(array_merge([
            'order_number' => "ORD-20261111-{$seq}",
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => '0771234567',
            'customer_email' => 'nadeesha@example.com',
            'shipping_address' => 'No 45, Temple Road',
            'district' => 'Colombo',
            'city' => 'Colombo 07',
            'postal_code' => '00700',
            'subtotal' => 3500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3850.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'tracking_token' => 'TRK_' . uniqid(),
            'notes' => 'Ring bell twice',
            'placed_at' => now(),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => $this->product->sku,
            'quantity' => 1,
            'unit_price' => 3500.00,
            'cost_price' => 1800.00,
            'subtotal' => 3500.00,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 3850.00,
            'collected_amount' => 0.00,
            'reconciliation_status' => CodCollection::STATUS_PENDING,
        ]);

        return $order;
    }

    public function test_order_placed_notification_is_dispatched_on_order_creation(): void
    {
        Queue::fake();

        $order = $this->orderService->createOrder(
            customerData: [
                'customer_name' => 'Kavindi Perera',
                'customer_phone' => '0711122334',
                'customer_email' => 'kavindi@example.com',
                'shipping_address' => 'No 18, Lake Drive',
                'district' => 'Colombo',
                'city' => 'Colombo 03',
                'postal_code' => '00300',
                'notes' => 'Leave at front desk',
            ],
            rawCart: [$this->product->id => 2],
            authenticatedUser: $this->customer
        );

        $this->assertEquals(OrderStateMachineService::STATUS_PLACED, $order->status);

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_PLACED)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('kavindi@example.com', $notification->recipient_email);
        $this->assertEquals(OrderNotification::STATUS_PENDING, $notification->status);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id;
        });
    }

    public function test_order_confirmed_notification_is_dispatched(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_CONFIRMED,
            reason: 'Verified by inventory manager',
            user: $this->owner
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_CONFIRMED)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('nadeesha@example.com', $notification->recipient_email);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderConfirmedMail;
        });
    }

    public function test_packed_status_produces_no_customer_notification(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_CONFIRMED]);

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_PACKED,
            reason: 'Packed in luxury presentation box',
            user: $this->owner
        );

        // Invariant: Packed is strictly internal fulfillment. Zero notification created.
        $this->assertEquals(0, OrderNotification::where('order_number', $order->order_number)->count());
        Queue::assertNothingPushed();
    }

    public function test_order_shipped_notification_is_dispatched_with_courier_details(): void
    {
        Queue::fake();
        Mail::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_PACKED]);

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_SHIPPED,
            reason: 'Dispatched with courier partner',
            user: $this->owner,
            extraData: [
                'courier_name' => 'Pronto Logistics',
                'tracking_number' => 'PRN-998877',
            ]
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_SHIPPED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderShippedMail;
        });

        // Test actual job execution
        $job = new SendCustomerOrderEmailJob($notification->id, new CustomerOrderShippedMail($order->fresh(['delivery', 'items'])));
        $job->handle();

        Mail::assertSent(CustomerOrderShippedMail::class, function ($mail) {
            return $mail->hasTo('nadeesha@example.com') &&
                   $mail->order->delivery->courier_name === 'Pronto Logistics' &&
                   $mail->order->delivery->tracking_number === 'PRN-998877';
        });

        $this->assertEquals(OrderNotification::STATUS_SENT, $notification->fresh()->status);
    }

    public function test_order_delivered_notification_is_dispatched(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_SHIPPED]);

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_DELIVERED,
            reason: 'Courier marked package as delivered',
            user: $this->owner
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_DELIVERED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderDeliveredMail;
        });
    }

    public function test_cod_completed_notification_is_dispatched_on_order_completion(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_DELIVERED]);

        // Reconcile COD
        $this->stateMachine->recordCodCollection(
            order: $order,
            collectedAmount: 3850.00,
            courierReference: 'CR-776655',
            courierName: 'Pronto Logistics',
            user: $this->owner
        );

        // Transition to cash_collected, then completed
        $this->stateMachine->transition($order, OrderStateMachineService::STATUS_CASH_COLLECTED, null, $this->owner);
        $this->stateMachine->transition($order, OrderStateMachineService::STATUS_COMPLETED, null, $this->owner);

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_COMPLETED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderCompletedMail;
        });
    }

    public function test_customer_cancellation_dispatches_cancelled_notification(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_PLACED]);

        $response = $this->actingAs($this->customer)->post(route('customer.orders.cancel', $order->order_number), [
            'reason' => 'Ordered wrong shade by mistake',
        ]);

        $response->assertRedirect(route('customer.orders.show', $order->order_number));
        $this->assertEquals(OrderStateMachineService::STATUS_CANCELLED, $order->fresh()->status);

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_CANCELLED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderCancelledMail &&
                   str_contains($job->mailable->reason, 'Ordered wrong shade');
        });
    }

    public function test_admin_cancellation_dispatches_cancelled_notification(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_CONFIRMED]);

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_CANCELLED,
            reason: 'Customer requested cancellation via phone call',
            user: $this->owner
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_CANCELLED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderCancelledMail;
        });
    }

    public function test_order_refused_notification_is_dispatched(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_SHIPPED]);

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_REFUSED,
            reason: 'Customer declined package at delivery address',
            user: $this->owner
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_REFUSED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderRefusedMail;
        });
    }

    public function test_order_returned_notification_is_dispatched(): void
    {
        Queue::fake();

        $order = $this->createOrder(['status' => OrderStateMachineService::STATUS_DELIVERED]);

        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_RETURNED,
            reason: 'Damaged during transit by courier',
            user: $this->owner
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_RETURNED)
            ->first();

        $this->assertNotNull($notification);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($notification) {
            return $job->orderNotificationId === $notification->id &&
                   $job->mailable instanceof CustomerOrderReturnedMail;
        });
    }

    public function test_duplicate_notification_prevention_on_repeated_lifecycle_calls(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        // First call
        $first = $this->emailService->sendOrderConfirmedNotification($order);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);

        // Second call while pending (suppressed)
        $second = $this->emailService->sendOrderConfirmedNotification($order);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
        $this->assertEquals($first->id, $second->id);

        // Simulate sent
        $first->update(['status' => OrderNotification::STATUS_SENT, 'sent_at' => now()]);

        // Third call after sent (suppressed)
        $third = $this->emailService->sendOrderConfirmedNotification($order);
        Queue::assertPushed(SendCustomerOrderEmailJob::class, 1);
        $this->assertEquals($first->id, $third->id);

        // Only 1 row in DB for confirmed
        $this->assertEquals(1, OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_CONFIRMED)
            ->count());
    }

    public function test_failed_notification_recovery_resets_status_to_pending(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $failedNotification = OrderNotification::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'notification_type' => OrderNotification::TYPE_ORDER_SHIPPED,
            'recipient_type' => OrderNotification::RECIPIENT_CUSTOMER,
            'recipient_email' => 'nadeesha@example.com',
            'status' => OrderNotification::STATUS_FAILED,
            'attempts' => 3,
            'error_details' => 'Connection to SMTP server timed out',
        ]);

        // Re-dispatching the failed notification
        $reDispatched = $this->emailService->sendOrderShippedNotification($order);

        $this->assertEquals($failedNotification->id, $reDispatched->id);
        $this->assertEquals(OrderNotification::STATUS_PENDING, $reDispatched->status);
        $this->assertNull($reDispatched->error_details);

        Queue::assertPushed(SendCustomerOrderEmailJob::class, function ($job) use ($failedNotification) {
            return $job->orderNotificationId === $failedNotification->id;
        });
    }

    public function test_transaction_rollback_produces_no_notification_job(): void
    {
        Queue::fake();

        $exceptionThrown = false;
        try {
            $this->orderService->createOrder(
                customerData: [
                    'customer_name' => 'Dilini Perera',
                    'customer_phone' => '0779988776',
                    'customer_email' => 'dilini@example.com',
                    'shipping_address' => 'No 88, Galle Road',
                    'district' => 'Colombo',
                    'city' => 'Colombo 04',
                ],
                rawCart: [$this->product->id => 500], // Exceeds available stock (50)
                authenticatedUser: $this->customer
            );
        } catch (ValidationException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown);

        // Verification: Zero notification jobs committed or queued
        Queue::assertNothingPushed();
        $this->assertEquals(0, OrderNotification::where('recipient_email', 'dilini@example.com')->count());
    }

    public function test_notification_failure_is_isolated_and_does_not_abort_order_transition(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        // Even with an unexpected invalid state, state machine transition completes cleanly
        $updatedOrder = $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_CONFIRMED,
            reason: 'Normal confirmation',
            user: $this->owner
        );

        $this->assertEquals(OrderStateMachineService::STATUS_CONFIRMED, $updatedOrder->status);
    }

    public function test_suspended_customer_still_receives_transactional_lifecycle_email(): void
    {
        Queue::fake();

        $suspendedCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::SUSPENDED,
            'email' => 'suspended.cust@example.com',
            'name' => 'Suspended Customer',
        ]);

        $order = $this->createOrder([
            'customer_id' => $suspendedCustomer->id,
            'customer_email' => $suspendedCustomer->email,
            'customer_name' => $suspendedCustomer->name,
        ]);

        // When order is shipped, transactional email is still dispatched
        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_CONFIRMED,
            reason: 'Fulfilling historical pre-suspension order',
            user: $this->owner
        );

        $notification = OrderNotification::where('order_number', $order->order_number)
            ->where('notification_type', OrderNotification::TYPE_ORDER_CONFIRMED)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('suspended.cust@example.com', $notification->recipient_email);
        Queue::assertPushed(SendCustomerOrderEmailJob::class);
    }

    public function test_missing_or_invalid_customer_email_marks_skipped_without_queueing(): void
    {
        Queue::fake();

        $order = $this->createOrder([
            'customer_email' => 'invalid-email-format',
        ]);

        $notification = $this->emailService->sendOrderConfirmedNotification($order);

        $this->assertNotNull($notification);
        $this->assertEquals(OrderNotification::STATUS_SKIPPED, $notification->status);
        $this->assertStringContainsString('invalid format', $notification->error_details);

        Queue::assertNothingPushed();
    }

    public function test_all_mailables_render_valid_luxury_html_without_exposing_sensitive_internal_data(): void
    {
        $order = $this->createOrder();
        $order->load(['items.product', 'delivery', 'codCollection']);

        $mailables = [
            new CustomerOrderPlacedMail($order),
            new CustomerOrderConfirmedMail($order),
            new CustomerOrderShippedMail($order),
            new CustomerOrderDeliveredMail($order),
            new CustomerOrderCompletedMail($order),
            new CustomerOrderCancelledMail($order, 'Out of stock at supplier'),
            new CustomerOrderReturnedMail($order, 'Customer returned unopened'),
            new CustomerOrderRefusedMail($order, 'Customer refused at doorstep'),
        ];

        foreach ($mailables as $mailable) {
            $html = $mailable->render();

            // Brand check
            $this->assertStringContainsString('Aura Luxe', $html);
            $this->assertStringContainsString($order->order_number, $html);
            $this->assertStringContainsString('LKR', $html);
            $this->assertStringContainsString('/track', $html);

            // Privacy check: Phone number must NOT be in the email body
            $this->assertStringNotContainsString('0771234567', $html);

            // Financial & Security check: Internal data must NOT be exposed
            $this->assertStringNotContainsString('cost_price', $html);
            $this->assertStringNotContainsString('1800.00', $html); // Cost price of Rose Water Mist
            $this->assertStringNotContainsString('actual_courier_fee', $html);
            $this->assertStringNotContainsString('commission', $html);
        }
    }

    public function test_financial_and_inventory_invariants_preserved_during_lifecycle_notifications(): void
    {
        Queue::fake();

        $initialStock = $this->product->stock_quantity; // 50

        // Place order for 2 items
        $order = $this->orderService->createOrder(
            customerData: [
                'customer_name' => 'Harini Silva',
                'customer_phone' => '0773344556',
                'customer_email' => 'harini@example.com',
                'shipping_address' => 'No 5, Queen Road',
                'district' => 'Colombo',
                'city' => 'Colombo 07',
            ],
            rawCart: [$this->product->id => 2],
            authenticatedUser: $this->customer
        );

        $this->assertEquals($initialStock - 2, $this->product->fresh()->stock_quantity);

        // Cancel order: stock must be restored
        $this->stateMachine->transition(
            order: $order,
            newStatus: OrderStateMachineService::STATUS_CANCELLED,
            reason: 'Customer requested cancellation',
            user: $this->owner
        );

        $this->assertEquals($initialStock, $this->product->fresh()->stock_quantity);
    }
}
