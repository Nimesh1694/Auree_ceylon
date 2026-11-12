import React from 'react';

interface InquiryStatusBadgeProps {
    status: string;
    className?: string;
}

export default function InquiryStatusBadge({ status, className = '' }: InquiryStatusBadgeProps) {
    const config: Record<string, { label: string; bg: string; dot: string }> = {
        new: {
            label: 'New',
            bg: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/60',
            dot: 'bg-sky-500',
        },
        in_progress: {
            label: 'In Progress',
            bg: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60',
            dot: 'bg-amber-500',
        },
        waiting_customer: {
            label: 'Waiting on Customer',
            bg: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800/60',
            dot: 'bg-purple-500',
        },
        resolved: {
            label: 'Resolved',
            bg: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60',
            dot: 'bg-emerald-500',
        },
        closed: {
            label: 'Closed',
            bg: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
            dot: 'bg-slate-400',
        },
    };

    const item = config[status] ?? {
        label: status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
        bg: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
        dot: 'bg-slate-400',
    };

    return (
        <span
            className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${item.bg} ${className}`}
        >
            <span className={`w-1.5 h-1.5 rounded-full ${item.dot}`} />
            {item.label}
        </span>
    );
}

