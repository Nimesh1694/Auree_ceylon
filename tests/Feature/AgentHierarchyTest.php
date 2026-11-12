<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AgentHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hierarchyService = app(AgentHierarchyService::class);
    }

    public function test_can_attach_root_agent(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);

        $this->hierarchyService->attachAgent($agentA, null);

        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $agentA->id,
            'descendant_id' => $agentA->id,
            'depth' => 0,
        ]);
    }

    public function test_can_attach_child_and_grandchild_agents(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);
        $agentB = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);
        $agentC = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);

        $this->hierarchyService->attachAgent($agentA, null);
        $this->hierarchyService->attachAgent($agentB, $agentA);
        $this->hierarchyService->attachAgent($agentC, $agentB);

        // A -> B (depth 1)
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $agentA->id,
            'descendant_id' => $agentB->id,
            'depth' => 1,
        ]);

        // A -> C (depth 2)
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $agentA->id,
            'descendant_id' => $agentC->id,
            'depth' => 2,
        ]);

        // B -> C (depth 1)
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $agentB->id,
            'descendant_id' => $agentC->id,
            'depth' => 1,
        ]);

        // Downline queries
        $downlineA = $this->hierarchyService->getDownline($agentA);
        $this->assertCount(2, $downlineA);
        $this->assertTrue($downlineA->pluck('id')->contains($agentB->id));
        $this->assertTrue($downlineA->pluck('id')->contains($agentC->id));

        // Direct children only (depth = 1)
        $directChildrenA = $this->hierarchyService->getDownline($agentA, maxDepth: 1);
        $this->assertCount(1, $directChildrenA);
        $this->assertEquals($agentB->id, $directChildrenA->first()->id);

        // Upline query for C: should return B (depth 1) then A (depth 2)
        $uplineC = $this->hierarchyService->getUpline($agentC);
        $this->assertCount(2, $uplineC);
        $this->assertEquals($agentB->id, $uplineC->first()->id);
        $this->assertEquals($agentA->id, $uplineC->last()->id);
    }

    public function test_cycle_detection_prevents_moving_parent_under_child(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);
        $agentB = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);

        $this->hierarchyService->attachAgent($agentA, null);
        $this->hierarchyService->attachAgent($agentB, $agentA);

        $this->expectException(InvalidArgumentException::class);
        $this->hierarchyService->moveAgent($agentA, $agentB);
    }

    public function test_safe_move_agent_to_new_parent(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);
        $agentB = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);
        $agentC = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);

        $this->hierarchyService->attachAgent($agentA, null);
        $this->hierarchyService->attachAgent($agentB, null);
        $this->hierarchyService->attachAgent($agentC, $agentA);

        $this->assertEquals(1, UserClosure::where('ancestor_id', $agentA->id)->where('descendant_id', $agentC->id)->count());

        // Move C under B
        $this->hierarchyService->moveAgent($agentC, $agentB);

        // Old link A -> C must be removed
        $this->assertEquals(0, UserClosure::where('ancestor_id', $agentA->id)->where('descendant_id', $agentC->id)->count());

        // New link B -> C must exist
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $agentB->id,
            'descendant_id' => $agentC->id,
            'depth' => 1,
        ]);
    }
}

