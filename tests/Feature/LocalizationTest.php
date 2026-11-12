<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_english(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $this->assertEquals('en', App::getLocale());
    }

    public function test_can_switch_locale_to_sinhala_via_legacy_get(): void
    {
        $response = $this->get('/?lang=si');
        $response->assertStatus(200);
        $this->assertEquals('si', session('locale'));
        $this->assertEquals('si', App::getLocale());
    }

    public function test_unsupported_locale_defaults_to_english_via_legacy_get(): void
    {
        $response = $this->get('/?lang=fr');
        $response->assertStatus(200);
        $this->assertEquals('en', App::getLocale());
    }

    public function test_post_locale_can_switch_to_english_and_sinhala(): void
    {
        // Switch to Sinhala via POST /locale
        $response = $this->post(route('locale.switch'), ['locale' => 'si']);
        $response->assertRedirect(route('home'));
        $this->assertEquals('si', session('locale'));

        // Subsequent page view is in Sinhala
        $homeResponse = $this->get(route('home'));
        $homeResponse->assertStatus(200);
        $this->assertEquals('si', App::getLocale());

        // Switch back to English via POST /locale
        $responseEn = $this->post(route('locale.switch'), ['locale' => 'en']);
        $responseEn->assertRedirect(route('home'));
        $this->assertEquals('en', session('locale'));
    }

    public function test_post_locale_rejects_unsupported_locales(): void
    {
        $response = $this->post(route('locale.switch'), ['locale' => 'fr']);
        $response->assertSessionHasErrors('locale');
        $this->assertNotEquals('fr', session('locale'));
    }

    public function test_post_locale_redirects_safely_to_valid_internal_referrer(): void
    {
        $response = $this->from('/products?page=2&filter=featured')
            ->post(route('locale.switch'), ['locale' => 'si']);

        $response->assertRedirect('/products?page=2&filter=featured');
        $this->assertEquals('si', session('locale'));
    }

    public function test_post_locale_cleans_legacy_lang_query_from_referrer(): void
    {
        $response = $this->from('/products?page=2&lang=en')
            ->post(route('locale.switch'), ['locale' => 'si']);

        $response->assertRedirect('/products?page=2');
        $this->assertEquals('si', session('locale'));
    }

    public function test_post_locale_prevents_open_redirect_to_external_host(): void
    {
        $response = $this->withHeaders(['Referer' => 'https://evil-attacker.com/malicious'])
            ->post(route('locale.switch'), ['locale' => 'si']);

        $response->assertRedirect(route('home'));
        $this->assertEquals('si', session('locale'));
    }

    public function test_locale_switching_across_storefront_cart_and_checkout_pages(): void
    {
        // Switch on product page
        $this->from('/products')->post(route('locale.switch'), ['locale' => 'si'])->assertRedirect('/products');
        $this->assertEquals('si', session('locale'));

        // Visit cart in Sinhala
        $this->get('/cart')->assertStatus(200);
        $this->assertEquals('si', App::getLocale());

        // Visit checkout in Sinhala
        $this->get('/checkout')->assertStatus(302); // Redirects to cart if empty, but locale is retained
        $this->assertEquals('si', App::getLocale());
    }
}
