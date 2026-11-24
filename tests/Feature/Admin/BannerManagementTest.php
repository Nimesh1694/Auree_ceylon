<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_manager_can_view_banners_and_trust_badges(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        Banner::create([
            'placement' => 'trust_badge',
            'theme_style' => '🌿',
            'title_en' => '100% Ceylon Botanicals',
            'subtitle_en' => 'No parabens, sulfates or alcohol',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketing)->get('/admin/banners');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Banners/Index')
            ->has('banners')
            ->has('counts')
        );
    }

    public function test_marketing_manager_can_create_new_trust_badge(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        $response = $this->actingAs($marketing)->post('/admin/banners', [
            'placement' => 'trust_badge',
            'theme_style' => '💎',
            'title_en' => '100% Authentic Luxury Guarantee',
            'title_si' => '100% සත්‍ය රාජකීය සහතිකය',
            'subtitle_en' => 'Direct from Ceylon royal heritage botanicals',
            'subtitle_si' => 'ලංකාවේ පාරම්පරික වට්ටෝරු ඇසුරින්',
            'cta_url' => '/about',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('banners', [
            'placement' => 'trust_badge',
            'theme_style' => '💎',
            'title_en' => '100% Authentic Luxury Guarantee',
            'sort_order' => 5,
            'is_active' => 1,
        ]);
    }

    public function test_marketing_manager_can_update_trust_badge(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        $badge = Banner::create([
            'placement' => 'trust_badge',
            'theme_style' => '🌿',
            'title_en' => '100% Ceylon Botanicals',
            'subtitle_en' => 'No parabens, sulfates or alcohol',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketing)->put("/admin/banners/{$badge->id}", [
            'placement' => 'trust_badge',
            'theme_style' => '🍃',
            'title_en' => '100% Pure Ceylon Botanicals (Updated)',
            'subtitle_en' => 'Certified organic Ceylon Ayurveda',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('banners', [
            'id' => $badge->id,
            'title_en' => '100% Pure Ceylon Botanicals (Updated)',
            'theme_style' => '🍃',
        ]);
    }

    public function test_public_storefront_displays_active_trust_badges(): void
    {
        Banner::create([
            'placement' => 'trust_badge',
            'theme_style' => '🌿',
            'title_en' => '100% Ceylon Botanicals',
            'subtitle_en' => 'No parabens, sulfates or alcohol',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('trustBadges')
        );
    }

    public function test_marketing_manager_can_swap_experience_mode_to_video_and_update_video_settings(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        $response = $this->actingAs($marketing)->post('/admin/banners/experience', [
            'mode' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_type' => 'youtube',
            'video_eyebrow_en' => 'BOTANICAL HARVEST RITUAL',
            'video_title_en' => 'The Essence of Ceylon Lotus',
            'video_description_en' => 'Hand-harvested lotus flowers steeped in organic cold-pressed coconut oil.',
            'video_cta_text_en' => 'Shop The Elixir',
            'video_cta_url' => '/products',
            'video_autoplay' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('video', \App\Models\Setting::get('storefront_experience_mode'));

        $videoData = \App\Models\Setting::get('storefront_experience_video');
        $this->assertEquals('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $videoData['video_url']);
        $this->assertEquals('The Essence of Ceylon Lotus', $videoData['title_en']);
        $this->assertTrue($videoData['autoplay']);

        // Check storefront loads video mode
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);
        $homeResponse->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('experienceMode', 'video')
            ->has('experienceVideo')
        );
    }

    public function test_marketing_manager_can_swap_back_to_reviews_mode(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        // First set to video
        \App\Models\Setting::set('storefront_experience_mode', 'video', 'storefront', 'string');

        // Swap back to reviews
        $response = $this->actingAs($marketing)->post('/admin/banners/experience', [
            'mode' => 'reviews',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('reviews', \App\Models\Setting::get('storefront_experience_mode'));

        // Check storefront loads reviews mode
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);
        $homeResponse->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('experienceMode', 'reviews')
        );
    }

    public function test_experience_mode_validation_rejects_invalid_modes(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        $response = $this->actingAs($marketing)->post('/admin/banners/experience', [
            'mode' => 'invalid_mode',
        ]);

        $response->assertSessionHasErrors('mode');
    }

    public function test_marketing_manager_can_update_whatsapp_support_number(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);

        $response = $this->actingAs($marketing)->post('/admin/banners/whatsapp', [
            'whatsapp_number' => '077 987 6543',
            'whatsapp_message' => 'Hello Aura Luxe, I want to inquire about custom orders.',
            'is_enabled' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('94779876543', \App\Models\Setting::get('whatsapp_number'));
        $this->assertEquals('Hello Aura Luxe, I want to inquire about custom orders.', \App\Models\Setting::get('whatsapp_message'));
        $this->assertTrue(\App\Models\Setting::get('whatsapp_enabled'));

        // Check storefront shares updated WhatsApp settings globally
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);
        $homeResponse->assertInertia(fn ($page) => $page
            ->where('whatsapp.number', '94779876543')
            ->where('whatsapp.message', 'Hello Aura Luxe, I want to inquire about custom orders.')
            ->where('whatsapp.is_enabled', true)
        );
    }

    public function test_unauthorized_customer_cannot_update_whatsapp_settings(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $response = $this->actingAs($customer)->post('/admin/banners/whatsapp', [
            'whatsapp_number' => '0771112233',
        ]);

        $response->assertStatus(403);
    }
}
