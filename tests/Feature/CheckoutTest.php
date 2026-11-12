<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
    }

    public function test_guest_can_place_cod_order_successfully(): void
    {
        $product = Product::create([
            'name_en' => 'Botanical Night Elixir',
            'name_si' => 'රාත්‍රී එලික්සර්',
            'slug' => 'botanical-night-elixir',
            'sku' => 'AL-ELI-005',
            'price' => 4200.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        session([
            'cart' => [$product->id => 2],
            'referral_code' => 'SPONSOR123',
        ]);

        $checkoutData = [
            'customer_name' => 'Dilani Wijeratne',
            'customer_phone' => '0779876543',
            'customer_email' => 'dilani@example.com',
            'shipping_address' => 'No 12, Temple Road',
            'district' => 'Gampaha',
            'city' => 'Negombo',
            'postal_code' => '11500',
            'notes' => 'Ring bell twice',
        ];

        $response = $this->post('/checkout', $checkoutData);

        // 1. Order created in database
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Dilani Wijeratne',
            'customer_phone' => '0779876543',
            'district' => 'Gampaha',
            'city' => 'Negombo',
            'subtotal' => 8400.00,       // 2 * 4200.00
            'delivery_charge' => 350.00,
            'total_amount' => 8750.00,   // 8400 + 350
            'payment_method' => 'cod',
            'status' => 'placed',
            'referral_code' => 'SPONSOR123',
        ]);

        $order = Order::where('customer_phone', '0779876543')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->customer_id); // Guest customer!

        // 2. OrderItem has immutable snapshot
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Botanical Night Elixir',
            'product_sku' => 'AL-ELI-005',
            'unit_price' => 4200.00,
            'quantity' => 2,
            'subtotal' => 8400.00,
        ]);

        // 3. Stock safely decremented from 15 to 13
        $this->assertEquals(13, $product->fresh()->stock_quantity);

        // 4. Delivery record created
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        // 5. Cart cleared
        $this->assertEmpty(session('cart', []));

        // 6. Redirected to confirmation with tracking token
        $response->assertRedirect(route('order.confirmation', [
            'orderNumber' => $order->order_number,
            'token' => $order->tracking_token,
        ]));
    }

    public function test_authenticated_user_places_order_with_user_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Nuwan Gamage',
            'phone' => '0711122334',
        ]);

        $product = Product::create([
            'name_en' => 'Day Protection Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'day-protection-cream',
            'price' => 2000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $response = $this->actingAs($user)->post('/checkout', [
            'customer_name' => 'Nuwan Gamage',
            'customer_phone' => '0711122334',
            'shipping_address' => '45/2 Peradeniya Road',
            'district' => 'Kandy',
            'city' => 'Kandy City',
        ]);

        $order = Order::where('customer_phone', '0711122334')->first();
        $this->assertNotNull($order);
        $this->assertEquals($user->id, $order->customer_id);
    }

    public function test_checkout_fails_if_cart_is_empty(): void
    {
        session(['cart' => []]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Empty Buyer',
            'customer_phone' => '0770000000',
            'shipping_address' => 'Nowhere St',
            'district' => 'Colombo',
            'city' => 'Colombo',
        ]);

        $response->assertRedirect('/cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_if_stock_is_insufficient(): void
    {
        $product = Product::create([
            'name_en' => 'Rare Orchid Serum',
            'name_si' => 'සීරම්',
            'slug' => 'rare-orchid-serum',
            'price' => 6000.00,
            'stock_quantity' => 1, // Only 1 left!
            'is_active' => true,
        ]);

        // Cart somehow requested 5 (e.g. concurrent order reduced stock)
        session(['cart' => [$product->id => 5]]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Late Buyer',
            'customer_phone' => '0770001122',
            'shipping_address' => 'Galle Fort',
            'district' => 'Galle',
            'city' => 'Galle',
        ]);

        $response->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(1, $product->fresh()->stock_quantity); // Stock untouched!
    }

    public function test_checkout_validates_required_fields(): void
    {
        $response = $this->post('/checkout', []);

        $response->assertSessionHasErrors([
            'customer_name',
            'customer_phone',
            'shipping_address',
            'district',
            'city',
        ]);
    }
}

