import AdminLayout from '@/Layouts/AdminLayout';
import { PaginatedData } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface StockAlertRow {
    id: number;
    product_id: number;
    product_name: string | null;
    product_sku: string | null;
    live_stock: number | null;
    live_threshold: number | null;
    status: 'open' | 'acknowledged' | 'resolved';
    stock_at_trigger: number;
    threshold_at_trigger: number;
    current_stock: number;
    trigger_source: string;
    opened_at: string;
    last_notified_at: string | null;
    acknowledged_by_name: string | null;
    acknowledged_at: string | null;
    acknowledgement_note: string | null;
    resolved_at: string | null;
    resolution_type: string | null;
}

interface Props {
    alerts: PaginatedData<StockAlertRow>;
    filters: {
        search?: string;
        status?: string;
    };
    stats: {
        open: number;
        acknowledged: number;
        resolved: number;
    };
    canAcknowledge: boolean;
}

const STATUS_STYLES: Record<string, string> = {
    open: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
    acknowledged: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
    resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
};

export default function InventoryAlerts({ alerts, filters, stats, canAcknowledge }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'open');
    const [selectedAlert, setSelectedAlert] = useState<StockAlertRow | null>(null);
    const [acknowledgingAlert, setAcknowledgingAlert] = useState<StockAlertRow | null>(null);

    const ackForm = useForm({
        acknowledgement_note: '',
    });

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/inventory/alerts', {
            search: search || undefined,
            status: status !== 'all' ? status : 'all',
        }, { preserveState: true });
    };

    const handleAcknowledgeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!acknowledgingAlert) return;
        ackForm.post(`/admin/inventory/alerts/${acknowledgingAlert.id}/acknowledge`, {
            preserveScroll: true,
            onSuccess: () => {
                setAcknowledgingAlert(null);
                ackForm.reset();
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Automatic Low-Stock Alerts | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">
                            Automatic Low-Stock Alerts
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Monitor automated threshold triggers, acknowledge pending restocks, and review resolved alert history.
                        </p>
                    </div>
                    <Link
                        href="/admin/inventory"
                        className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-center"
                    >
                        &larr; Stock Inventory
                    </Link>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-rose-200/80 dark:border-rose-900/40">
                        <span className="text-[11px] uppercase tracking-wider text-rose-700 dark:text-rose-400 font-semibold">Open Alerts</span>
                        <span className="text-2xl font-bold font-mono text-rose-900 dark:text-rose-300 block mt-1">{stats.open}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-amber-200/80 dark:border-amber-900/40">
                        <span className="text-[11px] uppercase tracking-wider text-amber-700 dark:text-amber-400 font-semibold">Acknowledged</span>
                        <span className="text-2xl font-bold font-mono text-amber-900 dark:text-amber-300 block mt-1">{stats.acknowledged}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-emerald-200/80 dark:border-emerald-900/40">
                        <span className="text-[11px] uppercase tracking-wider text-emerald-700 dark:text-emerald-400 font-semibold">Resolved History</span>
                        <span className="text-2xl font-bold font-mono text-emerald-900 dark:text-emerald-300 block mt-1">{stats.resolved}</span>
                    </div>
                </div>

                {/* Filter */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row gap-3">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by product name or SKU..."
                        className="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white"
                    >
                        <option value="all">All Statuses</option>
                        <option value="open">Open</option>
                        <option value="acknowledged">Acknowledged</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <button type="submit" className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-xl">
                        Filter
                    </button>
                </form>

                {/* Alerts Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-xs">
                    {alerts.data.length === 0 ? (
                        <div className="p-12 text-center text-xs text-slate-500">
                            No low-stock alerts match the selected filter.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/70 dark:bg-slate-900/50">
                                        <th className="py-3 px-4 font-semibold">Product</th>
                                        <th className="py-3 px-4 font-semibold text-center">Current Stock</th>
                                        <th className="py-3 px-4 font-semibold text-center">Threshold</th>
                                        <th className="py-3 px-4 font-semibold text-center">Status</th>
                                        <th className="py-3 px-4 font-semibold">Opened</th>
                                        <th className="py-3 px-4 font-semibold">Acknowledgement</th>
                                        <th className="py-3 px-4 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    {alerts.data.map((alert) => (
                                        <tr key={alert.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/40">
                                            <td className="py-3.5 px-4">
                                                <div className="font-semibold text-slate-900 dark:text-white">{alert.product_name}</div>
                                                <div className="text-[11px] font-mono text-slate-400">{alert.product_sku || '—'}</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-center font-mono font-bold text-slate-900 dark:text-white">
                                                {alert.live_stock ?? alert.current_stock}
                                            </td>
                                            <td className="py-3.5 px-4 text-center font-mono text-slate-600 dark:text-slate-300">
                                                {alert.live_threshold ?? alert.threshold_at_trigger}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-semibold capitalize ${STATUS_STYLES[alert.status] ?? ''}`}>
                                                    {alert.status}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                                {new Date(alert.opened_at).toLocaleString()}
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                                {alert.acknowledged_by_name ? (
                                                    <div>
                                                        <div className="font-medium">{alert.acknowledged_by_name}</div>
                                                        {alert.acknowledgement_note && (
                                                            <div className="text-[11px] text-slate-400 truncate max-w-[180px]">{alert.acknowledgement_note}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedAlert(alert)}
                                                    className="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                                >
                                                    Details
                                                </button>
                                                {canAcknowledge && alert.status === 'open' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            ackForm.reset();
                                                            setAcknowledgingAlert(alert);
                                                        }}
                                                        className="text-amber-700 dark:text-amber-400 hover:underline font-medium"
                                                    >
                                                        Acknowledge
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {alerts.links && alerts.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 flex flex-wrap gap-1 justify-end">
                            {alerts.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`px-3 py-1 rounded-lg text-xs ${
                                        link.active
                                            ? 'bg-slate-900 text-white dark:bg-blue-600'
                                            : link.url
                                                ? 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                : 'text-slate-400 pointer-events-none'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Alert Details Modal */}
            {selectedAlert && (
                <div className="fixed inset-0 z-50 bg-slate-950/60 flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 space-y-3 border border-slate-200 dark:border-slate-800 text-xs">
                        <h3 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">
                            Alert Details: {selectedAlert.product_name}
                        </h3>
                        <div className="space-y-1.5 text-slate-600 dark:text-slate-300">
                            <p><strong>SKU:</strong> {selectedAlert.product_sku || '—'}</p>
                            <p><strong>Status:</strong> <span className="capitalize">{selectedAlert.status}</span></p>
                            <p><strong>Stock at Trigger:</strong> {selectedAlert.stock_at_trigger} (Threshold: {selectedAlert.threshold_at_trigger})</p>
                            <p><strong>Current Live Stock:</strong> {selectedAlert.live_stock ?? selectedAlert.current_stock}</p>
                            <p><strong>Trigger Source:</strong> {selectedAlert.trigger_source.replace('_', ' ')}</p>
                            <p><strong>Opened At:</strong> {new Date(selectedAlert.opened_at).toLocaleString()}</p>
                            {selectedAlert.acknowledged_at && (
                                <p><strong>Acknowledged:</strong> {selectedAlert.acknowledged_by_name} on {new Date(selectedAlert.acknowledged_at).toLocaleString()}</p>
                            )}
                            {selectedAlert.acknowledgement_note && (
                                <p><strong>Note:</strong> {selectedAlert.acknowledgement_note}</p>
                            )}
                            {selectedAlert.resolved_at && (
                                <p><strong>Resolved At:</strong> {new Date(selectedAlert.resolved_at).toLocaleString()} ({selectedAlert.resolution_type})</p>
                            )}
                        </div>
                        <div className="flex justify-end pt-2">
                            <button type="button" onClick={() => setSelectedAlert(null)} className="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Acknowledge Modal */}
            {acknowledgingAlert && (
                <div className="fixed inset-0 z-50 bg-slate-950/60 flex items-center justify-center p-4">
                    <form onSubmit={handleAcknowledgeSubmit} className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h3 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">
                            Acknowledge Alert: {acknowledgingAlert.product_name}
                        </h3>
                        <div>
                            <label className="block text-xs font-semibold mb-1">Follow-up Note (Optional)</label>
                            <textarea
                                rows={3}
                                value={ackForm.data.acknowledgement_note}
                                onChange={(e) => ackForm.setData('acknowledgement_note', e.target.value)}
                                placeholder="e.g., PO-2026-0012 submitted to preferred supplier."
                                className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs"
                            />
                            {ackForm.errors.acknowledgement_note && (
                                <p className="text-[11px] text-rose-600 mt-1">{ackForm.errors.acknowledgement_note}</p>
                            )}
                        </div>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setAcknowledgingAlert(null)} className="px-4 py-2 text-xs text-slate-600 rounded-xl">
                                Cancel
                            </button>
                            <button type="submit" disabled={ackForm.processing} className="px-4 py-2 text-xs font-semibold bg-amber-600 text-white rounded-xl disabled:opacity-50">
                                {ackForm.processing ? 'Saving...' : 'Confirm Acknowledgement'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AdminLayout>
    );
}

