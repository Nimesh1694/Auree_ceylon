<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected Product $product;
    protected User $customer;
    protected Order $deliveredOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Purifying Cleanser',
            'name_si' => 'පිරිසිදුකාරකය',
            'slug' => 'purifying-cleanser',
            'sku' => 'SKIN-CLN-001',
            'price' => '2500.00',
            'cost_price' => '1000.00',
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'email' => 'client@test.com',
        ]);

        $this->deliveredOrder = $this->createOrder($this->customer, 'delivered', 2500.00);

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

    protected function createOrder(User $customer, string $status = 'delivered', float $amount = 2500.00): Order
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

    public function test_owner_can_view_reviews_index(): void
    {
        $owner = $this->createOwnerUser();

        $response = $this->actingAs($owner)->get(route('admin.reviews.index'));
        $response->assertOk();
    }

    public function test_marketing_manager_can_view_reviews_index(): void
    {
        $marketing = $this->createMarketingUser();

        $response = $this->actingAs($marketing)->get(route('admin.reviews.index'));
        $response->assertOk();
    }

    public function test_super_admin_can_view_reviews_index(): void
    {
        $superAdmin = $this->createSuperAdminUser();

        $response = $this->actingAs($superAdmin)->get(route('admin.reviews.index'));
        $response->assertOk();
    }

    public function test_customer_forbidden_from_admin_reviews_index(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.reviews.index'));
        $response->assertForbidden();
    }

    public function test_marketing_manager_can_approve_review_via_controller(): void
    {
        $marketing = $this->createMarketingUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Pending Review',
            'comment' => 'Will approve',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($marketing)->patch(route('admin.reviews.approve', $review->id), [
            'status' => 'approved',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'status' => 'approved',
            'moderated_by' => $marketing->id,
        ]);

        $this->product->refresh();
        $this->assertEquals(1, $this->product->reviews_count);
        $this->assertEquals('5.00', (string) $this->product->rating_cache);
    }

    public function test_marketing_manager_can_reject_review_with_valid_reason(): void
    {
        $marketing = $this->createMarketingUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 1,
            'title' => 'Spam Title',
            'comment' => 'Inappropriate comment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($marketing)->patch(route('admin.reviews.reject', $review->id), [
            'status' => 'rejected',
            'rejection_reason' => 'Comment contains inappropriate language.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'status' => 'rejected',
            'rejection_reason' => 'Comment contains inappropriate language.',
        ]);
    }

    public function test_rejection_without_reason_fails_validation(): void
    {
        $marketing = $this->createMarketingUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 1,
            'title' => 'Spam Title',
            'comment' => 'Inappropriate comment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($marketing)->patch(route('admin.reviews.reject', $review->id), [
            'status' => 'rejected',
            'rejection_reason' => '', // Empty reason fails
        ]);

        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_super_admin_forbidden_from_approving_review(): void
    {
        $superAdmin = $this->createSuperAdminUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Pending Review',
            'comment' => 'Comment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->patch(route('admin.reviews.approve', $review->id), [
            'status' => 'approved',
        ]);

        $response->assertForbidden();
    }

    public function test_super_admin_forbidden_from_rejecting_review(): void
    {
        $superAdmin = $this->createSuperAdminUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 1,
            'title' => 'Spam Review',
            'comment' => 'Comment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->patch(route('admin.reviews.reject', $review->id), [
            'status' => 'rejected',
            'rejection_reason' => 'Spam content',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_delete_review_via_controller(): void
    {
        $owner = $this->createOwnerUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Delete Me',
            'comment' => 'Delete Comment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->delete(route('admin.reviews.destroy', $review->id));
        $response->assertRedirect(route('admin.reviews.index'));
        $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);
    }

    public function test_marketing_manager_forbidden_from_deleting_review_via_controller(): void
    {
        $marketing = $this->createMarketingUser();

        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 4,
            'title' => 'Delete Me',
            'comment' => 'Delete Comment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($marketing)->delete(route('admin.reviews.destroy', $review->id));
        $response->assertForbidden();
        $this->assertDatabaseHas('product_reviews', ['id' => $review->id]);
    }

    public function test_public_reviews_api_returns_paginated_approved_reviews(): void
    {
        // 1 approved review
        ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->deliveredOrder->id,
            'rating' => 5,
            'title' => 'Public Review',
            'comment' => 'Visible review',
            'status' => 'approved',
        ]);

        // 1 pending review (must not appear)
        $customer2 = User::factory()->create(['role' => 'customer']);
        $order2 = $this->createOrder($customer2, 'delivered', 2500.00);
        ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $customer2->id,
            'order_id' => $order2->id,
            'rating' => 1,
            'title' => 'Hidden Pending Review',
            'comment' => 'Should be hidden',
            'status' => 'pending',
        ]);

        $this->product->rating_cache = '5.00';
        $this->product->reviews_count = 1;
        $this->product->save();

        $response = $this->getJson(route('products.reviews.index', $this->product->id));
        $response->assertOk();
        $response->assertJson([
            'rating_cache' => '5.00',
            'reviews_count' => 1,
            'reviews' => [
                'total' => 1,
                'data' => [
                    [
                        'title' => 'Public Review',
                        'rating' => 5,
                    ],
                ],
            ],
        ]);
    }
}
