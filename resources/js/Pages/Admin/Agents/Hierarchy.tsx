import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface AgentProfileSummary {
    id: number;
    user_id: number;
    kyc_status?: 'pending' | 'verified' | 'rejected';
    business_name?: string | null;
}

export interface HierarchyNode {
    id: number;
    name: string;
    email: string;
    role: 'high_level_agent' | 'low_level_agent' | string;
    status: 'active' | 'pending' | 'suspended' | string;
    referral_code: string;
    parent_id: number | null;
    parent?: {
        id: number;
        name: string;
        referral_code: string;
        role: string;
    } | null;
    parent_name?: string | null;
    parent_referral_code?: string | null;
    children_count: number;
    agent_profile?: AgentProfileSummary | null;
    kyc_status?: 'pending' | 'verified' | 'rejected' | string;
    created_at?: string;
    ancestor_path?: number[];
}

export interface SearchResultItem extends HierarchyNode {
    ancestor_path: number[];
}

interface HierarchyProps extends PageProps {
    rootAgents: HierarchyNode[];
    canRequestHierarchyMove: boolean;
}

// =============================================================================
// SUBCOMPONENT: RECURSIVE TREE NODE
// =============================================================================

interface TreeNodeProps {
    node: HierarchyNode;
    depth: number;
    expandedNodes: Set<number>;
    childrenCache: Record<number, HierarchyNode[]>;
    loadingNodes: Record<number, boolean>;
    errorNodes: Record<number, string>;
    selectedNodeId: number | null;
    highlightedNodeId: number | null;
    onToggleExpand: (node: HierarchyNode) => void;
    onSelectNode: (node: HierarchyNode) => void;
    onRetry: (nodeId: number) => void;
}

const TreeNode: React.FC<TreeNodeProps> = React.memo(({
    node,
    depth,
    expandedNodes,
    childrenCache,
    loadingNodes,
    errorNodes,
    selectedNodeId,
    highlightedNodeId,
    onToggleExpand,
    onSelectNode,
    onRetry,
}) => {
    const isExpanded = expandedNodes.has(node.id);
    const isLoading = !!loadingNodes[node.id];
    const errorMessage = errorNodes[node.id];
    const children = childrenCache[node.id] || [];
    const hasChildren = node.children_count > 0;
    const isSelected = selectedNodeId === node.id;
    const isHighlighted = highlightedNodeId === node.id;
    const isHla = node.role === 'high_level_agent';

    return (
        <div className="relative group" id={`node-${node.id}`}>
            {/* Visual connector lines for nested levels */}
            {depth > 0 && (
                <div
                    className="absolute -left-5 top-5 w-5 h-px bg-slate-300 dark:bg-slate-700 pointer-events-none"
                    aria-hidden="true"
                />
            )}

            {/* Node Card */}
            <div
                className={`flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border transition-all duration-200 ${
                    isSelected
                        ? 'bg-amber-500/10 dark:bg-amber-500/15 border-amber-500/60 shadow-sm'
                        : isHighlighted
                        ? 'bg-amber-100/70 dark:bg-amber-950/40 border-amber-400 ring-2 ring-amber-400/50'
                        : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 shadow-xs'
                }`}
            >
                <div className="flex items-center gap-3 min-w-0">
                    {/* Expand/Collapse Toggle Button */}
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={() => onToggleExpand(node)}
                            disabled={isLoading}
                            aria-expanded={isExpanded}
                            aria-label={`${isExpanded ? 'Collapse' : 'Expand'} downline for ${node.name}`}
                            className={`w-7 h-7 shrink-0 rounded-lg flex items-center justify-center transition-colors ${
                                isExpanded
                                    ? 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100'
                                    : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300'
                            }`}
                        >
                            {isLoading ? (
                                <span className="w-3.5 h-3.5 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
                            ) : isExpanded ? (
                                <Icons.ChevronDown className="w-4 h-4" />
                            ) : (
                                <Icons.ChevronRight className="w-4 h-4" />
                            )}
                        </button>
                    ) : (
                        <div
                            className="w-7 h-7 shrink-0 flex items-center justify-center text-slate-300 dark:text-slate-600"
                            title="No downline agents"
                        >
                            <span className="w-2 h-2 rounded-full bg-slate-200 dark:bg-slate-700" />
                        </div>
                    )}

                    {/* Tier Avatar Badge */}
                    <div
                        className={`w-9 h-9 shrink-0 rounded-xl flex items-center justify-center font-bold text-xs tracking-wider border shadow-2xs ${
                            isHla
                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30'
                                : 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border-indigo-500/30'
                        }`}
                        title={isHla ? 'Company-Direct High-Level Agent' : 'Low-Level Agent'}
                    >
                        {isHla ? 'HLA' : 'LLA'}
                    </div>

                    {/* Agent Details & Code */}
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={() => onSelectNode(node)}
                                className="font-semibold text-slate-900 dark:text-slate-100 hover:text-amber-600 dark:hover:text-amber-400 text-sm truncate text-left transition-colors"
                            >
                                {node.name}
                            </button>

                            <span className="px-2 py-0.5 rounded-md font-mono text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60">
                                {node.referral_code}
                            </span>

                            {/* Account Status Badge */}
                            <span
                                className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium ${
                                    node.status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : node.status === 'pending'
                                        ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                        : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                }`}
                            >
                                <span
                                    className={`w-1.5 h-1.5 rounded-full ${
                                        node.status === 'active'
                                            ? 'bg-emerald-500'
                                            : node.status === 'pending'
                                            ? 'bg-amber-500'
                                            : 'bg-rose-500'
                                    }`}
                                />
                                {node.status.charAt(0).toUpperCase() + node.status.slice(1)}
                            </span>

                            {/* KYC status tag */}
                            {(node.agent_profile?.kyc_status || node.kyc_status) && (
                                <span
                                    className={`text-[10px] font-medium px-1.5 py-0.5 rounded border ${
                                        (node.agent_profile?.kyc_status || node.kyc_status) === 'verified'
                                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-300/40'
                                            : (node.agent_profile?.kyc_status || node.kyc_status) === 'rejected'
                                            ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-300/40'
                                            : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-300/40'
                                    }`}
                                >
                                    KYC: {node.agent_profile?.kyc_status || node.kyc_status}
                                </span>
                            )}
                        </div>

                        {/* Sponsor attribution text for LLAs */}
                        {!isHla && (node.parent_name || node.parent?.name) && (
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                Sponsor: <span className="font-medium text-slate-700 dark:text-slate-300">{node.parent_name || node.parent?.name}</span>
                                {node.parent_referral_code && ` (${node.parent_referral_code})`}
                            </p>
                        )}
                    </div>
                </div>

                {/* Right Metadata & Action Buttons */}
                <div className="flex items-center gap-3 shrink-0 self-end sm:self-center">
                    {/* Downline Count */}
                    <div className="text-right">
                        <span className="text-xs font-semibold text-slate-900 dark:text-white">
                            {node.children_count} {node.children_count === 1 ? 'Downline' : 'Downlines'}
                        </span>
                        <p className="text-[10px] text-slate-400">Direct Network</p>
                    </div>

                    {/* Inspect Button */}
                    <button
                        type="button"
                        onClick={() => onSelectNode(node)}
                        className="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors flex items-center gap-1.5"
                    >
                        <Icons.Eye className="w-3.5 h-3.5" />
                        <span>Inspect</span>
                    </button>
                </div>
            </div>

            {/* Error Banner with Retry */}
            {isExpanded && errorMessage && (
                <div className="mt-2 ml-7 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs flex items-center justify-between">
                    <span>{errorMessage}</span>
                    <button
                        type="button"
                        onClick={() => onRetry(node.id)}
                        className="underline font-semibold hover:text-rose-800 dark:hover:text-rose-300 ml-2"
                    >
                        Retry
                    </button>
                </div>
            )}

            {/* Recursive Children Container */}
            {isExpanded && !errorMessage && (
                <div className="mt-2 ml-4 pl-4 border-l-2 border-slate-200 dark:border-slate-800 space-y-2 relative">
                    {isLoading && children.length === 0 ? (
                        <div className="py-2 pl-3 space-y-2">
                            <div className="h-10 bg-slate-100 dark:bg-slate-800/60 rounded-xl animate-pulse" />
                            <div className="h-10 bg-slate-100 dark:bg-slate-800/60 rounded-xl animate-pulse" />
                        </div>
                    ) : children.length === 0 ? (
                        <p className="text-xs text-slate-400 dark:text-slate-500 py-1.5 pl-3 italic">
                            No active downline agents under this sponsor.
                        </p>
                    ) : (
                        children.map((child) => (
                            <TreeNode
                                key={child.id}
                                node={child}
                                depth={depth + 1}
                                expandedNodes={expandedNodes}
                                childrenCache={childrenCache}
                                loadingNodes={loadingNodes}
                                errorNodes={errorNodes}
                                selectedNodeId={selectedNodeId}
                                highlightedNodeId={highlightedNodeId}
                                onToggleExpand={onToggleExpand}
                                onSelectNode={onSelectNode}
                                onRetry={onRetry}
                            />
                        ))
                    )}
                </div>
            )}
        </div>
    );
});

TreeNode.displayName = 'TreeNode';

// =============================================================================
// MAIN COMPONENT: HIERARCHY TREE EXPLORER
// =============================================================================

export default function Hierarchy({ rootAgents, canRequestHierarchyMove }: HierarchyProps) {
    // In-memory cache of child branches keyed by parent_id
    const [childrenCache, setChildrenCache] = useState<Record<number, HierarchyNode[]>>({});
    // Set of currently expanded node IDs
    const [expandedNodes, setExpandedNodes] = useState<Set<number>>(new Set());
    // Active in-flight requests map to prevent duplicate requests
    const [loadingNodes, setLoadingNodes] = useState<Record<number, boolean>>({});
    // Branch loading error tracker
    const [errorNodes, setErrorNodes] = useState<Record<number, string>>({});

    // Selected agent for the Inspector sidebar
    const [selectedNode, setSelectedNode] = useState<HierarchyNode | null>(null);
    // Transient highlighted node for search jumps
    const [highlightedNodeId, setHighlightedNodeId] = useState<number | null>(null);

    // Search state
    const [searchTerm, setSearchTerm] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [searchResults, setSearchResults] = useState<SearchResultItem[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [searchMenuOpen, setSearchMenuOpen] = useState(false);
    const [copiedCode, setCopiedCode] = useState(false);

    const searchRef = useRef<HTMLDivElement>(null);

    // Debounce search input (300ms)
    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(searchTerm.trim());
        }, 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    // Backend network search
    useEffect(() => {
        if (!debouncedSearch || debouncedSearch.length < 2) {
            setSearchResults([]);
            setIsSearching(false);
            return;
        }

        let isCancelled = false;
        setIsSearching(true);

        axios
            .get('/admin/agents/hierarchy/nodes', {
                params: { search: debouncedSearch },
            })
            .then((res) => {
                if (!isCancelled) {
                    setSearchResults(res.data.results || []);
                    setSearchMenuOpen(true);
                }
            })
            .catch(() => {
                if (!isCancelled) {
                    setSearchResults([]);
                }
            })
            .finally(() => {
                if (!isCancelled) {
                    setIsSearching(false);
                }
            });

        return () => {
            isCancelled = true;
        };
    }, [debouncedSearch]);

    // Click outside to close search dropdown
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (searchRef.current && !searchRef.current.contains(event.target as Node)) {
                setSearchMenuOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Toggle expansion with lazy loading and cache reuse
    const handleToggleExpand = useCallback(async (node: HierarchyNode) => {
        const nodeId = node.id;

        // If already expanded, collapse it
        if (expandedNodes.has(nodeId)) {
            setExpandedNodes((prev) => {
                const next = new Set(prev);
                next.delete(nodeId);
                return next;
            });
            return;
        }

        // Expand it
        setExpandedNodes((prev) => new Set(prev).add(nodeId));

        // If already cached, reuse cached nodes without network roundtrip
        if (childrenCache[nodeId] !== undefined) {
            return;
        }

        // Prevent duplicate simultaneous requests
        if (loadingNodes[nodeId]) {
            return;
        }

        // Mark as loading
        setLoadingNodes((prev) => ({ ...prev, [nodeId]: true }));
        setErrorNodes((prev) => {
            const next = { ...prev };
            delete next[nodeId];
            return next;
        });

        try {
            const res = await axios.get('/admin/agents/hierarchy/nodes', {
                params: { parent_id: nodeId },
            });

            const fetchedChildren: HierarchyNode[] = res.data.nodes || [];
            setChildrenCache((prev) => ({
                ...prev,
                [nodeId]: fetchedChildren,
            }));
        } catch {
            setErrorNodes((prev) => ({
                ...prev,
                [nodeId]: 'Failed to load network branch. Please retry.',
            }));
        } finally {
            setLoadingNodes((prev) => {
                const next = { ...prev };
                delete next[nodeId];
                return next;
            });
        }
    }, [expandedNodes, childrenCache, loadingNodes]);

    // Retry branch load on failure
    const handleRetry = useCallback((nodeId: number) => {
        const dummyNode = { id: nodeId, children_count: 1 } as HierarchyNode;
        handleToggleExpand(dummyNode);
    }, [handleToggleExpand]);

    // Expand all root HLA nodes
    const handleExpandAllRoots = async () => {
        const newExpanded = new Set(expandedNodes);
        for (const root of rootAgents) {
            newExpanded.add(root.id);
            if (childrenCache[root.id] === undefined && !loadingNodes[root.id]) {
                handleToggleExpand(root);
            }
        }
        setExpandedNodes(newExpanded);
    };

    // Collapse all branches back to root
    const handleCollapseAll = () => {
        setExpandedNodes(new Set());
    };

    // Handle selection from search dropdown with automated path expansion
    const handleSelectSearchResult = async (result: SearchResultItem) => {
        setSearchMenuOpen(false);
        setSelectedNode(result);
        setHighlightedNodeId(result.id);

        // Expand ancestor path if nested
        if (result.ancestor_path && result.ancestor_path.length > 0) {
            const nextExpanded = new Set(expandedNodes);

            for (const ancestorId of result.ancestor_path) {
                nextExpanded.add(ancestorId);

                // If not cached, load synchronously before descending
                if (childrenCache[ancestorId] === undefined) {
                    try {
                        const res = await axios.get('/admin/agents/hierarchy/nodes', {
                            params: { parent_id: ancestorId },
                        });
                        setChildrenCache((prev) => ({
                            ...prev,
                            [ancestorId]: res.data.nodes || [],
                        }));
                    } catch (err) {
                        console.error('Failed to load ancestor level', ancestorId, err);
                    }
                }
            }

            setExpandedNodes(nextExpanded);
        }

        // Smooth scroll to targeted agent node
        setTimeout(() => {
            const el = document.getElementById(`node-${result.id}`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 250);
    };

    // Copy referral code helper
    const handleCopyReferral = (code: string) => {
        navigator.clipboard.writeText(code);
        setCopiedCode(true);
        setTimeout(() => setCopiedCode(false), 2000);
    };

    // Filter root agents when typing a local search
    const filteredRoots = rootAgents.filter((agent) => {
        if (!searchTerm) return true;
        const q = searchTerm.toLowerCase();
        return (
            agent.name.toLowerCase().includes(q) ||
            agent.referral_code.toLowerCase().includes(q)
        );
    });

    return (
        <AdminLayout>
            <Head title="Agent Network Tree Explorer" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Card */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Icons.GitBranch className="w-6 h-6" />
                            </span>
                            <div>
                                <h1 className="text-xl md:text-2xl font-bold text-slate-900 dark:text-white">
                                    Agent Network Tree Explorer
                                </h1>
                                <p className="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                                    Interactive downline visualization, lazy branch loading, and real-time hierarchy mapping.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Action Controls & Navigation */}
                    <div className="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                        <button
                            type="button"
                            onClick={handleExpandAllRoots}
                            className="px-3 py-2 rounded-xl text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors flex items-center gap-1.5"
                        >
                            <Icons.Plus className="w-3.5 h-3.5" />
                            <span>Expand Roots</span>
                        </button>

                        <button
                            type="button"
                            onClick={handleCollapseAll}
                            className="px-3 py-2 rounded-xl text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors flex items-center gap-1.5"
                        >
                            <Icons.Minimize2 className="w-3.5 h-3.5" />
                            <span>Collapse All</span>
                        </button>

                        {canRequestHierarchyMove && (
                            <Link
                                href="/admin/agents/hierarchy-requests"
                                className="px-3.5 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-colors flex items-center gap-1.5"
                            >
                                <Icons.ArrowRightLeft className="w-3.5 h-3.5" />
                                <span>Move Requests</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Search Bar with Live Network Resolution */}
                <div className="relative" ref={searchRef}>
                    <div className="relative flex items-center">
                        <Icons.Search className="w-4 h-4 absolute left-3.5 text-slate-400 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Search entire network by agent name or referral code (e.g. AGT-HLA-001)..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            onFocus={() => {
                                if (searchResults.length > 0) setSearchMenuOpen(true);
                            }}
                            className="w-full pl-10 pr-10 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-xs"
                        />
                        {searchTerm && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    setSearchResults([]);
                                    setSearchMenuOpen(false);
                                }}
                                className="absolute right-3 p-1 rounded-md text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <Icons.XCircle className="w-4 h-4" />
                            </button>
                        )}
                    </div>

                    {/* Search Results Dropdown */}
                    {searchMenuOpen && (
                        <div className="absolute z-30 left-0 right-0 mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl overflow-hidden max-h-80 overflow-y-auto">
                            {isSearching ? (
                                <div className="p-4 text-center text-xs text-slate-400 flex items-center justify-center gap-2">
                                    <span className="w-3.5 h-3.5 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
                                    <span>Searching network across all tiers...</span>
                                </div>
                            ) : searchResults.length === 0 ? (
                                <div className="p-4 text-center text-xs text-slate-500 dark:text-slate-400">
                                    No agents found matching &ldquo;{debouncedSearch}&rdquo;.
                                </div>
                            ) : (
                                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {searchResults.map((result) => (
                                        <button
                                            key={result.id}
                                            type="button"
                                            onClick={() => handleSelectSearchResult(result)}
                                            className="w-full text-left p-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors flex items-center justify-between gap-3"
                                        >
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                <span
                                                    className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                        result.role === 'high_level_agent'
                                                            ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                                            : 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400'
                                                    }`}
                                                >
                                                    {result.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                                </span>
                                                <div className="truncate">
                                                    <p className="font-semibold text-xs text-slate-900 dark:text-white truncate">
                                                        {result.name}
                                                    </p>
                                                    <p className="text-[11px] text-slate-400">
                                                        Code: <span className="font-mono text-slate-600 dark:text-slate-300">{result.referral_code}</span>
                                                        {result.parent_name && ` • Sponsor: ${result.parent_name}`}
                                                    </p>
                                                </div>
                                            </div>

                                            <span className="text-[11px] font-medium text-amber-600 dark:text-amber-400 shrink-0 flex items-center gap-1">
                                                <span>Locate in Tree</span>
                                                <Icons.ChevronRight className="w-3 h-3" />
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Legend & Summary Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3 py-2 px-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400">
                    <div className="flex items-center gap-4">
                        <span className="font-medium text-slate-900 dark:text-slate-200">
                            Root Pillars: <strong className="text-amber-600 dark:text-amber-400">{rootAgents.length}</strong> Company-Direct HLAs
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-4 text-[11px]">
                        <span className="flex items-center gap-1.5">
                            <span className="w-2.5 h-2.5 rounded-full bg-amber-500" />
                            <span>High-Level Agent (HLA)</span>
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="w-2.5 h-2.5 rounded-full bg-indigo-500" />
                            <span>Low-Level Agent (LLA)</span>
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-emerald-500" />
                            <span>Active</span>
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-amber-500" />
                            <span>Pending</span>
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-rose-500" />
                            <span>Suspended</span>
                        </span>
                    </div>
                </div>

                {/* Main Split Layout: Tree View Canvas + Inspector Sidebar */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    {/* Tree Canvas (Spans 2 cols on desktop) */}
                    <div className="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-3 min-h-[500px]">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <h2 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <Icons.Users className="w-4 h-4 text-amber-500" />
                                <span>Network Hierarchy Canvas</span>
                            </h2>
                            <span className="text-xs text-slate-400">
                                Click chevrons to load & expand branches
                            </span>
                        </div>

                        {filteredRoots.length === 0 ? (
                            <div className="text-center py-16">
                                <Icons.GitBranch className="w-12 h-12 text-slate-300 dark:text-slate-700 mx-auto mb-3" />
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                                    No agents match your filter
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                    {searchTerm
                                        ? `No root agents match "${searchTerm}". Use the search bar above to locate nested agents directly.`
                                        : 'No Company-Direct High-Level Agents are currently active in the network.'}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3 pt-2">
                                {filteredRoots.map((root) => (
                                    <TreeNode
                                        key={root.id}
                                        node={root}
                                        depth={0}
                                        expandedNodes={expandedNodes}
                                        childrenCache={childrenCache}
                                        loadingNodes={loadingNodes}
                                        errorNodes={errorNodes}
                                        selectedNodeId={selectedNode?.id ?? null}
                                        highlightedNodeId={highlightedNodeId}
                                        onToggleExpand={handleToggleExpand}
                                        onSelectNode={setSelectedNode}
                                        onRetry={handleRetry}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Agent Inspector Panel (Desktop sticky, mobile full-width) */}
                    <div className="lg:col-span-1 sticky top-6">
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-4">
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Icons.Eye className="w-4 h-4 text-amber-500" />
                                    <span>Agent Hierarchy Inspector</span>
                                </h3>
                                {selectedNode && (
                                    <button
                                        type="button"
                                        onClick={() => setSelectedNode(null)}
                                        className="p-1 rounded-md text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                        title="Close inspector"
                                    >
                                        <Icons.XCircle className="w-4 h-4" />
                                    </button>
                                )}
                            </div>

                            {selectedNode ? (
                                <div className="space-y-4">
                                    {/* Agent Header Identity */}
                                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 flex items-start gap-3">
                                        <div
                                            className={`w-10 h-10 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 border ${
                                                selectedNode.role === 'high_level_agent'
                                                    ? 'bg-amber-500/20 text-amber-700 dark:text-amber-400 border-amber-500/40'
                                                    : 'bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border-indigo-500/40'
                                            }`}
                                        >
                                            {selectedNode.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <h4 className="font-bold text-slate-900 dark:text-white text-sm truncate">
                                                {selectedNode.name}
                                            </h4>
                                            <p className="text-xs text-slate-500 dark:text-slate-400 truncate">
                                                {selectedNode.email}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Attributes Grid */}
                                    <div className="space-y-2.5 text-xs">
                                        {/* Referral Code */}
                                        <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500 dark:text-slate-400">Referral Code</span>
                                            <div className="flex items-center gap-1.5">
                                                <span className="font-mono font-semibold text-slate-900 dark:text-white">
                                                    {selectedNode.referral_code}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => handleCopyReferral(selectedNode.referral_code)}
                                                    className="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                                    title="Copy referral code"
                                                >
                                                    {copiedCode ? (
                                                        <Icons.Check className="w-3.5 h-3.5 text-emerald-500" />
                                                    ) : (
                                                        <Icons.Copy className="w-3.5 h-3.5" />
                                                    )}
                                                </button>
                                            </div>
                                        </div>

                                        {/* Tier / Role */}
                                        <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500 dark:text-slate-400">Agent Tier</span>
                                            <span className="font-semibold text-slate-900 dark:text-white">
                                                {selectedNode.role === 'high_level_agent'
                                                    ? 'High-Level Agent (Leader)'
                                                    : 'Low-Level Agent (Member)'}
                                            </span>
                                        </div>

                                        {/* Account Status */}
                                        <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500 dark:text-slate-400">Account Status</span>
                                            <span
                                                className={`px-2 py-0.5 rounded-full font-medium ${
                                                    selectedNode.status === 'active'
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                        : selectedNode.status === 'pending'
                                                        ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                        : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                                }`}
                                            >
                                                {selectedNode.status.charAt(0).toUpperCase() + selectedNode.status.slice(1)}
                                            </span>
                                        </div>

                                        {/* KYC Status */}
                                        <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500 dark:text-slate-400">KYC Status</span>
                                            <span className="font-semibold text-slate-900 dark:text-white capitalize">
                                                {selectedNode.agent_profile?.kyc_status || selectedNode.kyc_status || 'Pending'}
                                            </span>
                                        </div>

                                        {/* Direct Downline Count */}
                                        <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500 dark:text-slate-400">Direct Downline</span>
                                            <span className="font-bold text-amber-600 dark:text-amber-400">
                                                {selectedNode.children_count} LLAs
                                            </span>
                                        </div>

                                        {/* Sponsor / Parent Attribution */}
                                        <div className="p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800 space-y-1">
                                            <div className="text-slate-500 dark:text-slate-400">Direct Sponsor</div>
                                            <p className="font-semibold text-slate-900 dark:text-white">
                                                {selectedNode.role === 'high_level_agent'
                                                    ? 'None (Company-Direct Executive)'
                                                    : selectedNode.parent_name || selectedNode.parent?.name || 'Assigned Sponsor'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Strict Data Privacy Alert */}
                                    <div className="p-3 rounded-xl bg-slate-100/70 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/60 text-[11px] text-slate-500 dark:text-slate-400">
                                        <div className="flex items-center gap-1.5 font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            <Icons.Lock className="w-3.5 h-3.5 text-amber-500" />
                                            <span>Privacy Guard Active</span>
                                        </div>
                                        <p>
                                            Sensitive PII (NIC, Banking, Wallet Ledgers) is strictly restricted to authorized compliance workflows.
                                        </p>
                                    </div>

                                    {/* Action Link to Full Agent Profile */}
                                    <Link
                                        href={`/admin/agents/${selectedNode.id}`}
                                        className="w-full py-2.5 px-4 rounded-xl font-semibold text-xs text-center bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 transition-colors flex items-center justify-center gap-2 shadow-xs"
                                    >
                                        <span>View Complete Agent Dossier</span>
                                        <Icons.ExternalLink className="w-3.5 h-3.5" />
                                    </Link>
                                </div>
                            ) : (
                                <div className="text-center py-10 px-2 space-y-2">
                                    <div className="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
                                        <Icons.User className="w-5 h-5" />
                                    </div>
                                    <h4 className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        No Agent Selected
                                    </h4>
                                    <p className="text-[11px] text-slate-400 max-w-xs mx-auto">
                                        Select any agent node on the tree canvas to inspect tier status, referral codes, and direct downlines.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
