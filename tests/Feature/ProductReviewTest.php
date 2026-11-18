<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\ProductReviewService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    protected ProductReviewService $reviewService;
    protected Category $category;
    protected Product $product;
    protected User $customer;
    protected Order $deliveredOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->reviewService = app(ProductReviewService::class);

        $this->category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Hydrating Facial Serum',
            'name_si' => 'මුහුණ සත්කාර සීරම්',
            'slug' => 'hydrating-facial-serum',
            'sku' => 'SKIN-SER-001',
            'price' => '3500.00',
            'cost_price' => '1400.00',
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'email' => 'customer@test.com',
        ]);

        $this->deliveredOrder = $this->createOrder($this->customer, 'delivered', 3500.00);

        $this->createOrderItem($this->deliveredOrder, $this->product);
    }

    protected function createOrderItem(Order $order, Product $product, int $qty = 1): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'product_sku' => $product->sku,
            'unit_price' => $product->price,
            'quantity' => $qty,
            'subtotal' => bcmul((string) $product->price, (string) $qty, 2),
        ]);
    }

    protected function createOrder(User $customer, string $status = 'delivered', float $amount = 3500.00): Order
    {
        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '0771234567',
            'customer_email' => $customer->email,
            'shipping_address' => '123 Main St',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => $amount,
            'delivery_charge' => 350.00,
            'total_amount' => $amount + 350.00,
            'payment_method' => 'cod',
            'status' => $status,
            'tracking_token' => Order::generateTrackingToken(),
            'placed_at' => now(),
        ]);
    }

    protected function createOwnerUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::OWNER->value,
            'email' => 'owner@test.com',
        ]);
    }

    protected function createMarketingUser(): User
    {
        return User::factory()->create([
            'role' => 'marketing_manager',
            'email' => 'marketing@test.com',
        ]);
    }

    protected function createSuperAdminUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'email' => 'superadmin@test.com',
        ]);
    }

    /** Test 2.01: Verified purchaser with delivered order can submit review. */
    public function test_verified_purchaser_with_delivered_order_can_submit_review(): void
    {
        $review = $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Exceptional hydration!',
            'comment' => 'Transformed my skin texture within a week.',
        ]);

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'status' => 'pending',
            'moderated_by' => null,
        ]);
    }

    /** Test 2.02: Non-purchaser forbidden from submitting review. */
    public function test_non_purchaser_forbidden_from_submitting_review(): void
    {
        $otherProduct = Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Unpurchased Night Cream',
            'name_si' => 'රාත්‍රී ක්‍රීම්',
            'slug' => 'unpurchased-night-cream',
            'sku' => 'SKIN-CREAM-002',
            'price' => '4200.00',
            'cost_price' => '1800.00',
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->reviewService->submitReview($this->customer, [
            'product_id' => $otherProduct->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Nice cream',
            'comment' => 'Smells good but was not in this order.',
        ]);
    }

    /** Test 2.02b: Unfulfilled order purchaser forbidden from submitting review. */
    public function test_unfulfilled_order_purchaser_forbidden_from_submitting_review(): void
    {
        $pendingOrder = $this->createOrder($this->customer, 'processing', 3500.00);

        $this->createOrderItem($pendingOrder, $this->product);

        $this->expectException(ValidationException::class);
        $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $pendingOrder->id,
            'rating' => 5,
            'title' => 'Too early review',
            'comment' => 'Has not arrived yet.',
        ]);
    }

    /** Test 2.02c: Order ownership tampering rejected. */
    public function test_order_ownership_tampering_rejected(): void
    {
        $otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'email' => 'other_customer@test.com',
        ]);

        $this->expectException(ValidationException::class);
        $this->reviewService->submitReview($otherCustomer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id, // Belongs to $this->customer, not $otherCustomer
            'rating' => 5,
            'title' => 'Impersonated review',
            'comment' => 'Attempting to use someone else order.',
        ]);
    }

    /** Test 2.03: Duplicate review for same product prevented by unique constraint. */
    public function test_duplicate_review_for_same_product_prevented_by_unique_constraint(): void
    {
        // First submission succeeds
        $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'First Review',
            'comment' => 'Initial feedback.',
        ]);

        // Second submission for the same product must be rejected
        $this->expectException(ValidationException::class);
        $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Second Review',
            'comment' => 'Duplicate attempt.',
        ]);
    }

    /** Test 2.03b: Concurrent duplicate submission fails gracefully with structured 422 error. */
    public function test_concurrent_duplicate_submission_fails_gracefully(): void
    {
        // Directly insert review simulating a concurrent request that committed first
        ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Existing review',
            'comment' => 'Committed concurrently',
            'status' => 'pending',
        ]);

        try {
            $this->reviewService->submitReview($this->customer, [
                'product_id' => $this->product->id,
                'order_id' => $this->deliveredOrder->id,
                'rating' => 5,
                'title' => 'Concurrent race review',
                'comment' => 'Raced and caught by unique constraint',
            ]);
            $this->fail('Expected ValidationException was not thrown on duplicate review.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('review', $e->errors());
            $this->assertStringContainsString('already submitted', $e->errors()['review'][0]);
        }
    }

    /** Test 2.03c: Unrelated database integrity exceptions are re-thrown. */
    public function test_unrelated_database_integrity_exceptions_are_re_thrown(): void
    {
        ProductReview::saving(function () {
            throw new QueryException(
                'sqlite',
                'insert into product_reviews (...)',
                [],
                new \Exception('Unrelated database connection or deadlock exception')
            );
        });

        $this->expectException(QueryException::class);
        $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Test',
            'comment' => 'Will trigger QueryException',
        ]);
    }

    /** Test 2.04: Review defaults to pending status and is hidden from public display. */
    public function test_review_defaults_to_pending_status_and_is_hidden_from_public_display(): void
    {
        $review = $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Pending Review',
            'comment' => 'Under moderation.',
        ]);

        $this->assertEquals('pending', $review->status);

        // Verify public endpoint does not list pending reviews
        $response = $this->getJson(route('products.reviews.index', $this->product->id));
        $response->assertOk();
        $response->assertJson([
            'rating_cache' => '0.00',
            'reviews_count' => 0,
            'reviews' => [
                'total' => 0,
            ],
        ]);
    }

    /** Test 2.05: Moderator approval publishes review and updates rating cache. */
    public function test_moderator_approval_publishes_review_and_updates_rating_cache(): void
    {
        $marketingUser = $this->createMarketingUser();

        $review = $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Superb Quality',
            'comment' => 'Best serum ever.',
        ]);

        $this->reviewService->approveReview($review, $marketingUser);

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'status' => 'approved',
            'moderated_by' => $marketingUser->id,
        ]);

        $this->product->refresh();
        $this->assertEquals(1, $this->product->reviews_count);
        $this->assertEquals('5.00', (string) $this->product->rating_cache);
    }

    /** Test 2.05b: Newly approved review is included in same transaction aggregate. */
    public function test_newly_approved_review_is_included_in_same_transaction_aggregate(): void
    {
        $marketingUser = $this->createMarketingUser();

        // Seed 1st approved review (rating: 4)
        ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Good product',
            'comment' => 'Solid 4 stars',
            'status' => 'approved',
            'moderated_by' => $marketingUser->id,
            'moderated_at' => now(),
        ]);

        // Create 2nd customer and review (rating: 5, pending)
        $customer2 = User::factory()->create(['role' => 'customer']);
        $order2 = $this->createOrder($customer2, 'delivered', 3500.00);
        $this->createOrderItem($order2, $this->product);

        $review2 = $this->reviewService->submitReview($customer2, [
            'product_id' => $this->product->id,
            'order_id' => $order2->id,
            'rating' => 5,
            'title' => '5 stars',
            'comment' => 'Loved it',
        ]);

        // Approve review2: average should be (4 + 5) / 2 = 4.50
        $this->reviewService->approveReview($review2, $marketingUser);

        $this->product->refresh();
        $this->assertEquals(2, $this->product->reviews_count);
        $this->assertEquals('4.50', (string) $this->product->rating_cache);
    }

    /** Test 2.06: Moderator rejection requires reason and keeps review hidden. */
    public function test_moderator_rejection_requires_reason_and_keeps_review_hidden(): void
    {
        $marketingUser = $this->createMarketingUser();

        $review = $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 1,
            'title' => 'Spam Title',
            'comment' => 'Inappropriate content spam link.',
        ]);

        $this->reviewService->rejectReview($review, $marketingUser, 'Violates content policy and contains spam.');

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'status' => 'rejected',
            'rejection_reason' => 'Violates content policy and contains spam.',
            'moderated_by' => $marketingUser->id,
        ]);

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reviews_count);
        $this->assertEquals('0.00', (string) $this->product->rating_cache);
    }

    /** Test 2.06b: Rejection of previously approved review recalculates cache. */
    public function test_rejection_of_previously_approved_review_recalculates_cache(): void
    {
        $marketingUser = $this->createMarketingUser();

        $review = $this->reviewService->submitReview($this->customer, [
            'product_id' => $this->product->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Initially approved',
            'comment' => 'Approved first',
        ]);

        $this->reviewService->approveReview($review, $marketingUser);
        $this->product->refresh();
        $this->assertEquals(1, $this->product->reviews_count);
        $this->assertEquals('5.00', (string) $this->product->rating_cache);

        // Later rejected due to updated policy
        $this->reviewService->rejectReview($review, $marketingUser, 'Identified as non-compliant after audit.');

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reviews_count);
        $this->assertEquals('0.00', (string) $this->product->rating_cache);
    }

    /** Test 2.07: Non-owner forbidden from permanently deleting review at controller. */
    public function test_non_owner_forbidden_from_permanently_deleting_review_at_controller(): void
    {
        $marketingUser = $this->createMarketingUser();
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Delete test',
            'comment' => 'Will test delete',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($marketingUser)->deleteJson(route('admin.reviews.destroy', $review->id));
        $response->assertForbidden();
    }

    /** Test 2.07b: Non-owner forbidden from permanently deleting review at service layer. */
    public function test_non_owner_forbidden_from_permanently_deleting_review_at_service_layer(): void
    {
        $superAdmin = $this->createSuperAdminUser();
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Delete test',
            'comment' => 'Will test delete',
            'status' => 'pending',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->reviewService->deleteReviewPermanently($review, $superAdmin);
    }

    /** Test 2.07c: Owner can permanently delete review and cache synchronizes. */
    public function test_owner_can_permanently_delete_review_and_cache_synchronizes(): void
    {
        $owner = $this->createOwnerUser();
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Approved Review to Delete',
            'comment' => 'Comment',
            'status' => 'approved',
        ]);

        // Manually set cache
        $this->product->update(['rating_cache' => '5.00', 'reviews_count' => 1]);

        $this->reviewService->deleteReviewPermanently($review, $owner);

        $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reviews_count);
        $this->assertEquals('0.00', (string) $this->product->rating_cache);
    }

    /** Test 2.07d: Permanently deleted approved review is excluded from aggregate. */
    public function test_permanently_deleted_approved_review_is_excluded_from_aggregate(): void
    {
        $owner = $this->createOwnerUser();

        // 2 approved reviews (3 and 5 stars = 4.00 average)
        $review1 = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 3,
            'title' => '3 Stars',
            'comment' => 'Average',
            'status' => 'approved',
        ]);

        $customer2 = User::factory()->create(['role' => 'customer']);
        $order2 = $this->createOrder($customer2, 'delivered', 3500.00);
        $review2 = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $customer2->id,
            'order_id' => $order2->id,
            'rating' => 5,
            'title' => '5 Stars',
            'comment' => 'Great',
            'status' => 'approved',
        ]);

        $this->product->update(['rating_cache' => '4.00', 'reviews_count' => 2]);

        // Delete review1 (3 stars), only review2 (5 stars) remains
        $this->reviewService->deleteReviewPermanently($review1, $owner);

        $this->product->refresh();
        $this->assertEquals(1, $this->product->reviews_count);
        $this->assertEquals('5.00', (string) $this->product->rating_cache);
    }

    /** Test 2.08: Rating cache recalculation handles zero approved reviews deterministically. */
    public function test_rating_cache_recalculation_handles_zero_approved_reviews(): void
    {
        $owner = $this->createOwnerUser();
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Last review',
            'comment' => 'Will delete',
            'status' => 'approved',
        ]);

        $this->product->update(['rating_cache' => '4.00', 'reviews_count' => 1]);

        $this->reviewService->deleteReviewPermanently($review, $owner);

        $this->product->refresh();
        $this->assertSame(0, $this->product->reviews_count);
        $this->assertSame('0.00', (string) $this->product->rating_cache);
    }

    /** Test 2.09: Rating cache synchronization runs atomically under hierarchical lock. */
    public function test_rating_cache_synchronization_runs_atomically_under_hierarchical_lock(): void
    {
        $marketingUser = $this->createMarketingUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Lock test',
            'comment' => 'Testing atomic lock',
            'status' => 'pending',
        ]);

        // Should complete successfully under atomic transaction
        $this->reviewService->approveReview($review, $marketingUser);

        $this->product->refresh();
        $this->assertEquals('5.00', (string) $this->product->rating_cache);
        $this->assertEquals(1, $this->product->reviews_count);
    }

    /** Test 2.09b: Mismatched review and product aborts transaction with DomainException. */
    public function test_mismatched_review_and_product_aborts_transaction(): void
    {
        $marketingUser = $this->createMarketingUser();

        $otherProduct = Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Other Product',
            'name_si' => 'වෙනත් නිෂ්පාදනයක්',
            'slug' => 'other-product',
            'sku' => 'SKIN-OTH-003',
            'price' => '2000.00',
            'cost_price' => '800.00',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $review = ProductReview::create([
            'product_id' => $otherProduct->id, // Mismatched product
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Mismatched review',
            'comment' => 'Simulating corrupted review object',
            'status' => 'pending',
        ]);

        // Mutate review in memory to have product_id of $this->product, while DB has $otherProduct->id
        $review->product_id = $this->product->id;

        $this->expectException(DomainException::class);
        $this->reviewService->approveReview($review, $marketingUser);
    }

    /** Test 2.10: Super admin forbidden from moderating reviews at controller. */
    public function test_super_admin_forbidden_from_moderating_reviews_at_controller(): void
    {
        $superAdmin = $this->createSuperAdminUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Pending review',
            'comment' => 'Needs moderation',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->patchJson(route('admin.reviews.approve', $review->id), [
            'status' => 'approved',
        ]);
        $response->assertForbidden();
    }

    /** Test 2.10b: Unauthorized direct service call cannot approve or reject reviews. */
    public function test_unauthorized_direct_service_call_cannot_approve_or_reject_reviews(): void
    {
        $superAdmin = $this->createSuperAdminUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Direct service test',
            'comment' => 'Testing unauthorized call',
            'status' => 'pending',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->reviewService->approveReview($review, $superAdmin);
    }
}
