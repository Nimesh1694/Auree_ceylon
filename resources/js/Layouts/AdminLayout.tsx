import * as Icons from '@/Components/Icons';
import { useTheme } from '@/hooks/useTheme';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import React, { PropsWithChildren, useEffect, useMemo, useRef, useState } from 'react';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    show: boolean;
    badge?: number | string;
    hasDot?: boolean;
}

interface NavGroup {
    group: string;
    items: NavItem[];
}

export default function AdminLayout({ children }: PropsWithChildren) {
    const { props, url } = usePage<PageProps>();
    const { auth, flash } = props;

    const user = auth.user;
    const permissions = auth.permissions ?? [];
    const isOwner = Boolean(auth.is_owner || user?.role === 'owner');
    const isSuperAdmin = Boolean(auth.is_super_admin || user?.role === 'super_admin' || user?.role === 'it_manager');
    const isAgentManager = Boolean(user?.role === 'agent_manager');

    // Theme (Light / Dark mode) with reactive Hook
    const { theme, toggleTheme, isDark } = useTheme();

    // Sidebar Visibility State: Always Open by default, with Hide option
    const [sidebarOpen, setSidebarOpen] = useState<boolean>(() => {
        if (typeof window === 'undefined') return true;
        const saved = localStorage.getItem('admin_sidebar_open');
        return saved !== null ? saved === 'true' : true;
    });

    const [mobileOpen, setMobileOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);

    const toggleSidebar = () => {
        setSidebarOpen((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('admin_sidebar_open', String(next));
            } catch {
                // ignore localStorage quota errors
            }
            return next;
        });
    };

    // Close user menu on outside click
    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
                setUserMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const hasPermission = (perm: string) => {
        if (isOwner) return true;
        return permissions.includes(perm);
    };

    // Pending counts from props
    const pendingActionCounts = (props as unknown as { pendingActionCounts?: { payouts?: number; permission_requests?: number; hierarchy_requests?: number; rule_changes?: number; low_stock?: number; total_pending?: number; pending_applications?: number; pending_kyc?: number; pending_hierarchy?: number } })?.pendingActionCounts;
    const pendingPayouts = pendingActionCounts?.payouts ?? 0;
    const pendingPermRequests = pendingActionCounts?.permission_requests ?? 0;
    const pendingHierarchyMoves = pendingActionCounts?.hierarchy_requests ?? (pendingActionCounts?.pending_hierarchy ?? 0);
    const pendingRuleChanges = pendingActionCounts?.rule_changes ?? 0;
    const lowStockAlerts = pendingActionCounts?.low_stock ?? 0;
    const pendingApplications = pendingActionCounts?.pending_applications ?? 0;
    const pendingKyc = pendingActionCounts?.pending_kyc ?? 0;

    const totalPendingApprovals = isAgentManager && !isOwner
        ? (pendingApplications + pendingKyc + pendingHierarchyMoves)
        : (pendingActionCounts?.total_pending ?? (pendingPayouts + pendingPermRequests + pendingHierarchyMoves + pendingRuleChanges));
    const totalPendingAlerts = (totalPendingApprovals + (isOwner ? lowStockAlerts : 0));

    // Nav Groups according to Approved Final Architecture Plan
    const navGroups: NavGroup[] = isSuperAdmin && !isOwner ? [
        // 1. System Overview (Super Admin)
        {
            group: 'System Overview',
            items: [
                {
                    label: 'Technical Console',
                    href: '/admin/dashboard',
                    icon: Icons.LayoutDashboard,
                    show: true,
                },
            ],
        },
        // 2. Access & Identity
        {
            group: 'Access & Identity',
            items: [
                {
                    label: 'Staff Directory',
                    href: '/admin/staff',
                    icon: Icons.User,
                    show: true,
                },
                {
                    label: 'Roles & Permissions',
                    href: '/admin/roles',
                    icon: Icons.Shield,
                    show: true,
                },
                {
                    label: 'Permission Requests',
                    href: '/admin/roles/permission-requests',
                    icon: Icons.CheckSquare,
                    show: true,
                    badge: pendingPermRequests > 0 ? pendingPermRequests : undefined,
                },
            ],
        },
        // 3. Security & Audit
        {
            group: 'Security & Audit',
            items: [
                {
                    label: 'Audit Logs',
                    href: '/admin/system/logs',
                    icon: Icons.History,
                    show: true,
                },
            ],
        },
        // 4. Infrastructure & Operations
        {
            group: 'Infrastructure & Operations',
            items: [
                {
                    label: 'System Diagnostics',
                    href: '/admin/system/health',
                    icon: Icons.Settings,
                    show: true,
                },
            ],
        },
        // 5. Storefront Support (Troubleshooting)
        {
            group: 'Storefront Support',
            items: [
                {
                    label: 'Orders Inspection',
                    href: '/admin/orders',
                    icon: Icons.Activity,
                    show: hasPermission('orders.view'),
                },
                {
                    label: 'Customer Directory',
                    href: '/admin/customers',
                    icon: Icons.Users,
                    show: isOwner || isSuperAdmin || hasPermission('users.view'),
                },
                {
                    label: 'Customer Inquiries',
                    href: '/admin/inquiries',
                    icon: Icons.Mail,
                    show: isOwner || hasPermission('inquiries.manage'),
                },
                {
                    label: 'Agent Network',
                    href: '/admin/agents',
                    icon: Icons.GitBranch,
                    show: hasPermission('agents.view'),
                },
                {
                    label: 'Product Catalog',
                    href: '/admin/products',
                    icon: Icons.Package,
                    show: hasPermission('products.view'),
                },
                {
                    label: 'Categories',
                    href: '/admin/categories',
                    icon: Icons.Layers,
                    show: hasPermission('categories.manage'),
                },
                {
                    label: 'Stock Inventory',
                    href: '/admin/inventory',
                    icon: Icons.Boxes,
                    show: hasPermission('inventory.view'),
                    badge: lowStockAlerts > 0 ? `${lowStockAlerts} low` : undefined,
                },
            ],
        },
    ] : isAgentManager && !isOwner ? [
        // 1. Agent Overview (Agent Manager)
        {
            group: 'Agent Overview',
            items: [
                {
                    label: 'Operations Console',
                    href: '/admin/dashboard',
                    icon: Icons.LayoutDashboard,
                    show: true,
                },
            ],
        },
        // 2. Agent Management
        {
            group: 'Agent Management',
            items: [
                {
                    label: 'Agent Directory',
                    href: '/admin/agents',
                    icon: Icons.Users,
                    show: hasPermission('agents.view'),
                },
                {
                    label: 'Applications Queue',
                    href: '/admin/agents?status=pending',
                    icon: Icons.UserCheck,
                    show: hasPermission('agents.applications.review') || hasPermission('agents.status.update'),
                    badge: pendingApplications > 0 ? pendingApplications : undefined,
                    hasDot: pendingApplications > 0,
                },
                {
                    label: 'KYC Verification',
                    href: '/admin/agents/kyc-queue',
                    icon: Icons.ShieldCheck,
                    show: hasPermission('agents.kyc.verify'),
                    badge: pendingKyc > 0 ? pendingKyc : undefined,
                },
                {
                    label: 'Recruitment & Invitations',
                    href: '/admin/agents/invitations',
                    icon: Icons.Mail,
                    show: hasPermission('agents.invitations.view') || hasPermission('agents.invitations.create_hla'),
                },
            ],
        },
        // 3. Network & Hierarchy
        {
            group: 'Network & Hierarchy',
            items: [
                {
                    label: 'Network Tree Explorer',
                    href: '/admin/agents/hierarchy',
                    icon: Icons.GitBranch,
                    show: hasPermission('agents.view'),
                },
                {
                    label: 'Hierarchy Move Requests',
                    href: '/admin/agents/hierarchy-requests',
                    icon: Icons.ArrowRightLeft,
                    show: hasPermission('agents.hierarchy.request'),
                    badge: pendingHierarchyMoves > 0 ? pendingHierarchyMoves : undefined,
                },
                {
                    label: 'Hierarchy Integrity',
                    href: '/admin/agents/hierarchy/integrity',
                    icon: Icons.ShieldAlert,
                    show: isOwner || hasPermission('agents.hierarchy.integrity') || (isSuperAdmin && hasPermission('system.health.view')),
                },
            ],
        },
        // 4. Performance & Reports
        {
            group: 'Performance & Reports',
            items: [
                {
                    label: 'Sales Leaderboard',
                    href: '/admin/agents/performance',
                    icon: Icons.TrendingUp,
                    show: hasPermission('agents.view'),
                },
                {
                    label: 'Activity Audit Trail',
                    href: '/admin/agents/activity',
                    icon: Icons.History,
                    show: hasPermission('agents.view'),
                },
                {
                    label: 'Export Reports',
                    href: '/admin/agents/reports',
                    icon: Icons.Download,
                    show: hasPermission('agents.view'),
                },
            ],
        },
        // 5. Storefront Support (Read-Only)
        {
            group: 'Storefront Support',
            items: [
                {
                    label: 'Orders Inspection',
                    href: '/admin/orders',
                    icon: Icons.Activity,
                    show: hasPermission('orders.view'),
                },
                {
                    label: 'Customer Directory',
                    href: '/admin/customers',
                    icon: Icons.Users,
                    show: isOwner || isSuperAdmin || hasPermission('users.view'),
                },
                {
                    label: 'Customer Inquiries',
                    href: '/admin/inquiries',
                    icon: Icons.Mail,
                    show: isOwner || hasPermission('inquiries.manage'),
                },
                {
                    label: 'Product Catalog',
                    href: '/admin/products',
                    icon: Icons.Package,
                    show: hasPermission('products.view'),
                },
            ],
        },
    ] : [
        // 1. Business Overview (Owner & Operational Staff)
        {
            group: 'Business Overview',
            items: [
                {
                    label: 'Executive Dashboard',
                    href: '/admin/dashboard',
                    icon: Icons.LayoutDashboard,
                    show: true,
                },
            ],
        },
        // 2. Business Operations
        {
            group: 'Business Operations',
            items: [
                {
                    label: 'Orders & Fulfillment',
                    href: '/admin/orders',
                    icon: Icons.Activity,
                    show: hasPermission('orders.view'),
                },
                {
                    label: 'Customer Directory',
                    href: '/admin/customers',
                    icon: Icons.Users,
                    show: isOwner || isSuperAdmin || user?.role === 'operations_manager' || user?.role === 'marketing_manager' || hasPermission('users.view'),
                },
                {
                    label: 'Customer Inquiries',
                    href: '/admin/inquiries',
                    icon: Icons.Mail,
                    show: isOwner || hasPermission('inquiries.manage'),
                },
                {
                    label: 'Agent Network',
                    href: '/admin/agents',
                    icon: Icons.GitBranch,
                    show: hasPermission('agents.view'),
                },
                {
                    label: 'Product Catalog',
                    href: '/admin/products',
                    icon: Icons.Package,
                    show: hasPermission('products.view'),
                },
                {
                    label: 'Categories',
                    href: '/admin/categories',
                    icon: Icons.Layers,
                    show: hasPermission('categories.manage'),
                },
                {
                    label: 'Stock Inventory',
                    href: '/admin/inventory',
                    icon: Icons.Boxes,
                    show: hasPermission('inventory.view'),
                    badge: lowStockAlerts > 0 ? `${lowStockAlerts} low` : undefined,
                },
            ],
        },
        // 3. Finance & Governance
        {
            group: 'Finance & Governance',
            items: [
                {
                    label: 'Approval Center',
                    href: '/admin/approvals',
                    icon: Icons.CheckSquare,
                    show: isOwner,
                    badge: totalPendingApprovals > 0 ? totalPendingApprovals : undefined,
                    hasDot: totalPendingApprovals > 0,
                },
                {
                    label: 'Payout Requests',
                    href: '/admin/payouts',
                    icon: Icons.DollarSign,
                    show: isOwner || user?.role === 'accountant',
                    badge: pendingPayouts > 0 ? pendingPayouts : undefined,
                },
                {
                    label: 'Financial Contribution',
                    href: '/admin/finance/contribution',
                    icon: Icons.TrendingUp,
                    show: isOwner || user?.role === 'accountant',
                },
                {
                    label: 'Commission Rules',
                    href: '/admin/commissions/rules',
                    icon: Icons.Tag,
                    show: isOwner || hasPermission('commissions.rules.request'),
                },
                {
                    label: 'Commission Ledger',
                    href: '/admin/commissions/ledger',
                    icon: Icons.FileCheck,
                    show: isOwner || hasPermission('commissions.ledger.view'),
                },
            ],
        },
        // 4. Audit & Compliance / Technical Administration
        {
            group: (isOwner || user?.role === 'accountant') ? 'Audit & Compliance' : 'Technical Administration',
            items: [
                {
                    label: 'Staff Administration',
                    href: '/admin/staff',
                    icon: Icons.User,
                    show: isSuperAdmin || isOwner,
                },
                {
                    label: 'Custom Roles & Perms',
                    href: '/admin/roles',
                    icon: Icons.Shield,
                    show: isSuperAdmin || isOwner,
                },
                {
                    label: 'Audit Logs',
                    href: '/admin/system/logs',
                    icon: Icons.History,
                    show: isOwner || isSuperAdmin || user?.role === 'accountant' || hasPermission('system.logs.view') || hasPermission('system.health.view'),
                },
                {
                    label: 'System Diagnostics',
                    href: '/admin/system/health',
                    icon: Icons.Settings,
                    show: isSuperAdmin || isOwner || hasPermission('system.health.view'),
                },
            ],
        },
    ];

    const visibleGroups = navGroups
        .map(g => ({ ...g, items: g.items.filter(i => i.show) }))
        .filter(g => g.items.length > 0);

    // Strictly resolve the single active sidebar navigation item
    const activeItemHref = useMemo(() => {
        const allItems = visibleGroups.flatMap(g => g.items);
        if (allItems.length === 0) return null;

        // 1. First priority: Exact match with full URL (including exact query string)
        const exactMatch = allItems.find(item => item.href === url);
        if (exactMatch) {
            return exactMatch.href;
        }

        const [currentPath, currentQuery] = url.split('?');
        const currentParams = new URLSearchParams(currentQuery || '');

        // 2. Second priority: If current URL has a query string, match items that require specific query parameters
        const queryMatches = allItems.filter(item => {
            if (!item.href.includes('?')) return false;
            const [itemPath, itemQuery] = item.href.split('?');
            if (itemPath !== currentPath) return false;
            const itemParams = new URLSearchParams(itemQuery);
            for (const [key, val] of itemParams.entries()) {
                if (currentParams.get(key) !== val) return false;
            }
            return true;
        });

        if (queryMatches.length > 0) {
            // Sort by length descending to pick the most specific query match
            queryMatches.sort((a, b) => b.href.length - a.href.length);
            return queryMatches[0].href;
        }

        // 3. Third priority: Path matching for items without query strings (longest prefix / base match)
        const pathMatches = allItems.filter(item => {
            if (item.href.includes('?')) {
                // Do not match query-specific items on base path alone
                return false;
            }
            const itemBase = item.href.split('?')[0];
            if (itemBase === currentPath) {
                return true;
            }
            // Match sub-routes (e.g. /admin/agents/123 -> /admin/agents) with slash boundary
            return currentPath.startsWith(itemBase + '/');
        });

        if (pathMatches.length > 0) {
            // Sort by base path length descending to pick the most specific path (e.g. /admin/agents/invitations over /admin/agents)
            pathMatches.sort((a, b) => b.href.split('?')[0].length - a.href.split('?')[0].length);
            return pathMatches[0].href;
        }

        return null;
    }, [url, visibleGroups]);

    const isCurrent = (href: string) => href === activeItemHref;

    const roleBadgeColor = isOwner
        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30'
        : isSuperAdmin
        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30'
        : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30';

    return (
        <div className="min-h-screen bg-[#F8FAFC] dark:bg-[#0B0F19] text-slate-800 dark:text-slate-100 flex flex-col font-sans antialiased selection:bg-rose-500/20 selection:text-rose-900 transition-colors duration-200">
            {/* Top Navigation Bar */}
            <header className="sticky top-0 z-30 bg-white/95 dark:bg-[#0F172A]/95 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/80 shadow-2xs h-16 transition-colors duration-200">
                <div className="w-full px-4 sm:px-6 h-full flex items-center justify-between gap-4">
                    {/* Left: Sidebar Hide/Show Toggle & Breadcrumbs */}
                    <div className="flex items-center gap-3 shrink-0">
                        {/* Mobile Drawer Button */}
                        <button
                            onClick={() => setMobileOpen(!mobileOpen)}
                            className="lg:hidden p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            title="Toggle menu"
                            aria-label="Toggle mobile menu"
                        >
                            <Icons.Menu className="w-5 h-5" />
                        </button>

                        {/* Desktop Show / Hide Toggle Button */}
                        <button
                            onClick={toggleSidebar}
                            className="hidden lg:flex items-center gap-2 p-2 rounded-xl text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            title={sidebarOpen ? 'Hide Menu' : 'Show Menu'}
                            aria-label="Toggle sidebar menu"
                        >
                            <Icons.Menu className="w-5 h-5" />
                            {!sidebarOpen && (
                                <span className="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    Show Menu
                                </span>
                            )}
                        </button>

                        {/* Breadcrumbs */}
                        <div className="hidden sm:flex items-center gap-2 text-xs">
                            <Link href="/admin/dashboard" className="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium">
                                Aura Luxe
                            </Link>
                            <span className="text-slate-300 dark:text-slate-700">/</span>
                            <span className="font-semibold text-slate-800 dark:text-slate-200">
                                {isOwner
                                    ? (url.includes('view=agent_network') ? 'Agent Operations Console' : url.includes('view=technical') ? 'Technical Console' : 'Executive Dashboard')
                                    : isSuperAdmin ? 'Technical Console' : isAgentManager ? 'Agent Operations Console' : 'Management Portal'}
                            </span>
                        </div>
                    </div>

                    {/* Right: Theme Switcher, Storefront Button, Notifications, User Menu */}
                    <div className="flex items-center gap-3">
                        {/* Prominent Storefront Button */}
                        <Link
                            href="/"
                            target="_blank"
                            className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 rounded-xl shadow-xs shadow-blue-500/20 hover:shadow-md hover:shadow-blue-500/30 transition-all cursor-pointer"
                            title="Visit Public Storefront"
                        >
                            <Icons.ShoppingCart className="w-3.5 h-3.5 text-white" />
                            <span>Storefront</span>
                            <Icons.ExternalLink className="w-3 h-3 text-blue-100" />
                        </Link>

                        {/* Theme Switcher Toggle (Light / Dark Mode) */}
                        <button
                            onClick={toggleTheme}
                            className="p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            title={isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
                            aria-label="Toggle theme"
                        >
                            {isDark ? (
                                <Icons.Sun className="w-5 h-5 text-amber-400" />
                            ) : (
                                <Icons.Moon className="w-5 h-5 text-slate-600" />
                            )}
                        </button>

                        {/* Notification Bell */}
                        <div className="relative">
                            <button
                                onClick={() => {
                                    if (isOwner) {
                                        window.location.href = '/admin/approvals';
                                    } else if (isAgentManager) {
                                        if (pendingApplications > 0) {
                                            window.location.href = '/admin/agents?status=pending';
                                        } else if (pendingKyc > 0) {
                                            window.location.href = '/admin/agents/kyc-queue';
                                        } else if (pendingHierarchyMoves > 0) {
                                            window.location.href = '/admin/agents/hierarchy-requests';
                                        } else {
                                            window.location.href = '/admin/dashboard';
                                        }
                                    } else if (pendingPayouts > 0) {
                                        window.location.href = '/admin/payouts';
                                    } else if (pendingPermRequests > 0) {
                                        window.location.href = '/admin/roles/permission-requests';
                                    } else {
                                        window.location.href = '/admin/dashboard';
                                    }
                                }}
                                className="relative p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                                title="Notifications"
                            >
                                <Icons.Bell className="w-5 h-5" />
                                {totalPendingAlerts > 0 && (
                                    <span className="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500 text-white"></span>
                                    </span>
                                )}
                            </button>
                        </div>

                        {/* User Profile Menu */}
                        <div className="relative" ref={userMenuRef}>
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2.5 p-1 pl-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            >
                                <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-xs font-semibold shadow-sm">
                                    {user?.name ? user.name.charAt(0).toUpperCase() : 'A'}
                                </div>
                                <div className="hidden md:block text-left">
                                    <div className="text-xs font-semibold text-slate-900 dark:text-white leading-tight">
                                        {user?.name ?? 'Account'}
                                    </div>
                                    <div className="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider font-mono">
                                        {user?.role.replace('_', ' ')}
                                    </div>
                                </div>
                                <Icons.ChevronDown className="w-3.5 h-3.5 text-slate-400 ml-0.5" />
                            </button>

                            {/* Dropdown Card */}
                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-56 bg-white dark:bg-[#1E293B] rounded-2xl shadow-xl border border-slate-200/80 dark:border-slate-700/80 py-1.5 z-50 text-xs transition-colors">
                                    <div className="px-4 py-3 border-b border-slate-100 dark:border-slate-700/60">
                                        <div className="font-semibold text-slate-900 dark:text-white">{user?.name}</div>
                                        <div className="text-slate-500 dark:text-slate-400 text-[11px] truncate">{user?.email}</div>
                                        <div className="mt-2 flex items-center gap-1.5">
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full border font-mono font-medium ${roleBadgeColor}`}>
                                                {isOwner ? 'Business Owner' : user?.role.replace('_', ' ')}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="py-1">
                                        <Link
                                            href="/profile"
                                            className="flex items-center gap-2.5 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 font-medium"
                                        >
                                            <Icons.User className="w-4 h-4 text-slate-400" />
                                            <span>My Profile</span>
                                        </Link>
                                        <Link
                                            href="/two-factor/setup"
                                            className="flex items-center gap-2.5 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 font-medium"
                                        >
                                            <Icons.Lock className="w-4 h-4 text-slate-400" />
                                            <span>Security & MFA</span>
                                        </Link>
                                        <button
                                            onClick={toggleTheme}
                                            className="w-full text-left flex items-center justify-between px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 font-medium cursor-pointer"
                                        >
                                            <div className="flex items-center gap-2.5">
                                                {isDark ? <Icons.Sun className="w-4 h-4 text-amber-400" /> : <Icons.Moon className="w-4 h-4 text-slate-400" />}
                                                <span>Theme</span>
                                            </div>
                                            <span className="text-[10px] uppercase font-mono text-slate-400 font-semibold">
                                                {isDark ? 'Dark' : 'Light'}
                                            </span>
                                        </button>
                                    </div>

                                    <div className="border-t border-slate-100 dark:border-slate-700/60 pt-1">
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="w-full text-left flex items-center gap-2.5 px-4 py-2.5 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 font-semibold cursor-pointer"
                                        >
                                            <Icons.XCircle className="w-4 h-4 text-rose-500" />
                                            <span>Sign out</span>
                                        </Link>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            {/* Flash Banners */}
            {Boolean(flash?.success) && (
                <div className="bg-emerald-50 dark:bg-emerald-950/50 border-b border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs py-2 px-4 text-center font-medium flex items-center justify-center gap-2">
                    <Icons.CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>{String(flash.success)}</span>
                </div>
            )}
            {Boolean(flash?.error) && (
                <div className="bg-rose-50 dark:bg-rose-950/50 border-b border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-xs py-2 px-4 text-center font-medium flex items-center justify-center gap-2">
                    <Icons.AlertCircle className="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                    <span>{String(flash.error)}</span>
                </div>
            )}

            {/* Main Workspace Layout */}
            <div className="flex-1 flex w-full relative">
                {/* Mobile Drawer Overlay */}
                {mobileOpen && (
                    <div
                        className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 lg:hidden"
                        onClick={() => setMobileOpen(false)}
                    />
                )}

                {/* Left Sidebar: Sticky full-height with Independent Menu Scroll */}
                <aside
                    className={`
                        fixed lg:sticky top-16 z-40
                        h-[calc(100vh-4rem)]
                        bg-white dark:bg-[#0F172A] border-r border-slate-200/80 dark:border-slate-800/80
                        transition-all duration-300 ease-in-out
                        flex flex-col justify-between shrink-0
                        ${sidebarOpen ? 'lg:w-64' : 'lg:w-0 lg:overflow-hidden lg:border-r-0'}
                        ${mobileOpen ? 'w-64 translate-x-0' : '-translate-x-full lg:translate-x-0'}
                    `}
                >
                    {/* Fixed Top Brand Header (No Mac dots, No search input) */}
                    <div className="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800/80 shrink-0 flex items-center justify-between">
                        <Link href="/admin/dashboard" className="flex items-center gap-3 overflow-hidden">
                            <div className="w-10 h-10 rounded-2xl bg-[#2563EB] text-white flex items-center justify-center shadow-md shadow-blue-500/20 shrink-0">
                                <Icons.Sparkles className="w-5 h-5 text-white" />
                            </div>
                            <div className="truncate">
                                <div className="font-sans text-base font-bold tracking-tight text-slate-900 dark:text-white leading-tight">
                                    Aura Luxe
                                </div>
                                <div className="text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                                    Studio &bull; Management
                                </div>
                            </div>
                        </Link>

                        {/* Quick Hide Button in Sidebar Header */}
                        <button
                            onClick={toggleSidebar}
                            className="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                            title="Hide Menu"
                        >
                            <Icons.ChevronLeft className="w-4 h-4" />
                        </button>
                    </div>

                    {/* Independently Scrollable Navigation Menu (Only this scrolls up and down) */}
                    <div className="flex-1 overflow-y-auto overscroll-contain p-3 space-y-6 custom-scrollbar">
                        {visibleGroups.map((grp, gIdx) => (
                            <div key={gIdx} className="space-y-1">
                                <div className="px-3 pb-1.5 text-xs font-semibold text-slate-400 dark:text-slate-500 tracking-wide">
                                    {grp.group}
                                </div>

                                {grp.items.map((item, iIdx) => {
                                    const active = isCurrent(item.href);
                                    const IconComp = item.icon;

                                    return (
                                        <div key={iIdx} className="relative">
                                            <Link
                                                href={item.href}
                                                onClick={() => setMobileOpen(false)}
                                                className={`
                                                    flex items-center rounded-2xl transition-all duration-150 gap-3 px-3.5 py-2.5 text-xs
                                                    ${
                                                        active
                                                            ? 'bg-slate-100 dark:bg-[#1E293B] text-slate-900 dark:text-white font-semibold shadow-2xs'
                                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800/60 font-medium'
                                                    }
                                                `}
                                            >
                                                <div className="relative shrink-0">
                                                    <IconComp className={`w-5 h-5 ${active ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white'}`} />
                                                </div>

                                                <span className="truncate flex-1">{item.label}</span>

                                                {item.badge && (
                                                    <span className="bg-[#EF4444] text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-2xs">
                                                        {item.badge}
                                                    </span>
                                                )}
                                            </Link>
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>

                    {/* Fixed Bottom Profile Section */}
                    <div className="p-3 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                        <div className="flex items-center justify-between gap-2 p-2 rounded-2xl bg-white dark:bg-[#1E293B] border border-slate-200/60 dark:border-slate-700/60 shadow-2xs">
                            <div className="flex items-center gap-2.5 overflow-hidden min-w-0">
                                <div className="w-8 h-8 rounded-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center text-xs font-bold shrink-0">
                                    {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                                </div>
                                <div className="truncate min-w-0">
                                    <div className="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                        {user?.name}
                                    </div>
                                    <div className="text-[10px] text-slate-400 dark:text-slate-400 truncate">
                                        {isOwner ? 'Business Owner' : user?.email}
                                    </div>
                                </div>
                            </div>

                            {/* Clear Sign Out Button */}
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 border border-rose-200/60 dark:border-rose-900/60 rounded-xl cursor-pointer transition-colors shrink-0 shadow-2xs"
                                title="Sign Out of Management Portal"
                            >
                                <Icons.LogOut className="w-3.5 h-3.5 text-rose-500 shrink-0" />
                                <span>Sign Out</span>
                            </Link>
                        </div>
                    </div>
                </aside>

                {/* Main Content Canvas */}
                <main className="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto transition-all duration-300">
                    {children}
                </main>
            </div>
        </div>
    );
}
