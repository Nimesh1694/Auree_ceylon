<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::create([
            'name_en' => 'Jasmine Face Mist',
            'name_si' => 'මිස්ට්',
            'slug' => 'jasmine-face-mist',
            'price' => 1800.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-20261102-TEST1',
            'customer_name' => 'Kusal Mendis',
            'customer_phone' => '0771234567',
            'shipping_address' => '10 Havelock Road',
            'district' => 'Colombo',
            'city' => 'Colombo 05',
            'subtotal' => 1800.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2150.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'SECURE_TOKEN_XYZ_123',
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'product_name' => 'Jasmine Face Mist',
            'unit_price' => 1800.00,
            'quantity' => 1,
            'subtotal' => 1800.00,
        ]);
    }

    public function test_can_track_order_with_valid_order_number_and_phone(): void
    {
        $response = $this->post('/track', [
            'order_number' => 'ORD-20261102-TEST1',
            'phone' => '0771234567',
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', true)
                ->where('order.order_number', 'ORD-20261102-TEST1')
                ->where('order.customer_name', 'Kusal Mendis')
        );
    }

    public function test_tracking_fails_if_phone_number_does_not_match(): void
    {
        // Attacker knows order number but doesn't know phone number
        $response = $this->post('/track', [
            'order_number' => 'ORD-20261102-TEST1',
            'phone' => '0779999999', // Wrong phone number!
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', true)
                ->where('order', null) // No order data exposed!
        );
    }

    public function test_tracking_fails_if_order_number_does_not_exist(): void
    {
        $response = $this->post('/track', [
            'order_number' => 'ORD-NON-EXISTENT',
            'phone' => '0771234567',
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', true)
                ->where('order', null)
        );
    }

    public function test_confirmation_page_requires_valid_tracking_token(): void
    {
        // Attempting to view confirmation without token
        $response = $this->get("/orders/{$this->order->order_number}/confirmation");
        $response->assertStatus(404);

        // Attempting to view confirmation with wrong token
        $responseWrong = $this->get("/orders/{$this->order->order_number}/confirmation?token=INVALID");
        $responseWrong->assertStatus(404);

        // Viewing confirmation with valid matching token
        $responseValid = $this->get("/orders/{$this->order->order_number}/confirmation?token=SECURE_TOKEN_XYZ_123");
        $responseValid->assertStatus(200);
        $responseValid->assertInertia(fn ($page) =>
            $page->component('Checkout/Confirmation')
                ->where('order.order_number', 'ORD-20261102-TEST1')
        );
    }
}

