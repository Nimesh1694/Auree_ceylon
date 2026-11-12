<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $accountant;
    private User $customer;
    private Product $product;
    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->accountant = User::factory()->create(['role' => UserRole::ACCOUNTANT]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->product = Product::create([
            'name_en' => 'Gotukola Eye Gel',
            'name_si' => 'ගොටුකොළ ජෙල්',
            'slug' => 'gotukola-eye-gel',
            'price' => 1800.00,
            'stock_quantity' => 15,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $this->stockService = app(StockService::class);
    }

    public function test_customer_cannot_adjust_stock_or_view_inventory(): void
    {
        $this->actingAs($this->customer)->get('/admin/inventory')->assertStatus(403);
        $this->actingAs($this->customer)->post('/admin/inventory/adjust', [])->assertStatus(403);
    }

    public function test_owner_can_restock_product_with_immutable_ledger_record(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/inventory/adjust', [
            'product_id' => $this->product->id,
            'quantity_delta' => 25,
            'movement_type' => 'restock',
            'reason' => 'Batch #GK-01 restocked from production lab',
        ]);

        $response->assertSessionHas('success');

        // Product stock increased from 15 to 40
        $this->assertEquals(40, $this->product->fresh()->stock_quantity);

        // Immutable ledger record created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'restock',
            'quantity' => 25,
            'previous_stock' => 15,
            'new_stock' => 40,
            'reason' => 'Batch #GK-01 restocked from production lab',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_stock_adjustment_strictly_prevents_negative_stock(): void
    {
        // Current stock is 15. Attempting to deduct 20 units.
        $this->expectException(ValidationException::class);

        $this->stockService->adjustStock(
            product: $this->product,
            quantityDelta: -20,
            movementType: InventoryMovement::TYPE_ADJUSTMENT,
            reason: 'Excessive deduction test',
            user: $this->owner
        );

        $this->assertEquals(15, $this->product->fresh()->stock_quantity); // Untouched
    }

    public function test_accountant_can_view_movements_but_cannot_perform_adjustments(): void
    {
        $this->actingAs($this->accountant)->get('/admin/inventory/movements')->assertStatus(200);

        // Accountant is not permitted to adjust physical inventory directly
        $response = $this->actingAs($this->accountant)->post('/admin/inventory/adjust', [
            'product_id' => $this->product->id,
            'quantity_delta' => 10,
            'movement_type' => 'restock',
            'reason' => 'Unauthorized attempt',
        ]);
        $response->assertStatus(403);
    }
}

