<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_registration_page_is_accessible(): void
    {
        $response = $this->get(route('agent.register'));
        $response->assertStatus(200);
    }

    public function test_registration_requires_valid_active_sponsor_referral_code(): void
    {
        $response = $this->post(route('agent.register.store'), [
            'name' => 'Dilshan Perera',
            'email' => 'dilshan@example.com',
            'phone' => '0771234567',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referral_code' => 'INVALIDCODE',
            'nic' => '199512345678',
            'address' => 'No 45, High Level Road, Maharagama',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Maharagama',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'D M D Perera',
        ]);

        $response->assertSessionHasErrors(['referral_code']);
    }

    public function test_registration_succeeds_with_valid_sponsor_and_creates_pending_agent(): void
    {
        $sponsor = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'SPONSOR99',
        ]);

        $response = $this->post(route('agent.register.store'), [
            'name' => 'Kamal Silva',
            'email' => 'kamal@example.com',
            'phone' => '0712345678',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'referral_code' => 'SPONSOR99',
            'business_name' => 'Kamal Glow Beauty',
            'nic' => '199212345678',
            'address' => 'No 12, Galle Road, Colombo 03',
            'bank_name' => 'Bank of Ceylon',
            'bank_branch' => 'Kollupitiya',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'Kamal Silva',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'kamal@example.com',
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::PENDING->value,
            'parent_id' => $sponsor->id,
        ]);

        $createdUser = User::where('email', 'kamal@example.com')->first();
        $this->assertNotNull($createdUser->referral_code);

        $this->assertDatabaseHas('agent_profiles', [
            'user_id' => $createdUser->id,
            'business_name' => 'Kamal Glow Beauty',
            'nic' => '199212345678',
            'kyc_status' => 'pending',
            'bank_account_number' => '9876543210',
        ]);
    }

    public function test_pending_agent_cannot_access_agent_dashboard(): void
    {
        $pendingAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);

        $response = $this->actingAs($pendingAgent)->get(route('agent.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_referral_code_in_url_is_captured_in_session_and_cookie(): void
    {
        $sponsor = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'TOPAGENT1',
        ]);

        $response = $this->get('/?ref=TOPAGENT1');
        $response->assertStatus(200);
        $response->assertSessionHas('referral_code', 'TOPAGENT1');
        $response->assertCookie('referral_code', 'TOPAGENT1');
    }
}
