<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\CommissionLedgerEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use App\Services\PeriodBoundaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionLedgerReversalAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private AgentAnalyticsService $analyticsService;
    private PeriodBoundaryService $boundaryService;
    private User $agent;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = app(AgentAnalyticsService::class);
        $this->boundaryService = app(PeriodBoundaryService::class);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'COMMAGENT01',
        ]);
        AgentProfile::create([
            'user_id' => $this->agent->id,
            'nic' => '199312345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-COMM-001',
            'agent_id' => $this->agent->id,
            'customer_name' => 'Comm Customer',
            'customer_phone' => '0775555555',
            'shipping_address' => 'Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 10000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 10350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-COMM-001',
            'placed_at' => now(),
        ]);
    }

    public function test_active_commission_cohorts_properly_segregate_approved_and_pending(): void
    {
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Entry 1: Approved Active (500.00)
        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-001',
            'order_id' => $this->order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'personal_sales',
            'calculation_base' => 'subtotal',
            'base_amount' => 5000.00,
            'rate_percentage' => 10.00,
            'calculated_amount' => 500.00,
            'currency' => 'LKR',
            'status' => 'approved',
            'approved_at' => now(),
            'reversed_at' => null,
            'created_at' => now(),
        ]);

        // Entry 2: Pending Active (300.00)
        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-002',
            'order_id' => $this->order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'personal_sales',
            'calculation_base' => 'subtotal',
            'base_amount' => 3000.00,
            'rate_percentage' => 10.00,
            'calculated_amount' => 300.00,
            'currency' => 'LKR',
            'status' => 'pending',
            'approved_at' => null,
            'reversed_at' => null,
            'created_at' => now(),
        ]);

        $cohort = $this->analyticsService->getActiveCommissionCohort($this->agent->id, $boundaries['startUtc'], $boundaries['endUtc']);

        $this->assertEquals('500.00', $cohort['active_approved_commission']);
        $this->assertEquals('300.00', $cohort['active_pending_commission']);
    }

    public function test_reversed_commissions_are_strictly_excluded_from_active_cohorts(): void
    {
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Reversed entry: status = 'reversed', reversed_at is set
        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-003',
            'order_id' => $this->order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'personal_sales',
            'calculation_base' => 'subtotal',
            'base_amount' => 4000.00,
            'rate_percentage' => 10.00,
            'calculated_amount' => 400.00,
            'currency' => 'LKR',
            'status' => 'reversed',
            'approved_at' => now()->subDay(),
            'reversed_at' => now(),
            'reversal_reason' => 'Customer returned package',
            'created_at' => now()->subDay(),
        ]);

        $cohort = $this->analyticsService->getActiveCommissionCohort($this->agent->id, $boundaries['startUtc'], $boundaries['endUtc']);

        // Must be zero because the reversed entry cannot be included in active cohorts
        $this->assertEquals('0.00', $cohort['active_approved_commission']);
        $this->assertEquals('0.00', $cohort['active_pending_commission']);
    }

    public function test_period_activity_correctly_aggregates_credits_and_debits(): void
    {
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Credit 1: Approved at now() for 600.00
        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-004',
            'order_id' => $this->order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'personal_sales',
            'calculation_base' => 'subtotal',
            'base_amount' => 6000.00,
            'rate_percentage' => 10.00,
            'calculated_amount' => 600.00,
            'currency' => 'LKR',
            'status' => 'approved',
            'approved_at' => now(),
            'reversed_at' => null,
            'created_at' => now(),
        ]);

        // Debit 1: Reversal at now() for -200.00
        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-005',
            'order_id' => $this->order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_REVERSAL,
            'calculation_base' => 'subtotal',
            'base_amount' => 2000.00,
            'rate_percentage' => 10.00,
            'calculated_amount' => -200.00,
            'currency' => 'LKR',
            'status' => 'reversed',
            'approved_at' => now()->subDays(2),
            'reversed_at' => now(),
            'reversal_reason' => 'Defective return',
            'created_at' => now()->subDays(2),
        ]);

        $activity = $this->analyticsService->getPeriodCommissionActivity($this->agent->id, $boundaries['startUtc'], $boundaries['endUtc']);

        $this->assertEquals('600.00', $activity['approved_credits']);
        $this->assertEquals('-200.00', $activity['reversal_debits']);
        $this->assertEquals('400.00', $activity['net_period_activity']);
    }
}
