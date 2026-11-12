import React from 'react';

interface InquiryPriorityBadgeProps {
    priority: string;
    className?: string;
}

export default function InquiryPriorityBadge({ priority, className = '' }: InquiryPriorityBadgeProps) {
    const config: Record<string, { label: string; bg: string }> = {
        low: {
            label: 'Low',
            bg: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        },
        normal: {
            label: 'Normal',
            bg: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/60',
        },
        high: {
            label: 'High',
            bg: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60 font-medium',
        },
        urgent: {
            label: 'Urgent',
            bg: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60 font-semibold',
        },
    };

    const item = config[priority] ?? {
        label: priority.charAt(0).toUpperCase() + priority.slice(1),
        bg: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    };

    return (
        <span
            className={`inline-flex items-center px-2 py-0.5 rounded-md text-xs border ${item.bg} ${className}`}
        >
            {item.label}
        </span>
    );
}

