<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CommissionLedgerEntry;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderStateMachineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $accountant;
    private User $superAdmin;
    private User $operationsManager;
    private User $marketingManager;
    private User $agentManager;
    private User $highLevelAgent;
    private User $lowLevelAgent;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
        Setting::set('commission_return_period_days', 7, 'commission', 'integer');

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'protected_authority' => 'business',
        ]);
        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
        ]);
        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'protected_authority' => 'technical',
        ]);
        $this->operationsManager = User::factory()->create([
            'role' => UserRole::OPERATIONS_MANAGER,
        ]);
        $this->marketingManager = User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER,
        ]);
        $this->agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
        ]);
        $this->highLevelAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
        ]);
        $this->lowLevelAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
        ]);
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);
    }

    /**
     * Helper to create a fully completed and delivered order with audited cost snapshot.
     */
    private function createAuditedDeliveredOrder(
        string $orderNumber,
        float $productPrice = 5000.00,
        float $costPrice = 2000.00,
        float $deliveryCharge = 350.00,
        float $actualCourierFee = 320.00,
        float $commissionAmount = 500.00,
        int $daysAgoDelivered = 10
    ): Order {
        $product = Product::create([
            'name_en' => 'Test Product ' . $orderNumber,
            'name_si' => 'ටෙස්ට්',
            'slug' => 'test-product-' . strtolower($orderNumber),
            'sku' => 'SKU-' . $orderNumber,
            'price' => $productPrice,
            'cost_price' => $costPrice,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'customer_name' => 'Dilshan Test',
            'customer_phone' => '0771234567',
            'shipping_address' => 'No 1, Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => $productPrice,
            'delivery_charge' => $deliveryCharge,
            'total_amount' => $productPrice + $deliveryCharge,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_DELIVERED,
            'tracking_token' => 'TRK-' . $orderNumber,
            'placed_at' => Carbon::now()->subDays($daysAgoDelivered + 2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'quantity' => 1,
            'unit_price' => $productPrice,
            'cost_price' => $costPrice, // Audited cost snapshot
            'subtotal' => $productPrice,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Koombiyo',
            'tracking_number' => 'TRK-NUM-' . $orderNumber,
            'status' => 'delivered',
            'actual_courier_fee' => $actualCourierFee,
            'delivered_at' => Carbon::now()->subDays($daysAgoDelivered),
        ]);

        $order->codCollection()->create([
            'collected_amount' => $order->total_amount,
            'expected_amount' => $order->total_amount,
            'reconciliation_status' => \App\Models\CodCollection::STATUS_MATCHED,
            'collected_at' => Carbon::now()->subDays($daysAgoDelivered),
            'reconciled_at' => Carbon::now()->subDays($daysAgoDelivered),
        ]);

        CommissionLedgerEntry::create([
            'entry_number' => 'COM-' . $orderNumber,
            'order_id' => $order->id,
            'agent_id' => $this->highLevelAgent->id,
            'selling_agent_id' => $this->highLevelAgent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => $productPrice,
            'rate_percentage' => ($commissionAmount / $productPrice) * 100,
            'calculated_amount' => $commissionAmount,
            'status' => CommissionLedgerEntry::STATUS_APPROVED,
        ]);

        return $order;
    }

    // =========================================================================
    // 1. RBAC & PERMISSION PROTECTION TESTS
    // =========================================================================

    public function test_business_owner_can_access_financial_contribution_report(): void
    {
        $this->createAuditedDeliveredOrder('ORD-OWNER-01');

        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution'));

        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'certification',
            'revenue',
            'cogs',
            'logistics',
            'commissions',
            'cod_reconciliation',
            'contribution',
        ]);
        $this->assertEquals('Gross Operating Contribution (Before OPEX)', $response->json('contribution.metric_name'));
    }

    public function test_accountant_can_access_financial_contribution_report(): void
    {
        $this->createAuditedDeliveredOrder('ORD-ACCT-01');

        $response = $this->actingAs($this->accountant)
            ->getJson(route('admin.finance.contribution'));

        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'certification',
            'revenue',
            'cogs',
            'logistics',
            'commissions',
            'cod_reconciliation',
            'contribution',
        ]);
    }

    public function test_owner_can_render_inertia_contribution_dashboard_page(): void
    {
        $this->createAuditedDeliveredOrder('ORD-INERTIA-01');

        $response = $this->actingAs($this->owner)
            ->get(route('admin.finance.contribution'));

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Finance/ContributionReport')
            ->has('report')
            ->has('filters')
            ->where('report.contribution.metric_name', 'Gross Operating Contribution (Before OPEX)')
        );
    }

    public function test_accountant_can_render_inertia_contribution_dashboard_page(): void
    {
        $this->createAuditedDeliveredOrder('ORD-INERTIA-02');

        $response = $this->actingAs($this->accountant)
            ->get(route('admin.finance.contribution'));

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Finance/ContributionReport')
            ->has('report')
            ->has('filters')
        );
    }

    public function test_unauthorized_roles_receive_403_on_inertia_page_request(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.finance.contribution'))
            ->assertForbidden();

        $this->actingAs($this->operationsManager)
            ->get(route('admin.finance.contribution'))
            ->assertForbidden();

        $this->actingAs($this->highLevelAgent)
            ->get(route('admin.finance.contribution'))
            ->assertForbidden();
    }

    public function test_owner_and_accountant_can_access_summary_endpoint(): void
    {
        $this->createAuditedDeliveredOrder('ORD-SUMM-01');

        $ownerResponse = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution.summary'));
        $ownerResponse->assertOk();
        $ownerResponse->assertJsonStructure([
            'period',
            'certification',
            'revenue',
            'cogs',
            'logistics',
            'commissions',
            'cod_reconciliation',
            'contribution',
        ]);

        $acctResponse = $this->actingAs($this->accountant)
            ->getJson(route('admin.finance.contribution.summary'));
        $acctResponse->assertOk();
        $this->assertEquals($ownerResponse->json('contribution.amount'), $acctResponse->json('contribution.amount'));
    }

    public function test_super_admin_is_strictly_forbidden_from_viewing_financial_reports(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.finance.contribution'));

        $response->assertForbidden();

        $summaryResponse = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.finance.contribution.summary'));

        $summaryResponse->assertForbidden();
    }

    public function test_operations_manager_is_strictly_forbidden_from_viewing_financial_reports(): void
    {
        $response = $this->actingAs($this->operationsManager)
            ->getJson(route('admin.finance.contribution'));

        $response->assertForbidden();

        $summaryResponse = $this->actingAs($this->operationsManager)
            ->getJson(route('admin.finance.contribution.summary'));

        $summaryResponse->assertForbidden();
    }

    public function test_marketing_manager_is_strictly_forbidden_from_viewing_financial_reports(): void
    {
        $response = $this->actingAs($this->marketingManager)
            ->getJson(route('admin.finance.contribution'));

        $response->assertForbidden();
    }

    public function test_agent_manager_is_strictly_forbidden_from_viewing_financial_reports(): void
    {
        $response = $this->actingAs($this->agentManager)
            ->getJson(route('admin.finance.contribution'));

        $response->assertForbidden();
    }

    public function test_agents_and_customers_cannot_access_financial_reports(): void
    {
        // High-level agent
        $this->actingAs($this->highLevelAgent)
            ->getJson(route('admin.finance.contribution'))
            ->assertForbidden();

        // Low-level agent
        $this->actingAs($this->lowLevelAgent)
            ->getJson(route('admin.finance.contribution'))
            ->assertForbidden();

        // Customer
        $this->actingAs($this->customer)
            ->getJson(route('admin.finance.contribution'))
            ->assertForbidden();
    }

    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.finance.contribution'))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2. FINANCIAL METRICS & SERVICE DELEGATION INTEGRITY
    // =========================================================================

    public function test_financial_report_endpoint_correctly_reflects_service_calculations(): void
    {
        // Product 5,000, Cost 2,000, Delivery charge 350, Actual courier fee 320, Commission 500, Delivered 10 days ago (Matured)
        // Expected:
        // Net Product Revenue = 5,000
        // Audited COGS = 2,000, Estimated Legacy COGS = 0
        // Gross Product Profit = 3,000
        // Collected Delivery Revenue = 350, Courier Expense = 320 -> Logistics Contribution = +30
        // Net Commission Expense = 500
        // Confirmed COD Loss = 0
        // Gross Operating Contribution = 3,000 + 30 - 500 = 2,530.00
        $this->createAuditedDeliveredOrder(
            'ORD-CALC-01',
            productPrice: 5000.00,
            costPrice: 2000.00,
            deliveryCharge: 350.00,
            actualCourierFee: 320.00,
            commissionAmount: 500.00,
            daysAgoDelivered: 10
        );

        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution'));

        $response->assertOk();

        // Revenue
        $this->assertEquals(5000.00, $response->json('revenue.gross_product_sales'));
        $this->assertEquals(5000.00, $response->json('revenue.net_recognized_product_revenue'));

        // COGS Segregation
        $this->assertEquals(2000.00, $response->json('cogs.audited_cogs'));
        $this->assertEquals(0.00, $response->json('cogs.estimated_legacy_cogs'));
        $this->assertEquals(2000.00, $response->json('cogs.total_cogs'));
        $this->assertEquals(3000.00, $response->json('cogs.gross_product_profit'));

        // Logistics
        $this->assertEquals(350.00, $response->json('logistics.collected_delivery_revenue'));
        $this->assertEquals(320.00, $response->json('logistics.actual_courier_expense'));
        $this->assertEquals(30.00, $response->json('logistics.logistics_contribution'));

        // Commissions
        $this->assertEquals(500.00, $response->json('commissions.matured_commission_expense'));
        $this->assertEquals(500.00, $response->json('commissions.net_commission_expense'));

        // Gross Operating Contribution (Before OPEX)
        $this->assertEquals(2530.00, $response->json('contribution.amount'));
        $this->assertStringContainsString('OPEX', $response->json('contribution.opex_excluded_disclaimer'));
        $this->assertEquals('certified', $response->json('certification.status'));
    }

    public function test_summary_endpoint_provides_concise_operational_payload(): void
    {
        $this->createAuditedDeliveredOrder('ORD-SUMM-PAYLOAD', 4000.00, 1500.00, 350.00, 300.00, 400.00, 12);

        $response = $this->actingAs($this->accountant)
            ->getJson(route('admin.finance.contribution.summary'));

        $response->assertOk();
        $this->assertEquals(4000.00, $response->json('revenue.net_recognized_product_revenue'));
        $this->assertEquals(1500.00, $response->json('cogs.audited_cogs'));
        $this->assertEquals(2500.00, $response->json('cogs.gross_product_profit'));
        $this->assertEquals(50.00, $response->json('logistics.logistics_contribution'));
        $this->assertEquals(400.00, $response->json('commissions.net_commission_expense'));
        // 2500 + 50 - 400 = 2150.00
        $this->assertEquals(2150.00, $response->json('contribution.amount'));
    }

    // =========================================================================
    // 3. DATE FILTERS, PARAMETER VALIDATION & DEFENSIVE CONTROLS
    // =========================================================================

    public function test_date_range_filters_orders_appropriately(): void
    {
        // Order 1: Delivered 20 days ago
        $this->createAuditedDeliveredOrder('ORD-DATE-OLD', 3000.00, 1000.00, 350.00, 300.00, 300.00, 20);

        // Order 2: Delivered 5 days ago (within last 7 days)
        $this->createAuditedDeliveredOrder('ORD-DATE-NEW', 6000.00, 2000.00, 350.00, 300.00, 600.00, 5);

        // Query only the last 7 days
        $startDate = Carbon::now()->subDays(7)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution', [
                'date_from' => $startDate,
                'date_to' => $endDate,
            ]));

        $response->assertOk();
        // Only Order 2 should be in this range
        $this->assertEquals(6000.00, $response->json('revenue.gross_product_sales'));
        $this->assertEquals($startDate, $response->json('period.start_date'));
        $this->assertEquals($endDate, $response->json('period.end_date'));
    }

    public function test_start_date_and_end_date_aliases_work_identically(): void
    {
        $startDate = Carbon::now()->subDays(14)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->accountant)
            ->getJson(route('admin.finance.contribution', [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]));

        $response->assertOk();
        $this->assertEquals($startDate, $response->json('period.start_date'));
        $this->assertEquals($endDate, $response->json('period.end_date'));
    }

    public function test_invalid_date_format_triggers_422_validation_error(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution', [
                'date_from' => 'invalid-date-string',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
    }

    public function test_end_date_before_start_date_triggers_422_validation_error(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution', [
                'date_from' => '2026-11-20',
                'date_to' => '2026-11-10',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_date']);
    }

    public function test_invalid_perspective_triggers_422_validation_error(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution', [
                'perspective' => 'unknown_perspective',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['perspective']);
    }

    public function test_date_range_exceeding_five_years_is_safely_rejected(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution', [
                'date_from' => '2015-01-01',
                'date_to' => '2025-01-01', // 10 years span
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_date']);
    }

    // =========================================================================
    // 4. AUDIT TRAIL LOGGING & IMMUTABILITY
    // =========================================================================

    public function test_accessing_financial_report_creates_audit_log_record(): void
    {
        AuditLog::query()->delete();

        $this->actingAs($this->owner)
            ->getJson(route('admin.finance.contribution', [
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-30',
            ]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'financial_report_accessed',
            'user_id' => $this->owner->id,
        ]);

        $log = AuditLog::where('action', 'financial_report_accessed')->first();
        $this->assertNotNull($log);
        $this->assertEquals('index', $log->new_values['endpoint']);
        $this->assertEquals('2026-11-01', $log->new_values['period_start']);
        $this->assertEquals('2026-11-30', $log->new_values['period_end']);
        $this->assertEquals('owner', $log->new_values['accessed_by_role']);
    }

    public function test_financial_report_is_strictly_read_only_and_does_not_mutate_state(): void
    {
        $order = $this->createAuditedDeliveredOrder('ORD-READONLY-01');

        $initialOrderUpdatedAt = $order->fresh()->updated_at;
        $initialDeliveryUpdatedAt = $order->fresh()->delivery->updated_at;

        $this->actingAs($this->accountant)
            ->getJson(route('admin.finance.contribution'));

        $this->assertEquals($initialOrderUpdatedAt, $order->fresh()->updated_at);
        $this->assertEquals($initialDeliveryUpdatedAt, $order->fresh()->delivery->updated_at);
    }
}
