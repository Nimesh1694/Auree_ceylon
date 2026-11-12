<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = app(CartService::class);
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
    }

    public function test_can_add_item_to_cart(): void
    {
        $product = Product::create([
            'name_en' => 'Serum One',
            'name_si' => 'සීරම් එක',
            'slug' => 'serum-one',
            'price' => 3000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertSessionHas('cart', [
            $product->id => 2,
        ]);
        $response->assertSessionHas('success');
    }

    public function test_can_update_item_quantity(): void
    {
        $product = Product::create([
            'name_en' => 'Lipstick Two',
            'name_si' => 'ලිප්ස්ටික් දෙක',
            'slug' => 'lipstick-two',
            'price' => 2000.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 2]]);

        $response = $this->patch('/cart/update', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertSessionHas('cart', [
            $product->id => 5,
        ]);
    }

    public function test_cannot_exceed_available_stock_in_cart(): void
    {
        $product = Product::create([
            'name_en' => 'Limited Edition Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'limited-cream',
            'price' => 5000.00,
            'stock_quantity' => 3, // Only 3 in stock!
            'is_active' => true,
        ]);

        // Attempting to add 10 items
        $this->cartService->add($product->id, 10);

        $cart = $this->cartService->getRawCart();
        $this->assertEquals(3, $cart[$product->id]); // Capped at 3!
    }

    public function test_can_remove_item_from_cart(): void
    {
        $product = Product::create([
            'name_en' => 'Toner Three',
            'name_si' => 'ටෝනර්',
            'slug' => 'toner-three',
            'price' => 1500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 2]]);

        $response = $this->delete("/cart/remove/{$product->id}");
        $response->assertSessionMissing("cart.{$product->id}");
    }

    public function test_server_calculates_verified_cart_totals(): void
    {
        $p1 = Product::create([
            'name_en' => 'Product A',
            'name_si' => 'භාණ්ඩය A',
            'slug' => 'product-a',
            'price' => 2500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $p2 = Product::create([
            'name_en' => 'Product B',
            'name_si' => 'භාණ්ඩය B',
            'slug' => 'product-b',
            'price' => 1200.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // 2 of A (5000) + 3 of B (3600) = Subtotal 8600.00
        // Delivery charge = 350.00
        // Total = 8950.00
        session(['cart' => [
            $p1->id => 2,
            $p2->id => 3,
        ]]);

        $details = $this->cartService->getDetails();

        $this->assertEquals(8600.00, $details['subtotal']);
        $this->assertEquals(350.00, $details['delivery_charge']);
        $this->assertEquals(8950.00, $details['total']);
        $this->assertFalse($details['has_out_of_stock']);
    }

    public function test_cart_flags_out_of_stock_item(): void
    {
        $product = Product::create([
            'name_en' => 'Sold Out Balm',
            'name_si' => 'බාම්',
            'slug' => 'sold-out-balm',
            'price' => 1000.00,
            'stock_quantity' => 0, // Out of stock!
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $details = $this->cartService->getDetails();
        $this->assertTrue($details['has_out_of_stock']);
    }
}

