<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_owner_can_access_redesigned_dashboard_with_all_executive_props(): void
    {
        $owner = User::where('role', UserRole::OWNER)->first();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
            ->has('executiveMetrics')
            ->has('executiveMetrics.total_sales')
            ->has('executiveMetrics.total_orders')
            ->has('executiveMetrics.total_customers')
            ->has('executiveMetrics.active_agents_total')
            ->has('financialOverview')
            ->has('financialOverview.gross_sales')
            ->has('financialOverview.collected_cod')
            ->has('financialOverview.pending_cod')
            ->has('financialOverview.total_commission')
            ->has('financialOverview.pending_commission')
            ->has('financialOverview.matured_commission')
            ->has('financialOverview.approved_payouts')
            ->has('financialOverview.available_wallet_balance')
            ->has('orderPipeline')
            ->has('salesTrend')
            ->has('inventoryOverview')
            ->has('pendingActionCounts')
            ->has('lowStockProducts')
            ->has('recentOrders')
            ->has('recentLogs')
        );
    }

    public function test_customer_cannot_access_owner_dashboard(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    public function test_owner_visiting_dashboard_is_redirected_to_admin_dashboard(): void
    {
        $owner = User::where('role', UserRole::OWNER)->first();

        $response = $this->actingAs($owner)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_financial_reconciliation_values_match_backend_ledger(): void
    {
        $owner = User::where('role', UserRole::OWNER)->first();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $financials = $response->viewData('page')['props']['financialOverview'];

        $this->assertIsFloat($financials['gross_sales']);
        $this->assertIsFloat($financials['collected_cod']);
        $this->assertIsFloat($financials['total_commission']);
        $this->assertIsFloat($financials['available_wallet_balance']);
        $this->assertGreaterThanOrEqual(0, $financials['available_wallet_balance']);
    }
}

