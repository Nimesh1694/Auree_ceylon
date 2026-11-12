<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\OrderService;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCostPriceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
        $this->orderService = app(OrderService::class);

        $this->product = Product::create([
            'name_en' => 'Rosewater Cleanser',
            'name_si' => 'රෝස වතුර ක්ලෙන්සර්',
            'slug' => 'rosewater-cleanser',
            'sku' => 'AL-RC-001',
            'price' => 3000.00,
            'cost_price' => 1800.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    public function test_order_checkout_captures_historical_cost_price_snapshot(): void
    {
        $customerData = [
            'customer_name' => 'Dilshan Test',
            'customer_phone' => '0771234567',
            'shipping_address' => '123 Galle Road, Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'notes' => 'Test order',
        ];

        $rawCart = [
            $this->product->id => 2,
        ];

        $order = $this->orderService->createOrder($customerData, $rawCart);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 3000.00,
            'cost_price' => 1800.00,
        ]);

        $orderItem = $order->items()->first();
        $this->assertNotNull($orderItem->cost_price);
        $this->assertEquals(1800.00, (float) $orderItem->cost_price);
        $this->assertTrue($orderItem->isCostAudited());
        $this->assertEquals(1800.00, $orderItem->getEffectiveCostPrice());
    }

    public function test_subsequent_product_cost_price_changes_do_not_alter_historical_order_item_cost(): void
    {
        $customerData = [
            'customer_name' => 'Historical Integrity Test',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 Kandy Road',
            'district' => 'Kandy',
            'city' => 'Kandy',
        ];

        $rawCart = [
            $this->product->id => 1,
        ];

        $order = $this->orderService->createOrder($customerData, $rawCart);
        $orderItem = $order->items()->first();
        $this->assertEquals(1800.00, (float) $orderItem->cost_price);

        // Later supplier cost increase
        $this->product->update([
            'cost_price' => 2500.00,
            'price' => 3500.00,
        ]);

        // Reload order item from database
        $refreshedItem = OrderItem::find($orderItem->id);

        // Snapshot is locked at 1800.00, unaffected by current product cost of 2500.00
        $this->assertEquals(1800.00, (float) $refreshedItem->cost_price);
        $this->assertEquals(1800.00, $refreshedItem->getEffectiveCostPrice());
    }

    public function test_legacy_order_items_without_cost_price_are_handled_gracefully(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-LEGACY-001',
            'customer_name' => 'Legacy Customer',
            'customer_phone' => '0779998877',
            'shipping_address' => 'Old Street',
            'district' => 'Galle',
            'city' => 'Galle',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'tracking_token' => 'TOKEN_LEGACY_001',
            'placed_at' => now(),
        ]);

        // Simulate legacy order item where cost_price is NULL
        $legacyItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Rosewater Cleanser',
            'product_sku' => 'AL-RC-001',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => null,
        ]);

        $this->assertNull($legacyItem->cost_price);
        $this->assertFalse($legacyItem->isCostAudited());
        // Falls back to product current cost price as estimated baseline
        $this->assertEquals(1800.00, $legacyItem->getEffectiveCostPrice());
    }

    public function test_cost_price_is_hidden_from_serialized_order_item_payloads(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-SECURITY-001',
            'customer_name' => 'Security Check',
            'customer_phone' => '0779998877',
            'shipping_address' => 'Secure Road',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'tracking_token' => 'TOKEN_SECURITY_001',
            'placed_at' => now(),
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Rosewater Cleanser',
            'product_sku' => 'AL-RC-001',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1800.00,
        ]);

        $arrayPayload = $orderItem->toArray();
        $jsonPayload = json_encode($orderItem);

        $this->assertArrayNotHasKey('cost_price', $arrayPayload);
        $this->assertStringNotContainsString('"cost_price"', $jsonPayload);
    }
}
