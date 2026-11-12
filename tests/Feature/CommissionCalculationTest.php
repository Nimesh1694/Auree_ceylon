<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionRuleChange;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\AgentHierarchyService;
use App\Services\CommissionService;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private CommissionService $commissionService;
    private User $owner;
    private User $marketingManager;
    private User $accountant;
    private User $highLevelAgent;
    private User $lowLevelAgent;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float');
        Setting::set('max_commission_percent_per_order', 35.0, 'commission', 'float');
        Setting::set('commission_return_period_days', 7, 'commission', 'integer');
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');

        $this->hierarchyService = app(AgentHierarchyService::class);
        $this->commissionService = app(CommissionService::class);

        // Management roles
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
        ]);

        $this->marketingManager = User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        // Agent hierarchy: HighLevelAgent -> LowLevelAgent
        $this->highLevelAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HIGH-AGT-01',
        ]);
        AgentProfile::create([
            'user_id' => $this->highLevelAgent->id,
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'kyc_status' => 'approved',
        ]);
        $this->hierarchyService->attachAgent($this->highLevelAgent, null);

        $this->lowLevelAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'LOW-AGT-01',
        ]);
        AgentProfile::create([
            'user_id' => $this->lowLevelAgent->id,
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'kyc_status' => 'approved',
        ]);
        $this->hierarchyService->attachAgent($this->lowLevelAgent, $this->highLevelAgent);
    }

    private function createTestOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-TEST-' . uniqid(),
            'tracking_token' => \Illuminate\Support\Str::random(32),
            'customer_name' => 'Test Customer',
            'customer_phone' => '0771234567',
            'shipping_address' => '123 Flower Road',
            'district' => 'Colombo',
            'city' => 'Colombo 07',
            'subtotal' => 10000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 10350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'placed_at' => now(),
        ], $attributes));
    }

    // =========================================================================
    // 1. TIER & CALCULATION LOGIC
    // =========================================================================

    public function test_1_high_level_agent_direct_sale_earns_25_percent_on_subtotal_excluding_shipping(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 10350.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(1, $entries);
        $entry = $entries->first();

        $this->assertEquals(CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL, $entry->commission_type);
        $this->assertEquals($this->highLevelAgent->id, $entry->agent_id);
        $this->assertEquals('10000.00', number_format((float) $entry->base_amount, 2, '.', ''));
        $this->assertEquals(25.0, (float) $entry->rate_percentage);
        $this->assertEquals('2500.00', number_format((float) $entry->calculated_amount, 2, '.', ''));
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $entry->status);
    }

    public function test_2_low_level_agent_direct_sale_earns_25_percent_for_seller(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->lowLevelAgent->referral_code,
            'agent_id' => $this->lowLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $directEntry = $entries->where('agent_id', $this->lowLevelAgent->id)->first();
        $this->assertNotNull($directEntry);
        $this->assertEquals(CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL, $directEntry->commission_type);
        $this->assertEquals(25.0, (float) $directEntry->rate_percentage);
        $this->assertEquals('2500.00', number_format((float) $directEntry->calculated_amount, 2, '.', ''));
    }

    public function test_3_direct_high_level_upline_receives_10_percent_override_on_low_level_sale(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->lowLevelAgent->referral_code,
            'agent_id' => $this->lowLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Must have exactly 2 entries: 25% direct + 10% override = 35% total
        $this->assertCount(2, $entries);

        $uplineEntry = $entries->where('agent_id', $this->highLevelAgent->id)->first();
        $this->assertNotNull($uplineEntry);
        $this->assertEquals(CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE, $uplineEntry->commission_type);
        $this->assertEquals($this->lowLevelAgent->id, $uplineEntry->selling_agent_id);
        $this->assertEquals(10.0, (float) $uplineEntry->rate_percentage);
        $this->assertEquals('1000.00', number_format((float) $uplineEntry->calculated_amount, 2, '.', ''));
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $uplineEntry->status);
    }

    public function test_4_direct_customer_order_without_agent_referral_generates_zero_commission(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => null,
            'agent_id' => null,
            'subtotal' => 8000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(0, $entries);
        $this->assertEquals(0, CommissionLedgerEntry::where('order_id', $order->id)->count());
    }

    public function test_5_order_with_invalid_referral_code_generates_zero_commission(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => 'NON-EXISTENT-CODE',
            'agent_id' => null,
            'subtotal' => 8000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(0, $entries);
        $this->assertEquals(0, CommissionLedgerEntry::where('order_id', $order->id)->count());
    }

    public function test_6_suspended_or_inactive_agent_referral_generates_zero_commission(): void
    {
        $this->highLevelAgent->update(['status' => UserStatus::SUSPENDED]);

        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 8000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(0, $entries);
        $this->assertEquals(0, CommissionLedgerEntry::where('order_id', $order->id)->count());
    }

    // =========================================================================
    // 2. LIFECYCLE, COD RECONCILIATION & RETURN WINDOW APPROVAL
    // =========================================================================

    public function test_7_newly_placed_order_commissions_are_in_pending_status(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $entries->first()->status);
        $this->assertEquals(0.00, (float) $this->highLevelAgent->fresh()->agentProfile->wallet_balance);
    }

    public function test_8_delivered_order_commission_remains_pending_during_return_window(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-01',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(2), // Only 2 days ago (return window is 7 days)
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $approved = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(0, $approved);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, CommissionLedgerEntry::where('order_id', $order->id)->first()->status);
    }

    public function test_9_commission_remains_pending_if_cod_is_not_verified(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-02',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10), // Delivered 10 days ago (> 7 days)
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_PENDING, // Not matched yet
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $approved = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(0, $approved);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, CommissionLedgerEntry::where('order_id', $order->id)->first()->status);
    }

    public function test_10_cod_verified_but_within_return_window_remains_pending(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-03',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(3), // Only 3 days ago
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED, // Matched!
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $approved = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(0, $approved);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, CommissionLedgerEntry::where('order_id', $order->id)->first()->status);
    }

    public function test_11_return_period_passed_and_cod_matched_approves_commission_and_credits_wallet(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-04',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(8), // Delivered 8 days ago (> 7 days)
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $approved = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(1, $approved);
        $entry = CommissionLedgerEntry::where('order_id', $order->id)->first();
        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $entry->status);
        $this->assertNotNull($entry->approved_at);

        // Agent profile wallet balance is credited
        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals('2500.00', number_format((float) $profile->wallet_balance, 2, '.', ''));
        $this->assertEquals('2500.00', number_format((float) $profile->total_earned, 2, '.', ''));
    }

    // =========================================================================
    // 3. REVERSALS & IMMUTABILITY
    // =========================================================================

    public function test_12_cancelled_order_reverses_commission_with_negative_ledger_entry(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $reversedCount = $this->commissionService->reverseOrderCommissions($order, 'Customer cancelled before dispatch');

        $this->assertEquals(1, $reversedCount);

        // Original entry is immutable and retains its original status (append-only ledger rule)
        $original = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL)
            ->first();
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $original->status);
        $this->assertNull($original->reversed_at);
        $this->assertNull($original->reversal_reason);

        // Reversal ledger entry is appended
        $reversal = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->first();
        $this->assertNotNull($reversal);
        $this->assertEquals('-2500.00', number_format((float) $reversal->calculated_amount, 2, '.', ''));
        $this->assertEquals($original->id, $reversal->original_entry_id);
    }

    public function test_13_refused_order_reverses_commission_with_negative_ledger_entry(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $reversedCount = $this->commissionService->reverseOrderCommissions($order, 'Customer refused package at doorstep');

        $this->assertEquals(1, $reversedCount);

        $reversal = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->first();
        $this->assertEquals('-2500.00', number_format((float) $reversal->calculated_amount, 2, '.', ''));
    }

    public function test_14_returned_order_before_approval_reversed_cleanly(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->commissionService->reverseOrderCommissions($order, 'Returned within cooling-off period');

        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals(0.00, (float) $profile->wallet_balance);
        $this->assertEquals(0.00, (float) $profile->total_earned);
    }

    public function test_15_returned_order_after_approval_reverses_and_decrements_wallet_balance(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-05',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(8),
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);
        $this->commissionService->approveEligibleCommissions();

        // Wallet was credited 2500.00
        $this->assertEquals('2500.00', number_format((float) $this->highLevelAgent->fresh()->agentProfile->wallet_balance, 2, '.', ''));

        // Customer returns product post-approval (e.g. warranty defect return)
        $reversed = $this->commissionService->reverseOrderCommissions($order, 'Warranty defect returned');
        $this->assertEquals(1, $reversed);

        // Wallet is decremented back to 0.00
        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals('0.00', number_format((float) $profile->wallet_balance, 2, '.', ''));
        $this->assertEquals('0.00', number_format((float) $profile->total_earned, 2, '.', ''));
    }

    public function test_16_duplicate_commission_calculation_is_idempotent(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 5000.00,
        ]);

        $call1 = $this->commissionService->calculateAndRecordPendingCommissions($order);
        $call2 = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(1, $call1);
        $this->assertCount(1, $call2);
        $this->assertEquals(1, CommissionLedgerEntry::where('order_id', $order->id)->count());
    }

    public function test_17_duplicate_reversal_is_idempotent(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 5000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $rev1 = $this->commissionService->reverseOrderCommissions($order, 'First cancellation');
        $rev2 = $this->commissionService->reverseOrderCommissions($order, 'Duplicate cancellation call');

        $this->assertEquals(1, $rev1);
        $this->assertEquals(0, $rev2);
        $this->assertEquals(1, CommissionLedgerEntry::where('order_id', $order->id)->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)->count());
    }

    // =========================================================================
    // 4. HISTORICAL SNAPSHOTS & GOVERNANCE RULES
    // =========================================================================

    public function test_18_historical_rate_snapshot_persists_even_after_global_rate_setting_changed(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Now modify global rate setting to 30.0%
        Setting::set('direct_agent_commission_percent', 30.0, 'commission', 'float');

        $entry = CommissionLedgerEntry::where('order_id', $order->id)->first();
        $this->assertEquals(25.0, (float) $entry->rate_percentage);
        $this->assertEquals('2500.00', number_format((float) $entry->calculated_amount, 2, '.', ''));
        $this->assertEquals(25.0, $entry->rate_snapshot['rate_percentage']);
    }

    public function test_19_non_owner_cannot_review_or_approve_commission_rule_changes(): void
    {
        $change = CommissionRuleChange::create([
            'rule_key' => 'direct_agent_commission_percent',
            'old_value' => '25.0',
            'new_value' => '30.0',
            'requested_by' => $this->marketingManager->id,
            'status' => CommissionRuleChange::STATUS_PENDING,
            'reason' => 'Quarterly promotion incentive',
        ]);

        // Marketing manager attempts to approve
        $response1 = $this->actingAs($this->marketingManager)
            ->post(route('admin.commissions.rules.review', $change->id), [
                'action' => 'approve',
            ]);
        $response1->assertStatus(403);

        // Accountant attempts to approve
        $response2 = $this->actingAs($this->accountant)
            ->post(route('admin.commissions.rules.review', $change->id), [
                'action' => 'approve',
            ]);
        $response2->assertStatus(403);

        // Owner approves successfully
        $responseOwner = $this->actingAs($this->owner)
            ->post(route('admin.commissions.rules.review', $change->id), [
                'action' => 'approve',
            ]);
        $responseOwner->assertRedirect();
        $this->assertEquals(CommissionRuleChange::STATUS_APPROVED, $change->fresh()->status);
        $this->assertEquals(30.0, (float) Setting::get('direct_agent_commission_percent'));
    }

    public function test_20_agent_cannot_access_another_agents_commission_ledger(): void
    {
        $otherAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'OTHER-AGT-99',
        ]);

        $orderOther = $this->createTestOrder([
            'referral_code' => $otherAgent->referral_code,
            'agent_id' => $otherAgent->id,
            'subtotal' => 6000.00,
        ]);
        $this->commissionService->calculateAndRecordPendingCommissions($orderOther);

        // LowLevelAgent logs in and visits /agent/commissions
        $response = $this->actingAs($this->lowLevelAgent)->get(route('agent.commissions.index'));
        $response->assertStatus(200);

        // Agent should not see the other agent's commissions
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Commissions/Index')
            ->has('entries.data', 0)
        );
    }

    public function test_21_management_users_do_not_receive_per_sale_commissions(): void
    {
        // Owner tries to attribute order
        $order = $this->createTestOrder([
            'referral_code' => 'OWNER-CODE',
            'agent_id' => $this->owner->id,
            'subtotal' => 5000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(0, $entries);
    }

    public function test_22_no_recruitment_commission_generated(): void
    {
        $newAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'NEW-RECRUIT-01',
        ]);
        $this->hierarchyService->attachAgent($newAgent, $this->highLevelAgent);

        // Recruitment does not produce ledger entries
        $this->assertEquals(0, CommissionLedgerEntry::where('agent_id', $this->highLevelAgent->id)->count());
    }

    public function test_23_decimal_precision_and_rounding_boundaries(): void
    {
        // 25% of 1000.33 = 250.0825 -> rounded to 250.08
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 1000.33,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $entry = $entries->first();
        $this->assertEquals('250.08', number_format((float) $entry->calculated_amount, 2, '.', ''));
    }

    public function test_24_multiple_eligible_orders_from_same_agent_accumulate(): void
    {
        $order1 = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 4000.00,
        ]);
        $order2 = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 6000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order1);
        $this->commissionService->calculateAndRecordPendingCommissions($order2);

        $entries = CommissionLedgerEntry::where('agent_id', $this->highLevelAgent->id)->get();
        $this->assertCount(2, $entries);
        $this->assertEquals('2500.00', number_format((float) $entries->sum('calculated_amount'), 2, '.', '')); // (1000 + 1500)
    }

    public function test_25_concurrent_commission_processing_safety(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-25',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10),
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Run approval in two quick succession calls
        $count1 = $this->commissionService->approveEligibleCommissions();
        $count2 = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(1, $count1);
        $this->assertEquals(0, $count2);

        // Ensure wallet balance is credited only once
        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals('2500.00', number_format((float) $profile->wallet_balance, 2, '.', ''));
    }

    public function test_26_suspended_high_level_upline_does_not_receive_override(): void
    {
        // Suspend the upline High-level agent
        $this->highLevelAgent->update(['status' => UserStatus::SUSPENDED]);

        $order = $this->createTestOrder([
            'referral_code' => $this->lowLevelAgent->referral_code,
            'agent_id' => $this->lowLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Low-level agent gets direct 25% (2500.00)
        $this->assertCount(1, $entries);
        $directEntry = $entries->first();
        $this->assertEquals($this->lowLevelAgent->id, $directEntry->agent_id);
        $this->assertEquals(25.0, (float) $directEntry->rate_percentage);

        // Suspended upline received 0 entries
        $this->assertEquals(0, CommissionLedgerEntry::where('agent_id', $this->highLevelAgent->id)->count());
    }

    public function test_27_mismatched_underpaid_cod_prevents_commission_approval_even_after_return_window(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-27',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(12), // 12 days ago (> 7 days)
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 9000.00, // Shortfall / underpaid
            'reconciliation_status' => CodCollection::STATUS_MISMATCHED_UNDERPAID,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $approved = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(0, $approved);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, CommissionLedgerEntry::where('order_id', $order->id)->first()->status);
    }

    public function test_28_agent_blocked_from_admin_commission_routes(): void
    {
        // Agent visits admin ledger -> 403
        $resp1 = $this->actingAs($this->highLevelAgent)->get(route('admin.commissions.ledger'));
        $resp1->assertStatus(403);

        // Agent visits admin rules -> 403
        $resp2 = $this->actingAs($this->highLevelAgent)->get(route('admin.commissions.rules'));
        $resp2->assertStatus(403);

        // Agent posts to process-eligible -> 403
        $resp3 = $this->actingAs($this->highLevelAgent)->post(route('admin.commissions.process-eligible'));
        $resp3->assertStatus(403);
    }

    public function test_29_customer_blocked_from_agent_and_admin_commission_routes(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Customer visits agent commissions -> 403
        $resp1 = $this->actingAs($customer)->get(route('agent.commissions.index'));
        $resp1->assertStatus(403);

        // Customer visits admin commission ledger -> 403
        $resp2 = $this->actingAs($customer)->get(route('admin.commissions.ledger'));
        $resp2->assertStatus(403);
    }

    public function test_30_exact_decimal_arithmetic_precision_with_fractional_cents(): void
    {
        // 25% of 1000.00 = "250.00"
        $amount1 = $this->commissionService->calculateAmount('1000.00', '25.0');
        $this->assertEquals('250.00', $amount1);

        // 10% of 1234.56 = 123.456 -> half-up rounds to "123.46"
        $amount2 = $this->commissionService->calculateAmount('1234.56', '10.0');
        $this->assertEquals('123.46', $amount2);

        // Key verification: 10.07 * 25% = 2.5175 -> commercial half-up rounds to "2.52"
        $amount3 = $this->commissionService->calculateAmount('10.07', '25.0');
        $this->assertEquals('2.52', $amount3);
    }

    public function test_31_half_cent_boundary_rounding_behavior(): void
    {
        // Exact half-cent boundary: 10.06 * 25% = 2.5150 -> rounds up to 2.52
        $this->assertEquals('2.52', $this->commissionService->calculateAmount('10.06', '25.0'));

        // Just below half-cent: 10.0596 * 25% = 2.5149 -> stays at 2.51
        $this->assertEquals('2.51', $this->commissionService->calculateAmount('10.0596', '25.0'));

        // Just above half-cent: 10.0604 * 25% = 2.5151 -> rounds up to 2.52
        $this->assertEquals('2.52', $this->commissionService->calculateAmount('10.0604', '25.0'));
    }

    public function test_32_large_monetary_value_decimal_arithmetic(): void
    {
        // 25% of 10,000,000.00 = 2,500,000.00 (decimal-safe string)
        $amount = $this->commissionService->calculateAmount('10000000.00', '25.0');
        $this->assertEquals('2500000.00', $amount);

        // 10% of 50,000,000.50 = 5,000,000.05
        $amount2 = $this->commissionService->calculateAmount('50000000.50', '10.0');
        $this->assertEquals('5000000.05', $amount2);
    }

    public function test_33_artisan_command_processes_eligible_maturing_commissions(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-CMD-01',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10), // Mature (> 7 days)
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Run the artisan console command
        $this->artisan('commissions:process-eligible')
            ->expectsOutputToContain('1 eligible commission entries approved')
            ->assertExitCode(0);

        // Verify ledger status and wallet balance
        $entry = CommissionLedgerEntry::where('order_id', $order->id)->first();
        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $entry->status);
        $this->assertEquals('2500.00', number_format((float) $this->highLevelAgent->fresh()->agentProfile->wallet_balance, 2, '.', ''));
    }

    public function test_34_reversal_database_unique_constraint_blocks_duplicate_reversal_record(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);
        $originalEntry = CommissionLedgerEntry::where('order_id', $order->id)->first();

        // First reversal via service succeeds
        $this->commissionService->reverseOrderCommissions($order, 'Legitimate customer return');

        // Direct database insert attempt attempting to reuse original_entry_id violates unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);
        CommissionLedgerEntry::create([
            'entry_number' => 'COMM-DUP-REV',
            'order_id' => $order->id,
            'agent_id' => $this->highLevelAgent->id,
            'selling_agent_id' => $this->highLevelAgent->id,
            'commission_type' => CommissionLedgerEntry::TYPE_REVERSAL,
            'calculation_base' => 'order_subtotal',
            'base_amount' => 10000.00,
            'rate_percentage' => 25.0,
            'calculated_amount' => -2500.00,
            'currency' => 'LKR',
            'status' => CommissionLedgerEntry::STATUS_REVERSED,
            'original_entry_id' => $originalEntry->id, // DUPLICATE original_entry_id
        ]);
    }

    public function test_35_concurrency_race_condition_protection_on_approval(): void
    {
        $order = $this->createTestOrder([
            'referral_code' => $this->highLevelAgent->referral_code,
            'agent_id' => $this->highLevelAgent->id,
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-CONC-01',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10),
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 10350.00,
            'collected_amount' => 10350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);
        $entry = CommissionLedgerEntry::where('order_id', $order->id)->first();

        // Simulate Worker 1 and Worker 2 running approve simultaneously
        // Worker 1 marks it approved in the database
        $affected1 = CommissionLedgerEntry::where('id', $entry->id)
            ->where('status', CommissionLedgerEntry::STATUS_PENDING)
            ->update([
                'status' => CommissionLedgerEntry::STATUS_APPROVED,
                'approved_at' => now(),
            ]);
        $this->assertEquals(1, $affected1);

        // Worker 2 attempts to run the exact same atomic condition on the same entry
        $affected2 = CommissionLedgerEntry::where('id', $entry->id)
            ->where('status', CommissionLedgerEntry::STATUS_PENDING)
            ->update([
                'status' => CommissionLedgerEntry::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

        // Worker 2 gets affected = 0, proving race condition is neutralized
        $this->assertEquals(0, $affected2);
    }
}
