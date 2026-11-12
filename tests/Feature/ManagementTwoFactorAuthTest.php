<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ManagementTwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthService $twoFactorService;
    private User $owner;
    private User $accountant;
    private User $agent;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->twoFactorService = app(TwoFactorAuthService::class);

        // Management Owner
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
            'password' => bcrypt('AdminPassword123!'),
        ]);

        // Management Accountant
        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'email' => 'accountant@auraluxe.lk',
            'password' => bcrypt('AccountantPassword123!'),
        ]);

        // Non-management Agent
        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'email' => 'agent@auraluxe.lk',
            'password' => bcrypt('AgentPassword123!'),
        ]);

        // Non-management Customer
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'email' => 'customer@auraluxe.lk',
            'password' => bcrypt('CustomerPassword123!'),
        ]);
    }

    public function test_unenrolled_management_user_can_access_setup_and_enroll(): void
    {
        $response = $this->post(route('login'), [
            'email' => $this->owner->email,
            'password' => 'AdminPassword123!',
        ]);

        $response->assertRedirect(route('two-factor.setup'));
        $this->assertAuthenticatedAs($this->owner);
        $this->assertFalse($this->owner->hasTwoFactorEnabled());

        // Can access setup to enroll
        $this->get(route('two-factor.setup'))->assertOk();
    }

    public function test_customer_and_agent_logins_are_unaffected_by_two_factor_enforcement(): void
    {
        // Agent login
        $agentResponse = $this->post(route('login'), [
            'email' => $this->agent->email,
            'password' => 'AgentPassword123!',
        ]);
        $agentResponse->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        // Customer login
        $customerResponse = $this->post(route('login'), [
            'email' => $this->customer->email,
            'password' => 'CustomerPassword123!',
        ]);
        $customerResponse->assertRedirect(route('dashboard'));
    }

    public function test_management_user_can_setup_and_confirm_two_factor(): void
    {
        $this->actingAs($this->owner);

        // 1. Visit setup page
        $setupResponse = $this->get(route('two-factor.setup'));
        $setupResponse->assertOk();
        $setupResponse->assertInertia(fn ($page) => $page
            ->component('Auth/TwoFactorSetup')
            ->has('secret')
            ->has('formattedSecret')
            ->has('otpAuthUrl')
        );

        $secret = session('two_factor_pending_secret');
        $this->assertNotEmpty($secret);

        // 2. Calculate valid TOTP code
        $validCode = $this->twoFactorService->calculateCode($secret);

        // 3. Confirm 2FA
        $confirmResponse = $this->post(route('two-factor.confirm'), [
            'code' => $validCode,
        ]);

        $confirmResponse->assertRedirect(route('two-factor.recovery-codes'));
        $confirmResponse->assertSessionHas('newRecoveryCodes');

        $this->owner->refresh();
        $this->assertTrue($this->owner->hasTwoFactorEnabled());
        $this->assertNotNull($this->owner->two_factor_confirmed_at);
        $this->assertCount(8, $this->owner->two_factor_recovery_codes);

        // Check secret is stored encrypted
        $rawSecretInDb = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->owner->id)
            ->value('two_factor_secret');
        $this->assertNotEquals($secret, $rawSecretInDb); // Encrypted!
    }

    public function test_enrolled_management_user_is_challenged_for_two_factor_on_login(): void
    {
        // Enroll owner
        $secret = $this->twoFactorService->generateSecretKey();
        $validCode = $this->twoFactorService->calculateCode($secret);
        $this->twoFactorService->confirmTwoFactor($this->owner, $secret, $validCode);

        // Log out
        $this->post(route('logout'));

        // Log back in
        $loginResponse = $this->post(route('login'), [
            'email' => $this->owner->email,
            'password' => 'AdminPassword123!',
        ]);

        $loginResponse->assertRedirect(route('two-factor.challenge'));

        // Accessing admin routes without 2FA verification must redirect back to challenge
        $adminAttempt = $this->get(route('admin.dashboard'));
        $adminAttempt->assertRedirect(route('two-factor.challenge'));
    }

    public function test_valid_totp_code_verifies_session_and_grants_admin_access(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $validCode = $this->twoFactorService->calculateCode($secret);
        $this->twoFactorService->confirmTwoFactor($this->owner, $secret, $validCode);

        // In challenge state
        $this->actingAs($this->owner);
        session(['two_factor_authenticated' => false]);

        $currentValidCode = $this->twoFactorService->calculateCode($secret);

        $response = $this->post(route('two-factor.verify'), [
            'code' => $currentValidCode,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('two_factor_authenticated'));

        // Now admin dashboard is accessible
        $dashboardResponse = $this->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
    }

    public function test_invalid_totp_code_is_rejected_and_rate_limited(): void
    {
        RateLimiter::clear('2fa-verify:' . $this->owner->id);

        $secret = $this->twoFactorService->generateSecretKey();
        $validCode = $this->twoFactorService->calculateCode($secret);
        $this->twoFactorService->confirmTwoFactor($this->owner, $secret, $validCode);

        $this->actingAs($this->owner);
        session(['two_factor_authenticated' => false]);

        // Submit 5 invalid codes
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('two-factor.verify'), [
                'code' => '999999',
            ]);
            $response->assertSessionHasErrors('code');
            $this->assertFalse(session('two_factor_authenticated', false));
        }

        // 6th attempt should be rate limited
        $rateLimitedResponse = $this->post(route('two-factor.verify'), [
            'code' => '999999',
        ]);
        $rateLimitedResponse->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many verification attempts', session('errors')->first('code'));
    }

    public function test_emergency_recovery_code_can_authenticate_and_is_single_use(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $validCode = $this->twoFactorService->calculateCode($secret);
        $recoveryCodes = $this->twoFactorService->confirmTwoFactor($this->owner, $secret, $validCode);

        $firstRecoveryCode = $recoveryCodes[0];

        $this->actingAs($this->owner);
        session(['two_factor_authenticated' => false]);

        // 1. Authenticate with recovery code
        $response = $this->post(route('two-factor.recovery'), [
            'recovery_code' => $firstRecoveryCode,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('two_factor_authenticated'));

        // 2. Recovery code must be permanently removed (single use)
        $this->owner->refresh();
        $this->assertCount(7, $this->owner->two_factor_recovery_codes);
        $this->assertNotContains($firstRecoveryCode, $this->owner->two_factor_recovery_codes);

        // 3. Attempting to reuse the exact same recovery code fails
        session(['two_factor_authenticated' => false]);
        $reuseResponse = $this->post(route('two-factor.recovery'), [
            'recovery_code' => $firstRecoveryCode,
        ]);
        $reuseResponse->assertSessionHasErrors('recovery_code');
        $this->assertFalse(session('two_factor_authenticated', false));
    }

    public function test_management_user_can_regenerate_recovery_codes_with_password(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $validCode = $this->twoFactorService->calculateCode($secret);
        $initialCodes = $this->twoFactorService->confirmTwoFactor($this->owner, $secret, $validCode);

        $this->actingAs($this->owner);
        session(['two_factor_authenticated' => true]);

        // Wrong password fails
        $wrongPassResponse = $this->post(route('two-factor.regenerate-recovery-codes'), [
            'password' => 'WrongPassword!',
        ]);
        $wrongPassResponse->assertSessionHasErrors('password');

        // Correct password regenerates 8 new codes
        $regenResponse = $this->post(route('two-factor.regenerate-recovery-codes'), [
            'password' => 'AdminPassword123!',
        ]);
        $regenResponse->assertSessionHas('newRecoveryCodes');

        $this->owner->refresh();
        $this->assertCount(8, $this->owner->two_factor_recovery_codes);
        $this->assertNotEquals($initialCodes, $this->owner->two_factor_recovery_codes);
    }
}
