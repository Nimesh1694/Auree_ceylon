import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    Lock,
    Search,
    RefreshCw,
    Clock,
    AlertCircle,
    ArrowLeft,
} from '@/Components/Icons';

interface ErrorProps {
    status: number;
}

interface ErrorConfig {
    code: number;
    title: string;
    headline: string;
    description: string;
    icon: React.ReactNode;
}

export default function Error({ status }: ErrorProps) {
    const page = usePage<PageProps<{ status: number }>>();
    const auth = page.props?.auth;
    const user = auth?.user;
    const permissions = auth?.permissions ?? [];
    const role = user?.role ?? '';

    const BUILTIN_MANAGEMENT_ROLES = [
        'owner',
        'super_admin',
        'operations_manager',
        'marketing_manager',
        'it_manager',
        'accountant',
        'agent_manager',
        'product_inventory_manager',
    ];
    const AGENT_ROLES = ['high_level_agent', 'low_level_agent'];
    const NON_MANAGEMENT_ROLES = ['customer', ...AGENT_ROLES];

    const errorConfigs: Record<number, ErrorConfig> = {
        403: {
            code: 403,
            title: 'Access Restricted',
            headline: 'Access Restricted',
            description: 'You do not have permission to access this page.',
            icon: <Lock className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
        },
        404: {
            code: 404,
            title: 'Page Not Found',
            headline: 'Page Not Found',
            description: 'The page you are looking for could not be found.',
            icon: <Search className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
        },
        419: {
            code: 419,
            title: 'Session Expired',
            headline: 'Session Expired',
            description: 'Your secure session has expired. Please refresh and try again.',
            icon: <RefreshCw className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
        },
        429: {
            code: 429,
            title: 'Too Many Requests',
            headline: 'Too Many Requests',
            description: 'Too many requests were received. Please wait before trying again.',
            icon: <Clock className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
        },
        500: {
            code: 500,
            title: 'Something Went Wrong',
            headline: 'Something Went Wrong',
            description: 'An unexpected error occurred while processing your request.',
            icon: <AlertCircle className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
        },
        503: {
            code: 503,
            title: 'Scheduled Maintenance',
            headline: 'Scheduled Maintenance',
            description: 'AURA LUXE is temporarily unavailable while we improve your experience.',
            icon: <RefreshCw className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
        },
    };

    const config = errorConfigs[status] || {
        code: status || 500,
        title: 'Error Encountered',
        headline: 'Something Went Wrong',
        description: 'An unexpected error occurred while processing your request.',
        icon: <AlertCircle className="w-8 h-8 text-amber-600 dark:text-amber-500" />,
    };

    const isAgent = Boolean(user && AGENT_ROLES.includes(role));
    const isCustomer = Boolean(user && role === 'customer');

    const hasCustomManagementRole = Boolean(
        user &&
            role !== '' &&
            !NON_MANAGEMENT_ROLES.includes(role) &&
            !BUILTIN_MANAGEMENT_ROLES.includes(role) &&
            (Boolean(user.role_id) || permissions.length > 0)
    );

    const isAdminOrStaff = Boolean(
        user &&
            !isCustomer &&
            !isAgent &&
            (Boolean(auth?.is_owner) ||
                Boolean(auth?.is_super_admin) ||
                Boolean(auth?.is_business_authority) ||
                Boolean(auth?.is_technical_authority) ||
                BUILTIN_MANAGEMENT_ROLES.includes(role) ||
                hasCustomManagementRole)
    );

    const hasPortalDestination = isAdminOrStaff || isAgent || isCustomer;

    const getDashboardRoute = () => {
        if (isAdminOrStaff) return '/admin/dashboard';
        if (isAgent) return '/agent/dashboard';
        if (isCustomer) return '/customer/orders';
        return '/';
    };

    const getDashboardLabel = () => {
        if (isAdminOrStaff) return 'Admin Console';
        if (isAgent) return 'Agent Portal';
        if (isCustomer) return 'My Account';
        return 'Return to Storefront';
    };

    return (
        <div className="min-h-screen bg-[#FAF8F5] dark:bg-[#0B0F19] text-slate-900 dark:text-slate-100 flex flex-col justify-between items-center p-4 sm:p-8 transition-colors duration-200">
            <Head title={`${config.title} — Aura Luxe`} />

            {/* Header */}
            <header className="text-center py-6">
                <Link
                    href="/"
                    className="font-serif text-2xl tracking-[0.25em] uppercase font-semibold text-slate-900 dark:text-slate-100 hover:text-amber-700 dark:hover:text-amber-400 transition-colors"
                >
                    Aura Luxe
                </Link>
                <span className="block text-[10px] tracking-[0.35em] uppercase text-slate-500 dark:text-slate-400 mt-1">
                    Botanical Cosmetics
                </span>
            </header>

            {/* Main Error Card */}
            <main className="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 sm:p-10 text-center shadow-xl shadow-slate-200/50 dark:shadow-none transition-all">
                {/* Status Badge */}
                <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-medium tracking-wider uppercase bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 mb-6">
                    <span>{config.code}</span>
                    <span>&mdash;</span>
                    <span>{config.title}</span>
                </div>

                {/* Icon Container */}
                <div className="w-16 h-16 mx-auto mb-6 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40 flex items-center justify-center">
                    {config.icon}
                </div>

                {/* Title */}
                <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-slate-100 mb-3 tracking-tight">
                    {config.headline}
                </h1>

                {/* Description */}
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-8 max-w-md mx-auto leading-relaxed">
                    {config.description}
                </p>

                {/* Actions */}
                <div className="flex flex-col sm:flex-row gap-3 justify-center items-center">
                    {status === 419 || status === 429 || status === 503 ? (
                        <>
                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-slate-900 dark:bg-amber-600 text-white font-medium text-sm hover:bg-slate-800 dark:hover:bg-amber-700 transition-colors shadow-sm"
                            >
                                <RefreshCw className="w-4 h-4" />
                                <span>{status === 503 ? 'Check Again' : 'Refresh Page'}</span>
                            </button>
                            <Link
                                href="/"
                                className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                            >
                                Return to Storefront
                            </Link>
                        </>
                    ) : status === 404 ? (
                        <>
                            <Link
                                href="/products"
                                className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-slate-900 dark:bg-amber-600 text-white font-medium text-sm hover:bg-slate-800 dark:hover:bg-amber-700 transition-colors shadow-sm"
                            >
                                Explore Collections
                            </Link>
                            <Link
                                href="/"
                                className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                            >
                                Return to Storefront
                            </Link>
                            {hasPortalDestination && (
                                <Link
                                    href={getDashboardRoute()}
                                    className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                >
                                    {getDashboardLabel()}
                                </Link>
                            )}
                        </>
                    ) : status === 403 ? (
                        <>
                            {hasPortalDestination && (
                                <Link
                                    href={getDashboardRoute()}
                                    className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-slate-900 dark:bg-amber-600 text-white font-medium text-sm hover:bg-slate-800 dark:hover:bg-amber-700 transition-colors shadow-sm"
                                >
                                    {getDashboardLabel()}
                                </Link>
                            )}
                            <Link
                                href="/"
                                className={`w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl font-medium text-sm transition-colors ${
                                    hasPortalDestination
                                        ? 'border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'
                                        : 'bg-slate-900 dark:bg-amber-600 text-white hover:bg-slate-800 dark:hover:bg-amber-700 shadow-sm'
                                }`}
                            >
                                Return to Storefront
                            </Link>
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                            >
                                <ArrowLeft className="w-4 h-4" />
                                <span>Go Back</span>
                            </button>
                        </>
                    ) : (
                        /* 500 or generic */
                        <>
                            <Link
                                href="/"
                                className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-slate-900 dark:bg-amber-600 text-white font-medium text-sm hover:bg-slate-800 dark:hover:bg-amber-700 transition-colors shadow-sm"
                            >
                                Return to Storefront
                            </Link>
                            <Link
                                href="/contact"
                                className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                            >
                                Contact Support
                            </Link>
                        </>
                    )}
                </div>
            </main>

            {/* Footer */}
            <footer className="text-center py-6 text-xs text-slate-500 dark:text-slate-400">
                &copy; {new Date().getFullYear()} Aura Luxe. Botanical Formulations & Luxury Care.
            </footer>
        </div>
    );
}

