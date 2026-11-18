<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use App\Services\AgentHierarchyService;
use App\Services\PeriodBoundaryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AgentAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentAnalyticsService $analyticsService;
    private PeriodBoundaryService $boundaryService;
    private AgentHierarchyService $hierarchyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = app(AgentAnalyticsService::class);
        $this->boundaryService = app(PeriodBoundaryService::class);
        $this->hierarchyService = app(AgentHierarchyService::class);
    }

    public function test_normalize_decimal_string_strictly_validates_and_normalizes(): void
    {
        $this->assertEquals('1250.50', $this->analyticsService->normalizeDecimalString('1250.50'));
        $this->assertEquals('100.00', $this->analyticsService->normalizeDecimalString('100'));
        $this->assertEquals('500.00', $this->analyticsService->normalizeDecimalString(500));
        $this->assertEquals('0.00', $this->analyticsService->normalizeDecimalString(null, true));

        $this->expectException(InvalidArgumentException::class);
        $this->analyticsService->normalizeDecimalString('');
    }

    public function test_normalize_decimal_string_rejects_null_when_not_allowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->analyticsService->normalizeDecimalString(null, false);
    }

    public function test_cents_to_lkr_converts_integer_cents_exact_string(): void
    {
        $this->assertEquals('1250.50', $this->analyticsService->centsToLkr('125050'));
        $this->assertEquals('0.05', $this->analyticsService->centsToLkr('5'));
        $this->assertEquals('0.00', $this->analyticsService->centsToLkr('0'));
        $this->assertEquals('100.00', $this->analyticsService->centsToLkr('10000'));
    }

    public function test_calculate_full_return_cents_computes_exact_difference(): void
    {
        $cents = $this->analyticsService->calculateFullReturnCents('10000.00', '1500.00');
        $this->assertEquals('850000', $cents); // 8500.00 LKR = 850000 cents

        $this->expectException(DomainException::class);
        $this->analyticsService->calculateFullReturnCents('1000.00', '1500.00');
    }

    public function test_attribution_precedence_gives_absolute_priority_to_order_agent_id(): void
    {
        $agentA = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGENTA01',
        ]);
        $agentB = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGENTB02',
        ]);

        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Order 1: agent_id points to Agent A, but referral_code points to Agent B
        // Authoritative source: Agent A
        Order::create([
            'order_number' => 'ORD-ATTR-001',
            'agent_id' => $agentA->id,
            'referral_code' => $agentB->referral_code,
            'customer_name' => 'Customer One',
            'customer_phone' => '0771111111',
            'shipping_address' => 'Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 5000.00,
            'discount_amount' => 500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4850.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-ATTR-001',
            'placed_at' => now(),
        ]);

        // Order 2: agent_id is null, but referral_code is Agent B
        // Fallback attribution: Agent B
        Order::create([
            'order_number' => 'ORD-ATTR-002',
            'agent_id' => null,
            'referral_code' => $agentB->referral_code,
            'customer_name' => 'Customer Two',
            'customer_phone' => '0772222222',
            'shipping_address' => 'Kandy',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 3000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-ATTR-002',
            'placed_at' => now(),
        ]);

        $analyticsA = $this->analyticsService->getPersonalAnalytics($agentA, $boundaries);
        $analyticsB = $this->analyticsService->getPersonalAnalytics($agentB, $boundaries);

        // Agent A has Order 1: Gross 5000.00, Net 4500.00
        $this->assertEquals('5000.00', $analyticsA['metrics']['gross_product_sales']);
        $this->assertEquals('500.00', $analyticsA['metrics']['product_discounts']);
        $this->assertEquals('4500.00', $analyticsA['metrics']['net_product_sales']);
        $this->assertEquals(1, $analyticsA['metrics']['total_placed_orders']);

        // Agent B has Order 2 only: Gross 3000.00, Net 3000.00
        $this->assertEquals('3000.00', $analyticsB['metrics']['gross_product_sales']);
        $this->assertEquals('0.00', $analyticsB['metrics']['product_discounts']);
        $this->assertEquals('3000.00', $analyticsB['metrics']['net_product_sales']);
        $this->assertEquals(1, $analyticsB['metrics']['total_placed_orders']);
    }

    public function test_refund_populations_are_mutually_exclusive_and_non_overlapping(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGENTREF1',
        ]);
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Order 1: Returned order (full return turnover reversal)
        $orderReturned = Order::create([
            'order_number' => 'ORD-RET-001',
            'agent_id' => $agent->id,
            'referral_code' => $agent->referral_code,
            'customer_name' => 'Return Customer',
            'customer_phone' => '0773333333',
            'shipping_address' => 'Galle',
            'district' => 'Galle',
            'city' => 'Galle',
            'subtotal' => 6000.00,
            'discount_amount' => 1000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'payment_method' => 'cod',
            'status' => 'returned',
            'tracking_token' => 'TOK-RET-001',
            'placed_at' => now(),
        ]);

        // Order 2: Delivered order with partial refund item
        $orderDelivered = Order::create([
            'order_number' => 'ORD-DEL-002',
            'agent_id' => $agent->id,
            'referral_code' => $agent->referral_code,
            'customer_name' => 'Delivered Customer',
            'customer_phone' => '0774444444',
            'shipping_address' => 'Matara',
            'district' => 'Matara',
            'city' => 'Matara',
            'subtotal' => 8000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 8350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-DEL-002',
            'placed_at' => now(),
        ]);

        $staff = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);

        $category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'Skincare',
            'name_ta' => 'Skincare',
            'slug' => 'skincare-test',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name_en' => 'Luxe Night Cream',
            'name_si' => 'Luxe Night Cream',
            'slug' => 'luxe-night-cream',
            'price' => 8000.00,
            'is_active' => true,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $orderDelivered->id,
            'product_id' => $product->id,
            'product_name' => 'Luxe Night Cream',
            'quantity' => 1,
            'unit_price' => 8000.00,
            'subtotal' => 8000.00,
        ]);

        // Create partial refund record in order_refunds (150000 cents = 1500.00 LKR)
        DB::table('order_refunds')->insert([
            'order_id' => $orderDelivered->id,
            'order_item_id' => $orderItem->id,
            'unit_index' => 0,
            'refund_cents' => 150000,
            'refund_amount' => 1500.00,
            'reason' => 'Damaged outer seal',
            'processed_by' => $staff->id,
            'created_at' => now(),
        ]);

        $analytics = $this->analyticsService->getPersonalAnalytics($agent, $boundaries);

        // Full-return turnover reversal: 6000 - 1000 = 5000.00
        $this->assertEquals('5000.00', $analytics['metrics']['full_return_order_value']);

        // Fulfilled partial refund: 1500.00
        $this->assertEquals('1500.00', $analytics['metrics']['fulfilled_partial_refund_amount']);

        // Combined deductions: 5000.00 + 1500.00 = 6500.00
        $this->assertEquals('6500.00', $analytics['metrics']['total_commercial_deductions']);

        // Returned orders count is 1, Fulfilled is 1
        $this->assertEquals(1, $analytics['metrics']['total_returned_orders']);
        $this->assertEquals(1, $analytics['metrics']['total_fulfilled_orders']);
    }

    public function test_population_hierarchy_counts_and_rates(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGENTPOP1',
        ]);
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Order 1: delivered
        Order::create([
            'order_number' => 'ORD-POP-001',
            'agent_id' => $agent->id,
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-POP-001',
            'customer_name' => 'C1',
            'customer_phone' => '0770000001',
            'shipping_address' => 'Addr',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'placed_at' => now(),
        ]);

        // Order 2: returned
        Order::create([
            'order_number' => 'ORD-POP-002',
            'agent_id' => $agent->id,
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'returned',
            'tracking_token' => 'TOK-POP-002',
            'customer_name' => 'C2',
            'customer_phone' => '0770000002',
            'shipping_address' => 'Addr',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'placed_at' => now(),
        ]);

        // Order 3: cancelled
        Order::create([
            'order_number' => 'ORD-POP-003',
            'agent_id' => $agent->id,
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'cancelled',
            'tracking_token' => 'TOK-POP-003',
            'customer_name' => 'C3',
            'customer_phone' => '0770000003',
            'shipping_address' => 'Addr',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'placed_at' => now(),
        ]);

        // Order 4: completed
        Order::create([
            'order_number' => 'ORD-POP-004',
            'agent_id' => $agent->id,
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'completed',
            'tracking_token' => 'TOK-POP-004',
            'customer_name' => 'C4',
            'customer_phone' => '0770000004',
            'shipping_address' => 'Addr',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'placed_at' => now(),
        ]);

        $analytics = $this->analyticsService->getPersonalAnalytics($agent, $boundaries);

        // Placed: 4
        $this->assertEquals(4, $analytics['metrics']['total_placed_orders']);
        // Commercial: delivered (1) + returned (1) + completed (1) = 3
        $this->assertEquals(3, $analytics['metrics']['total_commercial_orders']);
        // Fulfilled: delivered (1) + completed (1) = 2
        $this->assertEquals(2, $analytics['metrics']['total_fulfilled_orders']);
        // Returned: 1
        $this->assertEquals(1, $analytics['metrics']['total_returned_orders']);
        // Cancelled: 1
        $this->assertEquals(1, $analytics['metrics']['total_cancelled_orders']);

        // Delivery Success Rate: 2 fulfilled / 3 dispatched = 66.66%
        $this->assertEquals('66.66%', $analytics['metrics']['delivery_success_rate']);
        // Full Return Rate: 1 returned / 3 commercial = 33.33%
        $this->assertEquals('33.33%', $analytics['metrics']['full_return_order_rate']);
    }

    public function test_cash_collected_status_is_strictly_included_in_commercial_and_fulfilled_populations(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGENTCASH01',
        ]);
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        Order::create([
            'order_number' => 'ORD-CASH-001',
            'agent_id' => $agent->id,
            'subtotal' => 4500.00,
            'discount_amount' => 500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4350.00,
            'payment_method' => 'cod',
            'status' => 'cash_collected',
            'tracking_token' => 'TOK-CASH-001',
            'customer_name' => 'Cash Customer',
            'customer_phone' => '0770001234',
            'shipping_address' => 'Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'placed_at' => now(),
        ]);

        $analytics = $this->analyticsService->getPersonalAnalytics($agent, $boundaries);

        $this->assertEquals('4500.00', $analytics['metrics']['gross_product_sales']);
        $this->assertEquals('500.00', $analytics['metrics']['product_discounts']);
        $this->assertEquals('4000.00', $analytics['metrics']['net_product_sales']);
        $this->assertEquals(1, $analytics['metrics']['total_placed_orders']);
        $this->assertEquals(1, $analytics['metrics']['total_dispatched_orders']);
        $this->assertEquals(1, $analytics['metrics']['total_commercial_orders'], 'cash_collected orders must be strictly part of P_commercial');
        $this->assertEquals(1, $analytics['metrics']['total_fulfilled_orders'], 'cash_collected orders must be strictly part of P_fulfilled');
        $this->assertEquals(0, $analytics['metrics']['total_returned_orders']);
        $this->assertEquals('100.00%', $analytics['metrics']['delivery_success_rate']);
    }
}
