<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $marketingManager;
    private User $accountant;
    private User $customer;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->marketingManager = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);
        $this->accountant = User::factory()->create(['role' => UserRole::ACCOUNTANT]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->category = Category::create([
            'name_en' => 'Botanical Toners',
            'name_si' => 'ටෝනර්',
            'slug' => 'botanical-toners',
            'is_active' => true,
        ]);
    }

    public function test_customer_cannot_access_product_admin(): void
    {
        $this->actingAs($this->customer)->get('/admin/products')->assertStatus(403);
        $this->actingAs($this->customer)->get('/admin/products/create')->assertStatus(403);
    }

    public function test_owner_can_create_product_with_opening_stock_and_images(): void
    {
        $imageFile = UploadedFile::fake()->image('toner.jpg', 600, 600);

        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'category_id' => $this->category->id,
            'name_en' => 'Rose Water Hydrosol',
            'name_si' => 'රෝස වතුර ටෝනර්',
            'sku' => 'AL-TON-001',
            'price' => 2400.00,
            'compare_at_price' => 2800.00,
            'cost_price' => 1100.00,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
            'expiry_date' => '2028-12-31',
            'is_active' => true,
            'images' => [$imageFile],
        ]);

        $response->assertRedirect('/admin/products');

        $product = Product::where('sku', 'AL-TON-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals(50, $product->stock_quantity);
        $this->assertEquals('1100.00', (string) $product->cost_price);

        // 1. Opening stock recorded in inventory ledger
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'movement_type' => InventoryMovement::TYPE_OPENING_STOCK,
            'quantity' => 50,
            'previous_stock' => 0,
            'new_stock' => 50,
        ]);

        // 2. ProductImage created and marked primary
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'is_primary' => true,
        ]);
    }

    public function test_cost_price_is_hidden_from_marketing_manager(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Secret Cost Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'secret-cost-cream',
            'sku' => 'AL-CRM-SEC',
            'price' => 5000.00,
            'cost_price' => 1200.00, // Highly sensitive cost price
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        // Marketing manager viewing list
        $response = $this->actingAs($this->marketingManager)->get('/admin/products');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/Products/Index')
                ->where('canViewCostPrice', false)
                ->where('products.data.0.cost_price', null) // Masked!
        );

        // Owner viewing list
        $responseOwner = $this->actingAs($this->owner)->get('/admin/products');
        $responseOwner->assertStatus(200);
        $responseOwner->assertInertia(fn ($page) =>
            $page->component('Admin/Products/Index')
                ->where('canViewCostPrice', true)
                ->where('products.data.0.cost_price', '1200.00') // Visible!
        );
    }

    public function test_image_management_upload_primary_and_delete(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Multi Image Serum',
            'name_si' => 'සීරම්',
            'slug' => 'multi-image-serum',
            'price' => 3000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // 1. Upload first image
        $image1 = UploadedFile::fake()->image('image1.png');
        $this->actingAs($this->owner)->post("/admin/products/{$product->id}/images", [
            'image' => $image1,
        ]);

        $this->assertDatabaseCount('product_images', 1);
        $firstImage = ProductImage::where('product_id', $product->id)->first();
        $this->assertTrue((bool) $firstImage->is_primary);

        // 2. Upload second image
        $image2 = UploadedFile::fake()->image('image2.png');
        $this->actingAs($this->owner)->post("/admin/products/{$product->id}/images", [
            'image' => $image2,
        ]);

        $this->assertDatabaseCount('product_images', 2);
        $secondImage = ProductImage::where('id', '!=', $firstImage->id)->first();
        $this->assertFalse((bool) $secondImage->is_primary);

        // 3. Make second image primary
        $this->actingAs($this->owner)->patch("/admin/products/{$product->id}/images/{$secondImage->id}/primary");
        $this->assertTrue((bool) $secondImage->fresh()->is_primary);
        $this->assertFalse((bool) $firstImage->fresh()->is_primary);

        // 4. Delete an image
        $this->actingAs($this->owner)->delete("/admin/products/{$product->id}/images/{$secondImage->id}");
        $this->assertDatabaseCount('product_images', 1);
        $this->assertTrue((bool) $firstImage->fresh()->is_primary); // Promoted to primary!
    }

    public function test_product_allows_maximum_6_images(): void
    {
        // 1. Creating product with 6 images succeeds
        $images = [];
        for ($i = 1; $i <= 6; $i++) {
            $images[] = UploadedFile::fake()->image("image{$i}.jpg", 600, 600);
        }

        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'category_id' => $this->category->id,
            'name_en' => 'Six Image Product',
            'name_si' => 'හය පින්තූර භාණ්ඩය',
            'price' => 2500.00,
            'stock_quantity' => 15,
            'low_stock_threshold' => 3,
            'is_active' => true,
            'images' => $images,
        ]);

        $response->assertRedirect('/admin/products');
        $product = Product::where('slug', 'six-image-product')->first();
        $this->assertNotNull($product);
        $this->assertCount(6, $product->images);

        // 2. Attempting to upload a 7th image to this product is rejected
        $extraImage = UploadedFile::fake()->image('image7.jpg');
        $uploadResponse = $this->actingAs($this->owner)->post("/admin/products/{$product->id}/images", [
            'image' => $extraImage,
        ]);

        $uploadResponse->assertSessionHasErrors(['image']);
        $this->assertCount(6, $product->fresh()->images);

        // 3. Attempting to create product with 7 images fails validation
        $sevenImages = [];
        for ($i = 1; $i <= 7; $i++) {
            $sevenImages[] = UploadedFile::fake()->image("img{$i}.jpg", 600, 600);
        }

        $failResponse = $this->actingAs($this->owner)->post('/admin/products', [
            'category_id' => $this->category->id,
            'name_en' => 'Seven Image Product',
            'name_si' => 'හත පින්තූර භාණ්ඩය',
            'price' => 2500.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
            'images' => $sevenImages,
        ]);

        $failResponse->assertSessionHasErrors(['images']);
    }
}

