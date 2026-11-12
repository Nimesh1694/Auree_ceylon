<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Category;
use App\Models\CommissionLedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AgentHierarchyService;
use App\Services\CommissionService;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndToEndSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private CommissionService $commissionService;
    private PayoutService $payoutService;
    private WalletService $walletService;

    private User $owner;
    private User $accountant;
    private User $uplineAgent;
    private User $sellingAgent;
    private User $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchyService = app(AgentHierarchyService::class);
        $this->commissionService = app(CommissionService::class);
        $this->payoutService = app(PayoutService::class);
        $this->walletService = app(WalletService::class);

        // Settings
        Setting::set('commission_return_period_days', 7);
        Setting::set('return_window_days', 7);
        Setting::set('default_delivery_charge', 350.00);
        Setting::set('direct_commission_rate', 25.0);
        Setting::set('override_commission_rate', 10.0);

        // Management Owner (with enrolled 2FA)
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['REC-1111', 'REC-2222'],
        ]);

        // Management Accountant (with enrolled 2FA)
        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'email' => 'accountant@auraluxe.lk',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['REC-3333', 'REC-4444'],
        ]);

        // Upline High-Level Agent
        $this->uplineAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-UPLINE-01',
        ]);
        AgentProfile::create([
            'user_id' => $this->uplineAgent->id,
            'business_name' => 'Upline Beauty',
            'nic' => '199011112222',
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1001001001',
            'bank_account_name' => 'Upline Beauty Pvt Ltd',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'reserved_balance' => 0.00,
        ]);
        $this->hierarchyService->attachAgent($this->uplineAgent);

        // Selling Low-Level Agent (sponsored by upline)
        $this->sellingAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-SELLER-02',
            'parent_id' => $this->uplineAgent->id,
        ]);
        AgentProfile::create([
            'user_id' => $this->sellingAgent->id,
            'business_name' => 'Seller Cosmetics',
            'nic' => '199533334444',
            'bank_name' => 'Sampath Bank',
            'bank_account_number' => '2002002002',
            'bank_account_name' => 'Seller Cosmetics Store',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'reserved_balance' => 0.00,
        ]);
        $this->hierarchyService->attachAgent($this->sellingAgent, $this->uplineAgent);

        // Customer
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Product with stock
        $category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Aura Night Radiance Serum',
            'name_si' => 'ඕරා නයිට් සීරම්',
            'slug' => 'aura-night-radiance-serum',
            'sku' => 'AUR-SRM-001',
            'price' => 10000.00,
            'cost_price' => 4000.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    /**
     * Complete End-to-End Customer Storefront to Agent Payout & Statement Lifecycle
     */
    public function test_complete_e2e_customer_to_agent_payout_and_statement_lifecycle(): void
    {
        Storage::fake('local');

        // =========================================================================
        // STAGE 1: Storefront Visit & Referral Capture
        // =========================================================================
        $this->get('/?ref=' . $this->sellingAgent->referral_code)
            ->assertOk()
            ->assertSessionHas('referral_code', $this->sellingAgent->referral_code);

        // =========================================================================
        // STAGE 2: Product Detail & Server-Side Price / Stock Validation
        // =========================================================================
        $this->get('/products/' . $this->product->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.price', '10000.00'));

        // =========================================================================
        // STAGE 3: Customer Places COD Order
        // =========================================================================
        $order = Order::create([
            'order_number' => 'ORD-E2E-' . uniqid(),
            'tracking_token' => \Illuminate\Support\Str::random(32),
            'customer_name' => 'Kumari Perera',
            'customer_email' => 'kumari@gmail.com',
            'customer_phone' => '0771234567',
            'shipping_address' => '45 Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 20000.00, // 2 items x 10,000
            'delivery_charge' => 350.00,
            'total_amount' => 20350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'referral_code' => $this->sellingAgent->referral_code,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => $this->product->sku,
            'unit_price' => 10000.00,
            'quantity' => 2,
            'subtotal' => 20000.00,
        ]);

        // =========================================================================
        // STAGE 4: Pending Commissions Calculated on Order Placement
        // =========================================================================
        $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Selling Agent gets 25% of 20,000 subtotal = 5,000.00 (Pending)
        $sellerEntry = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('agent_id', $this->sellingAgent->id)
            ->first();
        $this->assertNotNull($sellerEntry);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $sellerEntry->status);
        $this->assertEquals('5000.00', $sellerEntry->calculated_amount);

        // Upline Agent gets 10% override of 20,000 subtotal = 2,000.00 (Pending)
        $uplineEntry = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('agent_id', $this->uplineAgent->id)
            ->first();
        $this->assertNotNull($uplineEntry);
        $this->assertEquals(CommissionLedgerEntry::STATUS_PENDING, $uplineEntry->status);
        $this->assertEquals('2000.00', $uplineEntry->calculated_amount);

        // Wallet balances must remain 0.00 while pending
        $this->assertEquals('0.00', $this->sellingAgent->agentProfile->fresh()->wallet_balance);
        $this->assertEquals('0.00', $this->uplineAgent->agentProfile->fresh()->wallet_balance);

        // =========================================================================
        // STAGE 5: Order Lifecycle Progression (Placed -> Processing -> Shipped -> Delivered)
        // =========================================================================
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'shipped']);
        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()->subDays(8),
            'cod_collected_amount' => 20350.00,
            'cod_reconciled' => true,
            'cod_reconciled_at' => now()->subDays(8),
        ]);

        \App\Models\Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Pronto Courier',
            'tracking_number' => 'PRN-998877',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(8),
        ]);

        \App\Models\CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 20350.00,
            'collected_amount' => 20350.00,
            'reconciliation_status' => \App\Models\CodCollection::STATUS_MATCHED,
            'reconciled_by' => $this->accountant->id,
            'collected_at' => now()->subDays(8),
        ]);

        // =========================================================================
        // STAGE 6: Automated Commission Maturation Command
        // =========================================================================
        $this->artisan('commissions:process-eligible')
            ->assertSuccessful();

        $sellerEntry->refresh();
        $uplineEntry->refresh();
        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $sellerEntry->status);
        $this->assertEquals(CommissionLedgerEntry::STATUS_APPROVED, $uplineEntry->status);

        // Wallets are credited with exact decimal amounts
        $this->sellingAgent->agentProfile->refresh();
        $this->uplineAgent->agentProfile->refresh();
        $this->assertSame('5000.00', $this->sellingAgent->agentProfile->wallet_balance);
        $this->assertSame('5000.00', $this->sellingAgent->agentProfile->available_balance);
        $this->assertSame('2000.00', $this->uplineAgent->agentProfile->wallet_balance);
        $this->assertSame('2000.00', $this->uplineAgent->agentProfile->available_balance);

        // =========================================================================
        // STAGE 7: Selling Agent Submits Payout Request (Escrow Hold)
        // =========================================================================
        $payout = $this->payoutService->createPayoutRequest($this->sellingAgent, '3000.00');

        $this->sellingAgent->agentProfile->refresh();
        // Gross balance remains 5000.00, reserved is 3000.00, available is 2000.00
        $this->assertSame('5000.00', $this->sellingAgent->agentProfile->wallet_balance);
        $this->assertSame('3000.00', $this->sellingAgent->agentProfile->reserved_balance);
        $this->assertSame('2000.00', $this->sellingAgent->agentProfile->available_balance);

        // =========================================================================
        // STAGE 8: Owner Approves Payout
        // =========================================================================
        $this->payoutService->approvePayoutRequest($payout, $this->owner);
        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $payout->status);

        // =========================================================================
        // STAGE 9: Accountant Records Bank Transfer with Receipt to Private Storage
        // Management session has completed mandatory 2FA (Phase 7)
        // =========================================================================
        $this->payoutService->markProcessing($payout, $this->accountant);

        $fakeSlip = UploadedFile::fake()->create('bank_transfer_slip.pdf', 150, 'application/pdf');

        $this->actingAs($this->accountant);
        session(['two_factor_authenticated' => true]);

        $response = $this->post(route('admin.payouts.record-payment', $payout), [
            'bank_transfer_reference' => 'E2E-BANK-REF-777',
            'receipt' => $fakeSlip,
            'bank_transfer_notes' => 'Settled via Commercial Bank Online Batch',
        ]);

        $response->assertRedirect();
        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payout->status);
        $this->assertEquals('E2E-BANK-REF-777', $payout->bank_transfer_reference);
        $this->assertNotNull($payout->bank_transfer_receipt_path);

        // Receipt is in private storage
        Storage::disk('local')->assertExists($payout->bank_transfer_receipt_path);

        // Selling Agent Wallet adjusted
        $this->sellingAgent->agentProfile->refresh();
        $this->assertSame('2000.00', $this->sellingAgent->agentProfile->wallet_balance);
        $this->assertSame('0.00', $this->sellingAgent->agentProfile->reserved_balance);
        $this->assertSame('2000.00', $this->sellingAgent->agentProfile->available_balance);
        $this->assertSame('3000.00', $this->sellingAgent->agentProfile->total_withdrawn);

        // =========================================================================
        // STAGE 10: Private Receipt Access Verification
        // =========================================================================
        // Owner and Accountant can download (with active 2FA verified session)
        $this->actingAs($this->owner);
        session(['two_factor_authenticated' => true]);
        $this->get(route('admin.payouts.receipt', $payout))->assertOk();

        $this->actingAs($this->accountant);
        session(['two_factor_authenticated' => true]);
        $this->get(route('admin.payouts.receipt', $payout))->assertOk();

        // Selling Agent can download via portal
        $this->actingAs($this->sellingAgent)->get(route('agent.wallet.payout-requests.receipt', $payout))->assertOk();

        // Upline Agent CANNOT download selling agent's receipt (403 Forbidden)
        $this->actingAs($this->uplineAgent)->get(route('agent.wallet.payout-requests.receipt', $payout))->assertStatus(403);

        // =========================================================================
        // STAGE 11: Monthly Financial Statement Dual-Track Reconciliation
        // =========================================================================
        $statement = $this->walletService->getStatement($this->sellingAgent, now()->year, now()->month);

        // 1. Gross Commission Reconciliation:
        // Opening Gross (0.00) + Approved Credits (5000.00) - Completed Payouts (3000.00) = Closing Gross (2000.00)
        $this->assertEquals('0.00', $statement['opening_gross_balance']);
        $this->assertEquals('5000.00', $statement['total_credited']);
        $this->assertEquals('3000.00', $statement['total_paid_out']);
        $this->assertEquals('2000.00', $statement['closing_gross_balance']);

        // 2. Escrow Reconciliation:
        // Opening Reserved (0.00) + New Reservations (3000.00) - Completed Payouts (3000.00) = Closing Reserved (0.00)
        $this->assertEquals('0.00', $statement['opening_reserved_balance']);
        $this->assertEquals('3000.00', $statement['new_reservations']);
        $this->assertEquals('0.00', $statement['closing_reserved_balance']);

        // 3. Available Position:
        // Closing Available = 2000.00 - 0.00 = 2000.00
        $this->assertEquals('2000.00', $statement['closing_available_balance']);
    }
}
