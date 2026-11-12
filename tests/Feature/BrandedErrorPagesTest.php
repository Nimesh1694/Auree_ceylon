<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BrandedErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/error/{code}', function ($code) {
            abort((int) $code);
        });

        Route::get('/_test/throw-exception', function () {
            throw new \RuntimeException('Database connection failure simulated for testing');
        });
    }

    public function test_browser_request_to_unrouted_path_returns_branded_404_blade_view(): void
    {
        $response = $this->get('/non-existent-aura-luxe-route-' . uniqid());

        $response->assertStatus(404);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Aura Luxe');
        $response->assertSee('Botanical Cosmetics');
        $response->assertSee('Page Not Found');
        $response->assertSee('The page you are looking for could not be found.');
        $response->assertSee('Explore Collections');
        $response->assertSee('Return to Storefront');
    }

    public function test_inertia_xhr_request_to_unrouted_path_returns_branded_404_inertia_component(): void
    {
        $response = $this->get('/non-existent-aura-luxe-route-' . uniqid(), [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(404);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 404,
            ],
        ]);
    }

    public function test_browser_request_for_403_renders_branded_blade_view(): void
    {
        $response = $this->get('/_test/error/403');

        $response->assertStatus(403);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Aura Luxe');
        $response->assertSee('Access Restricted');
        $response->assertSee('You do not have permission to access this page.');
        $response->assertSee('Return to Storefront');
        $response->assertSee('Go Back');
    }

    public function test_inertia_xhr_request_for_403_renders_error_inertia_component(): void
    {
        $response = $this->get('/_test/error/403', [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(403);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 403,
            ],
        ]);
    }

    public function test_browser_request_for_419_renders_branded_blade_view(): void
    {
        $response = $this->get('/_test/error/419');

        $response->assertStatus(419);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Aura Luxe');
        $response->assertSee('Session Expired');
        $response->assertSee('Your secure session has expired. Please refresh and try again.');
        $response->assertSee('Refresh Page');
    }

    public function test_inertia_xhr_request_for_419_renders_error_inertia_component(): void
    {
        $response = $this->get('/_test/error/419', [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(419);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 419,
            ],
        ]);
    }

    public function test_browser_request_for_429_renders_branded_blade_view(): void
    {
        $response = $this->get('/_test/error/429');

        $response->assertStatus(429);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Aura Luxe');
        $response->assertSee('Too Many Requests');
        $response->assertSee('Too many requests were received. Please wait before trying again.');
        $response->assertSee('Refresh Page');
    }

    public function test_inertia_xhr_request_for_429_renders_error_inertia_component(): void
    {
        $response = $this->get('/_test/error/429', [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(429);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 429,
            ],
        ]);
    }

    public function test_browser_request_for_500_renders_branded_blade_view_when_debug_disabled(): void
    {
        Config::set('app.debug', false);

        $response = $this->get('/_test/error/500');

        $response->assertStatus(500);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Aura Luxe');
        $response->assertSee('Something Went Wrong');
        $response->assertSee('An unexpected error occurred while processing your request.');
        $response->assertSee('Return to Storefront');
        $response->assertSee('Contact Support');
    }

    public function test_inertia_xhr_request_for_500_renders_error_inertia_component_when_debug_disabled(): void
    {
        Config::set('app.debug', false);

        $response = $this->get('/_test/error/500', [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(500);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 500,
            ],
        ]);
    }

    public function test_maintenance_mode_503_always_returns_standalone_blade_view(): void
    {
        $response = $this->get('/_test/error/503');

        $response->assertStatus(503);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Aura Luxe');
        $response->assertSee('Scheduled Maintenance');
        $response->assertSee('AURA LUXE is temporarily unavailable while we improve your experience.');
        $response->assertSee('Check Again');
    }

    public function test_maintenance_mode_503_returns_standalone_blade_even_on_inertia_request(): void
    {
        $response = $this->get('/_test/error/503', [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(503);
        $response->assertHeaderMissing('X-Inertia');
        $response->assertSee('Scheduled Maintenance');
    }

    public function test_json_requests_are_isolated_and_return_json_responses(): void
    {
        $response = $this->getJson('/non-existent-json-route-' . uniqid());

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeaderMissing('X-Inertia');
        $response->assertJsonStructure(['message']);
    }

    public function test_500_error_does_not_leak_stack_traces_or_sensitive_data_in_production(): void
    {
        Config::set('app.debug', false);

        $response = $this->get('/_test/throw-exception');

        $response->assertStatus(500);
        $response->assertDontSee('Database connection failure simulated for testing');
        $response->assertDontSee('Stack trace');
        $response->assertDontSee('vendor/laravel');
        $response->assertSee('Something Went Wrong');
    }

    public function test_guest_accessing_real_protected_management_route_redirects_to_login(): void
    {
        $response = $this->get(route('admin.inquiries.index'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_customer_accessing_real_protected_management_route_receives_branded_403(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Browser request
        $browserResponse = $this->actingAs($customer)->get(route('admin.inquiries.index'));
        $browserResponse->assertStatus(403);
        $browserResponse->assertHeaderMissing('X-Inertia');
        $browserResponse->assertSee('Aura Luxe');
        $browserResponse->assertSee('Access Restricted');
        $browserResponse->assertSee('You do not have permission to access this page.');

        // Inertia XHR request
        $inertiaResponse = $this->actingAs($customer)->get(
            route('admin.inquiries.index'),
            $this->inertiaHeaders()
        );
        $inertiaResponse->assertStatus(403);
        $inertiaResponse->assertHeader('X-Inertia', 'true');
        $inertiaResponse->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 403,
            ],
        ]);
    }

    public function test_agent_without_management_permission_accessing_protected_route_receives_branded_403(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        // Browser request
        $browserResponse = $this->actingAs($agent)->get(route('admin.inquiries.index'));
        $browserResponse->assertStatus(403);
        $browserResponse->assertHeaderMissing('X-Inertia');
        $browserResponse->assertSee('Access Restricted');

        // Inertia XHR request
        $inertiaResponse = $this->actingAs($agent)->get(
            route('admin.inquiries.index'),
            $this->inertiaHeaders()
        );
        $inertiaResponse->assertStatus(403);
        $inertiaResponse->assertHeader('X-Inertia', 'true');
        $inertiaResponse->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 403,
            ],
        ]);
    }

    public function test_inactive_product_resource_returns_branded_404(): void
    {
        $inactive = Product::create([
            'name_en' => 'Archived Botanical Serum',
            'name_si' => 'පැරණි සේරම්',
            'slug' => 'archived-botanical-serum',
            'price' => 4500.00,
            'stock_quantity' => 0,
            'is_active' => false,
        ]);

        // Browser request
        $browserResponse = $this->get("/products/{$inactive->slug}");
        $browserResponse->assertStatus(404);
        $browserResponse->assertHeaderMissing('X-Inertia');
        $browserResponse->assertSee('Page Not Found');
        $browserResponse->assertSee('Aura Luxe');

        // Inertia XHR request
        $inertiaResponse = $this->get("/products/{$inactive->slug}", $this->inertiaHeaders());
        $inertiaResponse->assertStatus(404);
        $inertiaResponse->assertHeader('X-Inertia', 'true');
        $inertiaResponse->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 404,
            ],
        ]);
    }

    public function test_authenticated_user_specific_error_responses_do_not_use_public_caching(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $browserResponse = $this->actingAs($customer)->get(route('admin.inquiries.index'));
        $browserResponse->assertStatus(403);
        $browserCacheControl = strtolower((string) $browserResponse->headers->get('Cache-Control', ''));
        $this->assertStringNotContainsString('public', $browserCacheControl);
        $this->assertTrue(
            str_contains($browserCacheControl, 'private')
            || str_contains($browserCacheControl, 'no-cache')
            || str_contains($browserCacheControl, 'no-store')
        );

        $inertiaResponse = $this->actingAs($customer)->get(
            route('admin.inquiries.index'),
            $this->inertiaHeaders()
        );
        $inertiaResponse->assertStatus(403);
        $inertiaCacheControl = strtolower((string) $inertiaResponse->headers->get('Cache-Control', ''));
        $this->assertStringNotContainsString('public', $inertiaCacheControl);
        $this->assertTrue(
            str_contains($inertiaCacheControl, 'private')
            || str_contains($inertiaCacheControl, 'no-cache')
            || str_contains($inertiaCacheControl, 'no-store')
        );
    }

    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) (new \App\Http\Middleware\HandleInertiaRequests())->version(request()),
        ];
    }
}
