<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_and_catalog_are_seeded_in_testing_and_local_environments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Role::count());
        $this->assertTrue(Permission::where('name', 'inquiries.manage')->exists());
        $this->assertDatabaseHas('users', [
            'email' => 'owner@auraluxe.lk',
            'role' => UserRole::OWNER->value,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'it@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN->value,
        ]);
        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, Product::count());
    }

    public function test_production_seeding_initializes_rbac_permissions_without_creating_demo_accounts(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertGreaterThan(0, Role::count());
        $this->assertTrue(Permission::where('name', 'inquiries.manage')->exists());
        $this->assertSame(0, User::count());
        $this->assertSame(0, User::whereIn('role', [UserRole::OWNER->value, UserRole::SUPER_ADMIN->value])->count());
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Product::count());
    }
}

