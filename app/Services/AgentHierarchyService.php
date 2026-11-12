<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserClosure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AgentHierarchyService
{
    /**
     * Add a newly registered or assigned agent to the closure hierarchy.
     */
    public function attachAgent(User $agent, ?User $parent = null): void
    {
        if ($parent && $agent->id === $parent->id) {
            throw new InvalidArgumentException('An agent cannot be their own parent.');
        }

        if ($parent && (!$parent->isAgent() || !$parent->isActive())) {
            throw new InvalidArgumentException('Parent sponsor must be an active agent.');
        }

        DB::transaction(function () use ($agent, $parent) {
            // Self-referencing link at depth 0
            UserClosure::firstOrCreate([
                'ancestor_id' => $agent->id,
                'descendant_id' => $agent->id,
            ], [
                'depth' => 0,
            ]);

            // If a parent exists, link all ancestors of the parent to the new agent
            if ($parent) {
                // Ensure parent has their own self-reference at depth 0
                UserClosure::firstOrCreate([
                    'ancestor_id' => $parent->id,
                    'descendant_id' => $parent->id,
                ], [
                    'depth' => 0,
                ]);

                $agent->update(['parent_id' => $parent->id]);

                // Insert (ancestor_id, descendant_id, depth) for each ancestor of parent
                $parentAncestors = UserClosure::where('descendant_id', $parent->id)->get();

                foreach ($parentAncestors as $ancestorClosure) {
                    UserClosure::firstOrCreate([
                        'ancestor_id' => $ancestorClosure->ancestor_id,
                        'descendant_id' => $agent->id,
                    ], [
                        'depth' => $ancestorClosure->depth + 1,
                    ]);
                }
            }
        });
    }

    /**
     * Retrieve all descendants (downline) of an agent.
     *
     * @return Collection<int, User>
     */
    public function getDownline(User $agent, ?int $maxDepth = null): Collection
    {
        $query = UserClosure::with('descendant.agentProfile')
            ->where('ancestor_id', $agent->id)
            ->where('depth', '>', 0);

        if ($maxDepth !== null) {
            $query->where('depth', '<=', $maxDepth);
        }

        return $query->orderBy('depth')->get()->pluck('descendant')->filter();
    }

    /**
     * Retrieve all ancestors (upline) of an agent ordered from direct parent upwards.
     * Includes a read-only cycle-safe fallback traversal if closure records are missing,
     * partial, stale, or inconsistent. Never modifies the database.
     *
     * @return Collection<int, User>
     */
    public function getUpline(User $agent, ?int $maxDepth = null): Collection
    {
        // High-level agents and agents without a parent have no upline
        if (!$agent->parent_id || $agent->isHighLevelAgent()) {
            return collect();
        }

        $query = UserClosure::with('ancestor.agentProfile')
            ->where('descendant_id', $agent->id)
            ->where('depth', '>', 0);

        if ($maxDepth !== null) {
            $query->where('depth', '<=', $maxDepth);
        }

        $ancestors = $query->orderBy('depth')->get()->pluck('ancestor')->filter();

        // Integrity checks for closure chain
        $isConsistent = true;

        if ($ancestors->isEmpty()) {
            // Completely missing closure records
            $isConsistent = false;
        } elseif ($ancestors->first()->id !== $agent->parent_id) {
            // Missing direct-parent closure or stale closure
            $isConsistent = false;
        } else {
            // Verify all ancestors are valid active agents
            foreach ($ancestors as $ancestor) {
                if (!$ancestor->isAgent()) {
                    $isConsistent = false;
                    break;
                }
            }

            // Verify closure completeness:
            // In Aura Luxe network, low-level agents are rooted under an active High-Level Agent (HLA).
            // If the last ancestor in the closure list is a Low-Level Agent who has a parent_id,
            // then intermediate or root ancestor closure records are missing!
            if ($isConsistent && ($maxDepth === null || $maxDepth > $ancestors->count())) {
                $lastAncestor = $ancestors->last();
                if ($lastAncestor && $lastAncestor->isLowLevelAgent() && $lastAncestor->parent_id) {
                    $isConsistent = false;
                }
            }
        }

        if (!$isConsistent) {
            Log::warning("Hierarchy Closure Inconsistency for Agent [{$agent->id}]. Falling back to cycle-safe parent_id traversal.");
            return $this->resolveFallbackUpline($agent, $maxDepth);
        }

        return $ancestors;
    }

    /**
     * Cycle-safe read-only fallback traversal using users.parent_id.
     * Never performs database writes.
     *
     * @return Collection<int, User>
     */
    public function resolveFallbackUpline(User $agent, ?int $maxDepth = null): Collection
    {
        $fallbackUpline = collect();
        $currentParentId = $agent->parent_id;
        $visited = [$agent->id];
        $depth = 1;

        while ($currentParentId && ($maxDepth === null || $depth <= $maxDepth)) {
            // Cycle detection
            if (in_array($currentParentId, $visited, true)) {
                Log::error("Hierarchy Cycle Detected during fallback traversal for agent [{$agent->id}]. Aborting traversal at parent [{$currentParentId}].");
                break;
            }

            $visited[] = $currentParentId;
            $parentUser = User::with('agentProfile')->find($currentParentId);

            // Role validation: Must be an agent (HLA or LLA), never customer or staff
            if (!$parentUser || !$parentUser->isAgent()) {
                Log::warning("Hierarchy Invalid Parent: User [{$currentParentId}] is not an agent. Stopping traversal.");
                break;
            }

            $fallbackUpline->push($parentUser);

            // High-Level Agents are company-direct (cannot have parents in Aura Luxe)
            if ($parentUser->isHighLevelAgent()) {
                break;
            }

            $currentParentId = $parentUser->parent_id;
            $depth++;

            if ($depth > 20) { // Safety ceiling to prevent unbounded loops
                break;
            }
        }

        return $fallbackUpline;
    }

    /**
     * Safely move an agent (and their entire subtree) under a new parent.
     * Includes cycle detection, role validation, and deterministic pessimistic locking.
     */
    public function moveAgent(User $agent, ?User $newParent): void
    {
        if ($newParent && $agent->id === $newParent->id) {
            throw new InvalidArgumentException('An agent cannot be their own parent.');
        }

        if ($newParent && (!$newParent->isAgent() || !$newParent->isActive())) {
            throw new InvalidArgumentException('New parent must be an active agent.');
        }

        // Pre-check cycle detection: check if newParent is already a descendant of agent
        if ($newParent) {
            $isDescendant = UserClosure::where('ancestor_id', $agent->id)
                ->where('descendant_id', $newParent->id)
                ->exists();

            if ($isDescendant) {
                throw new InvalidArgumentException('Cannot move an agent under one of their own descendants (cycle detected).');
            }
        }

        // HLA tier rule: High-level agents must remain company-direct (parent = null)
        if ($agent->isHighLevelAgent() && $newParent !== null) {
            throw new InvalidArgumentException('A High-Level Agent must remain company-direct and cannot be placed under a sponsor.');
        }

        // Pre-compute all subtree IDs to enforce deterministic locking across the entire moving branch
        $subtreeIds = UserClosure::where('ancestor_id', $agent->id)->pluck('descendant_id')->all();
        $idsToLock = array_unique(array_merge([$agent->id], $subtreeIds, $newParent ? [$newParent->id] : []));
        sort($idsToLock);

        DB::transaction(function () use ($agent, $newParent, $idsToLock) {
            // Deterministic pessimistic locking: lock affected subtree nodes in sorted ID order
            User::whereIn('id', $idsToLock)->lockForUpdate()->get();

            // Re-fetch fresh instances within lock
            $freshAgent = User::find($agent->id);
            $freshNewParent = $newParent ? User::find($newParent->id) : null;

            // Re-validate self-parenting after lock
            if ($freshNewParent && $freshAgent->id === $freshNewParent->id) {
                throw new InvalidArgumentException('An agent cannot be their own parent.');
            }

            // Re-validate cycle detection inside transaction
            if ($freshNewParent) {
                $isDescendant = UserClosure::where('ancestor_id', $freshAgent->id)
                    ->where('descendant_id', $freshNewParent->id)
                    ->exists();

                if ($isDescendant) {
                    throw new InvalidArgumentException('Cannot move an agent under one of their own descendants (cycle detected).');
                }
            }

            // Find all descendants of $freshAgent (including $freshAgent itself)
            $subtreeIds = UserClosure::where('ancestor_id', $freshAgent->id)->pluck('descendant_id')->all();

            // Find all ancestors of $freshAgent (excluding $freshAgent itself)
            $oldAncestorIds = UserClosure::where('descendant_id', $freshAgent->id)
                ->where('ancestor_id', '!=', $freshAgent->id)
                ->pluck('ancestor_id')->all();

            // Delete links from old ancestors to all nodes in the subtree
            if (!empty($oldAncestorIds)) {
                UserClosure::whereIn('ancestor_id', $oldAncestorIds)
                    ->whereIn('descendant_id', $subtreeIds)
                    ->delete();
            }

            // Update direct parent_id
            $freshAgent->update(['parent_id' => $freshNewParent?->id]);

            // If moving under a new parent, attach new ancestors to all nodes in subtree
            if ($freshNewParent) {
                $newAncestors = UserClosure::where('descendant_id', $freshNewParent->id)->get();
                $subtreeNodes = UserClosure::where('ancestor_id', $freshAgent->id)->get();

                foreach ($newAncestors as $newAncestor) {
                    foreach ($subtreeNodes as $subtreeNode) {
                        UserClosure::firstOrCreate([
                            'ancestor_id' => $newAncestor->ancestor_id,
                            'descendant_id' => $subtreeNode->descendant_id,
                        ], [
                            'depth' => $newAncestor->depth + $subtreeNode->depth + 1,
                        ]);
                    }
                }
            }
        });
    }
}
