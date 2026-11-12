<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'role:owner'])->get('/test-owner-only', function () {
            return response()->json(['message' => 'owner_ok']);
        });

        Route::middleware(['web', 'role:management'])->get('/test-management-only', function () {
            return response()->json(['message' => 'management_ok']);
        });

        Route::middleware(['web', 'role:agent'])->get('/test-agent-only', function () {
            return response()->json(['message' => 'agent_ok']);
        });
    }

    public function test_user_roles_identify_correctly(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $marketing = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);
        $agent = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->assertTrue($owner->isOwner());
        $this->assertTrue($owner->isManagement());
        $this->assertFalse($owner->isAgent());

        $this->assertTrue($marketing->isManagement());
        $this->assertFalse($marketing->isOwner());

        $this->assertTrue($agent->isAgent());
        $this->assertFalse($agent->isManagement());

        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAgent());
        $this->assertFalse($customer->isManagement());
    }

    public function test_owner_can_access_owner_and_management_routes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER, 'status' => UserStatus::ACTIVE]);

        $response = $this->actingAs($owner)->get('/test-owner-only');
        $response->assertStatus(200);

        $response2 = $this->actingAs($owner)->get('/test-management-only');
        $response2->assertStatus(200);
    }

    public function test_customer_cannot_access_management_or_owner_routes(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER, 'status' => UserStatus::ACTIVE]);

        $response = $this->actingAs($customer)->get('/test-management-only');
        $response->assertStatus(403);
    }

    public function test_agent_can_access_agent_route_but_not_management(): void
    {
        $agent = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);

        $response = $this->actingAs($agent)->get('/test-agent-only');
        $response->assertStatus(200);

        $response2 = $this->actingAs($agent)->get('/test-management-only');
        $response2->assertStatus(403);
    }

    public function test_suspended_user_is_logged_out_and_redirected(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER, 'status' => UserStatus::SUSPENDED]);

        $response = $this->actingAs($user)->get('/test-owner-only');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}

