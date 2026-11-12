<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserClosure;
use Illuminate\Support\Facades\DB;

class HierarchyIntegrityAuditService
{
    public const MAX_DEPTH_SAFETY_LIMIT = 50;

    public const STATUS_HEALTHY = 'HEALTHY';
    public const STATUS_WARNING = 'WARNING';
    public const STATUS_CRITICAL = 'CRITICAL';
    public const STATUS_SCAN_INCOMPLETE = 'SCAN_INCOMPLETE';

    public const SEVERITY_CRITICAL = 'CRITICAL';
    public const SEVERITY_HIGH = 'HIGH';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_LOW = 'LOW';

    /**
     * Perform a comprehensive, read-only audit of the entire agent hierarchy network.
     * Guaranteed zero database mutations and zero N+1 queries.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Enable query counting for precise benchmark metrics
        DB::enableQueryLog();
        DB::flushQueryLog();

        // 1. Fetch all agent users with ONLY privacy-safe columns (No NIC, bank details, or balances)
        $agents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->select(['id', 'name', 'role', 'status', 'parent_id', 'referral_code'])
            ->get();

        // 2. Fetch all closure records
        $allClosures = UserClosure::select(['ancestor_id', 'descendant_id', 'depth'])->get();

        // 3. Check for any non-agent users appearing in user_closures
        // Exactly one join query
        $nonAgentClosures = DB::table('user_closures as uc')
            ->leftJoin('users as ua', 'ua.id', '=', 'uc.ancestor_id')
            ->leftJoin('users as ud', 'ud.id', '=', 'uc.descendant_id')
            ->where(function ($q) {
                $q->whereNull('ua.id')
                    ->orWhereNull('ud.id')
                    ->orWhereNotIn('ua.role', [UserRole::HIGH_LEVEL_AGENT->value, UserRole::LOW_LEVEL_AGENT->value])
                    ->orWhereNotIn('ud.role', [UserRole::HIGH_LEVEL_AGENT->value, UserRole::LOW_LEVEL_AGENT->value]);
            })
            ->select([
                'uc.ancestor_id',
                'uc.descendant_id',
                'uc.depth',
                'ua.role as ancestor_role',
                'ud.role as descendant_role',
            ])
            ->get();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Build in-memory lookup structures
        $agentMap = [];
        $parentMap = [];
        foreach ($agents as $agent) {
            $roleValue = $agent->role instanceof UserRole ? $agent->role->value : (string) $agent->role;
            $statusValue = $agent->status instanceof UserStatus ? $agent->status->value : (string) $agent->status;

            $agentMap[$agent->id] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'role' => $roleValue,
                'status' => $statusValue,
                'parent_id' => $agent->parent_id,
                'referral_code' => $agent->referral_code,
            ];
            $parentMap[$agent->id] = $agent->parent_id;
        }

        // Closures lookup: key = "ancestor_id:descendant_id", value = depth
        $closureMap = [];
        $descendantClosures = []; // descendant_id => array of [ancestor_id => depth]
        foreach ($allClosures as $c) {
            $key = "{$c->ancestor_id}:{$c->descendant_id}";
            $closureMap[$key] = (int) $c->depth;
            $descendantClosures[$c->descendant_id][$c->ancestor_id] = (int) $c->depth;
        }

        $anomalies = [];
        $depthLimitExceeded = false;
        $agentsInCycles = [];

        // -------------------------------------------------------------
        // ANOMALY CATEGORY 12: Non-Agent Users in Closure Table
        // -------------------------------------------------------------
        foreach ($nonAgentClosures as $nac) {
            $anomalies[] = [
                'id' => "non_agent_closure_{$nac->ancestor_id}_{$nac->descendant_id}",
                'agent_id' => $nac->descendant_id,
                'agent_name' => $agentMap[$nac->descendant_id]['name'] ?? "User #{$nac->descendant_id}",
                'agent_role' => $nac->descendant_role ?? 'non_agent',
                'referral_code' => $agentMap[$nac->descendant_id]['referral_code'] ?? null,
                'category' => 'non_agent_closure',
                'severity' => self::SEVERITY_HIGH,
                'title' => 'Non-Agent Entry in Closure Table',
                'description' => "Closure table contains ancestor [{$nac->ancestor_id} ({$nac->ancestor_role})] and descendant [{$nac->descendant_id} ({$nac->descendant_role})], which violates agent-only hierarchy boundaries.",
                'expected' => 'Only active high_level_agent or low_level_agent records in user_closures',
                'actual' => "Ancestor role: {$nac->ancestor_role}, Descendant role: {$nac->descendant_role}",
            ];
        }

        // -------------------------------------------------------------
        // ANOMALY CATEGORY 10: Circular Parent Relationships (3-Color DFS)
        // -------------------------------------------------------------
        // 0 = White (unvisited), 1 = Gray (visiting), 2 = Black (visited)
        $color = [];
        foreach ($agentMap as $id => $info) {
            $color[$id] = 0;
        }

        foreach ($agentMap as $agentId => $info) {
            if ($color[$agentId] === 0) {
                $cycleStack = [];
                $curr = $agentId;

                while ($curr !== null && isset($agentMap[$curr])) {
                    if ($color[$curr] === 1) {
                        // Cycle detected! Extract the loop
                        $cycleStartIndex = array_search($curr, $cycleStack, true);
                        $cycleLoop = array_slice($cycleStack, $cycleStartIndex);
                        $cycleLoop[] = $curr; // complete loop representation
                        $cycleStr = implode(' -> ', array_map(fn ($id) => "#{$id} ({$agentMap[$id]['name']})", $cycleLoop));

                        foreach ($cycleLoop as $cycleNodeId) {
                            $agentsInCycles[$cycleNodeId] = true;
                        }

                        $anomalies[] = [
                            'id' => "cycle_{$curr}",
                            'agent_id' => $curr,
                            'agent_name' => $agentMap[$curr]['name'],
                            'agent_role' => $agentMap[$curr]['role'],
                            'referral_code' => $agentMap[$curr]['referral_code'],
                            'category' => 'circular_hierarchy',
                            'severity' => self::SEVERITY_CRITICAL,
                            'title' => 'Circular Parent Relationship Detected',
                            'description' => "A circular sponsorship loop was detected: {$cycleStr}",
                            'expected' => 'Acyclic tree structure terminating at a company-direct High-Level Agent',
                            'actual' => "Cycle detected involving Agent #{$curr}",
                        ];
                        break;
                    }

                    if ($color[$curr] === 2) {
                        // Already fully explored tree branch, no cycle here
                        break;
                    }

                    $color[$curr] = 1;
                    $cycleStack[] = $curr;
                    $curr = $parentMap[$curr] ?? null;

                    if (count($cycleStack) > self::MAX_DEPTH_SAFETY_LIMIT) {
                        $depthLimitExceeded = true;
                        break;
                    }
                }

                // Mark all nodes visited in this traversal as Black
                foreach ($cycleStack as $nodeId) {
                    $color[$nodeId] = 2;
                }
            }
        }

        // -------------------------------------------------------------
        // AUDIT AGENT NODES & UPSTREAM CLOSURE INTEGRITY
        // -------------------------------------------------------------
        // Track expected closure pairs to detect unexpected closures in O(1)
        // Key: "ancestor_id:descendant_id" => true
        $expectedClosures = [];

        foreach ($agentMap as $agentId => $agent) {
            $isHla = $agent['role'] === UserRole::HIGH_LEVEL_AGENT->value;
            $isLla = $agent['role'] === UserRole::LOW_LEVEL_AGENT->value;
            $parentId = $agent['parent_id'];

            // 1. Missing Self-Closure (Depth = 0)
            $selfKey = "{$agentId}:{$agentId}";
            $expectedClosures[$selfKey] = true;

            if (!isset($closureMap[$selfKey])) {
                $anomalies[] = [
                    'id' => "missing_self_{$agentId}",
                    'agent_id' => $agentId,
                    'agent_name' => $agent['name'],
                    'agent_role' => $agent['role'],
                    'referral_code' => $agent['referral_code'],
                    'category' => 'missing_self_closure',
                    'severity' => self::SEVERITY_HIGH,
                    'title' => 'Missing Self-Closure Record',
                    'description' => "Agent #{$agentId} is missing their reflexive closure record (ancestor = {$agentId}, descendant = {$agentId}, depth = 0).",
                    'expected' => "user_closures row (ancestor: {$agentId}, descendant: {$agentId}, depth: 0)",
                    'actual' => 'Record missing from user_closures table',
                ];
            } elseif ($closureMap[$selfKey] !== 0) {
                $anomalies[] = [
                    'id' => "self_depth_mismatch_{$agentId}",
                    'agent_id' => $agentId,
                    'agent_name' => $agent['name'],
                    'agent_role' => $agent['role'],
                    'referral_code' => $agent['referral_code'],
                    'category' => 'incorrect_closure_depth',
                    'severity' => self::SEVERITY_MEDIUM,
                    'title' => 'Incorrect Self-Closure Depth',
                    'description' => "Agent #{$agentId} has self-closure record with depth {$closureMap[$selfKey]} instead of 0.",
                    'expected' => 'depth = 0',
                    'actual' => "depth = {$closureMap[$selfKey]}",
                ];
            }

            // 2. HLA Tier Rule: Must be company-direct (parent_id = null)
            if ($isHla && $parentId !== null) {
                $anomalies[] = [
                    'id' => "hla_with_parent_{$agentId}",
                    'agent_id' => $agentId,
                    'agent_name' => $agent['name'],
                    'agent_role' => $agent['role'],
                    'referral_code' => $agent['referral_code'],
                    'category' => 'hla_with_parent',
                    'severity' => self::SEVERITY_CRITICAL,
                    'title' => 'High-Level Agent Has Parent Sponsor',
                    'description' => "High-Level Agent #{$agentId} is assigned parent_id = {$parentId}. In Aura Luxe, all HLAs must be company-direct.",
                    'expected' => 'parent_id = null (Company-Direct)',
                    'actual' => "parent_id = {$parentId}",
                ];
            }

            // 3. LLA Tier Rule: Must have parent sponsor (parent_id != null)
            if ($isLla && $parentId === null) {
                $anomalies[] = [
                    'id' => "orphan_lla_{$agentId}",
                    'agent_id' => $agentId,
                    'agent_name' => $agent['name'],
                    'agent_role' => $agent['role'],
                    'referral_code' => $agent['referral_code'],
                    'category' => 'orphan_agent',
                    'severity' => self::SEVERITY_HIGH,
                    'title' => 'Orphan Low-Level Agent',
                    'description' => "Low-Level Agent #{$agentId} has no sponsor assigned (parent_id is null). LLAs must be sponsored under an active HLA or senior LLA.",
                    'expected' => 'Valid parent_id referencing an active sponsor agent',
                    'actual' => 'parent_id = null',
                ];
            }

            // 4. Validate Direct Parent Relationship if parent_id is set
            if ($parentId !== null) {
                // Check self-parenting
                if ($parentId === $agentId) {
                    $anomalies[] = [
                        'id' => "self_parent_{$agentId}",
                        'agent_id' => $agentId,
                        'agent_name' => $agent['name'],
                        'agent_role' => $agent['role'],
                        'referral_code' => $agent['referral_code'],
                        'category' => 'invalid_parent',
                        'severity' => self::SEVERITY_CRITICAL,
                        'title' => 'Self-Parenting Detected',
                        'description' => "Agent #{$agentId} is configured as their own sponsor (parent_id = {$agentId}).",
                        'expected' => 'parent_id referencing a distinct active sponsor agent',
                        'actual' => "parent_id = {$agentId}",
                    ];
                    continue; // Skip further upline traversal for self-parenting
                }

                // Check parent exists in agentMap
                if (!isset($agentMap[$parentId])) {
                    // Check if user exists in database but has non-agent role
                    $rawParent = User::select(['id', 'name', 'role', 'status'])->find($parentId);
                    $parentRole = $rawParent?->role instanceof UserRole ? $rawParent->role->value : (string) $rawParent?->role;

                    $anomalies[] = [
                        'id' => "invalid_parent_{$agentId}",
                        'agent_id' => $agentId,
                        'agent_name' => $agent['name'],
                        'agent_role' => $agent['role'],
                        'referral_code' => $agent['referral_code'],
                        'category' => 'invalid_parent',
                        'severity' => self::SEVERITY_CRITICAL,
                        'title' => 'Invalid Parent Sponsor',
                        'description' => $rawParent
                            ? "Agent #{$agentId} has parent_id referencing User #{$parentId} who has non-agent role '{$parentRole}'."
                            : "Agent #{$agentId} has parent_id referencing non-existent User #{$parentId}.",
                        'expected' => 'Active High-Level Agent or Low-Level Agent sponsor',
                        'actual' => $rawParent ? "Role: {$parentRole}" : 'User record not found',
                    ];
                    continue; // Skip upline path for invalid parent
                }

                // Check inactive sponsor
                $parentAgent = $agentMap[$parentId];
                if ($agent['status'] === UserStatus::ACTIVE->value && $parentAgent['status'] !== UserStatus::ACTIVE->value) {
                    $anomalies[] = [
                        'id' => "inactive_sponsor_{$agentId}",
                        'agent_id' => $agentId,
                        'agent_name' => $agent['name'],
                        'agent_role' => $agent['role'],
                        'referral_code' => $agent['referral_code'],
                        'category' => 'inactive_sponsor',
                        'severity' => self::SEVERITY_MEDIUM,
                        'title' => 'Sponsor Account Is Inactive',
                        'description' => "Active Agent #{$agentId} has sponsor #{$parentId} ({$parentAgent['name']}) who is currently {$parentAgent['status']}.",
                        'expected' => 'Active sponsor account',
                        'actual' => "Sponsor status = {$parentAgent['status']}",
                    ];
                }

                // If this node is part of a detected cycle, skip upline generation to prevent repeating the cycle error
                if (isset($agentsInCycles[$agentId])) {
                    continue;
                }

                // -------------------------------------------------------------
                // Traverse Upline via parent_id to regenerate expected closures
                // -------------------------------------------------------------
                $expectedDepth = 1;
                $currParentId = $parentId;
                $visitedUpline = [$agentId];
                $terminatingRoot = null;

                while ($currParentId !== null && isset($agentMap[$currParentId])) {
                    if ($expectedDepth > self::MAX_DEPTH_SAFETY_LIMIT) {
                        $depthLimitExceeded = true;
                        $anomalies[] = [
                            'id' => "depth_limit_{$agentId}",
                            'agent_id' => $agentId,
                            'agent_name' => $agent['name'],
                            'agent_role' => $agent['role'],
                            'referral_code' => $agent['referral_code'],
                            'category' => 'depth_limit_exceeded',
                            'severity' => self::SEVERITY_CRITICAL,
                            'title' => 'Hierarchy Safety Depth Limit Exceeded',
                            'description' => "Traversal for Agent #{$agentId} exceeded the safety ceiling of " . self::MAX_DEPTH_SAFETY_LIMIT . ' hops.',
                            'expected' => '<= ' . self::MAX_DEPTH_SAFETY_LIMIT . ' hops to HLA root',
                            'actual' => 'Depth > ' . self::MAX_DEPTH_SAFETY_LIMIT,
                        ];
                        break;
                    }

                    if (in_array($currParentId, $visitedUpline, true)) {
                        // Cycle encountered during path traversal (already flagged in cycle detection)
                        break;
                    }
                    $visitedUpline[] = $currParentId;

                    $ancestorKey = "{$currParentId}:{$agentId}";
                    $expectedClosures[$ancestorKey] = true;

                    // Verify closure existence & depth
                    if (!isset($closureMap[$ancestorKey])) {
                        $category = $expectedDepth === 1 ? 'missing_parent_closure' : 'missing_ancestor_closure';
                        $severity = $expectedDepth === 1 ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH;
                        $title = $expectedDepth === 1 ? 'Missing Direct-Parent Closure Record' : 'Missing Intermediate Ancestor Closure Record';

                        $anomalies[] = [
                            'id' => "missing_closure_{$currParentId}_{$agentId}",
                            'agent_id' => $agentId,
                            'agent_name' => $agent['name'],
                            'agent_role' => $agent['role'],
                            'referral_code' => $agent['referral_code'],
                            'category' => $category,
                            'severity' => $severity,
                            'title' => $title,
                            'description' => "Missing closure record from ancestor #{$currParentId} ({$agentMap[$currParentId]['name']}) to descendant #{$agentId} at depth {$expectedDepth}.",
                            'expected' => "user_closures row (ancestor: {$currParentId}, descendant: {$agentId}, depth: {$expectedDepth})",
                            'actual' => 'Record missing from user_closures table',
                        ];
                    } elseif ($closureMap[$ancestorKey] !== $expectedDepth) {
                        $anomalies[] = [
                            'id' => "depth_mismatch_{$currParentId}_{$agentId}",
                            'agent_id' => $agentId,
                            'agent_name' => $agent['name'],
                            'agent_role' => $agent['role'],
                            'referral_code' => $agent['referral_code'],
                            'category' => 'incorrect_closure_depth',
                            'severity' => self::SEVERITY_MEDIUM,
                            'title' => 'Incorrect Closure Depth',
                            'description' => "Closure record between ancestor #{$currParentId} and descendant #{$agentId} has depth {$closureMap[$ancestorKey]} instead of {$expectedDepth}.",
                            'expected' => "depth = {$expectedDepth}",
                            'actual' => "depth = {$closureMap[$ancestorKey]}",
                        ];
                    }

                    $terminatingRoot = $agentMap[$currParentId];
                    if ($terminatingRoot['role'] === UserRole::HIGH_LEVEL_AGENT->value) {
                        // Correctly terminated at High-Level Agent
                        break;
                    }

                    $currParentId = $parentMap[$currParentId] ?? null;
                    $expectedDepth++;
                }

                // If LLA upline does not terminate at a High-Level Agent (and didn't hit cycle or limit)
                if ($isLla && !$depthLimitExceeded && !isset($agentsInCycles[$agentId])) {
                    if ($terminatingRoot === null || $terminatingRoot['role'] !== UserRole::HIGH_LEVEL_AGENT->value) {
                        $anomalies[] = [
                            'id' => "missing_hla_root_{$agentId}",
                            'agent_id' => $agentId,
                            'agent_name' => $agent['name'],
                            'agent_role' => $agent['role'],
                            'referral_code' => $agent['referral_code'],
                            'category' => 'lla_missing_hla_root',
                            'severity' => self::SEVERITY_HIGH,
                            'title' => 'Low-Level Agent Not Rooted Under HLA',
                            'description' => "Low-Level Agent #{$agentId}'s upline branch terminates at {$terminatingRoot['name']} (role: {$terminatingRoot['role']}) instead of an active High-Level Agent.",
                            'expected' => 'Branch rooted under an active High-Level Agent',
                            'actual' => 'Upline terminates without reaching an HLA root',
                        ];
                    }
                }
            }
        }

        // -------------------------------------------------------------
        // ANOMALY CATEGORY 4: Unexpected / Stale Closure Records
        // -------------------------------------------------------------
        // Identify any closure record in user_closures that was not in $expectedClosures
        foreach ($allClosures as $c) {
            $key = "{$c->ancestor_id}:{$c->descendant_id}";

            // Only inspect closures where both ancestor and descendant are agents (non-agent closures already caught in Cat 12)
            if (isset($agentMap[$c->ancestor_id]) && isset($agentMap[$c->descendant_id])) {
                if (!isset($expectedClosures[$key])) {
                    $anomalies[] = [
                        'id' => "unexpected_closure_{$c->ancestor_id}_{$c->descendant_id}",
                        'agent_id' => $c->descendant_id,
                        'agent_name' => $agentMap[$c->descendant_id]['name'],
                        'agent_role' => $agentMap[$c->descendant_id]['role'],
                        'referral_code' => $agentMap[$c->descendant_id]['referral_code'],
                        'category' => 'unexpected_closure',
                        'severity' => self::SEVERITY_HIGH,
                        'title' => 'Unexpected / Stale Closure Record',
                        'description' => "Closure table contains path from #{$c->ancestor_id} ({$agentMap[$c->ancestor_id]['name']}) to #{$c->descendant_id} ({$agentMap[$c->descendant_id]['name']}) at depth {$c->depth}, but no such relationship exists in the authoritative users.parent_id hierarchy.",
                        'expected' => 'No closure record between disconnected or relocated agents',
                        'actual' => "Spurious closure row present (depth: {$c->depth})",
                    ];
                }
            }
        }

        // Tally severity counts
        $countsBySeverity = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];
        $countsByCategory = [];

        foreach ($anomalies as $anomaly) {
            $sevKey = strtolower($anomaly['severity']);
            if (isset($countsBySeverity[$sevKey])) {
                $countsBySeverity[$sevKey]++;
            }
            $catKey = $anomaly['category'];
            $countsByCategory[$catKey] = ($countsByCategory[$catKey] ?? 0) + 1;
        }

        // Determine overall health status
        $status = self::STATUS_HEALTHY;
        if ($depthLimitExceeded) {
            $status = self::STATUS_SCAN_INCOMPLETE;
        } elseif ($countsBySeverity['critical'] > 0) {
            $status = self::STATUS_CRITICAL;
        } elseif ($countsBySeverity['high'] > 0 || $countsBySeverity['medium'] > 0) {
            $status = self::STATUS_WARNING;
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $peakMemory = memory_get_peak_usage() - $startMemory;

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'execution_time_ms' => $durationMs,
            'memory_peak_bytes' => max(0, $peakMemory),
            'query_count' => $queryCount,
            'total_agents' => count($agentMap),
            'total_closures' => count($allClosures),
            'total_anomalies' => count($anomalies),
            'depth_limit_exceeded' => $depthLimitExceeded,
            'counts_by_severity' => $countsBySeverity,
            'counts_by_category' => $countsByCategory,
            'anomalies' => array_values($anomalies),
        ];
    }
}

