<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\CommissionLedgerEntry;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\PayoutStatusNotification;
use App\Services\AgentHierarchyService;
use App\Services\CommissionService;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentWalletAndPayoutTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;
    private PayoutService $payoutService;
    private CommissionService $commissionService;

    private User $owner;
    private User $accountant;
    private User $itManager;
    private User $customer;
    private User $agent;
    private User $otherAgent;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float');
        Setting::set('max_commission_percent_per_order', 35.0, 'commission', 'float');
        Setting::set('commission_return_period_days', 7, 'commission', 'integer');
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');

        $this->walletService = app(WalletService::class);
        $this->payoutService = app(PayoutService::class);
        $this->commissionService = app(CommissionService::class);

        // Management roles
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'email' => 'accountant@auraluxe.lk',
        ]);

        $this->itManager = User::factory()->create([
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Primary Agent with full KYC and Bank Details
        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-WALLET-1',
        ]);

        AgentProfile::create([
            'user_id' => $this->agent->id,
            'business_name' => 'Aura Silk Care',
            'nic' => '199512345678',
            'bank_name' => 'Commercial Bank of Ceylon',
            'bank_branch' => 'Colombo Fort',
            'bank_account_number' => '8001234567',
            'bank_account_name' => 'Aura Silk Care Pvt Ltd',
            'kyc_status' => 'verified',
            'wallet_balance' => 25000.00,
            'reserved_balance' => 0.00,
            'total_earned' => 25000.00,
            'total_withdrawn' => 0.00,
        ]);

        // Prior wallet transaction backing the agent's initial 25000.00 balance
        $initTx = new WalletTransaction([
            'transaction_number' => 'WLT-INIT-0001',
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_CREDIT,
            'amount' => '25000.00',
            'balance_after' => '25000.00',
            'available_balance_after' => '25000.00',
            'currency' => 'LKR',
            'description' => 'Initial Commission Balance',
        ]);
        $initTx->created_at = Carbon::create(2024, 1, 1, 10, 0, 0);
        $initTx->updated_at = Carbon::create(2024, 1, 1, 10, 0, 0);
        $initTx->saveQuietly();

        // Secondary Agent
        $this->otherAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-OTHER-2',
        ]);

        AgentProfile::create([
            'user_id' => $this->otherAgent->id,
            'business_name' => 'Other Cosmetics',
            'nic' => '199698765432',
            'bank_name' => 'Sampath Bank',
            'bank_branch' => 'Kandy',
            'bank_account_number' => '1009876543',
            'bank_account_name' => 'Other Cosmetics',
            'kyc_status' => 'verified',
            'wallet_balance' => 10000.00,
            'reserved_balance' => 0.00,
            'total_earned' => 10000.00,
            'total_withdrawn' => 0.00,
        ]);
    }

    public function test_available_balance_calculation(): void
    {
        $profile = $this->agent->agentProfile;
        $this->assertEquals('25000.00', $profile->wallet_balance);
        $this->assertEquals('0.00', $profile->reserved_balance);
        $this->assertEquals('25000.00', $profile->available_balance);

        $profile->update(['reserved_balance' => '5000.00']);
        $profile->refresh();

        $this->assertEquals('20000.00', $profile->available_balance);
    }

    public function test_agent_wallet_index_renders_with_balances_and_summary(): void
    {
        $response = $this->actingAs($this->agent)->get(route('agent.wallet.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Agent/Wallet/Index')
            ->has('summary')
            ->where('summary.wallet_balance', fn ($v) => (float) $v == 25000.0)
            ->where('summary.reserved_balance', fn ($v) => (float) $v == 0.0)
            ->where('summary.available_balance', fn ($v) => (float) $v == 25000.0)
            ->has('payoutRequests')
            ->has('transactions')
        );
    }

    public function test_agent_can_submit_valid_payout_request(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-request'), [
            'amount' => 10000.00,
            'agent_notes' => 'Urgent transfer please',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->agent->agentProfile->refresh();

        // Wallet balance untouched, reserved balance increased, available balance decreased
        $this->assertEquals('25000.00', $this->agent->agentProfile->wallet_balance);
        $this->assertEquals('10000.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('15000.00', $this->agent->agentProfile->available_balance);

        // PayoutRequest row created
        $this->assertDatabaseHas('payout_requests', [
            'agent_id' => $this->agent->id,
            'amount' => '10000.00',
            'status' => PayoutRequest::STATUS_PENDING,
            'bank_name' => 'Commercial Bank of Ceylon',
            'bank_account_number' => '8001234567',
        ]);

        // Wallet transaction recorded for reservation (FIN-06-01: Gross balance remains 25000.00; available balance is 15000.00)
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_PAYOUT_RESERVED,
            'amount' => '-10000.00',
            'balance_after' => '25000.00',
            'available_balance_after' => '15000.00',
        ]);

        Notification::assertSentTo($this->agent, PayoutStatusNotification::class);
    }

    public function test_payout_request_exceeding_available_balance_is_rejected(): void
    {
        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-request'), [
            'amount' => 30000.00, // Available is only 25000.00
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->agent->agentProfile->refresh();
        $this->assertEquals('0.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals(0, PayoutRequest::count());
    }

    public function test_payout_request_without_verified_kyc_is_rejected(): void
    {
        $this->agent->agentProfile->update(['kyc_status' => 'pending']);

        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-request'), [
            'amount' => 5000.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, PayoutRequest::count());
    }

    public function test_payout_request_without_bank_details_is_rejected(): void
    {
        $this->agent->agentProfile->update(['bank_account_number' => null]);

        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-request'), [
            'amount' => 5000.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, PayoutRequest::count());
    }

    public function test_agent_can_cancel_own_pending_payout_request(): void
    {
        $payout = $this->payoutService->createPayoutRequest($this->agent, '8000.00');

        $this->agent->agentProfile->refresh();
        $this->assertEquals('8000.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('17000.00', $this->agent->agentProfile->available_balance);

        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-requests.cancel', $payout));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_CANCELLED, $payout->status);

        $this->agent->agentProfile->refresh();
        $this->assertEquals('0.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('25000.00', $this->agent->agentProfile->available_balance);

        // Released transaction recorded
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_PAYOUT_RELEASED,
            'amount' => '8000.00',
            'balance_after' => '25000.00',
        ]);
    }

    public function test_agent_cannot_cancel_approved_or_paid_payout(): void
    {
        $payout = $this->payoutService->createPayoutRequest($this->agent, '5000.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-requests.cancel', $payout));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $payout->status);
    }

    public function test_agent_cannot_cancel_another_agents_payout_request(): void
    {
        $payout = $this->payoutService->createPayoutRequest($this->otherAgent, '3000.00');

        $response = $this->actingAs($this->agent)->post(route('agent.wallet.payout-requests.cancel', $payout));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $payout->status);
    }

    public function test_owner_only_payout_approval(): void
    {
        Notification::fake();

        $payout = $this->payoutService->createPayoutRequest($this->agent, '6000.00');

        // Accountant attempts approval -> 403 Forbidden
        $response = $this->actingAs($this->accountant)->post(route('admin.payouts.approve', $payout), [
            'review_notes' => 'Attempt by accountant',
        ]);
        $response->assertStatus(403);

        // IT Manager attempts approval -> 403 Forbidden
        $response = $this->actingAs($this->itManager)->post(route('admin.payouts.approve', $payout), [
            'review_notes' => 'Attempt by IT manager',
        ]);
        $response->assertStatus(403);

        // Owner approves -> Success
        $response = $this->actingAs($this->owner)->post(route('admin.payouts.approve', $payout), [
            'review_notes' => 'Authorized by CEO',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $payout->status);
        $this->assertEquals($this->owner->id, $payout->reviewed_by);
        $this->assertNotNull($payout->reviewed_at);

        Notification::assertSentTo($this->agent, PayoutStatusNotification::class);
    }

    public function test_owner_only_payout_rejection_with_mandatory_reason(): void
    {
        Notification::fake();

        $payout = $this->payoutService->createPayoutRequest($this->agent, '7000.00');

        // Accountant attempts rejection -> 403 Forbidden
        $response = $this->actingAs($this->accountant)->post(route('admin.payouts.reject', $payout), [
            'rejection_reason' => 'Accountant rejection',
        ]);
        $response->assertStatus(403);

        // Owner attempts rejection without reason -> validation error
        $response = $this->actingAs($this->owner)->post(route('admin.payouts.reject', $payout), [
            'rejection_reason' => '',
        ]);
        $response->assertSessionHasErrors('rejection_reason');

        // Owner rejects with valid reason -> Success
        $response = $this->actingAs($this->owner)->post(route('admin.payouts.reject', $payout), [
            'rejection_reason' => 'Bank account holder mismatch with tax NIC',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_REJECTED, $payout->status);
        $this->assertEquals('Bank account holder mismatch with tax NIC', $payout->rejection_reason);
        $this->assertEquals($this->owner->id, $payout->reviewed_by);

        // Reserved balance released back
        $this->agent->agentProfile->refresh();
        $this->assertEquals('0.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('25000.00', $this->agent->agentProfile->available_balance);

        Notification::assertSentTo($this->agent, PayoutStatusNotification::class);
    }

    public function test_mark_processing_transitions_status(): void
    {
        $payout = $this->payoutService->createPayoutRequest($this->agent, '4000.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        // Accountant marks processing
        $response = $this->actingAs($this->accountant)->post(route('admin.payouts.process', $payout));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PROCESSING, $payout->status);
    }

    public function test_record_bank_transfer_payment_completes_payout(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();

        $payout = $this->payoutService->createPayoutRequest($this->agent, '12000.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        $receiptFile = UploadedFile::fake()->create('bank_receipt.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->accountant)->post(route('admin.payouts.record-payment', $payout), [
            'bank_transfer_reference' => 'SLIPS-2026-99384',
            'bank_transfer_notes' => 'Transferred via Commercial Bank Direct Online',
            'receipt' => $receiptFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payout->status);
        $this->assertEquals('SLIPS-2026-99384', $payout->bank_transfer_reference);
        $this->assertEquals($this->accountant->id, $payout->paid_by);
        $this->assertNotNull($payout->paid_at);
        $this->assertNotNull($payout->bank_transfer_receipt_path);

        // Assert file exists on private local disk (SEC-06-02)
        Storage::disk('local')->assertExists($payout->bank_transfer_receipt_path);

        // Balances decremented
        $this->agent->agentProfile->refresh();
        $this->assertEquals('13000.00', $this->agent->agentProfile->wallet_balance);
        $this->assertEquals('0.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('13000.00', $this->agent->agentProfile->available_balance);
        $this->assertEquals('12000.00', $this->agent->agentProfile->total_withdrawn);

        // Wallet transaction recorded
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_PAYOUT_COMPLETED,
            'amount' => '-12000.00',
            'balance_after' => '13000.00',
            'available_balance_after' => '13000.00',
        ]);

        Notification::assertSentTo($this->agent, PayoutStatusNotification::class);
    }

    public function test_duplicate_bank_transfer_reference_is_rejected(): void
    {
        $payout1 = $this->payoutService->createPayoutRequest($this->agent, '2000.00');
        $this->payoutService->approvePayoutRequest($payout1, $this->owner);
        $this->payoutService->recordPayment($payout1, $this->accountant, [
            'bank_transfer_reference' => 'DUPLICATE-REF-100',
        ]);

        $payout2 = $this->payoutService->createPayoutRequest($this->agent, '3000.00');
        $this->payoutService->approvePayoutRequest($payout2, $this->owner);

        $response = $this->actingAs($this->accountant)->post(route('admin.payouts.record-payment', $payout2), [
            'bank_transfer_reference' => 'DUPLICATE-REF-100',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $payout2->refresh();
        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $payout2->status);
    }

    public function test_monthly_statement_calculation_and_rendering(): void
    {
        // Record 1 credit and 1 payout for this month
        WalletTransaction::create([
            'transaction_number' => 'WLT-2026-0001',
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_CREDIT,
            'amount' => '15000.00',
            'balance_after' => '40000.00',
            'currency' => 'LKR',
            'description' => 'Direct Commission on Order #100',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->agent)->get(route('agent.wallet.statements', [
            'year' => now()->year,
            'month' => now()->month,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Agent/Wallet/Statements')
            ->has('statement')
            ->where('statement.agent.name', $this->agent->name)
            ->where('statement.total_credited', fn ($v) => (float) $v == 15000.0)
        );
    }

    public function test_agent_cannot_access_other_agent_statement(): void
    {
        // Statement route only pulls the authenticated agent's own data
        $response = $this->actingAs($this->agent)->get(route('agent.wallet.statements', [
            'year' => now()->year,
            'month' => now()->month,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('statement.agent.id', $this->agent->id)
        );
    }

    public function test_unauthorized_roles_blocked_from_agent_and_admin_payout_routes(): void
    {
        // Customer blocked from agent wallet
        $this->actingAs($this->customer)->get(route('agent.wallet.index'))->assertStatus(403);
        $this->actingAs($this->customer)->post(route('agent.wallet.payout-request'), ['amount' => 100])->assertStatus(403);

        // Agent blocked from admin payout queue and dashboard
        $this->actingAs($this->agent)->get(route('admin.payouts.dashboard'))->assertStatus(403);
        $this->actingAs($this->agent)->get(route('admin.payouts.index'))->assertStatus(403);
    }

    public function test_admin_payouts_dashboard_and_index_views(): void
    {
        $this->payoutService->createPayoutRequest($this->agent, '5000.00');

        $dashResponse = $this->actingAs($this->owner)->get(route('admin.payouts.dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts/Dashboard')
            ->has('metrics')
            ->has('recentPayouts')
        );

        $indexResponse = $this->actingAs($this->owner)->get(route('admin.payouts.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts/Index')
            ->has('payouts')
            ->has('counts')
            ->where('counts.pending', 1)
        );
    }

    public function test_admin_payouts_show_view(): void
    {
        $payout = $this->payoutService->createPayoutRequest($this->agent, '5000.00');

        $response = $this->actingAs($this->owner)->get(route('admin.payouts.show', $payout));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts/Show')
            ->has('payout')
            ->where('payout.id', $payout->id)
        );
    }

    public function test_concurrent_payout_requests_cannot_overdraw_balance(): void
    {
        // Available balance is 25000.00
        // First payout request for 20000.00 succeeds
        $payout1 = $this->payoutService->createPayoutRequest($this->agent, '20000.00');
        $this->assertEquals('5000.00', $this->agent->agentProfile->fresh()->available_balance);

        // Second payout request for 10000.00 must fail because only 5000.00 remains available
        $this->expectException(\InvalidArgumentException::class);
        $this->payoutService->createPayoutRequest($this->agent, '10000.00');
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
            'status' => 'delivered',
            'placed_at' => now(),
        ], $attributes));
    }

    public function test_commission_maturation_credits_wallet_and_records_wallet_transaction(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-MATURE-TEST',
            'agent_id' => $this->agent->id,
            'subtotal' => 10000.00,
            'total_amount' => 10350.00,
            'status' => 'completed',
            'placed_at' => now()->subDays(10),
        ]);

        $ledgerEntry = CommissionLedgerEntry::create([
            'entry_number' => CommissionLedgerEntry::generateEntryNumber(),
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'direct_high_level',
            'calculation_base' => '10000.00',
            'base_amount' => '10000.00',
            'rate_percentage' => '25.00',
            'calculated_amount' => '2500.00',
            'currency' => 'LKR',
            'status' => CommissionLedgerEntry::STATUS_PENDING,
            'notes' => 'Test pending commission',
            'created_at' => now()->subDays(10), // Passed return window
        ]);

        $initialWallet = (float) $this->agent->agentProfile->fresh()->wallet_balance;

        // Process eligible commissions
        $count = $this->commissionService->approveEligibleCommissions();
        $this->assertGreaterThanOrEqual(1, $count);

        $ledgerEntry->refresh();
        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $ledgerEntry->status);

        $newWallet = (float) $this->agent->agentProfile->fresh()->wallet_balance;
        $this->assertEquals($initialWallet + 2500.00, $newWallet);

        // WalletTransaction recorded
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_CREDIT,
            'amount' => '2500.00',
            'source_type' => CommissionLedgerEntry::class,
            'source_id' => $ledgerEntry->id,
        ]);
    }

    public function test_commission_reversal_debits_wallet_and_records_wallet_transaction(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-REV-TEST',
            'agent_id' => $this->agent->id,
            'subtotal' => 4000.00,
            'total_amount' => 4350.00,
            'status' => 'delivered',
        ]);

        $ledgerEntry = CommissionLedgerEntry::create([
            'entry_number' => CommissionLedgerEntry::generateEntryNumber(),
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'direct_high_level',
            'calculation_base' => '4000.00',
            'base_amount' => '4000.00',
            'rate_percentage' => '25.00',
            'calculated_amount' => '1000.00',
            'currency' => 'LKR',
            'status' => CommissionLedgerEntry::STATUS_APPROVED,
            'approved_at' => now()->subDay(),
            'notes' => 'Test approved commission for reversal',
        ]);

        $initialWallet = (float) $this->agent->agentProfile->fresh()->wallet_balance;

        // Reverse commissions on the order
        $this->commissionService->reverseOrderCommissions($order, 'Customer returned defective goods');

        $ledgerEntry->refresh();
        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $ledgerEntry->status);
        $this->assertNull($ledgerEntry->reversed_at);
        $this->assertDatabaseHas('commission_ledger_entries', [
            'original_entry_id' => $ledgerEntry->id,
            'commission_type' => CommissionLedgerEntry::TYPE_REVERSAL,
            'calculated_amount' => '-1000.00',
        ]);

        $newWallet = (float) $this->agent->agentProfile->fresh()->wallet_balance;
        $this->assertEquals($initialWallet - 1000.00, $newWallet);

        // Reversal wallet transaction recorded
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_REVERSAL,
            'amount' => '-1000.00',
        ]);
    }

    public function test_full_lifecycle_ledger_reconciliation(): void
    {
        // 1. Initial balance is 25000.00
        $agent = $this->agent;

        // 2. Submit payout for 10000.00
        $payout = $this->payoutService->createPayoutRequest($agent, '10000.00');

        // 3. Approve payout
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        // 4. Record payment for 10000.00
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'RECON-SLIPS-001',
        ]);

        $agent->agentProfile->refresh();
        $this->assertEquals('15000.00', $agent->agentProfile->wallet_balance);
        $this->assertEquals('0.00', $agent->agentProfile->reserved_balance);
        $this->assertEquals('15000.00', $agent->agentProfile->available_balance);
        $this->assertEquals('10000.00', $agent->agentProfile->total_withdrawn);

        // 5. Submit another payout for 5000.00 and cancel it
        $payout2 = $this->payoutService->createPayoutRequest($agent, '5000.00');
        $this->assertEquals('10000.00', $agent->agentProfile->fresh()->available_balance);
        $this->payoutService->cancelPayoutRequest($payout2, $agent);
        $this->assertEquals('15000.00', $agent->agentProfile->fresh()->available_balance);

        // 6. Verify audit logs exist
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout_requested',
            'auditable_type' => PayoutRequest::class,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout_approved',
            'auditable_type' => PayoutRequest::class,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout_paid',
            'auditable_type' => PayoutRequest::class,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout_cancelled',
            'auditable_type' => PayoutRequest::class,
        ]);
    }

    /**
     * FIN-06-01: Active reservation at month-end does not distort gross closing balance
     * Formula: Opening Gross + Credits - Reversals - Completed Payouts = Closing Gross
     */
    public function test_fin_06_01_statement_reconciles_active_reservation_at_month_end(): void
    {
        $agent = $this->agent;
        // Initial gross balance: 25000.00, available: 25000.00, reserved: 0.00
        
        // Agent requests payout of 7000.00
        $payout = $this->payoutService->createPayoutRequest($agent, '7000.00');

        $statement = $this->walletService->getStatement($agent, now()->year, now()->month);

        // Statement Gross Balance MUST remain 25000.00 because reserved is NOT an expense until paid!
        $this->assertEquals('25000.00', $statement['opening_gross_balance']);
        $this->assertEquals('25000.00', $statement['closing_gross_balance']);
        $this->assertEquals('0.00', $statement['net_change_gross']);

        // Escrow breakdown must reflect the reservation
        $this->assertEquals('0.00', $statement['opening_reserved_balance']);
        $this->assertEquals('7000.00', $statement['new_reservations']);
        $this->assertEquals('7000.00', $statement['closing_reserved_balance']);

        // Available balance must reflect spendable position
        $this->assertEquals('25000.00', $statement['opening_available_balance']);
        $this->assertEquals('18000.00', $statement['closing_available_balance']);
    }

    /**
     * FIN-06-01: Reservation and release in the same month preserves gross balance
     */
    public function test_fin_06_01_statement_reconciles_reservation_and_release_in_same_month(): void
    {
        $agent = $this->agent;
        $payout = $this->payoutService->createPayoutRequest($agent, '5000.00');
        $this->payoutService->cancelPayoutRequest($payout, $agent);

        $statement = $this->walletService->getStatement($agent, now()->year, now()->month);

        $this->assertEquals('25000.00', $statement['opening_gross_balance']);
        $this->assertEquals('25000.00', $statement['closing_gross_balance']);
        $this->assertEquals('5000.00', $statement['new_reservations']);
        $this->assertEquals('5000.00', $statement['released_reservations']);
        $this->assertEquals('0.00', $statement['closing_reserved_balance']);
        $this->assertEquals('25000.00', $statement['closing_available_balance']);
    }

    /**
     * FIN-06-01: Reservation in month 1 and completed payment in month 2 reconciles seamlessly
     */
    public function test_fin_06_01_statement_reconciles_across_month_boundary_reservation_and_payment(): void
    {
        $agent = $this->agent;

        // Simulate reservation in January 2026
        \Illuminate\Support\Carbon::setTestNow('2026-01-15 10:00:00');
        $payout = $this->payoutService->createPayoutRequest($agent, '10000.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        $januaryStatement = $this->walletService->getStatement($agent, 2026, 1);
        $this->assertEquals('25000.00', $januaryStatement['opening_gross_balance']);
        $this->assertEquals('25000.00', $januaryStatement['closing_gross_balance']);
        $this->assertEquals('10000.00', $januaryStatement['closing_reserved_balance']);
        $this->assertEquals('15000.00', $januaryStatement['closing_available_balance']);

        // Simulate payment in February 2026
        \Illuminate\Support\Carbon::setTestNow('2026-02-05 14:00:00');
        $this->payoutService->markProcessing($payout, $this->accountant);
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'FEB-TXN-999',
        ]);

        $februaryStatement = $this->walletService->getStatement($agent, 2026, 2);
        // Opening gross in February equals January closing gross (25000.00)
        $this->assertEquals('25000.00', $februaryStatement['opening_gross_balance']);
        $this->assertEquals('10000.00', $februaryStatement['completed_payouts']);
        // Closing gross is exactly 25000.00 - 10000.00 = 15000.00
        $this->assertEquals('15000.00', $februaryStatement['closing_gross_balance']);
        $this->assertEquals('0.00', $februaryStatement['closing_reserved_balance']);
        $this->assertEquals('15000.00', $februaryStatement['closing_available_balance']);

        \Illuminate\Support\Carbon::setTestNow();
    }

    /**
     * FIN-06-01: Empty statement month preserves balance
     */
    public function test_fin_06_01_empty_statement_month_preserves_balance(): void
    {
        $agent = $this->agent;

        // Query statement for June 2025 where no transactions occurred (initial balance was in Jan 2025)
        $statement = $this->walletService->getStatement($agent, 2025, 6);

        $this->assertEquals('25000.00', $statement['opening_gross_balance']);
        $this->assertEquals('25000.00', $statement['closing_gross_balance']);
        $this->assertEquals('0.00', $statement['net_change_gross']);
        $this->assertEmpty($statement['transactions']);
    }

    /**
     * SEC-06-01: Database-level unique constraint on bank_transfer_reference
     */
    public function test_sec_06_01_database_level_unique_constraint_on_bank_transfer_reference(): void
    {
        $payout1 = $this->payoutService->createPayoutRequest($this->agent, '5000.00');
        $this->payoutService->approvePayoutRequest($payout1, $this->owner);
        $this->payoutService->recordPayment($payout1, $this->accountant, [
            'bank_transfer_reference' => 'UNIQUE-REF-001',
        ]);

        $payout2 = $this->payoutService->createPayoutRequest($this->agent, '5000.00');
        $this->payoutService->approvePayoutRequest($payout2, $this->owner);

        // Case-insensitive / normalized duplicate must be rejected by service layer
        $this->expectException(\InvalidArgumentException::class);
        $this->payoutService->recordPayment($payout2, $this->accountant, [
            'bank_transfer_reference' => '  unique-ref-001  ',
        ]);
    }

    /**
     * SEC-06-02: Private storage and authenticated receipt download access control
     */
    public function test_sec_06_02_private_storage_and_authenticated_receipt_downloads(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $payout = $this->payoutService->createPayoutRequest($this->agent, '6000.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);

        $receipt = \Illuminate\Http\UploadedFile::fake()->create('private_bank_slip.pdf', 200, 'application/pdf');

        $this->actingAs($this->accountant)->post(route('admin.payouts.record-payment', $payout), [
            'bank_transfer_reference' => 'SLIP-SEC-002',
            'receipt' => $receipt,
        ])->assertRedirect();

        $payout->refresh();
        $this->assertNotNull($payout->bank_transfer_receipt_path);

        // 1. Owner can download via admin route
        $this->actingAs($this->owner)
            ->get(route('admin.payouts.receipt', $payout))
            ->assertOk();

        // 2. Accountant can download via admin route
        $this->actingAs($this->accountant)
            ->get(route('admin.payouts.receipt', $payout))
            ->assertOk();

        // 3. Beneficiary agent can download via agent route
        $this->actingAs($this->agent)
            ->get(route('agent.wallet.payout-requests.receipt', $payout))
            ->assertOk();

        // 4. Beneficiary agent CANNOT access admin receipt route
        $this->actingAs($this->agent)
            ->get(route('admin.payouts.receipt', $payout))
            ->assertStatus(403);

        // 5. Another agent CANNOT download via agent route
        $otherAgent = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        AgentProfile::create([
            'user_id' => $otherAgent->id,
            'agent_code' => 'AGT-OTHER-001',
            'wallet_balance' => 10000.00,
            'reserved_balance' => 0.00,
            'tier' => 'low',
        ]);

        $this->actingAs($otherAgent)
            ->get(route('agent.wallet.payout-requests.receipt', $payout))
            ->assertStatus(403);

        // 6. Unauthenticated visitor is redirected to login
        \Illuminate\Support\Facades\Auth::logout();
        $this->get(route('admin.payouts.receipt', $payout))->assertRedirect(route('login'));
        $this->get(route('agent.wallet.payout-requests.receipt', $payout))->assertRedirect(route('login'));
    }

    /**
     * OPS-06-01: Failed payout state transition releases reserved funds safely and logs reason
     */
    public function test_ops_06_01_payout_failure_releases_reserved_balance_and_records_audit(): void
    {
        Notification::fake();

        $payout = $this->payoutService->createPayoutRequest($this->agent, '8000.00');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);
        $this->payoutService->markProcessing($payout, $this->accountant);

        $this->agent->agentProfile->refresh();
        $this->assertEquals('8000.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('17000.00', $this->agent->agentProfile->available_balance);

        // Accountant marks bank transfer as failed
        $response = $this->actingAs($this->accountant)->post(route('admin.payouts.fail', $payout), [
            'failure_reason' => 'Invalid beneficiary bank account number',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_FAILED, $payout->status);
        $this->assertEquals('Invalid beneficiary bank account number', $payout->failure_reason);
        $this->assertEquals($this->accountant->id, $payout->failed_by);
        $this->assertNotNull($payout->failed_at);

        // Reserved balance must be released back to available balance
        $this->agent->agentProfile->refresh();
        $this->assertEquals('25000.00', $this->agent->agentProfile->wallet_balance);
        $this->assertEquals('0.00', $this->agent->agentProfile->reserved_balance);
        $this->assertEquals('25000.00', $this->agent->agentProfile->available_balance);

        // Wallet transaction logged
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_PAYOUT_RELEASED,
            'amount' => '8000.00',
            'balance_after' => '25000.00',
            'available_balance_after' => '25000.00',
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout_failed',
            'auditable_type' => PayoutRequest::class,
            'auditable_id' => $payout->id,
        ]);

        // Payout cannot be failed a second time
        $this->expectException(\InvalidArgumentException::class);
        $this->payoutService->failPayoutRequest($payout, $this->accountant, 'Trying again');
    }

    /**
     * SEC-06-03: Sensitive payout routes restricted from unauthorized management roles
     */
    public function test_sec_06_03_sensitive_payout_routes_restricted_from_other_management_roles(): void
    {
        $marketingManager = User::factory()->create(['role' => UserRole::MARKETING_MANAGER, 'status' => UserStatus::ACTIVE]);
        $itManager = User::factory()->create(['role' => UserRole::IT_MANAGER, 'status' => UserStatus::ACTIVE]);

        // Marketing Manager blocked
        $this->actingAs($marketingManager)->get(route('admin.payouts.dashboard'))->assertStatus(403);
        $this->actingAs($marketingManager)->get(route('admin.payouts.index'))->assertStatus(403);

        // IT Manager blocked
        $this->actingAs($itManager)->get(route('admin.payouts.dashboard'))->assertStatus(403);
        $this->actingAs($itManager)->get(route('admin.payouts.index'))->assertStatus(403);

        // Accountant and Owner permitted
        $this->actingAs($this->accountant)->get(route('admin.payouts.dashboard'))->assertStatus(200);
        $this->actingAs($this->owner)->get(route('admin.payouts.dashboard'))->assertStatus(200);
    }

    /**
     * FIN-06-02: BCMath exact precision without floating point drift
     */
    public function test_fin_06_02_bcmath_precision_without_float_drift(): void
    {
        $agent = $this->agent;
        // Start from 25000.00
        $payout = $this->payoutService->createPayoutRequest($agent, '1000.07');
        $this->payoutService->approvePayoutRequest($payout, $this->owner);
        $this->payoutService->recordPayment($payout, $this->accountant, [
            'bank_transfer_reference' => 'BCMATH-PRECISE-001',
        ]);

        $agent->agentProfile->refresh();
        // 25000.00 - 1000.07 = 23999.93 exactly
        $this->assertSame('23999.93', $agent->agentProfile->wallet_balance);
        $this->assertSame('23999.93', $agent->agentProfile->available_balance);
        $this->assertSame('0.00', $agent->agentProfile->reserved_balance);
        $this->assertSame('1000.07', $agent->agentProfile->total_withdrawn);

        // Ensure wallet transaction amount is exact
        $this->assertDatabaseHas('wallet_transactions', [
            'agent_id' => $agent->id,
            'transaction_type' => WalletTransaction::TYPE_PAYOUT_COMPLETED,
            'amount' => '-1000.07',
            'balance_after' => '23999.93',
            'available_balance_after' => '23999.93',
        ]);
    }
}
