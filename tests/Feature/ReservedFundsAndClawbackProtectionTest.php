<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AgentHierarchyService;
use App\Services\CommissionService;
use App\Services\OrderStateMachineService;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ReservedFundsAndClawbackProtectionTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private CommissionService $commissionService;
    private PayoutService $payoutService;
    private WalletService $walletService;
    private User $owner;
    private User $accountant;
    private User $highLevelAgent;

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
        $this->payoutService = app(PayoutService::class);
        $this->walletService = app(WalletService::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->highLevelAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HIGH-AGT-SHIELD',
        ]);

        AgentProfile::create([
            'user_id' => $this->highLevelAgent->id,
            'wallet_balance' => 0,
            'reserved_balance' => 0,
            'outstanding_clawback' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'kyc_status' => 'approved',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo Fort',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test High Agent',
        ]);

        $this->hierarchyService->attachAgent($this->highLevelAgent, null);
    }

    private function createTestOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-SHIELD-' . uniqid(),
            'tracking_token' => Str::random(32),
            'customer_name' => 'Shield Customer',
            'customer_phone' => '0779988776',
            'shipping_address' => '789 Lotus Road',
            'district' => 'Colombo',
            'city' => 'Colombo 01',
            'subtotal' => 10000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 10350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'placed_at' => now(),
            'agent_id' => $this->highLevelAgent->id,
            'referral_code' => $this->highLevelAgent->referral_code,
        ], $attributes));
    }

    /**
     * Helper: Sets up an agent with a delivered & approved order earning specified commission.
     */
    private function seedApprovedOrderWithCommission(float $subtotal, float $expectedCommission): Order
    {
        $order = $this->createTestOrder([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + 350.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-' . uniqid(),
            'status' => 'delivered',
            'delivered_at' => now()->subDays(8),
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => $subtotal + 350.00,
            'collected_amount' => $subtotal + 350.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);
        $this->commissionService->approveEligibleCommissions();

        return $order;
    }

    /**
     * Scenario A: Wallet 100.00, Reserved 80.00, Reversal 50.00
     */
    public function test_scenario_a_wallet_100_reserved_80_reversal_50(): void
    {
        // 1. Order 1: Subtotal 400.00 -> 25% = 100.00 commission
        $order1 = $this->seedApprovedOrderWithCommission(400.00, 100.00);

        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals('100.00', (string) $profile->wallet_balance);
        $this->assertEquals('100.00', (string) $profile->available_balance);

        // 2. Request Payout of 80.00
        $payout = $this->payoutService->createPayoutRequest($this->highLevelAgent, '80.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        $profile->refresh();
        $this->assertEquals('100.00', (string) $profile->wallet_balance);
        $this->assertEquals('80.00', (string) $profile->reserved_balance);
        $this->assertEquals('20.00', (string) $profile->available_balance);
        $this->assertEquals('0.00', (string) $profile->outstanding_clawback);

        // 3. Reversal of 50.00 occurs (e.g. on Order 2 where 50 was earned, or partial return on Order 1)
        // We simulate a 50.00 reversal by creating an approved commission entry for 50.00 and reversing it
        $order2 = $this->seedApprovedOrderWithCommission(200.00, 50.00);
        // Note: order2 added 50.00 to wallet (now 150.00). To test the exact 100.00 baseline:
        // Let's reset profile back to wallet 100.00, reserved 80.00
        $profile->refresh();
        $profile->wallet_balance = '100.00';
        $profile->save();

        // Now reverse Order 2 (50.00)
        $this->commissionService->reverseOrderCommissions($order2, 'Defective item returned');

        $profile->refresh();
        // Reserved 80.00 is protected! Unreserved was 20.00 (100 - 80).
        // 20.00 consumed from wallet -> wallet becomes 80.00.
        // 30.00 excess clawback recorded in outstanding_clawback.
        $this->assertEquals('80.00', (string) $profile->wallet_balance);
        $this->assertEquals('80.00', (string) $profile->reserved_balance);
        $this->assertEquals('0.00', (string) $profile->available_balance);
        $this->assertEquals('30.00', (string) $profile->outstanding_clawback);

        // 4. Verify Payout #1 is 100% funded and can be settled cleanly
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'CEFT-SCENARIO-A',
        ]);

        $profile->refresh();
        $this->assertEquals('0.00', (string) $profile->wallet_balance);
        $this->assertEquals('0.00', (string) $profile->reserved_balance);
        $this->assertEquals('0.00', (string) $profile->available_balance);
        $this->assertEquals('30.00', (string) $profile->outstanding_clawback);
        $this->assertEquals('80.00', (string) $profile->total_withdrawn);
    }

    /**
     * Scenario B: Wallet 100.00, Reserved 100.00, Reversal 50.00
     */
    public function test_scenario_b_wallet_100_reserved_100_reversal_50(): void
    {
        $order1 = $this->seedApprovedOrderWithCommission(400.00, 100.00);
        $payout = $this->payoutService->createPayoutRequest($this->highLevelAgent, '100.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals('100.00', (string) $profile->wallet_balance);
        $this->assertEquals('100.00', (string) $profile->reserved_balance);
        $this->assertEquals('0.00', (string) $profile->available_balance);

        // Reversal of 50.00 on Order 2
        $order2 = $this->seedApprovedOrderWithCommission(200.00, 50.00);
        $profile->refresh();
        $profile->wallet_balance = '100.00'; // exact baseline 100/100
        $profile->save();

        $this->commissionService->reverseOrderCommissions($order2, 'Warranty defect');

        $profile->refresh();
        // Since unreserved available was 0.00, entire 50.00 goes to outstanding_clawback
        // Protected reserved balance of 100.00 is completely untouched!
        $this->assertEquals('100.00', (string) $profile->wallet_balance);
        $this->assertEquals('100.00', (string) $profile->reserved_balance);
        $this->assertEquals('0.00', (string) $profile->available_balance);
        $this->assertEquals('50.00', (string) $profile->outstanding_clawback);

        // Settlement completes safely
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'CEFT-SCENARIO-B',
        ]);

        $profile->refresh();
        $this->assertEquals('0.00', (string) $profile->wallet_balance);
        $this->assertEquals('0.00', (string) $profile->reserved_balance);
        $this->assertEquals('50.00', (string) $profile->outstanding_clawback);
    }

    /**
     * Scenario C: Wallet 100.00, Reserved 80.00, Reversal 120.00
     */
    public function test_scenario_c_wallet_100_reserved_80_reversal_120(): void
    {
        $order1 = $this->seedApprovedOrderWithCommission(400.00, 100.00);
        $payout = $this->payoutService->createPayoutRequest($this->highLevelAgent, '80.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        // Reversal of 120.00
        $order2 = $this->seedApprovedOrderWithCommission(480.00, 120.00);
        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $profile->wallet_balance = '100.00'; // reset to 100/80 baseline
        $profile->save();

        $this->commissionService->reverseOrderCommissions($order2, 'Entire order recalled');

        $profile->refresh();
        // Unreserved was 20.00 (100 - 80). 20 consumed -> wallet becomes 80.00.
        // Excess 100.00 (120 - 20) recorded in outstanding_clawback.
        $this->assertEquals('80.00', (string) $profile->wallet_balance);
        $this->assertEquals('80.00', (string) $profile->reserved_balance);
        $this->assertEquals('100.00', (string) $profile->outstanding_clawback);

        // Payout of 80.00 settles without negative balance
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'CEFT-SCENARIO-C',
        ]);

        $profile->refresh();
        $this->assertEquals('0.00', (string) $profile->wallet_balance);
        $this->assertEquals('0.00', (string) $profile->reserved_balance);
        $this->assertEquals('100.00', (string) $profile->outstanding_clawback);
    }

    /**
     * Scenario D: Wallet 50.00, Outstanding Clawback 30.00, New Eligible Commission Credit 40.00
     */
    public function test_scenario_d_outstanding_clawback_amortized_by_new_commission(): void
    {
        $profile = $this->highLevelAgent->agentProfile;
        $profile->wallet_balance = '50.00';
        $profile->reserved_balance = '0.00';
        $profile->outstanding_clawback = '30.00';
        $profile->save();

        // New order delivered and matures with 40.00 commission (subtotal 160.00 -> 25% = 40.00)
        $order = $this->createTestOrder([
            'subtotal' => 160.00,
            'total_amount' => 510.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-MATURE-01',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(8),
        ]);
        CodCollection::create([
            'order_id' => $order->id,
            'courier_id' => 1,
            'expected_amount' => 510.00,
            'collected_amount' => 510.00,
            'reconciliation_status' => CodCollection::STATUS_MATCHED,
        ]);

        $this->commissionService->calculateAndRecordPendingCommissions($order);
        $approvedCount = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(1, $approvedCount);

        $profile->refresh();
        // 40.00 matured: 30.00 amortized outstanding_clawback to 0.00, remaining 10.00 added to wallet (50 + 10 = 60)
        $this->assertEquals('0.00', (string) $profile->outstanding_clawback);
        $this->assertEquals('60.00', (string) $profile->wallet_balance);
        $this->assertEquals('60.00', (string) $profile->available_balance);

        // Wallet summary reflects clean zero debt and updated wallet
        $summary = $this->walletService->getWalletSummary($this->highLevelAgent);
        $this->assertEquals(60.00, $summary['wallet_balance']);
        $this->assertEquals(0.00, $summary['outstanding_clawback']);
    }

    /**
     * Scenario E: Multiple payouts with aggregate reservations and multiple reversals.
     */
    public function test_scenario_e_multiple_payouts_and_reversals(): void
    {
        // 1. Orders 1 & 2 previously approved: 30.00 + 15.00
        $order1 = $this->seedApprovedOrderWithCommission(120.00, 30.00);
        $order2 = $this->seedApprovedOrderWithCommission(60.00, 15.00);

        // Establish Scenario E baseline: wallet = 120.00, reserved = 0.00, clawback = 0.00
        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $profile->wallet_balance = '120.00';
        $profile->reserved_balance = '0.00';
        $profile->outstanding_clawback = '0.00';
        $profile->save();

        // Payout A = 70.00, Payout B = 30.00 -> Reserved = 100.00, Available = 20.00
        $payoutA = $this->payoutService->createPayoutRequest($this->highLevelAgent, '70.00');
        $payoutB = $this->payoutService->createPayoutRequest($this->highLevelAgent, '30.00');

        $this->payoutService->approvePayoutRequest($payoutA, $this->owner);
        $this->payoutService->approvePayoutRequest($payoutB, $this->owner);

        $profile->refresh();
        $this->assertEquals('120.00', (string) $profile->wallet_balance);
        $this->assertEquals('100.00', (string) $profile->reserved_balance);
        $this->assertEquals('20.00', (string) $profile->available_balance);

        // Reversal 1: 30.00 (unreserved 20 consumed, 10 to clawback)
        $this->commissionService->reverseOrderCommissions($order1, 'First return');

        $profile->refresh();
        $this->assertEquals('100.00', (string) $profile->wallet_balance);
        $this->assertEquals('100.00', (string) $profile->reserved_balance);
        $this->assertEquals('10.00', (string) $profile->outstanding_clawback);

        // Reversal 2: 15.00 (unreserved 0, entire 15 to clawback -> 25 total)
        $this->commissionService->reverseOrderCommissions($order2, 'Second return');

        $profile->refresh();
        $this->assertEquals('100.00', (string) $profile->wallet_balance);
        $this->assertEquals('100.00', (string) $profile->reserved_balance);
        $this->assertEquals('25.00', (string) $profile->outstanding_clawback);

        // Settle Payout A (70.00)
        $this->payoutService->recordPayment($payoutA, $this->accountant, [
            'bank_transfer_reference' => 'CEFT-SCENARIO-E-1',
        ]);

        $profile->refresh();
        $this->assertEquals('30.00', (string) $profile->wallet_balance);
        $this->assertEquals('30.00', (string) $profile->reserved_balance);
        $this->assertEquals('25.00', (string) $profile->outstanding_clawback);

        // Settle Payout B (30.00)
        $this->payoutService->recordPayment($payoutB, $this->accountant, [
            'bank_transfer_reference' => 'CEFT-SCENARIO-E-2',
        ]);

        $profile->refresh();
        $this->assertEquals('0.00', (string) $profile->wallet_balance);
        $this->assertEquals('0.00', (string) $profile->reserved_balance);
        $this->assertEquals('25.00', (string) $profile->outstanding_clawback);
    }

    /**
     * Test Payout rejection symmetrically absorbs outstanding clawback.
     */
    public function test_payout_rejection_absorbs_outstanding_clawback_symmetrically(): void
    {
        $profile = $this->highLevelAgent->agentProfile;
        $profile->wallet_balance = '80.00';
        $profile->reserved_balance = '80.00';
        $profile->outstanding_clawback = '30.00';
        $profile->save();

        // Payout of 80.00 is in approved status
        $payout = PayoutRequest::create([
            'reference_number' => PayoutRequest::generateReferenceNumber(),
            'agent_id' => $this->highLevelAgent->id,
            'amount' => '80.00',
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_APPROVED,
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test High Agent',
        ]);

        // Owner rejects payout
        $this->payoutService->rejectPayoutRequest($payout, $this->owner, 'Rejection test for clawback absorption');

        $profile->refresh();
        // 80.00 released: absorbs 30.00 clawback to 0.00, remaining 50.00 becomes available in wallet
        $this->assertEquals('0.00', (string) $profile->reserved_balance);
        $this->assertEquals('0.00', (string) $profile->outstanding_clawback);
        $this->assertEquals('50.00', (string) $profile->wallet_balance);
        $this->assertEquals('50.00', (string) $profile->available_balance);
    }

    /**
     * Test aggregate reservation deficit blocks payout approval and processing.
     */
    public function test_aggregate_reservation_deficit_blocks_approval_and_processing(): void
    {
        $profile = $this->highLevelAgent->agentProfile;
        $profile->wallet_balance = '70.00';
        $profile->reserved_balance = '80.00'; // deficit of 10.00
        $profile->save();

        $payout = PayoutRequest::create([
            'reference_number' => PayoutRequest::generateReferenceNumber(),
            'agent_id' => $this->highLevelAgent->id,
            'amount' => '40.00',
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_PENDING,
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test High Agent',
        ]);

        // Attempting to approve must throw InvalidArgumentException due to aggregate deficit
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('aggregate reservation deficit');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);
    }

    /**
     * Test settlement guard prevents negative wallet balance.
     */
    public function test_settlement_guard_prevents_negative_wallet_balance(): void
    {
        $profile = $this->highLevelAgent->agentProfile;
        $profile->wallet_balance = '50.00';
        $profile->reserved_balance = '80.00';
        $profile->save();

        $payout = PayoutRequest::create([
            'reference_number' => PayoutRequest::generateReferenceNumber(),
            'agent_id' => $this->highLevelAgent->id,
            'amount' => '80.00',
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_APPROVED,
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test High Agent',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('insufficient to settle requested payout');
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'CEFT-GUARD-TEST',
        ]);
    }
}
