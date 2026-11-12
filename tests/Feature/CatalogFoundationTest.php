<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_product_relationships(): void
    {
        $category = Category::create([
            'name_en' => 'Lipsticks',
            'name_si' => 'ලිප්ස්ටික්',
            'slug' => 'lipsticks',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Ruby Silk Lipstick',
            'name_si' => 'රූබි සිල්ක් ලිප්ස්ටික්',
            'slug' => 'ruby-silk-lipstick',
            'price' => 2500.00,
            'stock_quantity' => 10,
        ]);

        $this->assertEquals($category->id, $product->category->id);
        $this->assertCount(1, $category->products);
        $this->assertTrue($product->isInStock());
    }

    public function test_localized_name_helpers(): void
    {
        $product = Product::create([
            'name_en' => 'Hydrating Toner',
            'name_si' => 'තෙතමනය රඳවන ටෝනර්',
            'slug' => 'hydrating-toner',
            'price' => 1800.00,
            'stock_quantity' => 5,
        ]);

        $this->assertEquals('Hydrating Toner', $product->localizedName('en'));
        $this->assertEquals('තෙතමනය රඳවන ටෝනර්', $product->localizedName('si'));
    }

    public function test_settings_typed_retrieval_and_storage(): void
    {
        Setting::set('direct_commission', 25.5, 'commission', 'float');
        Setting::set('is_cod_enabled', true, 'orders', 'boolean');
        Setting::set('max_levels', 3, 'agent', 'integer');

        $this->assertSame(25.5, Setting::get('direct_commission'));
        $this->assertTrue(Setting::get('is_cod_enabled'));
        $this->assertSame(3, Setting::get('max_levels'));
        $this->assertNull(Setting::get('non_existent_key'));
        $this->assertSame('fallback', Setting::get('non_existent_key', 'fallback'));
    }
}

