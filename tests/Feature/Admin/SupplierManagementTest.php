<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $invManager;
    private User $marketingManager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->makeUser(UserRole::OWNER, 'owner');
        $this->superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, 'superadmin');
        $this->invManager = $this->makeUser(UserRole::PRODUCT_INVENTORY_MANAGER, 'invmgr');
        $this->marketingManager = $this->makeUser(UserRole::MARKETING_MANAGER, 'marketing');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner->refresh();
        $this->superAdmin->refresh();
        $this->invManager->refresh();
        $this->marketingManager->refresh();

        $category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare-' . uniqid(),
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Aura Night Cream',
            'name_si' => 'අවුරා නයිට් ක්‍රීම්',
            'slug' => 'aura-night-cream-' . uniqid(),
            'sku' => 'ANC-001',
            'price' => 3500.00,
            'cost_price' => 1500.00,
            'stock_quantity' => 25,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, string $prefix): User
    {
        return User::create([
            'name' => ucfirst($prefix),
            'email' => "{$prefix}-" . uniqid() . '@auraluxe.test',
            'password' => bcrypt('Secret123!'),
            'role' => $role->value,
            'status' => UserStatus::ACTIVE->value,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_supplier_crud_auto_code_toggle_search_and_validation(): void
    {
        // Create supplier without code -> auto-generates SUP-YYYY-NNNN
        $response = $this->actingAs($this->invManager)->post('/admin/suppliers', [
            'name' => 'Ceylon Naturals Pvt Ltd',
            'contact_person' => 'Kamal Perera',
            'email' => 'kamal@ceylonnaturals.lk',
            'phone' => '0771234567',
            'payment_terms' => 'net_30',
            'default_lead_time_days' => 10,
            'status' => 'active',
        ]);

        $supplier = Supplier::where('name', 'Ceylon Naturals Pvt Ltd')->firstOrFail();
        $response->assertRedirect(route('admin.suppliers.show', $supplier));
        $this->assertMatchesRegularExpression('/^SUP-\d{4}-\d{4}$/', $supplier->code);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier_created',
            'auditable_type' => Supplier::class,
            'auditable_id' => $supplier->id,
        ]);

        // Duplicate code validation fails
        $this->actingAs($this->invManager)->post('/admin/suppliers', [
            'code' => $supplier->code,
            'name' => 'Duplicate Code Supplier',
            'payment_terms' => 'net_30',
            'default_lead_time_days' => 7,
            'status' => 'active',
        ])->assertSessionHasErrors('code');

        // Validation failure on invalid payment_terms
        $this->actingAs($this->invManager)->post('/admin/suppliers', [
            'name' => 'Invalid Terms Supplier',
            'payment_terms' => 'invalid_term',
            'default_lead_time_days' => -1,
            'status' => 'active',
        ])->assertSessionHasErrors(['payment_terms', 'default_lead_time_days']);

        // Update supplier
        $this->actingAs($this->invManager)->put("/admin/suppliers/{$supplier->id}", [
            'code' => $supplier->code,
            'name' => 'Ceylon Naturals Holdings',
            'contact_person' => 'Kamal Perera',
            'payment_terms' => 'net_15',
            'default_lead_time_days' => 5,
            'status' => 'active',
        ])->assertRedirect(route('admin.suppliers.show', $supplier));

        $this->assertSame('Ceylon Naturals Holdings', $supplier->fresh()->name);

        // Toggle status active -> inactive -> active
        $this->actingAs($this->invManager)
            ->patch("/admin/suppliers/{$supplier->id}/toggle-status")
            ->assertRedirect();
        $this->assertSame(Supplier::STATUS_INACTIVE, $supplier->fresh()->status);

        // Search and status filter
        $this->actingAs($this->invManager)
            ->get('/admin/suppliers?search=Holdings&status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Suppliers/Index')
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.id', $supplier->id)
            );
    }

    public function test_supplier_product_relationship_and_preferred_enforcement(): void
    {
        $service = app(SupplierService::class);
        $supplierA = $service->createSupplier(['name' => 'Supplier Alpha'], $this->owner);
        $supplierB = $service->createSupplier(['name' => 'Supplier Beta'], $this->owner);

        // Link product to Supplier A as preferred
        $this->actingAs($this->invManager)->post("/admin/suppliers/{$supplierA->id}/products", [
            'product_id' => $this->product->id,
            'supplier_sku' => 'ALPHA-01',
            'unit_cost' => 1400.00,
            'lead_time_days' => 6,
            'min_order_qty' => 10,
            'is_preferred' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_supplier', [
            'supplier_id' => $supplierA->id,
            'product_id' => $this->product->id,
            'is_preferred' => true,
            'preferred_product_id' => $this->product->id,
        ]);

        // Link product to Supplier B and update it to preferred -> clears A
        $this->actingAs($this->invManager)->post("/admin/suppliers/{$supplierB->id}/products", [
            'product_id' => $this->product->id,
            'supplier_sku' => 'BETA-01',
            'unit_cost' => 1350.00,
            'min_order_qty' => 5,
            'is_preferred' => false,
        ])->assertRedirect();

        $this->actingAs($this->invManager)
            ->patch("/admin/suppliers/{$supplierB->id}/products/{$this->product->id}/preferred")
            ->assertRedirect();

        $linkA = ProductSupplier::where('supplier_id', $supplierA->id)->where('product_id', $this->product->id)->firstOrFail();
        $linkB = ProductSupplier::where('supplier_id', $supplierB->id)->where('product_id', $this->product->id)->firstOrFail();

        $this->assertFalse($linkA->is_preferred);
        $this->assertNull($linkA->preferred_product_id);
        $this->assertTrue($linkB->is_preferred);
        $this->assertSame($this->product->id, $linkB->preferred_product_id);

        // Update link details
        $this->actingAs($this->invManager)->put("/admin/suppliers/{$supplierB->id}/products/{$this->product->id}", [
            'supplier_sku' => 'BETA-02',
            'unit_cost' => 1300.00,
            'lead_time_days' => 4,
            'min_order_qty' => 12,
            'is_preferred' => true,
        ])->assertRedirect();

        $this->assertSame('BETA-02', $linkB->fresh()->supplier_sku);

        // Remove relationship safely
        $this->actingAs($this->invManager)
            ->delete("/admin/suppliers/{$supplierA->id}/products/{$this->product->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('product_supplier', ['id' => $linkA->id]);
    }

    public function test_preferred_supplier_db_uniqueness_and_transaction_rollback(): void
    {
        $service = app(SupplierService::class);
        $supplierA = $service->createSupplier(['name' => 'Alpha'], $this->owner);
        $supplierB = $service->createSupplier(['name' => 'Beta'], $this->owner);

        $service->linkProduct($supplierA, $this->product, [
            'unit_cost' => 1400,
            'min_order_qty' => 1,
            'is_preferred' => true,
        ], $this->owner);

        // Simulate concurrent / raw bypass attempting to set a second preferred_product_id for same product
        $threw = false;
        try {
            DB::table('product_supplier')->insert([
                'product_id' => $this->product->id,
                'supplier_id' => $supplierB->id,
                'unit_cost' => 1350,
                'min_order_qty' => 1,
                'is_preferred' => true,
                'preferred_product_id' => $this->product->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Database UNIQUE constraint on preferred_product_id must reject duplicate preferred suppliers.');

        // Link Supplier B non-preferred, then simulate failed transaction rollback when switching preferred
        $service->linkProduct($supplierB, $this->product, [
            'unit_cost' => 1350,
            'min_order_qty' => 1,
            'is_preferred' => false,
        ], $this->owner);

        try {
            DB::transaction(function () use ($service, $supplierB) {
                $service->setPreferredSupplier($supplierB, $this->product, $this->owner);
                throw new \RuntimeException('Simulated failure after preferred switch');
            });
        } catch (\RuntimeException) {
            // Expected rollback
        }

        // Supplier A must still be the sole preferred supplier after rollback
        $this->assertSame(
            $supplierA->id,
            ProductSupplier::where('product_id', $this->product->id)->where('is_preferred', true)->value('supplier_id')
        );
    }

    public function test_authorization_and_cost_visibility_protection(): void
    {
        $service = app(SupplierService::class);
        $supplier = $service->createSupplier(['name' => 'Protected Cost Supplier'], $this->owner);
        $service->linkProduct($supplier, $this->product, [
            'unit_cost' => 1999.50,
            'min_order_qty' => 5,
            'is_preferred' => true,
        ], $this->owner);

        // Owner and Super Admin can access suppliers
        $this->actingAs($this->owner)->get('/admin/suppliers')->assertOk();
        $this->actingAs($this->superAdmin)->get('/admin/suppliers')->assertOk();

        // Unauthorized management user gets 403
        $this->actingAs($this->marketingManager)->get('/admin/suppliers')->assertForbidden();
        $this->actingAs($this->marketingManager)->post('/admin/suppliers', ['name' => 'Blocked'])->assertForbidden();

        // Custom role with suppliers.view ONLY (no products.cost_price.view) must NOT receive unit_cost
        $customRole = Role::create([
            'name' => 'supply_viewer',
            'display_name' => 'Supply Viewer',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $viewPerm = Permission::where('name', 'suppliers.view')->firstOrFail();
        $customRole->permissions()->sync([$viewPerm->id]);

        $customViewer = $this->makeUser(UserRole::OPERATIONS_MANAGER, 'viewer');
        $customViewer->update(['role_id' => $customRole->id]);
        $customViewer->assignedRoles()->sync([$customRole->id]);

        $this->actingAs($customViewer)
            ->get("/admin/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Suppliers/Show')
                ->where('canViewCost', false)
                ->where('canManage', false)
                ->where('linkedProducts.0.unit_cost', null)
            );
    }
}
