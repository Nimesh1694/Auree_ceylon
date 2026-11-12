<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_database_products(): void
    {
        $category = Category::create([
            'name_en' => 'Facial Serums',
            'name_si' => 'මුහුණේ සීරම්',
            'slug' => 'facial-serums',
        ]);

        $featured = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Rosehip Glow Serum',
            'name_si' => 'රෝස්හිප් සීරම්',
            'slug' => 'rosehip-glow-serum',
            'price' => 3500.00,
            'stock_quantity' => 10,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Welcome')
                ->has('categories', 1)
                ->has('featuredProducts', 1)
        );
    }

    public function test_product_listing_displays_active_products(): void
    {
        $cat = Category::create([
            'name_en' => 'Lip Care',
            'name_si' => 'තොල් සත්කාර',
            'slug' => 'lip-care',
        ]);

        Product::create([
            'category_id' => $cat->id,
            'name_en' => 'Velvet Lipstick Red',
            'name_si' => 'වෙල්වට් ලිප්ස්ටික් රතු',
            'slug' => 'velvet-lipstick-red',
            'price' => 2200.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $cat->id,
            'name_en' => 'Hidden Secret Product',
            'name_si' => 'සැඟවුණු නිෂ්පාදනය',
            'slug' => 'hidden-secret-product',
            'price' => 1000.00,
            'stock_quantity' => 5,
            'is_active' => false, // Inactive! Should not be displayed
        ]);

        $response = $this->get('/products');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Products/Index')
                ->has('products.data', 1)
        );
    }

    public function test_product_listing_category_filter(): void
    {
        $cat1 = Category::create(['name_en' => 'Serums', 'name_si' => 'සීරම්', 'slug' => 'serums']);
        $cat2 = Category::create(['name_en' => 'Creams', 'name_si' => 'ක්‍රීම්', 'slug' => 'creams']);

        Product::create([
            'category_id' => $cat1->id,
            'name_en' => 'Serum Alpha',
            'name_si' => 'සීරම් ඇල්ෆා',
            'slug' => 'serum-alpha',
            'price' => 4000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $cat2->id,
            'name_en' => 'Cream Beta',
            'name_si' => 'ක්‍රීම් බීටා',
            'slug' => 'cream-beta',
            'price' => 3000.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->get('/products?category=serums');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.slug', 'serum-alpha')
        );
    }

    public function test_product_listing_search(): void
    {
        Product::create([
            'name_en' => 'Golden Radiance Vitamin C',
            'name_si' => 'ගෝල්ඩන් විටමින් C',
            'slug' => 'golden-radiance-vit-c',
            'price' => 4500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'name_en' => 'Midnight Jasmine Oil',
            'name_si' => 'ජැස්මින් තෙල්',
            'slug' => 'midnight-jasmine-oil',
            'price' => 3200.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->get('/products?search=Jasmine');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.slug', 'midnight-jasmine-oil')
        );
    }

    public function test_product_listing_price_sorting(): void
    {
        Product::create([
            'name_en' => 'Expensive Cream',
            'name_si' => 'මිල අධික ක්‍රීම්',
            'slug' => 'expensive-cream',
            'price' => 8000.00,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        Product::create([
            'name_en' => 'Affordable Balm',
            'name_si' => 'ලාභ බාම්',
            'slug' => 'affordable-balm',
            'price' => 1500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->get('/products?sort=price_low_high');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Products/Index')
                ->where('products.data.0.slug', 'affordable-balm')
                ->where('products.data.1.slug', 'expensive-cream')
        );
    }

    public function test_product_details_page_renders_with_related_products(): void
    {
        $cat = Category::create(['name_en' => 'Toners', 'name_si' => 'ටෝනර්', 'slug' => 'toners']);

        $main = Product::create([
            'category_id' => $cat->id,
            'name_en' => 'Hydrating Rose Toner',
            'name_si' => 'රෝස ටෝනර්',
            'slug' => 'hydrating-rose-toner',
            'price' => 2800.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $related = Product::create([
            'category_id' => $cat->id,
            'name_en' => 'Clarifying Mint Toner',
            'name_si' => 'මින්ට් ටෝනර්',
            'slug' => 'clarifying-mint-toner',
            'price' => 2600.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->get("/products/{$main->slug}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Products/Show')
                ->where('product.slug', 'hydrating-rose-toner')
                ->has('relatedProducts', 1)
        );
    }

    public function test_inactive_product_returns_404(): void
    {
        $inactive = Product::create([
            'name_en' => 'Archived Item',
            'name_si' => 'පැරණි භාණ්ඩය',
            'slug' => 'archived-item',
            'price' => 1000.00,
            'stock_quantity' => 0,
            'is_active' => false,
        ]);

        $response = $this->get("/products/{$inactive->slug}");
        $response->assertStatus(404);
    }
}

