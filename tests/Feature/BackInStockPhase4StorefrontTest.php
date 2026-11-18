<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\BackInStockAlertMail;
use App\Mail\BackInStockVerificationMail;
use App\Models\BackInStockDelivery;
use App\Models\BackInStockSubscription;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestockEvent;
use App\Models\RestockEventClaim;
use App\Models\SubscriptionActiveLease;
use App\Models\User;
use App\Services\BackInStockService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackInStockPhase4StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private BackInStockService $service;
    private StockService $stockService;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BackInStockService::class);
        $this->stockService = app(StockService::class);

        $this->category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සත්කාරක',
            'slug' => 'skincare',
            'is_active' => true,
        ]);
    }

    private function createProduct(array $attributes = []): Product
    {
        static $seq = 100;
        $seq++;

        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name_en' => "Test Serum {$seq}",
            'name_si' => "ටෙස්ට් සීරම් {$seq}",
            'slug' => "test-serum-{$seq}",
            'sku' => "SKU-P4-{$seq}",
            'price' => 3500.00,
            'cost_price' => 1500.00,
            'stock_quantity' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function createCustomer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ], $attributes));
    }

    // ==========================================
    // A. STOREFRONT SUBSCRIPTION CAPTURE TESTS
    // ==========================================

    public function test_out_of_stock_active_product_accepts_guest_subscription(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock_quantity' => 0, 'is_active' => true]);

        $response = $this->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
            'email' => 'guest@aura.lux',
            'locale' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'verification_sent',
            'requires_verification' => true,
        ]);

        $subscription = BackInStockSubscription::where('product_id', $product->id)
            ->where('normalized_email', 'guest@aura.lux')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING_VERIFICATION, $subscription->status);
        $this->assertNull($subscription->is_active);
        $this->assertNotNull($subscription->verification_token_hash);
        $this->assertNotNull($subscription->verification_expires_at);

        // Verification email was queued
        Mail::assertQueued(BackInStockVerificationMail::class, function ($mail) {
            return $mail->hasTo('guest@aura.lux');
        });
    }

    public function test_in_stock_product_rejects_new_alert_subscription(): void
    {
        $product = $this->createProduct(['stock_quantity' => 15, 'is_active' => true]);

        $response = $this->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
            'email' => 'guest@aura.lux',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'status' => 'ineligible_product',
        ]);

        $this->assertDatabaseMissing('back_in_stock_subscriptions', [
            'product_id' => $product->id,
        ]);
    }

    public function test_inactive_product_rejects_subscription(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0, 'is_active' => false]);

        $response = $this->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
            'email' => 'guest@aura.lux',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'status' => 'ineligible_product',
        ]);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        $response = $this->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_duplicate_subscription_is_handled_safely(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        // Customer already subscribed
        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'dup@aura.lux',
            'normalized_email' => 'dup@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
        ]);

        // Another guest submits same email
        $response = $this->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
            'email' => 'dup@aura.lux',
        ]);

        // Generic safe response
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'verification_sent',
        ]);

        // Exactly one subscription remains in database
        $this->assertEquals(1, BackInStockSubscription::where('product_id', $product->id)->count());
    }

    public function test_verified_authenticated_customer_subscribes_directly_without_doi(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);
        $customer = $this->createCustomer(['email' => 'verified.customer@aura.lux']);

        $response = $this->actingAs($customer)->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'subscribed',
            'requires_verification' => false,
        ]);

        $sub = BackInStockSubscription::where('product_id', $product->id)
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($sub);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->status);
        $this->assertTrue($sub->is_active);
        $this->assertNotNull($sub->verified_at);
        $this->assertNull($sub->verification_token_hash);

        // No guest DOI verification email sent!
        Mail::assertNothingQueued();
    }

    public function test_unverified_authenticated_customer_cannot_bypass_verification(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);
        $unverifiedCustomer = $this->createCustomer(['email_verified_at' => null]);

        $response = $this->actingAs($unverifiedCustomer)->postJson(route('back-in-stock.subscribe'), [
            'product_id' => $product->id,
            'email' => 'unverified@aura.lux',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'requires_verification' => true,
        ]);

        $sub = BackInStockSubscription::where('product_id', $product->id)
            ->where('normalized_email', 'unverified@aura.lux')
            ->first();

        $this->assertEquals(BackInStockSubscription::STATUS_PENDING_VERIFICATION, $sub->status);
        $this->assertNull($sub->is_active);
    }

    // ==========================================
    // B. DOUBLE OPT-IN (DOI) VERIFICATION TESTS
    // ==========================================

    public function test_valid_token_displays_confirmation_page_without_activating_on_get(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);
        $rawToken = bin2hex(random_bytes(32));

        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'doi@aura.lux',
            'normalized_email' => 'doi@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
            'is_active' => null,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
            'verification_token_hash' => hash('sha256', $rawToken),
            'verification_expires_at' => now()->addHours(24),
        ]);

        // GET request (anti-scanner safe)
        $response = $this->get(route('back-in-stock.verify', ['token' => $rawToken]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('BackInStock/Verify')
            ->where('status', 'confirm_prompt')
            ->where('token', $rawToken)
        );

        // Subscription remains pending_verification and inactive!
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING_VERIFICATION, $sub->fresh()->status);
        $this->assertNull($sub->fresh()->is_active);
    }

    public function test_valid_post_activates_subscription(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);
        $rawToken = bin2hex(random_bytes(32));

        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'doi@aura.lux',
            'normalized_email' => 'doi@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
            'is_active' => null,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
            'verification_token_hash' => hash('sha256', $rawToken),
            'verification_expires_at' => now()->addHours(24),
        ]);

        // POST confirmation execution
        $response = $this->postJson(route('back-in-stock.verify.confirm'), [
            'token' => $rawToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'already_verified' => false,
        ]);

        $sub->refresh();
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->status);
        $this->assertTrue($sub->is_active);
        $this->assertNotNull($sub->verified_at);
        $this->assertNull($sub->verification_token_hash); // Token purged!
    }

    public function test_expired_token_is_rejected(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);
        $rawToken = bin2hex(random_bytes(32));

        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'doi@aura.lux',
            'normalized_email' => 'doi@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
            'is_active' => null,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
            'verification_token_hash' => hash('sha256', $rawToken),
            'verification_expires_at' => now()->subHours(2), // Expired!
        ]);

        $response = $this->postJson(route('back-in-stock.verify.confirm'), [
            'token' => $rawToken,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'token_expired',
        ]);

        $this->assertEquals(BackInStockSubscription::STATUS_PENDING_VERIFICATION, $sub->fresh()->status);
        $this->assertNull($sub->fresh()->is_active);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $response = $this->postJson(route('back-in-stock.verify.confirm'), [
            'token' => 'completely-bogus-token-12345',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'invalid_token',
        ]);
    }

    public function test_reused_token_cannot_activate_another_subscription(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);
        $rawToken = bin2hex(random_bytes(32));

        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'doi@aura.lux',
            'normalized_email' => 'doi@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
            'is_active' => null,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
            'verification_token_hash' => hash('sha256', $rawToken),
            'verification_expires_at' => now()->addHours(24),
        ]);

        // First use: succeeds
        $this->postJson(route('back-in-stock.verify.confirm'), ['token' => $rawToken])->assertStatus(200);

        // Second use: token was purged, so token is now invalid!
        $res2 = $this->postJson(route('back-in-stock.verify.confirm'), ['token' => $rawToken]);
        $res2->assertStatus(422);
        $res2->assertJson(['error' => 'invalid_token']);
    }

    // ==========================================
    // C. SECURE UNSUBSCRIBE TESTS
    // ==========================================

    public function test_valid_signed_url_displays_confirmation_without_unsubscribing_on_get(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);
        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'unsub@aura.lux',
            'normalized_email' => 'unsub@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
        ]);

        $signedUrl = URL::temporarySignedRoute('back-in-stock.unsubscribe', now()->addDays(30), ['subscription' => $sub->id]);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('BackInStock/Unsubscribe')
            ->where('status', 'confirm_prompt')
            ->where('subscriptionId', $sub->id)
        );

        // Subscription remains pending and active!
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->fresh()->status);
        $this->assertTrue($sub->fresh()->is_active);
    }

    public function test_valid_post_unsubscribes_and_releases_in_flight_claim(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'unsub@aura.lux',
            'normalized_email' => 'unsub@aura.lux',
            'status' => BackInStockSubscription::STATUS_PROCESSING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
        ]);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-1',
            'lease_token' => 'TOKEN-1',
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        $signedUrl = URL::temporarySignedRoute('back-in-stock.unsubscribe.confirm', now()->addDays(30), ['subscription' => $sub->id]);

        $response = $this->postJson($signedUrl);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'already_unsubscribed' => false,
        ]);

        $sub->refresh();
        $this->assertEquals(BackInStockSubscription::STATUS_UNSUBSCRIBED, $sub->status);
        $this->assertNull($sub->is_active);

        // In-flight claim was safely released!
        $this->assertEquals(RestockEventClaim::STATUS_RELEASED, $claim->fresh()->status);
        $this->assertEquals('unsubscribed_by_customer', $claim->fresh()->release_reason);

        // Active lease deleted!
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());
    }

    public function test_tampered_signed_url_is_rejected(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);
        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'unsub@aura.lux',
            'normalized_email' => 'unsub@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
        ]);

        $tamperedUrl = URL::temporarySignedRoute('back-in-stock.unsubscribe.confirm', now()->addDays(30), ['subscription' => $sub->id]) . 'tampered';

        $response = $this->postJson($tamperedUrl);
        $response->assertStatus(403);

        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->fresh()->status);
    }

    public function test_unsubscribed_user_cannot_be_claimed_in_future_batches(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = BackInStockSubscription::create([
            'product_id' => $product->id,
            'email' => 'unsub@aura.lux',
            'normalized_email' => 'unsub@aura.lux',
            'status' => BackInStockSubscription::STATUS_UNSUBSCRIBED,
            'is_active' => null,
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
        ]);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 0,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claimed = $this->service->claimNextBatchForEvent($event, 'worker-test');

        $this->assertEquals(0, $claimed);
        $this->assertEquals(0, RestockEventClaim::where('subscription_id', $sub->id)->count());
    }
}
