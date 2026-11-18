<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSalesTargetValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminManager;
    private User $activeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->adminManager = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->adminManager->syncRole('agent_manager');

        $this->activeAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'TARGETTEST01',
        ]);
        AgentProfile::create([
            'user_id' => $this->activeAgent->id,
            'nic' => '199512345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
    }

    public function test_target_creation_succeeds_with_valid_parameters(): void
    {
        $response = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2027-05',
                'sales_target' => '250000.00',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('agent_sales_targets', [
            'agent_id' => $this->activeAgent->id,
            'target_month' => '2027-05',
            'sales_target' => '250000.00',
            'created_by' => $this->adminManager->id,
        ]);
    }

    public function test_target_creation_rejects_unpadded_or_invalid_month_format(): void
    {
        // Unpadded month: 2026-3
        $response = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2026-3',
                'sales_target' => '100000.00',
            ]);
        $response->assertSessionHasErrors('target_month');

        // Invalid month: 2026-13
        $response2 = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2026-13',
                'sales_target' => '100000.00',
            ]);
        $response2->assertSessionHasErrors('target_month');

        // Invalid year: 2019-01
        $response3 = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2019-01',
                'sales_target' => '100000.00',
            ]);
        $response3->assertSessionHasErrors('target_month');
    }

    public function test_target_creation_rejects_zero_or_negative_sales_target(): void
    {
        // Zero
        $resZero = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2026-06',
                'sales_target' => '0.00',
            ]);
        $resZero->assertSessionHasErrors('sales_target');

        // Negative
        $resNeg = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2026-06',
                'sales_target' => '-500.00',
            ]);
        $resNeg->assertSessionHasErrors('sales_target');

        // Non-numeric
        $resString = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2026-06',
                'sales_target' => 'invalid-amount',
            ]);
        $resString->assertSessionHasErrors('sales_target');
    }

    public function test_target_creation_rejects_inactive_or_non_agent_user(): void
    {
        $inactiveAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::SUSPENDED,
        ]);

        $resInactive = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $inactiveAgent->id,
                'month' => '2026-07',
                'sales_target' => '100000.00',
            ]);
        $resInactive->assertSessionHasErrors('agent_id');

        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => UserStatus::ACTIVE,
        ]);

        $resCustomer = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $customer->id,
                'month' => '2026-07',
                'sales_target' => '100000.00',
            ]);
        $resCustomer->assertSessionHasErrors('agent_id');
    }

    public function test_target_creation_rejects_duplicate_agent_month_pair(): void
    {
        AgentSalesTarget::create([
            'agent_id' => $this->activeAgent->id,
            'target_month' => '2026-08',
            'sales_target' => '150000.00',
            'created_by' => $this->adminManager->id,
        ]);

        $resDuplicate = $this->actingAs($this->adminManager)
            ->post('/admin/agents/analytics/targets', [
                'agent_id' => $this->activeAgent->id,
                'month' => '2026-08',
                'sales_target' => '200000.00',
            ]);
        $resDuplicate->assertSessionHasErrors('target_month');
    }

    public function test_target_update_allows_modifying_amount_and_ignores_self_month_conflict(): void
    {
        $target = AgentSalesTarget::create([
            'agent_id' => $this->activeAgent->id,
            'target_month' => '2027-09',
            'sales_target' => '150000.00',
            'created_by' => $this->adminManager->id,
        ]);

        $resUpdate = $this->actingAs($this->adminManager)
            ->put("/admin/agents/analytics/targets/{$target->id}", [
                'sales_target' => '175000.00',
                'reason' => 'Routine monthly quota revision',
            ]);

        $resUpdate->assertRedirect();
        $this->assertDatabaseHas('agent_sales_targets', [
            'id' => $target->id,
            'sales_target' => '175000.00',
        ]);
    }
}
