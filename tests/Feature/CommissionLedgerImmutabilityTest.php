<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AgentHierarchyService;
use App\Services\CommissionService;
use App\Services\OrderStateMachineService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommissionLedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private CommissionService $commissionService;
    private WalletService $walletService;
    private User $owner;
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
        $this->walletService = app(WalletService::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->highLevelAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HIGH-AGT-IMMUTABLE',
        ]);

        AgentProfile::create([
            'user_id' => $this->highLevelAgent->id,
            'wallet_balance' => 0,
            'reserved_balance' => 0,
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
            'order_number' => 'ORD-IMMUTABLE-' . uniqid(),
            'tracking_token' => Str::random(32),
            'customer_name' => 'Immutability Customer',
            'customer_phone' => '0771122334',
            'shipping_address' => '456 Lotus Lane',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
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
     * 1. Test original pending commission ledger entry is NEVER mutated upon reversal.
     */
    public function test_original_pending_commission_ledger_entry_is_never_mutated_upon_reversal(): void
    {
        $order = $this->createTestOrder(['subtotal' => 10000.00]);
        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $originalEntry = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL)
            ->firstOrFail();

        $originalAttributes = $originalEntry->toArray();
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $originalEntry->status);
        $this->assertNull($originalEntry->reversed_at);
        $this->assertNull($originalEntry->reversal_reason);

        // Sleep briefly to ensure timestamps would differ if updated
        usleep(10000);

        // Reverse commissions upon order cancellation
        $reversedCount = $this->commissionService->reverseOrderCommissions($order, 'Customer cancelled before dispatch');
        $this->assertEquals(1, $reversedCount);

        // Re-fetch original entry from fresh database query
        $refreshedOriginal = CommissionLedgerEntry::find($originalEntry->id);
        $refreshedAttributes = $refreshedOriginal->toArray();

        // 1. Original attributes must be 100% identical (value-for-value)
        $this->assertEquals($originalAttributes['status'], $refreshedAttributes['status']);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $refreshedAttributes['status']);
        $this->assertNull($refreshedAttributes['reversed_at']);
        $this->assertNull($refreshedAttributes['reversal_reason']);
        $this->assertEquals($originalAttributes['calculated_amount'], $refreshedAttributes['calculated_amount']);
        $this->assertEquals($originalAttributes['updated_at'], $refreshedAttributes['updated_at']);

        // 2. Compensating negative entry is appended
        $reversalEntry = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->first();

        $this->assertNotNull($reversalEntry);
        $this->assertEquals($originalEntry->id, $reversalEntry->original_entry_id);
        $this->assertEquals('-2500.00', number_format((float) $reversalEntry->calculated_amount, 2, '.', ''));
        $this->assertEquals(CommissionLedgerEntry::STATUS_REVERSED, $reversalEntry->status);
        $this->assertEquals('Customer cancelled before dispatch', $reversalEntry->reversal_reason);
        $this->assertNotNull($reversalEntry->reversed_at);
    }

    /**
     * 2. Test original approved commission ledger entry is NEVER mutated upon post-approval reversal.
     */
    public function test_original_approved_commission_ledger_entry_is_never_mutated_upon_post_approval_reversal(): void
    {
        $order = $this->createTestOrder([
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-IMM-01',
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

        $originalEntry = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL)
            ->firstOrFail();

        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $originalEntry->status);
        $this->assertNotNull($originalEntry->approved_at);
        $originalAttributes = $originalEntry->toArray();

        // Wallet was credited 2500.00
        $this->assertEquals('2500.00', number_format((float) $this->highLevelAgent->fresh()->agentProfile->wallet_balance, 2, '.', ''));

        usleep(10000);

        // Customer returns product post-approval (e.g. warranty defect return)
        $reversed = $this->commissionService->reverseOrderCommissions($order, 'Warranty defect returned');
        $this->assertEquals(1, $reversed);

        // Original entry must remain strictly STATUS_APPROVED and unmodified
        $refreshedOriginal = CommissionLedgerEntry::find($originalEntry->id);
        $refreshedAttributes = $refreshedOriginal->toArray();

        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $refreshedAttributes['status']);
        $this->assertEquals($originalAttributes['approved_at'], $refreshedAttributes['approved_at']);
        $this->assertNull($refreshedAttributes['reversed_at']);
        $this->assertNull($refreshedAttributes['reversal_reason']);
        $this->assertEquals($originalAttributes['updated_at'], $refreshedAttributes['updated_at']);

        // Compensating negative entry created
        $reversalEntry = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->firstOrFail();

        $this->assertEquals('-2500.00', number_format((float) $reversalEntry->calculated_amount, 2, '.', ''));
        $this->assertEquals($originalEntry->id, $reversalEntry->original_entry_id);

        // Wallet deducted back to 0.00
        $profile = $this->highLevelAgent->fresh()->agentProfile;
        $this->assertEquals('0.00', number_format((float) $profile->wallet_balance, 2, '.', ''));
        $this->assertEquals('0.00', number_format((float) $profile->total_earned, 2, '.', ''));

        // Wallet transaction logged pointing to reversal entry
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->highLevelAgent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_REVERSAL,
            'amount' => '-2500.00',
            'source_id' => $reversalEntry->id,
        ]);
    }

    /**
     * 3. Test duplicate reversal calls are strictly idempotent and create at most one reversal entry.
     */
    public function test_duplicate_reversal_is_strictly_idempotent(): void
    {
        $order = $this->createTestOrder(['subtotal' => 10000.00]);
        $this->commissionService->calculateAndRecordPendingCommissions($order);

        $call1 = $this->commissionService->reverseOrderCommissions($order, 'First cancellation attempt');
        $call2 = $this->commissionService->reverseOrderCommissions($order, 'Duplicate cancellation attempt');

        $this->assertEquals(1, $call1);
        $this->assertEquals(0, $call2);

        $reversalsCount = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->count();
        $this->assertEquals(1, $reversalsCount);
    }

    /**
     * 4. Test database UNIQUE constraint on original_entry_id rejects duplicate reversal entries at engine level.
     */
    public function test_unique_constraint_on_original_entry_id_prevents_duplicate_reversals(): void
    {
        $order = $this->createTestOrder(['subtotal' => 10000.00]);
        $this->commissionService->calculateAndRecordPendingCommissions($order);
        $originalEntry = CommissionLedgerEntry::where('order_id', $order->id)->firstOrFail();

        // Legitimate reversal via service
        $this->commissionService->reverseOrderCommissions($order, 'Legitimate return');

        // Direct DB attempt to insert duplicate reversal pointing to same original_entry_id
        $this->expectException(\Illuminate\Database\QueryException::class);
        CommissionLedgerEntry::create([
            'entry_number' => 'COMM-DUP-TEST',
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
            'original_entry_id' => $originalEntry->id,
        ]);
    }

    /**
     * 5. Test approveEligibleCommissions never approves a reversed pending commission.
     */
    public function test_approve_eligible_commissions_never_approves_reversed_pending_commissions(): void
    {
        $order = $this->createTestOrder([
            'subtotal' => 10000.00,
            'status' => OrderStateMachineService::STATUS_DELIVERED,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-IMM-02',
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

        // Cancel / Reverse while still pending
        $this->commissionService->reverseOrderCommissions($order, 'Order cancelled before processing');

        // Execute batch approval
        $approvedCount = $this->commissionService->approveEligibleCommissions();

        $this->assertEquals(0, $approvedCount);

        // Original entry must STILL be STATUS_PENDING (unapproved because it was reversed)
        $original = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)
            ->firstOrFail();

        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $original->status);
        $this->assertNull($original->approved_at);

        // Agent wallet balance must remain 0.00
        $this->assertEquals('0.00', number_format((float) $this->highLevelAgent->fresh()->agentProfile->wallet_balance, 2, '.', ''));
    }

    /**
     * 6. Test wallet summary accurately calculates pending and reversed amounts without double-counting.
     */
    public function test_wallet_summary_reflects_accurate_pending_and_reversed_amounts(): void
    {
        // Order 1: Active pending order (subtotal 10000 -> 2500 pending)
        $order1 = $this->createTestOrder(['subtotal' => 10000.00]);
        $this->commissionService->calculateAndRecordPendingCommissions($order1);

        // Order 2: Cancelled order (subtotal 4000 -> 1000 pending reversed)
        $order2 = $this->createTestOrder(['subtotal' => 4000.00]);
        $this->commissionService->calculateAndRecordPendingCommissions($order2);
        $this->commissionService->reverseOrderCommissions($order2, 'Customer cancelled');

        $summary = $this->walletService->getWalletSummary($this->highLevelAgent);

        // Pending commissions must only count Order 1 (2500.00), excluding the reversed Order 2
        $this->assertEquals(2500.00, $summary['pending_commissions']);

        // Reversed commissions must report the negative reversal (-1000.00)
        $this->assertEquals(-1000.00, $summary['reversed_commissions']);
    }

    /**
     * 7. Test unreversed query scopes on CommissionLedgerEntry model.
     */
    public function test_commission_ledger_entry_scopes_operate_correctly(): void
    {
        $order = $this->createTestOrder(['subtotal' => 10000.00]);
        $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Before reversal: exactly 1 pending active
        $this->assertEquals(1, CommissionLedgerEntry::pendingActive()->count());
        $this->assertEquals(0, CommissionLedgerEntry::approvedActive()->count());
        $this->assertEquals(1, CommissionLedgerEntry::active()->count());

        $this->commissionService->reverseOrderCommissions($order, 'Cancelled');

        // After reversal: 0 pending active, 0 approved active, 0 active
        $this->assertEquals(0, CommissionLedgerEntry::pendingActive()->count());
        $this->assertEquals(0, CommissionLedgerEntry::approvedActive()->count());
        $this->assertEquals(0, CommissionLedgerEntry::active()->count());

        // Total count in database is 2 (original entry + compensating reversal entry)
        $this->assertEquals(2, CommissionLedgerEntry::count());
    }
}

