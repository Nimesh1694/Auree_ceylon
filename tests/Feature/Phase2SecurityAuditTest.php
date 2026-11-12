<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase2SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
    }

    /**
     * Scenario 1: A customer cannot manipulate product prices through browser requests.
     */
    public function test_customer_cannot_manipulate_price_via_request_payload(): void
    {
        $product = Product::create([
            'name_en' => 'Luxury Botanical Serum',
            'name_si' => 'සීරම්',
            'slug' => 'luxury-botanical-serum',
            'sku' => 'AL-SER-999',
            'price' => 5000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        // Attacker attempts to post their own price and delivery fee
        $response = $this->post('/checkout', [
            'customer_name' => 'Adversary User',
            'customer_phone' => '0770001122',
            'shipping_address' => '123 Exploit Way',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'price' => 1.00,             // Injected price
            'unit_price' => 1.00,        // Injected unit price
            'subtotal' => 1.00,          // Injected subtotal
            'delivery_charge' => 0.00,   // Injected free delivery
            'total_amount' => 1.00,      // Injected total
        ]);

        $order = Order::where('customer_phone', '0770001122')->first();
        $this->assertNotNull($order);

        // Assert server computed true price and ignored all injected amounts
        $this->assertEquals('5000.00', (string) $order->subtotal);
        $this->assertEquals('350.00', (string) $order->delivery_charge);
        $this->assertEquals('5350.00', (string) $order->total_amount);

        $orderItem = $order->items->first();
        $this->assertEquals('5000.00', (string) $orderItem->unit_price);
        $this->assertEquals('5000.00', (string) $orderItem->subtotal);
    }

    /**
     * Scenario 2: A customer cannot order more stock than is available.
     */
    public function test_customer_cannot_order_more_stock_than_available(): void
    {
        $product = Product::create([
            'name_en' => 'Limited Edition Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'limited-edition-cream',
            'price' => 2500.00,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        // Cart requesting 5 items when only 2 exist
        session(['cart' => [$product->id => 5]]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Greedy Buyer',
            'customer_phone' => '0771112233',
            'shipping_address' => 'No 5, Beach Road',
            'district' => 'Galle',
            'city' => 'Galle',
        ]);

        $response->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(2, $product->fresh()->stock_quantity); // Stock unchanged
    }

    /**
     * Scenario 3: Stock validation during cart operations caps or rejects out-of-bounds requests.
     */
    public function test_cart_operations_enforce_stock_bounds_and_reject_invalid_quantities(): void
    {
        $product = Product::create([
            'name_en' => 'Hydrating Toner',
            'name_si' => 'ටෝනර්',
            'slug' => 'hydrating-toner',
            'price' => 1500.00,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $cartService = app(CartService::class);

        // Adding 10 items to cart when only 3 exist should cap at 3
        $cartService->add($product->id, 10);
        $details = $cartService->getDetails();
        $this->assertEquals(3, $details['items'][0]['quantity']);

        // Updating to 5 should also cap at available stock (3)
        $cartService->update($product->id, 5);
        $details = $cartService->getDetails();
        $this->assertEquals(3, $details['items'][0]['quantity']);

        // Updating to 0 removes the item
        $cartService->update($product->id, 0);
        $details = $cartService->getDetails();
        $this->assertEmpty($details['items']);
    }

    /**
     * Scenario 4: A failed checkout does not leave partial stock deductions.
     */
    public function test_failed_checkout_rolls_back_atomic_transaction_completely(): void
    {
        $product1 = Product::create([
            'name_en' => 'In-Stock Item',
            'name_si' => 'අයිතමය 1',
            'slug' => 'in-stock-item',
            'price' => 1000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'name_en' => 'Low-Stock Item',
            'name_si' => 'අයිතමය 2',
            'slug' => 'low-stock-item',
            'price' => 2000.00,
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        // Cart requests 2 of product1 (available) and 5 of product2 (unavailable)
        session(['cart' => [
            $product1->id => 2,
            $product2->id => 5,
        ]]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Atomic Test Customer',
            'customer_phone' => '0772223344',
            'shipping_address' => '77 Rollback St',
            'district' => 'Colombo',
            'city' => 'Colombo',
        ]);

        $response->assertSessionHasErrors('stock');

        // Verify product 1 stock was NOT decremented despite appearing earlier in the cart loop
        $this->assertEquals(10, $product1->fresh()->stock_quantity);
        $this->assertEquals(1, $product2->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('deliveries', 0);
    }

    /**
     * Scenario 5: Duplicate checkout request does not create duplicate order.
     */
    public function test_subsequent_duplicate_checkout_does_not_create_duplicate_orders(): void
    {
        $product = Product::create([
            'name_en' => 'Herbal Shampoo',
            'name_si' => 'ෂැම්පු',
            'slug' => 'herbal-shampoo',
            'price' => 1200.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $checkoutData = [
            'customer_name' => 'Single Order Buyer',
            'customer_phone' => '0773334455',
            'shipping_address' => '99 Main Road',
            'district' => 'Matara',
            'city' => 'Matara',
        ];

        // 1st request succeeds
        $firstResponse = $this->post('/checkout', $checkoutData);
        $firstResponse->assertStatus(302);
        $this->assertDatabaseCount('orders', 1);

        // 2nd request (duplicate submission after cart is cleared)
        $secondResponse = $this->post('/checkout', $checkoutData);
        $secondResponse->assertRedirect('/cart');
        $this->assertDatabaseCount('orders', 1); // Still exactly 1 order!
    }

    /**
     * Scenario 6: Immutable snapshot retains original product name and price after admin changes product.
     */
    public function test_order_item_preserves_immutable_snapshot_after_product_modification(): void
    {
        $product = Product::create([
            'name_en' => 'Original Botanical Serum',
            'name_si' => 'මුල් සීරම්',
            'slug' => 'original-botanical-serum',
            'sku' => 'AL-SNAP-01',
            'price' => 3000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $this->post('/checkout', [
            'customer_name' => 'Snapshot Customer',
            'customer_phone' => '0774445566',
            'shipping_address' => '100 Snapshot Lane',
            'district' => 'Kalutara',
            'city' => 'Panadura',
        ]);

        $order = Order::where('customer_phone', '0774445566')->first();
        $this->assertNotNull($order);

        // Admin later changes price, name, and sku on the product
        $product->update([
            'name_en' => 'Updated Pricey Serum Ultra',
            'sku' => 'AL-SNAP-MODIFIED',
            'price' => 7500.00,
        ]);

        // Historical order item must remain completely untouched!
        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertEquals('Original Botanical Serum', $item->product_name);
        $this->assertEquals('AL-SNAP-01', $item->product_sku);
        $this->assertEquals('3000.00', (string) $item->unit_price);
        $this->assertEquals('3000.00', (string) $item->subtotal);
    }

    /**
     * Scenario 7: Order confirmation rejects invalid or missing tracking token.
     */
    public function test_confirmation_strictly_rejects_missing_or_invalid_tracking_token(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-TOKEN-99',
            'customer_name' => 'Token Test User',
            'customer_phone' => '0775556677',
            'shipping_address' => 'Token St',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 1000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 1350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'CRYPTO_SECURE_TOKEN_ABC_999',
            'placed_at' => now(),
        ]);

        // Missing token -> 404
        $this->get("/orders/{$order->order_number}/confirmation")->assertStatus(404);

        // Manipulated token -> 404
        $this->get("/orders/{$order->order_number}/confirmation?token=TAMPERED_TOKEN")->assertStatus(404);

        // Valid token -> 200
        $this->get("/orders/{$order->order_number}/confirmation?token=CRYPTO_SECURE_TOKEN_ABC_999")->assertStatus(200);
    }

    /**
     * Scenario 8: Tracking requires both order number and customer phone.
     */
    public function test_tracking_requires_both_order_number_and_phone(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TRACK-TEST-1',
            'customer_name' => 'Privacy Conscious',
            'customer_phone' => '0776667788',
            'shipping_address' => 'Secret Ave',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 2000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TOKEN_123',
            'placed_at' => now(),
        ]);

        // Wrong phone -> no order exposed
        $response = $this->post('/track', [
            'order_number' => 'ORD-TRACK-TEST-1',
            'phone' => '0770000000',
        ]);
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('order', null));

        // Correct order number and phone -> returns order
        $responseMatch = $this->post('/track', [
            'order_number' => 'ORD-TRACK-TEST-1',
            'phone' => '0776667788',
        ]);
        $responseMatch->assertStatus(200);
        $responseMatch->assertInertia(fn ($page) =>
            $page->where('order.order_number', 'ORD-TRACK-TEST-1')
                ->where('order.customer_name', 'Privacy Conscious')
        );
    }
}

