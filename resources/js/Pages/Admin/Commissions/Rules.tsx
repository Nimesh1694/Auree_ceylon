import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, CommissionRuleChange } from '@/types';
import {
    ShieldCheck,
    AlertCircle,
    CheckCircle2,
    XCircle,
    Clock,
    TrendingUp,
} from '@/Components/Icons';

interface RulesProps extends PageProps {
    rules: {
        direct_agent_commission_percent: number;
        upline_agent_commission_percent: number;
        max_commission_percent_per_order: number;
        commission_return_period_days: number;
    };
    changeRequests: CommissionRuleChange[];
}

export default function Rules({ rules, changeRequests }: RulesProps) {
    const { auth, flash } = usePage<PageProps>().props;
    const isOwner = auth.user.role === 'owner';

    // Form for requesting a rule change
    const { data, setData, post, processing, errors, reset } = useForm({
        rule_key: 'direct_agent_commission_percent',
        new_value: '',
        reason: '',
    });

    // Review modal or inline review state
    const [reviewingId, setReviewingId] = useState<number | null>(null);
    const [reviewAction, setReviewAction] = useState<'approve' | 'reject'>('approve');
    const [reviewNotes, setReviewNotes] = useState('');
    const { post: postReview, processing: reviewing } = useForm();

    const handleRequestSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.commissions.rules.request'), {
            onSuccess: () => reset('new_value', 'reason'),
        });
    };

    const handleReviewSubmit = (id: number) => {
        postReview(
            route('admin.commissions.rules.review', id),
            {
                data: {
                    action: reviewAction,
                    review_notes: reviewNotes,
                },
                onSuccess: () => {
                    setReviewingId(null);
                    setReviewNotes('');
                },
            } as any
        );
    };

    const ruleDefinitions: Record<string, { label: string; unit: string; description: string }> = {
        direct_agent_commission_percent: {
            label: 'Direct Agent Commission Rate',
            unit: '%',
            description: 'Direct commission earned by the selling agent (high-level or low-level) on product subtotal.',
        },
        upline_agent_commission_percent: {
            label: 'Upline Override Commission Rate',
            unit: '%',
            description: 'Additional override commission earned by the direct active High-Level upline when a Low-Level agent makes a sale.',
        },
        max_commission_percent_per_order: {
            label: 'Maximum Commission Cap',
            unit: '%',
            description: 'Hard safety cap limiting the combined commission payout percentage on any single order.',
        },
        commission_return_period_days: {
            label: 'Customer Return Window',
            unit: ' Days',
            description: 'Holding buffer after order delivery and COD reconciliation before pending commission converts to approved wallet balance.',
        },
    };

    return (
        <AdminLayout>
            <Head title="Commission Rules & Governance - Admin" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Commission Rules & Governance</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Executive policy configuration, rate tiers, and change audit log.
                        </p>
                    </div>
                </div>

                {/* Flash Messages */}
                {flash.success && (
                    <div className="bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 px-4 py-3 rounded-2xl flex items-center gap-3">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <span className="text-sm font-medium">{flash.success}</span>
                    </div>
                )}
                {flash.error && (
                    <div className="bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 px-4 py-3 rounded-2xl flex items-center gap-3">
                        <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" />
                        <span className="text-sm font-medium">{flash.error}</span>
                    </div>
                )}

                {/* Active Rules Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {Object.entries(ruleDefinitions).map(([key, def]) => {
                        const val = rules[key as keyof typeof rules];
                        return (
                            <div key={key} className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                                <div className="flex items-center justify-between text-slate-400 mb-2">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{def.label}</span>
                                    <TrendingUp className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div className="text-3xl font-extrabold text-slate-900 dark:text-white font-mono">
                                    {val}
                                    <span className="text-lg font-medium text-slate-500 dark:text-slate-400">{def.unit}</span>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                                    {def.description}
                                </p>
                            </div>
                        );
                    })}
                </div>

                {/* Rule Change Form & Governance Notice */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-1 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
                        <div>
                            <h2 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <ShieldCheck className="w-5 h-5 text-emerald-700 dark:text-emerald-400" />
                                Request Rule Adjustment
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Submit a formal commission adjustment proposal. Owner approval is mandatory prior to activation.
                            </p>
                        </div>

                        <form onSubmit={handleRequestSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Commission Rule</label>
                                <select
                                    value={data.rule_key}
                                    onChange={(e) => setData('rule_key', e.target.value)}
                                    className="w-full px-3 py-2 text-xs border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                >
                                    {Object.entries(ruleDefinitions).map(([key, def]) => (
                                        <option key={key} value={key}>
                                            {def.label} ({rules[key as keyof typeof rules]}{def.unit})
                                        </option>
                                    ))}
                                </select>
                                {errors.rule_key && <p className="text-xs text-rose-600 dark:text-rose-400 mt-1">{errors.rule_key}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Proposed New Value
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.new_value}
                                        onChange={(e) => setData('new_value', e.target.value)}
                                        placeholder="e.g. 25.0"
                                        className="w-full px-3 py-2 text-xs border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-blue-500"
                                        required
                                    />
                                    <span className="absolute right-3 top-2 text-xs font-semibold text-slate-400">
                                        {ruleDefinitions[data.rule_key]?.unit || ''}
                                    </span>
                                </div>
                                {errors.new_value && <p className="text-xs text-rose-600 dark:text-rose-400 mt-1">{errors.new_value}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Executive Justification / Reason
                                </label>
                                <textarea
                                    rows={3}
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    placeholder="State business justification for rate modification..."
                                    className="w-full px-3 py-2 text-xs border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {errors.reason && <p className="text-xs text-rose-600 dark:text-rose-400 mt-1">{errors.reason}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-2.5 px-4 bg-slate-900 dark:bg-blue-600 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition cursor-pointer"
                            >
                                {processing ? 'Submitting...' : 'Submit Proposal for Owner Review'}
                            </button>
                        </form>
                    </div>

                    {/* Change History & Approvals */}
                    <div className="lg:col-span-2 bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden flex flex-col">
                        <div className="p-5 border-b border-slate-100 dark:border-slate-800/80">
                            <h2 className="text-base font-bold text-slate-900 dark:text-white">Governance & Audit History</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Log of all proposed, approved, and rejected commission parameter adjustments.
                            </p>
                        </div>

                        <div className="overflow-x-auto flex-1">
                            <table className="w-full text-left text-sm divide-y divide-slate-200 dark:divide-slate-800">
                                <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3.5 font-semibold">Rule Key</th>
                                        <th className="px-4 py-3.5 font-semibold">Old → New</th>
                                        <th className="px-4 py-3.5 font-semibold">Requester & Reason</th>
                                        <th className="px-4 py-3.5 font-semibold">Status</th>
                                        <th className="px-4 py-3.5 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {changeRequests.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-12 text-center text-slate-400 dark:text-slate-500 text-sm">
                                                No commission rule modification requests found.
                                            </td>
                                        </tr>
                                    ) : (
                                        changeRequests.map((req) => {
                                            const requester = req.requester || req.requested_by_user;
                                            const reviewer = req.reviewer || req.reviewed_by_user;

                                            return (
                                                <tr key={req.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                                        <div className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                                                            {ruleDefinitions[req.rule_key]?.label || req.rule_key}
                                                        </div>
                                                        <span className="text-[11px] font-mono text-slate-400 dark:text-slate-500">
                                                            {req.rule_key}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-1.5 text-xs font-semibold">
                                                            <span className="text-slate-400">{req.old_value ?? '—'}</span>
                                                            <span className="text-slate-300 dark:text-slate-600">→</span>
                                                            <span className="text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-1.5 py-0.5 rounded font-bold">
                                                                {req.new_value}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 max-w-xs">
                                                        <div className="text-xs font-medium text-slate-800 dark:text-slate-200">
                                                            {requester?.name || `User #${req.requested_by}`}
                                                        </div>
                                                        <div className="text-[11px] text-slate-500 dark:text-slate-400 italic mt-0.5 line-clamp-2">
                                                            "{req.reason}"
                                                        </div>
                                                        <div className="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                                            {new Date(req.created_at).toLocaleDateString()}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {req.status === 'pending' && (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                                <Clock className="w-3.5 h-3.5" />
                                                                Pending Review
                                                            </span>
                                                        )}
                                                        {req.status === 'approved' && (
                                                            <div>
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                                    <CheckCircle2 className="w-3.5 h-3.5" />
                                                                    Approved
                                                                </span>
                                                                {reviewer && (
                                                                    <div className="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                                                        by {reviewer.name}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}
                                                        {req.status === 'rejected' && (
                                                            <div>
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                                                    <XCircle className="w-3.5 h-3.5" />
                                                                    Rejected
                                                                </span>
                                                                {reviewer && (
                                                                    <div className="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                                                        by {reviewer.name}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {req.status === 'pending' && isOwner && (
                                                            <div className="flex items-center justify-end gap-2">
                                                                {reviewingId === req.id ? (
                                                                    <div className="flex flex-col gap-2 p-2 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-200 dark:border-slate-700 text-left min-w-[220px]">
                                                                        <input
                                                                            type="text"
                                                                            placeholder="Review notes (optional)..."
                                                                            value={reviewNotes}
                                                                            onChange={(e) => setReviewNotes(e.target.value)}
                                                                            className="w-full text-xs px-2 py-1 border border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white rounded-lg"
                                                                        />
                                                                        <div className="flex items-center gap-2">
                                                                            <button
                                                                                onClick={() => {
                                                                                    setReviewAction('approve');
                                                                                    handleReviewSubmit(req.id);
                                                                                }}
                                                                                disabled={reviewing}
                                                                                className="flex-1 py-1 px-2 bg-emerald-700 text-white rounded-lg text-xs font-semibold hover:bg-emerald-800 disabled:opacity-50 cursor-pointer"
                                                                            >
                                                                                Confirm Approve
                                                                            </button>
                                                                            <button
                                                                                onClick={() => {
                                                                                    setReviewAction('reject');
                                                                                    handleReviewSubmit(req.id);
                                                                                }}
                                                                                disabled={reviewing}
                                                                                className="flex-1 py-1 px-2 bg-rose-600 text-white rounded-lg text-xs font-semibold hover:bg-rose-700 disabled:opacity-50 cursor-pointer"
                                                                            >
                                                                                Reject
                                                                            </button>
                                                                            <button
                                                                                onClick={() => setReviewingId(null)}
                                                                                className="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                                                                            >
                                                                                Cancel
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                ) : (
                                                                    <button
                                                                        onClick={() => setReviewingId(req.id)}
                                                                        className="px-3 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition cursor-pointer"
                                                                    >
                                                                        Review
                                                                    </button>
                                                                )}
                                                            </div>
                                                        )}
                                                        {req.status === 'pending' && !isOwner && (
                                                            <span className="text-xs text-slate-400 italic">Owner Approval Required</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
