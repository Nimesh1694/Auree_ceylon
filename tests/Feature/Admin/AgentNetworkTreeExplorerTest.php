<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AgentHierarchyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentNetworkTreeExplorerTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $marketingManager;
    private User $customer;
    private User $hla1;
    private User $hla2;
    private User $lla1;
    private User $subLla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->hierarchyService = app(AgentHierarchyService::class);

        // Staff users
        $this->owner = User::factory()->create([
            'name' => 'Executive Owner',
            'email' => 'owner@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'name' => 'Agent Network Manager',
            'email' => 'agentmgr@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'name' => 'Marketing Manager',
            'email' => 'marketing@auraluxe.lk',
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Retail Customer',
            'email' => 'customer@example.lk',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // HLA 1 (Company Direct)
        $this->hla1 = User::factory()->create([
            'name' => 'Suneth Silva',
            'email' => 'suneth.hla@example.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-HLA-SUNETH',
            'parent_id' => null,
        ]);
        AgentProfile::create([
            'user_id' => $this->hla1->id,
            'nic' => '198512345678',
            'kyc_status' => 'verified',
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1002003004',
            'wallet_balance' => 50000.00,
        ]);
        $this->hierarchyService->attachAgent($this->hla1, null);

        // HLA 2 (Company Direct)
        $this->hla2 = User::factory()->create([
            'name' => 'Nadeeka Perera',
            'email' => 'nadeeka.hla@example.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-HLA-NADEEKA',
            'parent_id' => null,
        ]);
        AgentProfile::create([
            'user_id' => $this->hla2->id,
            'nic' => '198898765432',
            'kyc_status' => 'verified',
            'bank_name' => 'Sampath Bank',
            'bank_account_number' => '2003004005',
            'wallet_balance' => 35000.00,
        ]);
        $this->hierarchyService->attachAgent($this->hla2, null);

        // LLA 1 (Under HLA 1)
        $this->lla1 = User::factory()->create([
            'name' => 'Kasun Jayawardena',
            'email' => 'kasun.lla@example.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-LLA-KASUN',
            'parent_id' => $this->hla1->id,
        ]);
        AgentProfile::create([
            'user_id' => $this->lla1->id,
            'nic' => '199511223344',
            'kyc_status' => 'verified',
            'bank_name' => 'HNB',
            'bank_account_number' => '3004005006',
            'wallet_balance' => 12000.00,
        ]);
        $this->hierarchyService->attachAgent($this->lla1, $this->hla1);

        // Sub LLA (Under LLA 1)
        $this->subLla = User::factory()->create([
            'name' => 'Ruwan Kumara',
            'email' => 'ruwan.sublla@example.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
            'referral_code' => 'AGT-LLA-RUWAN',
            'parent_id' => $this->lla1->id,
        ]);
        AgentProfile::create([
            'user_id' => $this->subLla->id,
            'nic' => '199988776655',
            'kyc_status' => 'pending',
            'bank_name' => 'BOC',
            'bank_account_number' => '4005006007',
            'wallet_balance' => 0.00,
        ]);
        $this->hierarchyService->attachAgent($this->subLla, $this->lla1);
    }

    // =========================================================================
    // 1. PAGE RENDERING & ROLE-SPECIFIC VISIBILITY
    // =========================================================================

    public function test_agent_manager_can_render_network_tree_page(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agents/Hierarchy')
            ->has('rootAgents', 2)
            ->where('canRequestHierarchyMove', true)
        );
    }

    public function test_owner_can_render_network_tree_page(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.agents.hierarchy'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agents/Hierarchy')
            ->has('rootAgents', 2)
            ->where('canRequestHierarchyMove', true)
        );
    }

    public function test_super_admin_with_agents_view_can_render_tree_page(): void
    {
        $this->assertTrue($this->superAdmin->hasPermissionTo('agents.view'));

        $response = $this->actingAs($this->superAdmin)->get(route('admin.agents.hierarchy'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agents/Hierarchy')
            ->has('rootAgents', 2)
        );
    }

    public function test_unauthorized_roles_and_customers_cannot_access_tree_page(): void
    {
        // Customer -> 403
        $this->actingAs($this->customer)
            ->get(route('admin.agents.hierarchy'))
            ->assertStatus(403);

        // Ordinary Agent -> 403
        $this->actingAs($this->lla1)
            ->get(route('admin.agents.hierarchy'))
            ->assertStatus(403);

        // Marketing Manager without permission -> 403
        $this->actingAs($this->marketingManager)
            ->get(route('admin.agents.hierarchy'))
            ->assertStatus(403);
    }

    // =========================================================================
    // 2. PRIVACY & SENSITIVE DATA EXCLUSION
    // =========================================================================

    public function test_tree_page_props_exclude_sensitive_financial_and_kyc_data(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy'));

        $response->assertStatus(200);

        // Raw response content check: sensitive financial credentials must NOT be present
        $content = $response->getContent();

        $this->assertStringNotContainsString('198512345678', $content); // HLA1 NIC
        $this->assertStringNotContainsString('1002003004', $content);   // HLA1 Bank Account
        $this->assertStringNotContainsString('50000.00', $content);     // HLA1 Wallet Balance
        $this->assertStringNotContainsString('199511223344', $content); // LLA1 NIC
        $this->assertStringNotContainsString('3004005006', $content);   // LLA1 Bank Account
    }

    // =========================================================================
    // 3. LAZY LOADING NODES API
    // =========================================================================

    public function test_nodes_api_returns_direct_children_lazily(): void
    {
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['parent_id' => $this->hla1->id])
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'parent_id',
            'nodes' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                    'referral_code',
                    'parent_id',
                    'children_count',
                    'created_at',
                ],
            ],
        ]);

        $nodes = $response->json('nodes');
        $this->assertCount(1, $nodes);
        $this->assertEquals($this->lla1->id, $nodes[0]['id']);
        $this->assertEquals('Kasun Jayawardena', $nodes[0]['name']);
        $this->assertEquals('AGT-LLA-KASUN', $nodes[0]['referral_code']);
        $this->assertEquals(1, $nodes[0]['children_count']); // Ruwan Kumara is subLla under Kasun
    }

    public function test_nested_sub_lla_nodes_load_correctly(): void
    {
        // Request children of Kasun (subLla)
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['parent_id' => $this->lla1->id])
        );

        $response->assertStatus(200);
        $nodes = $response->json('nodes');
        $this->assertCount(1, $nodes);
        $this->assertEquals($this->subLla->id, $nodes[0]['id']);
        $this->assertEquals('Ruwan Kumara', $nodes[0]['name']);
        $this->assertEquals('pending', $nodes[0]['status']);
        $this->assertEquals(0, $nodes[0]['children_count']);
    }

    public function test_nodes_api_excludes_sensitive_pii_and_bank_details(): void
    {
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['parent_id' => $this->hla1->id])
        );

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must NOT leak NIC, bank account, or wallet balances
        $this->assertStringNotContainsString('199511223344', $content);
        $this->assertStringNotContainsString('3004005006', $content);
        $this->assertStringNotContainsString('12000.00', $content);
        $this->assertStringNotContainsString('bank_account_number', $content);
        $this->assertStringNotContainsString('wallet_balance', $content);
    }

    // =========================================================================
    // 4. NETWORK SEARCH & ANCESTOR PATH RESOLUTION
    // =========================================================================

    public function test_nodes_api_search_by_name_returns_ancestor_path(): void
    {
        // Search for nested agent "Ruwan"
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['search' => 'Ruwan'])
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'search',
            'results' => [
                '*' => [
                    'id',
                    'name',
                    'role',
                    'status',
                    'referral_code',
                    'parent_id',
                    'parent_name',
                    'children_count',
                    'ancestor_path',
                ],
            ],
        ]);

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals($this->subLla->id, $results[0]['id']);
        $this->assertEquals('Ruwan Kumara', $results[0]['name']);
        $this->assertEquals('Kasun Jayawardena', $results[0]['parent_name']);

        // Ancestor path must lead from root HLA down to parent LLA: [hla1->id, lla1->id]
        $expectedPath = [$this->hla1->id, $this->lla1->id];
        $this->assertEquals($expectedPath, $results[0]['ancestor_path']);
    }

    public function test_nodes_api_search_by_referral_code(): void
    {
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['search' => 'AGT-LLA-KASUN'])
        );

        $response->assertStatus(200);
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals($this->lla1->id, $results[0]['id']);
        $this->assertEquals([$this->hla1->id], $results[0]['ancestor_path']);
    }

    public function test_nodes_api_search_returns_empty_when_no_match(): void
    {
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['search' => 'UNKNOWN-AGENT-XYZ'])
        );

        $response->assertStatus(200);
        $this->assertEquals('UNKNOWN-AGENT-XYZ', $response->json('search'));
        $this->assertEmpty($response->json('results'));
    }

    public function test_unauthorized_users_cannot_access_nodes_api(): void
    {
        $this->actingAs($this->customer)
            ->get(route('admin.agents.hierarchy.nodes', ['parent_id' => $this->hla1->id]))
            ->assertStatus(403);

        $this->actingAs($this->lla1)
            ->get(route('admin.agents.hierarchy.nodes', ['parent_id' => $this->hla1->id]))
            ->assertStatus(403);

        $this->actingAs($this->marketingManager)
            ->get(route('admin.agents.hierarchy.nodes', ['parent_id' => $this->hla1->id]))
            ->assertStatus(403);
    }
}

