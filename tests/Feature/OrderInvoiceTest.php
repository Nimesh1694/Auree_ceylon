<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderInvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $owner;
    private User $staffWithPermission;
    private User $staffWithoutPermission;
    private Product $product;
    private Order $order;
    private OrderInvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');

        $this->invoiceService = app(OrderInvoiceService::class);

        // Customer 1
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Kavindi Perera',
            'email' => 'kavindi@example.com',
            'phone' => '0771234567',
        ]);

        // Customer 2 (for IDOR tests)
        $this->otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Dilshan Silva',
            'email' => 'dilshan@example.com',
            'phone' => '0719876543',
        ]);

        // Owner (Universal permission)
        $this->owner = User::where('role', 'owner')->first()
            ?? User::factory()->create([
                'role' => UserRole::OWNER,
                'status' => UserStatus::ACTIVE,
                'two_factor_confirmed_at' => now(),
            ]);

        // Staff with orders.view (Accountant role has orders.view from seeder)
        $this->staffWithPermission = User::where('role', 'accountant')->first()
            ?? User::factory()->create([
                'role' => UserRole::ACCOUNTANT,
                'status' => UserStatus::ACTIVE,
                'two_factor_confirmed_at' => now(),
            ]);

        // Staff without orders.view (e.g. custom role or user without orders.view)
        $noViewRole = Role::create([
            'name' => 'guest_staff',
            'display_name' => 'Guest Staff',
            'description' => 'Staff without order view rights',
            'is_system' => false,
        ]);
        $this->staffWithoutPermission = User::factory()->create([
            'role' => 'guest_staff',
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);
        $this->staffWithoutPermission->syncRole($noViewRole);

        // Sample Product
        $this->product = Product::create([
            'name_en' => 'Velvet Matte Lip Cream',
            'name_si' => 'මැට් ලිප් ක්‍රීම්',
            'slug' => 'velvet-matte-lip-cream',
            'sku' => 'AL-LIP-001',
            'price' => 3200.00,
            'cost_price' => 1200.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        // Sample Order
        $this->order = $this->createOrder($this->customer, [
            'status' => 'placed',
            'subtotal' => 3200.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3550.00,
        ]);
    }

    private function createOrder(User $customer, array $overrides = []): Order
    {
        static $seq = 100;
        $seq++;

        $order = Order::create(array_merge([
            'order_number' => "ORD-20261111-{$seq}",
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone ?? '0771234567',
            'customer_email' => $customer->email,
            'shipping_address' => 'No 77, Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'postal_code' => '00300',
            'subtotal' => 3200.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3550.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => "TRK_{$seq}_" . uniqid(),
            'notes' => 'Customer private delivery instruction',
            'placed_at' => now()->subDay(),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => $this->product->sku,
            'unit_price' => 3200.00,
            'quantity' => 1,
            'subtotal' => 3200.00,
        ]);

        return $order;
    }

    // ==========================================
    // 1. AUTHENTICATION & IDOR SECURITY TESTS
    // ==========================================

    public function test_guest_cannot_download_customer_invoice_and_is_redirected_to_login(): void
    {
        $response = $this->get(route('customer.orders.invoice', $this->order->order_number));

        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_download_own_order_invoice(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('customer.orders.invoice', $this->order->order_number));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            "filename=Aura-Luxe-Invoice-{$this->order->order_number}.pdf",
            (string) $response->headers->get('Content-Disposition')
        );

        $binary = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $binary);
    }

    public function test_customer_cannot_download_another_customers_order_invoice_idor_prevention(): void
    {
        $response = $this->actingAs($this->otherCustomer)
            ->get(route('customer.orders.invoice', $this->order->order_number));

        // Returns 404 to prevent IDOR and enumeration
        $response->assertStatus(404);
    }

    // ==========================================
    // 2. ADMIN & PERMISSION TESTS
    // ==========================================

    public function test_admin_owner_wildcard_can_download_any_order_invoice(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('admin.orders.invoice', $this->order->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_admin_with_orders_view_permission_can_download_invoice(): void
    {
        $response = $this->actingAs($this->staffWithPermission)
            ->get(route('admin.orders.invoice', $this->order->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_staff_without_orders_view_permission_is_forbidden(): void
    {
        $response = $this->actingAs($this->staffWithoutPermission)
            ->get(route('admin.orders.invoice', $this->order->id));

        $response->assertStatus(403);
    }

    public function test_admin_invoice_download_creates_audit_log_entry(): void
    {
        $initialAuditCount = AuditLog::where('action', 'order.invoice_downloaded')->count();

        $this->actingAs($this->owner)
            ->get(route('admin.orders.invoice', $this->order->id));

        $this->assertEquals($initialAuditCount + 1, AuditLog::where('action', 'order.invoice_downloaded')->count());

        $log = AuditLog::where('action', 'order.invoice_downloaded')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->owner->id, $log->user_id);
        $this->assertEquals($this->order->id, $log->auditable_id);
        $this->assertEquals($this->order->order_number, $log->new_values['order_number'] ?? null);
    }

    // ==========================================
    // 3. DOCUMENT TITLE & RECEIPT ELIGIBILITY TESTS
    // ==========================================

    public function test_placed_order_generates_commercial_invoice_with_payment_due_badge(): void
    {
        $meta = $this->invoiceService->resolveDocumentMeta($this->order);

        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertEquals('invoice', $meta['document_type']);
        $this->assertEquals('PAYMENT DUE ON DELIVERY', $meta['status_badge']);
        $this->assertFalse($meta['is_paid']);
        $this->assertNull($meta['watermark']);
        $this->assertEquals("Aura-Luxe-Invoice-{$this->order->order_number}.pdf", $meta['filename']);
    }

    public function test_confirmed_packed_and_shipped_orders_generate_commercial_invoice(): void
    {
        foreach (['confirmed', 'packed', 'shipped'] as $status) {
            $this->order->update(['status' => $status]);
            $meta = $this->invoiceService->resolveDocumentMeta($this->order);

            $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
            $this->assertFalse($meta['is_paid']);
            $this->assertNull($meta['watermark']);
        }
    }

    public function test_delivered_order_without_reconciliation_generates_commercial_invoice_with_pending_badge(): void
    {
        $this->order->update(['status' => 'delivered']);
        $meta = $this->invoiceService->resolveDocumentMeta($this->order);

        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertEquals('DELIVERED (PENDING RECONCILIATION)', $meta['status_badge']);
        $this->assertFalse($meta['is_paid']);
        $this->assertNull($meta['watermark']);
    }

    public function test_cash_collected_order_with_matched_cod_collection_generates_official_payment_receipt(): void
    {
        $this->order->update(['status' => 'cash_collected']);

        CodCollection::create([
            'order_id' => $this->order->id,
            'expected_amount' => 3550.00,
            'collected_amount' => 3550.00,
            'courier_name' => 'Pronto Sri Lanka',
            'courier_reference' => 'PRONTO-REC-9988',
            'reconciled_by' => $this->staffWithPermission->id,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
            'collected_at' => now(),
        ]);

        $this->order->load('codCollection');

        $this->assertTrue($this->invoiceService->isPaymentVerified($this->order));

        $meta = $this->invoiceService->resolveDocumentMeta($this->order);
        $this->assertEquals('OFFICIAL PAYMENT RECEIPT', $meta['document_title']);
        $this->assertEquals('receipt', $meta['document_type']);
        $this->assertEquals('PAID IN FULL', $meta['status_badge']);
        $this->assertTrue($meta['is_paid']);
        $this->assertEquals('PAID', $meta['watermark']);
        $this->assertEquals("Aura-Luxe-Receipt-{$this->order->order_number}.pdf", $meta['filename']);
    }

    public function test_completed_order_with_matched_cod_collection_generates_official_payment_receipt(): void
    {
        $this->order->update(['status' => 'completed']);

        CodCollection::create([
            'order_id' => $this->order->id,
            'expected_amount' => 3550.00,
            'collected_amount' => 3550.00,
            'courier_name' => 'Pronto Sri Lanka',
            'courier_reference' => 'PRONTO-REC-9989',
            'reconciled_by' => $this->staffWithPermission->id,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
            'collected_at' => now(),
        ]);

        $this->order->load('codCollection');

        $this->assertTrue($this->invoiceService->isPaymentVerified($this->order));

        $meta = $this->invoiceService->resolveDocumentMeta($this->order);
        $this->assertEquals('OFFICIAL PAYMENT RECEIPT', $meta['document_title']);
        $this->assertTrue($meta['is_paid']);
        $this->assertEquals('PAID', $meta['watermark']);
    }

    public function test_completed_order_without_cod_collection_does_not_generate_paid_receipt(): void
    {
        // Completed status without verified COD collection must remain Commercial Invoice
        $this->order->update(['status' => 'completed']);
        $this->order->load('codCollection');

        $this->assertFalse($this->invoiceService->isPaymentVerified($this->order));

        $meta = $this->invoiceService->resolveDocumentMeta($this->order);
        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertFalse($meta['is_paid']);
    }

    public function test_completed_order_with_underpaid_cod_collection_does_not_generate_paid_receipt(): void
    {
        $this->order->update(['status' => 'completed']);

        CodCollection::create([
            'order_id' => $this->order->id,
            'expected_amount' => 3550.00,
            'collected_amount' => 2000.00, // Underpaid by 1550
            'courier_name' => 'Pronto Sri Lanka',
            'courier_reference' => 'PRONTO-UNDERPAID',
            'reconciled_by' => $this->staffWithPermission->id,
            'reconciliation_status' => CodCollection::STATUS_MISMATCHED_UNDERPAID,
            'collected_at' => now(),
        ]);

        $this->order->load('codCollection');

        $this->assertFalse($this->invoiceService->isPaymentVerified($this->order));

        $meta = $this->invoiceService->resolveDocumentMeta($this->order);
        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertFalse($meta['is_paid']);
    }

    // ==========================================
    // 4. TERMINATED ORDERS & VOID WATERMARKS
    // ==========================================

    public function test_cancelled_order_displays_cancelled_void_watermark_and_badge(): void
    {
        $this->order->update(['status' => 'cancelled']);
        $meta = $this->invoiceService->resolveDocumentMeta($this->order);

        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertEquals('CANCELLED', $meta['status_badge']);
        $this->assertEquals('CANCELLED / VOID', $meta['watermark']);
        $this->assertFalse($meta['is_paid']);
    }

    public function test_returned_order_displays_returned_void_watermark_and_badge(): void
    {
        $this->order->update(['status' => 'returned']);
        $meta = $this->invoiceService->resolveDocumentMeta($this->order);

        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertEquals('RETURNED', $meta['status_badge']);
        $this->assertEquals('RETURNED / VOID', $meta['watermark']);
        $this->assertFalse($meta['is_paid']);
    }

    public function test_refused_order_displays_refused_void_watermark_and_badge(): void
    {
        $this->order->update(['status' => 'refused']);
        $meta = $this->invoiceService->resolveDocumentMeta($this->order);

        $this->assertEquals('COMMERCIAL INVOICE', $meta['document_title']);
        $this->assertEquals('REFUSED', $meta['status_badge']);
        $this->assertEquals('REFUSED / VOID', $meta['watermark']);
        $this->assertFalse($meta['is_paid']);
    }

    // ==========================================
    // 5. PRIVACY & DATA INTEGRITY TESTS
    // ==========================================

    public function test_invoice_uses_immutable_order_items_snapshots(): void
    {
        // Modify product price in catalog
        $this->product->update(['price' => 5000.00, 'name_en' => 'Renamed Future Product']);

        $data = $this->invoiceService->buildInvoiceData($this->order);

        $this->assertEquals('Velvet Matte Lip Cream', $data['items'][0]['name']);
        $this->assertEquals(3200.00, $data['items'][0]['unit_price']);
        $this->assertEquals('LKR 3,200.00', $data['items'][0]['unit_price_formatted']);
    }

    public function test_invoice_data_strictly_excludes_cost_price_and_actual_courier_fee(): void
    {
        Delivery::create([
            'order_id' => $this->order->id,
            'courier_name' => 'Pronto Sri Lanka',
            'actual_courier_fee' => 450.00,
            'tracking_number' => 'PRONTO-12345',
            'status' => 'dispatched',
        ]);

        $data = $this->invoiceService->buildInvoiceData($this->order);

        // Verify cost price is nowhere in the data payload
        $json = json_encode($data);
        $this->assertStringNotContainsString('"cost_price"', $json);
        $this->assertStringNotContainsString('1200.00', $json);

        // Verify actual courier fee is nowhere in the data payload
        $this->assertStringNotContainsString('"actual_courier_fee"', $json);
        $this->assertStringNotContainsString('450.00', $json);
    }

    public function test_invoice_data_strictly_excludes_agent_commission_and_internal_notes(): void
    {
        $this->order->update([
            'agent_id' => $this->otherCustomer->id,
            'notes' => 'INTERNAL SECRET NOTE: Fragile VIP customer',
        ]);

        $data = $this->invoiceService->buildInvoiceData($this->order);
        $json = json_encode($data);

        $this->assertStringNotContainsString('INTERNAL SECRET NOTE', $json);
        $this->assertStringNotContainsString('agent_id', $json);
        $this->assertStringNotContainsString('commission', $json);
    }

    public function test_invoice_generation_does_not_mutate_database_records(): void
    {
        $orderCountBefore = Order::count();
        $itemCountBefore = OrderItem::count();
        $auditCountBefore = AuditLog::count();
        $orderBefore = $this->order->fresh()->toArray();

        // Customer downloads invoice
        $this->actingAs($this->customer)
            ->get(route('customer.orders.invoice', $this->order->order_number));

        $this->assertEquals($orderCountBefore, Order::count());
        $this->assertEquals($itemCountBefore, OrderItem::count());
        $this->assertEquals($orderBefore, $this->order->fresh()->toArray());
    }
}
