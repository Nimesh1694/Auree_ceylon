<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FinancialReportService;
use App\Services\OrderStateMachineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialReportService $reportService;
    protected User $owner;
    protected User $agent;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
        Setting::set('commission_return_period_days', 7, 'commissions', 'int');

        $this->reportService = app(FinancialReportService::class);

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'referral_code' => 'HLA-TEST-001',
        ]);

        $this->productA = Product::create([
            'name_en' => 'Herbal Face Wash',
            'name_si' => 'ෆේස් වොෂ්',
            'slug' => 'herbal-face-wash',
            'sku' => 'AL-HFW-01',
            'price' => 3000.00,
            'cost_price' => 1200.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'name_en' => 'Night Serum',
            'name_si' => 'සීරම්',
            'slug' => 'night-serum',
            'sku' => 'AL-NS-02',
            'price' => 5000.00,
            'cost_price' => 2000.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
    }

    public function test_empty_reporting_period_returns_safe_zero_structure(): void
    {
        $report = $this->reportService->generateReport('2026-01-01', '2026-01-31');

        $this->assertEquals(0.00, $report['revenue']['gross_product_sales']);
        $this->assertEquals(0.00, $report['revenue']['net_recognized_product_revenue']);
        $this->assertEquals(0.00, $report['cogs']['total_cogs']);
        $this->assertEquals(0.00, $report['cogs']['gross_product_profit']);
        $this->assertEquals(0.00, $report['logistics']['collected_delivery_revenue']);
        $this->assertEquals(0.00, $report['logistics']['actual_courier_expense']);
        $this->assertEquals(0.00, $report['commissions']['net_commission_expense']);
        $this->assertEquals(0.00, $report['contribution']['amount']);
        $this->assertEquals(FinancialReportService::STATUS_CERTIFIED, $report['certification']['status']);
    }

    public function test_mathematical_waterfall_formula_precision(): void
    {
        // Place and fulfill Order 1: 2 x Product A = 6,000. Delivery = 350. Total = 6,350.
        $deliveredDate = Carbon::now()->subDays(10); // Fully matured (> 7 days)

        $order = Order::create([
            'order_number' => 'ORD-FIN-001',
            'customer_name' => 'Nimal Silva',
            'customer_phone' => '0771122334',
            'shipping_address' => '100 Galle Rd',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 6000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 6350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-FIN-001',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'product_sku' => 'AL-HFW-01',
            'quantity' => 2,
            'unit_price' => 3000.00,
            'subtotal' => 6000.00,
            'cost_price' => 1200.00, // Audited COGS = 2,400.00
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Domex',
            'tracking_number' => 'DMX-111',
            'status' => 'delivered',
            'actual_courier_fee' => 320.00,
            'delivered_at' => $deliveredDate,
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 6350.00,
            'collected_amount' => 6350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
            'collected_at' => $deliveredDate,
        ]);

        CommissionLedgerEntry::create([
            'entry_number' => 'COM-FIN-001',
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => 6000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => 1500.00,
            'status' => CommissionLedgerEntry::STATUS_APPROVED,
            'approved_at' => $deliveredDate,
        ]);

        $report = $this->reportService->generateReport();

        // 1. Revenue
        $this->assertEquals(6000.00, $report['revenue']['gross_product_sales']);
        $this->assertEquals(0.00, $report['revenue']['returns_and_refunds']);
        $this->assertEquals(6000.00, $report['revenue']['net_recognized_product_revenue']);

        // 2. COGS (2 x 1,200 = 2,400)
        $this->assertEquals(2400.00, $report['cogs']['audited_cogs']);
        $this->assertEquals(0.00, $report['cogs']['estimated_legacy_cogs']);
        $this->assertEquals(2400.00, $report['cogs']['total_cogs']);
        $this->assertEquals(3600.00, $report['cogs']['gross_product_profit']); // 6,000 - 2,400
        $this->assertEquals(60.00, $report['cogs']['gross_product_margin_percent']);

        // 3. Logistics (Revenue 350 - Courier Expense 320 = +30 surplus)
        $this->assertEquals(350.00, $report['logistics']['collected_delivery_revenue']);
        $this->assertEquals(320.00, $report['logistics']['actual_courier_expense']);
        $this->assertEquals(30.00, $report['logistics']['logistics_contribution']);

        // 4. Commissions
        $this->assertEquals(1500.00, $report['commissions']['matured_commission_expense']);
        $this->assertEquals(1500.00, $report['commissions']['net_commission_expense']);

        // 5. Final Metric: Gross Operating Contribution (Before OPEX)
        // 3,600 (Gross Profit) + 30 (Logistics) - 1,500 (Commissions) = 2,130.00
        $this->assertEquals(2130.00, $report['contribution']['amount']);
        $this->assertEquals(35.50, $report['contribution']['margin_percent']);
        $this->assertEquals('Gross Operating Contribution (Before OPEX)', $report['contribution']['metric_name']);
        $this->assertEquals(FinancialReportService::STATUS_CERTIFIED, $report['certification']['status']);
    }

    public function test_missing_courier_fees_are_never_treated_as_zero_and_flag_provisional(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-NO-COURIER-FEE',
            'customer_name' => 'Missing Fee Customer',
            'customer_phone' => '0772233445',
            'shipping_address' => '50 Kandy Rd',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_DELIVERED,
            'tracking_token' => 'TOKEN-NO-FEE',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        // Missing courier invoice (actual_courier_fee = NULL)
        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Pronto',
            'tracking_number' => 'PRN-999',
            'status' => 'delivered',
            'actual_courier_fee' => null,
            'delivered_at' => $deliveredDate,
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 3350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
            'collected_at' => $deliveredDate,
        ]);

        $report = $this->reportService->generateReport();

        $this->assertEquals(1, $report['logistics']['missing_courier_fees_count']);
        $this->assertEquals(0.00, $report['logistics']['actual_courier_expense']);
        // Status must be flagged as PROVISIONAL because of un-invoiced logistics expense
        $this->assertEquals(FinancialReportService::STATUS_PROVISIONAL, $report['certification']['status']);
    }

    public function test_legacy_order_items_without_cost_snapshots_segregated_as_estimated_and_flag_qualified(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-LEGACY-ITEM',
            'customer_name' => 'Legacy Customer',
            'customer_phone' => '0773344556',
            'shipping_address' => '12 Temple St',
            'district' => 'Matara',
            'city' => 'Matara',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-LEGACY-1',
            'placed_at' => $deliveredDate,
        ]);

        // Legacy order item created prior to Step 1 migration (cost_price = null)
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => null, // Legacy null snapshot
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Domex',
            'tracking_number' => 'DMX-222',
            'status' => 'delivered',
            'actual_courier_fee' => 300.00,
            'delivered_at' => $deliveredDate,
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 3350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
            'collected_at' => $deliveredDate,
        ]);

        $report = $this->reportService->generateReport();

        $this->assertEquals(0.00, $report['cogs']['audited_cogs']);
        $this->assertEquals(1200.00, $report['cogs']['estimated_legacy_cogs']);
        $this->assertEquals(1200.00, $report['cogs']['total_cogs']);
        $this->assertEquals(1, $report['certification']['estimated_legacy_items_count']);
        // Status must be QUALIFIED (not certified, and not provisional)
        $this->assertEquals(FinancialReportService::STATUS_QUALIFIED, $report['certification']['status']);
    }

    public function test_7_day_maturity_window_order_by_order_evaluation(): void
    {
        // Order A: Delivered 10 days ago (fully matured)
        $orderOld = Order::create([
            'order_number' => 'ORD-OLD-MATURED',
            'customer_name' => 'Old Customer',
            'customer_phone' => '0774455667',
            'shipping_address' => '20 Beach Rd',
            'district' => 'Galle',
            'city' => 'Galle',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
            'tracking_token' => 'TOKEN-OLD',
            'placed_at' => Carbon::now()->subDays(12),
        ]);

        OrderItem::create([
            'order_id' => $orderOld->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $orderOld->id,
            'courier_name' => 'Domex',
            'actual_courier_fee' => 300.00,
            'delivered_at' => Carbon::now()->subDays(10), // > 7 days ago
        ]);

        CodCollection::create([
            'order_id' => $orderOld->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 3350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        CommissionLedgerEntry::create([
            'entry_number' => 'COM-OLD-01',
            'order_id' => $orderOld->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => 3000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => 750.00,
            'status' => CommissionLedgerEntry::STATUS_APPROVED,
        ]);

        // Order B: Delivered 2 days ago (still in active 7-day grace window!)
        $orderNew = Order::create([
            'order_number' => 'ORD-NEW-GRACE',
            'customer_name' => 'New Customer',
            'customer_phone' => '0775566778',
            'shipping_address' => '30 Hill St',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
            'tracking_token' => 'TOKEN-NEW',
            'placed_at' => Carbon::now()->subDays(3),
        ]);

        OrderItem::create([
            'order_id' => $orderNew->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $orderNew->id,
            'courier_name' => 'Domex',
            'actual_courier_fee' => 300.00,
            'delivered_at' => Carbon::now()->subDays(2), // Only 2 days ago!
        ]);

        CodCollection::create([
            'order_id' => $orderNew->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 3350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        CommissionLedgerEntry::create([
            'entry_number' => 'COM-NEW-02',
            'order_id' => $orderNew->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => 3000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => 750.00,
            'status' => CommissionLedgerEntry::STATUS_PENDING, // Pending in grace period
        ]);

        $report = $this->reportService->generateReport();

        // Matured commission expense only includes Order A (750.00)
        $this->assertEquals(750.00, $report['commissions']['matured_commission_expense']);
        $this->assertEquals(750.00, $report['commissions']['net_commission_expense']);

        // Order B's commission is kept in pending grace window accrual (750.00)
        $this->assertEquals(750.00, $report['commissions']['pending_grace_window_accrual']);
        $this->assertEquals(1, $report['period']['orders_in_grace_window']);
    }

    public function test_mismatched_underpaid_cod_is_held_as_dispute_and_not_deducted_as_loss_unless_confirmed(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-COD-SHORTFALL',
            'customer_name' => 'Dispute Customer',
            'customer_phone' => '0776677889',
            'shipping_address' => '40 Main St',
            'district' => 'Jaffna',
            'city' => 'Jaffna',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
            'tracking_token' => 'TOKEN-DISPUTE',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Domex',
            'actual_courier_fee' => 300.00,
            'delivered_at' => $deliveredDate,
        ]);

        // Courier remitted only 3,000.00 instead of 3,350.00 (Shortfall = 350.00)
        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 3000.00,
            'reconciliation_status' => CodCollection::STATUS_MISMATCHED_UNDERPAID,
        ]);

        $report = $this->reportService->generateReport();

        // 1. Underpayment is held as an UNRESOLVED DISPUTE
        $this->assertEquals(350.00, $report['cod_reconciliation']['unresolved_disputes_amount']);

        // 2. Confirmed Loss is 0.00 because it hasn't been confirmed via authorized audit log!
        $this->assertEquals(0.00, $report['cod_reconciliation']['confirmed_unrecoverable_loss']);

        // 3. Status is flagged as PROVISIONAL because of unresolved COD dispute
        $this->assertEquals(FinancialReportService::STATUS_PROVISIONAL, $report['certification']['status']);
    }

    public function test_confirmed_unrecoverable_cod_loss_is_deducted_from_gross_operating_contribution(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-CONFIRMED-LOSS',
            'customer_name' => 'Loss Customer',
            'customer_phone' => '0777788990',
            'shipping_address' => '60 Fort Rd',
            'district' => 'Galle',
            'city' => 'Galle',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-LOSS-1',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Domex',
            'actual_courier_fee' => 300.00,
            'delivered_at' => $deliveredDate,
        ]);

        // Courier short-remitted 500.00
        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 2850.00,
            'reconciliation_status' => CodCollection::STATUS_MISMATCHED_UNDERPAID,
        ]);

        // Formal authorization log confirming the loss as unrecoverable
        AuditLog::create([
            'action' => 'cod_loss_confirmed',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'user_id' => $this->owner->id,
            'new_values' => [
                'loss_amount' => 500.00,
                'reason' => 'Courier partner declared bankruptcy / confirmed theft.',
            ],
        ]);

        $report = $this->reportService->generateReport();

        $this->assertEquals(500.00, $report['cod_reconciliation']['confirmed_unrecoverable_loss']);
        $this->assertEquals(0.00, $report['cod_reconciliation']['unresolved_disputes_amount']);

        // Gross Product Profit = 3,000 - 1,200 = 1,800.
        // Logistics = 350 - 300 = +50.
        // Operating Contribution = 1,800 + 50 - 500 (confirmed loss) = 1,350.00
        $this->assertEquals(1350.00, $report['contribution']['amount']);
    }

    public function test_payout_disbursements_and_wallet_credits_do_not_double_count_commission_expenses(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-DOUBLE-COUNT-CHECK',
            'customer_name' => 'Wallet Check',
            'customer_phone' => '0778899001',
            'shipping_address' => '80 Lake Rd',
            'district' => 'Kurunegala',
            'city' => 'Kurunegala',
            'subtotal' => 4000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4350.00,
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-DOUBLE-CHECK',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 4000.00,
            'subtotal' => 4000.00,
            'cost_price' => 1500.00,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'actual_courier_fee' => 350.00,
            'delivered_at' => $deliveredDate,
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 4350.00,
            'collected_amount' => 4350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        // 1. Commission Ledger Entry (Calculated 1,000.00)
        $comm = CommissionLedgerEntry::create([
            'entry_number' => 'COM-CHECK-01',
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => 4000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => 1000.00,
            'status' => CommissionLedgerEntry::STATUS_APPROVED,
        ]);

        // 2. Simulated Wallet Credit Transaction
        WalletTransaction::create([
            'transaction_number' => 'WT-CHECK-01',
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_CREDIT,
            'amount' => 1000.00,
            'balance_after' => 1000.00,
            'source_type' => CommissionLedgerEntry::class,
            'source_id' => $comm->id,
            'description' => 'Commission credit',
            'metadata' => ['order_id' => $order->id],
        ]);

        // 3. Simulated Bank Payout Disbursement
        PayoutRequest::create([
            'reference_number' => 'PO-CHECK-01',
            'agent_id' => $this->agent->id,
            'amount' => 1000.00,
            'status' => 'paid',
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent Name',
            'paid_at' => now(),
        ]);

        $report = $this->reportService->generateReport();

        // Commission expense MUST remain strictly 1,000.00 (from ledger)
        // It must NOT be 2,000.00 (commission + payout) or 3,000.00 (commission + wallet + payout)!
        $this->assertEquals(1000.00, $report['commissions']['net_commission_expense']);
        $this->assertEquals(1000.00, $report['commissions']['wallet_credited_amount']);
        $this->assertEquals(1000.00, $report['commissions']['payouts_disbursed_cash']);
    }

    public function test_customer_returned_order_refunds_product_sales_and_credits_reversed_commissions(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-RETURNED-1',
            'customer_name' => 'Return Customer',
            'customer_phone' => '0779900112',
            'shipping_address' => '90 River Rd',
            'district' => 'Ratnapura',
            'city' => 'Ratnapura',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_RETURNED, // Returned
            'tracking_token' => 'TOKEN-RETURN-1',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'actual_courier_fee' => 350.00,
            'status' => 'returned',
            'delivered_at' => $deliveredDate,
        ]);

        // Reversal ledger entry
        CommissionLedgerEntry::create([
            'entry_number' => 'COM-REV-01',
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_REVERSAL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => 3000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => -750.00, // Negative reversal
            'status' => CommissionLedgerEntry::STATUS_REVERSED,
        ]);

        $report = $this->reportService->generateReport();

        // 1. Returns and Refunds accurately recorded
        $this->assertEquals(3000.00, $report['revenue']['returns_and_refunds']);
        $this->assertEquals(1, $report['revenue']['returned_orders_count']);

        // 2. Net Product Revenue is 0.00 (Gross 0 - Refund 3,000 = 0.00)
        $this->assertEquals(0.00, $report['revenue']['net_recognized_product_revenue']);

        // 3. Reversed commission credit recorded
        $this->assertEquals(750.00, $report['commissions']['reversed_commission_credit']);
        $this->assertEquals(0.00, $report['commissions']['net_commission_expense']);
    }

    public function test_refused_order_revenue_is_not_double_deducted_and_courier_fee_is_recognized(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-REFUSED-1',
            'customer_name' => 'Refused Customer',
            'customer_phone' => '0779911223',
            'shipping_address' => '70 Hill Side',
            'district' => 'Nuwara Eliya',
            'city' => 'Nuwara Eliya',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'status' => OrderStateMachineService::STATUS_REFUSED, // Refused at doorstep
            'tracking_token' => 'TOKEN-REFUSED-1',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_name' => 'Night Serum',
            'quantity' => 1,
            'unit_price' => 5000.00,
            'subtotal' => 5000.00,
            'cost_price' => 2000.00,
        ]);

        // Courier attempted delivery and billed the merchant 400.00
        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Certis',
            'actual_courier_fee' => 400.00,
            'status' => 'returned',
            'delivered_at' => $deliveredDate,
        ]);

        $report = $this->reportService->generateReport();

        // 1. Zero cash was collected, so Gross Sales = 0.00 and Returns = 0.00 (not double deducted)
        $this->assertEquals(0.00, $report['revenue']['gross_product_sales']);
        $this->assertEquals(0.00, $report['revenue']['returns_and_refunds']);
        $this->assertEquals(0.00, $report['revenue']['net_recognized_product_revenue']);
        $this->assertEquals(1, $report['revenue']['refused_orders_count']);

        // 2. Uncollected delivery charge tracked
        $this->assertEquals(350.00, $report['logistics']['uncollected_delivery_charge']);

        // 3. Courier expense was incurred (400.00) creating a net logistics loss of -400.00
        $this->assertEquals(400.00, $report['logistics']['actual_courier_expense']);
        $this->assertEquals(-400.00, $report['logistics']['logistics_contribution']);
    }

    public function test_cod_recovery_event_tracks_recovered_amount_and_prevents_duplicate_loss(): void
    {
        $deliveredDate = Carbon::now()->subDays(10);

        $order = Order::create([
            'order_number' => 'ORD-COD-RECOVERY-1',
            'customer_name' => 'Recovered Customer',
            'customer_phone' => '0778811223',
            'shipping_address' => '15 Temple Rd',
            'district' => 'Gampaha',
            'city' => 'Negombo',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-RECOVER-1',
            'placed_at' => $deliveredDate,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Domex',
            'actual_courier_fee' => 300.00,
            'delivered_at' => $deliveredDate,
        ]);

        // COD remittance initially underpaid by 350.00, then settled
        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 3350.00,
            'collected_amount' => 3350.00, // Recovered in full
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        // Audit log of the recovery transaction
        AuditLog::create([
            'action' => 'cod_shortfall_recovered',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'user_id' => $this->owner->id,
            'new_values' => [
                'recovered_amount' => 350.00,
                'courier_settlement_batch' => 'BATCH-NOV-098',
            ],
        ]);

        $report = $this->reportService->generateReport();

        $this->assertEquals(350.00, $report['cod_reconciliation']['recovered_amount']);
        $this->assertEquals(0.00, $report['cod_reconciliation']['unresolved_disputes_amount']);
        $this->assertEquals(0.00, $report['cod_reconciliation']['confirmed_unrecoverable_loss']);
        $this->assertEquals(FinancialReportService::STATUS_CERTIFIED, $report['certification']['status']);
    }

    public function test_reporting_period_boundaries_strictly_filter_by_date(): void
    {
        // Order 1 in January
        $orderJan = Order::create([
            'order_number' => 'ORD-JAN-1',
            'customer_name' => 'Jan Customer',
            'customer_phone' => '0771112223',
            'shipping_address' => 'Jan St',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-JAN',
            'placed_at' => Carbon::parse('2026-01-15'),
        ]);

        OrderItem::create([
            'order_id' => $orderJan->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Herbal Face Wash',
            'quantity' => 1,
            'unit_price' => 3000.00,
            'subtotal' => 3000.00,
            'cost_price' => 1200.00,
        ]);

        Delivery::create([
            'order_id' => $orderJan->id,
            'actual_courier_fee' => 300.00,
            'delivered_at' => Carbon::parse('2026-01-18'),
        ]);

        // Order 2 in February
        $orderFeb = Order::create([
            'order_number' => 'ORD-FEB-1',
            'customer_name' => 'Feb Customer',
            'customer_phone' => '0772223334',
            'shipping_address' => 'Feb St',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'status' => OrderStateMachineService::STATUS_COMPLETED,
            'tracking_token' => 'TOKEN-FEB',
            'placed_at' => Carbon::parse('2026-02-10'),
        ]);

        OrderItem::create([
            'order_id' => $orderFeb->id,
            'product_id' => $this->productB->id,
            'product_name' => 'Night Serum',
            'quantity' => 1,
            'unit_price' => 5000.00,
            'subtotal' => 5000.00,
            'cost_price' => 2000.00,
        ]);

        Delivery::create([
            'order_id' => $orderFeb->id,
            'actual_courier_fee' => 320.00,
            'delivered_at' => Carbon::parse('2026-02-12'),
        ]);

        // Query only January
        $janReport = $this->reportService->generateReport('2026-01-01', '2026-01-31');
        $this->assertEquals(3000.00, $janReport['revenue']['gross_product_sales']);
        $this->assertEquals(1200.00, $janReport['cogs']['total_cogs']);

        // Query only February
        $febReport = $this->reportService->generateReport('2026-02-01', '2026-02-28');
        $this->assertEquals(5000.00, $febReport['revenue']['gross_product_sales']);
        $this->assertEquals(2000.00, $febReport['cogs']['total_cogs']);
    }
}
