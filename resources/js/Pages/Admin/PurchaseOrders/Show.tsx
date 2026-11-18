import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface PoItemDetail {
    id: number;
    product_id: number;
    product_name: string;
    product_sku: string | null;
    supplier_sku: string | null;
    ordered_qty: number;
    received_qty: number;
    remaining_qty: number;
    unit_cost: string | null;
    line_total: string | null;
    current_stock: number | null;
}

interface ReceiptDetail {
    id: number;
    receipt_number: string;
    received_by_name: string | null;
    received_at: string;
    notes: string | null;
    items: Array<{
        id: number;
        product_name: string | null;
        product_sku: string | null;
        quantity_received: number;
        unit_cost: string | null;
    }>;
}

interface MovementDetail {
    id: number;
    product_name: string | null;
    quantity: number;
    previous_stock: number;
    new_stock: number;
    reason: string | null;
    actor_name: string | null;
    created_at: string;
}

interface Props {
    purchaseOrder: {
        id: number;
        po_number: string;
        status: string;
        supplier: { id: number; name: string; code: string } | null;
        expected_delivery_date: string | null;
        ordered_at: string | null;
        received_at: string | null;
        cancelled_at: string | null;
        cancellation_reason: string | null;
        notes: string | null;
        subtotal: string | null;
        tax_amount: string | null;
        shipping_cost: string | null;
        total_cost: string | null;
        creator_name: string | null;
        approver_name: string | null;
        canceller_name: string | null;
        items: PoItemDetail[];
        receipts: ReceiptDetail[];
        inventory_movements: MovementDetail[];
    };
    permissions: {
        canEdit: boolean;
        canApprove: boolean;
        canReceive: boolean;
        canCancel: boolean;
        canViewCost: boolean;
    };
}

export default function PurchaseOrderShow({ purchaseOrder, permissions }: Props) {
    const [showReceiveModal, setShowReceiveModal] = useState(false);
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [approving, setApproving] = useState(false);

    const receiveForm = useForm({
        notes: '',
        items: purchaseOrder.items
            .filter((i) => i.remaining_qty > 0)
            .map((i) => ({
                purchase_order_item_id: i.id,
                quantity_received: i.remaining_qty,
            })),
    });

    const cancelForm = useForm({
        cancellation_reason: '',
    });

    const handleApprove = () => {
        if (!window.confirm(`Approve Purchase Order ${purchaseOrder.po_number} and issue it to ${purchaseOrder.supplier?.name}?`)) {
            return;
        }
        setApproving(true);
        router.post(`/admin/purchase-orders/${purchaseOrder.id}/approve`, {}, {
            preserveScroll: true,
            onFinish: () => setApproving(false),
        });
    };

    const openReceiveModal = () => {
        receiveForm.setData({
            notes: '',
            items: purchaseOrder.items
                .filter((i) => i.remaining_qty > 0)
                .map((i) => ({
                    purchase_order_item_id: i.id,
                    quantity_received: i.remaining_qty,
                })),
        });
        setShowReceiveModal(true);
    };

    const handleReceiveSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        receiveForm.post(`/admin/purchase-orders/${purchaseOrder.id}/receive`, {
            preserveScroll: true,
            onSuccess: () => setShowReceiveModal(false),
        });
    };

    const handleCancelSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        cancelForm.post(`/admin/purchase-orders/${purchaseOrder.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => setShowCancelModal(false),
        });
    };

    return (
        <AdminLayout>
            <Head title={`${purchaseOrder.po_number} | Purchase Orders | Aura Luxe`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <Link href="/admin/purchase-orders" className="text-xs text-slate-500 hover:underline">
                            &larr; Back to Purchase Orders
                        </Link>
                        <div className="flex items-center gap-3 mt-1">
                            <h1 className="font-serif text-2xl font-semibold text-slate-900 dark:text-white font-mono">
                                {purchaseOrder.po_number}
                            </h1>
                            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold capitalize bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                {purchaseOrder.status.replace('_', ' ')}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Supplier: <span className="font-semibold text-slate-800 dark:text-slate-200">{purchaseOrder.supplier?.name}</span> ({purchaseOrder.supplier?.code})
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {permissions.canEdit && (
                            <Link
                                href={`/admin/purchase-orders/${purchaseOrder.id}/edit`}
                                className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50"
                            >
                                Edit Draft
                            </Link>
                        )}
                        {permissions.canApprove && (
                            <button
                                type="button"
                                disabled={approving}
                                onClick={handleApprove}
                                className="px-4 py-2 rounded-xl bg-emerald-700 text-white text-xs font-semibold hover:bg-emerald-600 disabled:opacity-50"
                            >
                                {approving ? 'Approving...' : 'Approve & Order (Owner)'}
                            </button>
                        )}
                        {permissions.canReceive && (
                            <button
                                type="button"
                                onClick={openReceiveModal}
                                className="px-4 py-2 rounded-xl bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold hover:bg-slate-800"
                            >
                                Receive Stock
                            </button>
                        )}
                        {permissions.canCancel && (
                            <button
                                type="button"
                                onClick={() => setShowCancelModal(true)}
                                className="px-4 py-2 rounded-xl border border-rose-300 text-rose-700 dark:text-rose-400 text-xs font-semibold hover:bg-rose-50 dark:hover:bg-rose-950/30"
                            >
                                Cancel PO
                            </button>
                        )}
                    </div>
                </div>

                {/* Metadata & Totals */}
                <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span className="text-slate-400 block">Created By</span>
                        <span className="font-medium text-slate-900 dark:text-white">{purchaseOrder.creator_name || '—'}</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Approved By</span>
                        <span className="font-medium text-slate-900 dark:text-white">{purchaseOrder.approver_name || 'Pending Owner Approval'}</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Expected Delivery</span>
                        <span className="font-medium text-slate-900 dark:text-white">{purchaseOrder.expected_delivery_date || '—'}</span>
                    </div>
                    {permissions.canViewCost && (
                        <div>
                            <span className="text-slate-400 block">Total Cost (LKR)</span>
                            <span className="font-mono font-bold text-base text-slate-900 dark:text-white">
                                {purchaseOrder.total_cost !== null ? Number(purchaseOrder.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}
                            </span>
                        </div>
                    )}
                    {purchaseOrder.cancellation_reason && (
                        <div className="md:col-span-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300">
                            <strong>Cancelled by {purchaseOrder.canceller_name}:</strong> {purchaseOrder.cancellation_reason}
                        </div>
                    )}
                </div>

                {/* Line Items */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden">
                    <div className="p-5 border-b border-slate-100 dark:border-slate-800">
                        <h2 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Order Items</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/70 dark:bg-slate-900/50">
                                    <th className="py-3 px-4 font-semibold">Product</th>
                                    <th className="py-3 px-4 font-semibold">Supplier SKU</th>
                                    <th className="py-3 px-4 font-semibold text-center">Ordered</th>
                                    <th className="py-3 px-4 font-semibold text-center">Received</th>
                                    <th className="py-3 px-4 font-semibold text-center">Remaining</th>
                                    {permissions.canViewCost && <th className="py-3 px-4 font-semibold text-right">Unit Cost</th>}
                                    {permissions.canViewCost && <th className="py-3 px-4 font-semibold text-right">Line Total</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                {purchaseOrder.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3.5 px-4">
                                            <div className="font-semibold text-slate-900 dark:text-white">{item.product_name}</div>
                                            <div className="text-[11px] font-mono text-slate-400">{item.product_sku || '—'}</div>
                                        </td>
                                        <td className="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-300">{item.supplier_sku || '—'}</td>
                                        <td className="py-3.5 px-4 text-center font-mono">{item.ordered_qty}</td>
                                        <td className="py-3.5 px-4 text-center font-mono text-emerald-700 dark:text-emerald-400">{item.received_qty}</td>
                                        <td className="py-3.5 px-4 text-center font-mono font-semibold">{item.remaining_qty}</td>
                                        {permissions.canViewCost && (
                                            <td className="py-3.5 px-4 text-right font-mono">
                                                {item.unit_cost !== null ? Number(item.unit_cost).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}
                                            </td>
                                        )}
                                        {permissions.canViewCost && (
                                            <td className="py-3.5 px-4 text-right font-mono font-semibold">
                                                {item.line_total !== null ? Number(item.line_total).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Receipt History */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden">
                    <div className="p-5 border-b border-slate-100 dark:border-slate-800">
                        <h2 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Receipt History</h2>
                    </div>
                    {purchaseOrder.receipts.length === 0 ? (
                        <div className="p-8 text-center text-xs text-slate-500">No stock receipts recorded yet.</div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800">
                            {purchaseOrder.receipts.map((r) => (
                                <div key={r.id} className="p-5 space-y-2 text-xs">
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <span className="font-mono font-semibold text-slate-900 dark:text-white">{r.receipt_number}</span>
                                        <span className="text-slate-500">
                                            Received by {r.received_by_name} on {new Date(r.received_at).toLocaleString()}
                                        </span>
                                    </div>
                                    {r.notes && <p className="text-slate-600 dark:text-slate-300">{r.notes}</p>}
                                    <ul className="list-disc list-inside text-slate-600 dark:text-slate-300">
                                        {r.items.map((ri) => (
                                            <li key={ri.id}>
                                                {ri.product_name}: <strong className="font-mono">{ri.quantity_received}</strong> units
                                                {permissions.canViewCost && ri.unit_cost ? ` @ LKR ${Number(ri.unit_cost).toFixed(2)}` : ''}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Inventory Movements */}
                {purchaseOrder.inventory_movements.length > 0 && (
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden">
                        <div className="p-5 border-b border-slate-100 dark:border-slate-800">
                            <h2 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Linked Inventory Movements</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/70 dark:bg-slate-900/50">
                                        <th className="py-3 px-4">Product</th>
                                        <th className="py-3 px-4 text-center">Qty Added</th>
                                        <th className="py-3 px-4 text-center">Stock Change</th>
                                        <th className="py-3 px-4">Reason</th>
                                        <th className="py-3 px-4">Actor</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    {purchaseOrder.inventory_movements.map((m) => (
                                        <tr key={m.id}>
                                            <td className="py-3 px-4 font-medium">{m.product_name}</td>
                                            <td className="py-3 px-4 text-center font-mono text-emerald-700">+{m.quantity}</td>
                                            <td className="py-3 px-4 text-center font-mono">{m.previous_stock} &rarr; {m.new_stock}</td>
                                            <td className="py-3 px-4 text-slate-600 dark:text-slate-300">{m.reason}</td>
                                            <td className="py-3 px-4 text-slate-500">{m.actor_name}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* Receive Modal */}
            {showReceiveModal && (
                <div className="fixed inset-0 z-50 bg-slate-950/60 flex items-center justify-center p-4">
                    <form onSubmit={handleReceiveSubmit} className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-lg w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h3 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">
                            Receive Stock for {purchaseOrder.po_number}
                        </h3>
                        <div className="space-y-3 max-h-72 overflow-y-auto">
                            {receiveForm.data.items.map((row, idx) => {
                                const poItem = purchaseOrder.items.find((i) => i.id === row.purchase_order_item_id);
                                if (!poItem) return null;
                                return (
                                    <div key={row.purchase_order_item_id} className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900">
                                        <div className="text-xs">
                                            <div className="font-semibold text-slate-900 dark:text-white">{poItem.product_name}</div>
                                            <div className="text-slate-500">Remaining: {poItem.remaining_qty}</div>
                                        </div>
                                        <input
                                            type="number"
                                            min={0}
                                            max={poItem.remaining_qty}
                                            value={row.quantity_received}
                                            onChange={(e) => {
                                                const next = receiveForm.data.items.map((item, i) =>
                                                    i === idx ? { ...item, quantity_received: Number(e.target.value) } : item
                                                );
                                                receiveForm.setData('items', next);
                                            }}
                                            className="w-24 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-1.5 text-xs font-mono"
                                        />
                                    </div>
                                );
                            })}
                        </div>
                        {(receiveForm.errors as Record<string, string>).items && (
                            <p className="text-[11px] text-rose-600">{(receiveForm.errors as Record<string, string>).items}</p>
                        )}
                        <div>
                            <label className="block text-xs font-semibold mb-1">Receipt Notes</label>
                            <textarea
                                rows={2}
                                value={receiveForm.data.notes}
                                onChange={(e) => receiveForm.setData('notes', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs"
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setShowReceiveModal(false)} className="px-4 py-2 text-xs text-slate-600 rounded-xl">
                                Close
                            </button>
                            <button type="submit" disabled={receiveForm.processing} className="px-4 py-2 text-xs font-semibold bg-slate-900 text-white rounded-xl disabled:opacity-50">
                                {receiveForm.processing ? 'Recording...' : 'Confirm Receipt'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Cancel Modal */}
            {showCancelModal && (
                <div className="fixed inset-0 z-50 bg-slate-950/60 flex items-center justify-center p-4">
                    <form onSubmit={handleCancelSubmit} className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h3 className="font-serif text-lg font-semibold text-rose-700 dark:text-rose-400">
                            Cancel {purchaseOrder.po_number}?
                        </h3>
                        <p className="text-xs text-slate-600 dark:text-slate-300">
                            This action cannot be undone. Cancellation is only permitted before any stock is received.
                        </p>
                        <div>
                            <label className="block text-xs font-semibold mb-1">Cancellation Reason <span className="text-rose-500">*</span></label>
                            <textarea
                                rows={3}
                                required
                                value={cancelForm.data.cancellation_reason}
                                onChange={(e) => cancelForm.setData('cancellation_reason', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs"
                            />
                            {cancelForm.errors.cancellation_reason && (
                                <p className="text-[11px] text-rose-600 mt-1">{cancelForm.errors.cancellation_reason}</p>
                            )}
                        </div>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setShowCancelModal(false)} className="px-4 py-2 text-xs text-slate-600 rounded-xl">
                                Back
                            </button>
                            <button type="submit" disabled={cancelForm.processing} className="px-4 py-2 text-xs font-semibold bg-rose-600 text-white rounded-xl disabled:opacity-50">
                                {cancelForm.processing ? 'Cancelling...' : 'Confirm Cancellation'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AdminLayout>
    );
}

