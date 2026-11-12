<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $customer;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $this->agent = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);
    }

    public function test_customer_and_agent_cannot_access_category_management(): void
    {
        $this->actingAs($this->customer)->get('/admin/categories')->assertStatus(403);
        $this->actingAs($this->agent)->get('/admin/categories')->assertStatus(403);
    }

    public function test_owner_can_view_and_create_bilingual_category(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/categories', [
            'name_en' => 'Ayurvedic Herbal Serums',
            'name_si' => 'ආයුර්වේද සීරම්',
            'slug' => 'ayurvedic-herbal-serums',
            'description_en' => 'Pure herbal essences from Ceylon.',
            'description_si' => 'ලක්දිව ස්වභාවික ශාකසාර.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', [
            'name_en' => 'Ayurvedic Herbal Serums',
            'name_si' => 'ආයුර්වේද සීරම්',
            'slug' => 'ayurvedic-herbal-serums',
        ]);
    }

    public function test_owner_can_update_category(): void
    {
        $category = Category::create([
            'name_en' => 'Original Name',
            'name_si' => 'මුල් නම',
            'slug' => 'original-name',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->put("/admin/categories/{$category->id}", [
            'name_en' => 'Updated Name',
            'name_si' => 'යාවත්කාලීන නම',
            'slug' => 'updated-name',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name_en' => 'Updated Name',
            'is_active' => false,
        ]);
    }

    public function test_cannot_delete_category_assigned_to_products(): void
    {
        $category = Category::create([
            'name_en' => 'Face Cleansers',
            'name_si' => 'මුහුණු ක්ලෙන්සර්',
            'slug' => 'face-cleansers',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name_en' => 'Aloe Cleanser',
            'name_si' => 'ක්ලෙන්සර්',
            'slug' => 'aloe-cleanser',
            'price' => 1200.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Attempt deletion
        $response = $this->actingAs($this->owner)->delete("/admin/categories/{$category->id}");
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]); // Preserved!
    }

    public function test_can_delete_empty_category(): void
    {
        $category = Category::create([
            'name_en' => 'Empty Line',
            'name_si' => 'හිස් කාණ්ඩය',
            'slug' => 'empty-line',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->delete("/admin/categories/{$category->id}");
        $response->assertRedirect('/admin/categories');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}

