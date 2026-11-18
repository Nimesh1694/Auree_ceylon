import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface SupplierDetail {
    id: number;
    code: string;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    tax_id: string | null;
    payment_terms: string;
    default_lead_time_days: number;
    status: 'active' | 'inactive';
    notes: string | null;
    creator_name: string | null;
}

interface LinkedProduct {
    id: number;
    product_id: number;
    product_name: string | null;
    product_sku: string | null;
    product_is_active: boolean;
    product_stock: number;
    supplier_sku: string | null;
    unit_cost: string | null;
    lead_time_days: number | null;
    min_order_qty: number;
    is_preferred: boolean;
    last_purchased_at: string | null;
}

interface AvailableProduct {
    id: number;
    name_en: string;
    sku: string | null;
}

interface PurchaseOrderSummary {
    id: number;
    po_number: string;
    status: string;
    ordered_at: string | null;
    expected_delivery_date: string | null;
    total_cost: string | null;
}

interface Props {
    supplier: SupplierDetail;
    linkedProducts: LinkedProduct[];
    availableProducts: AvailableProduct[];
    purchaseOrders: PurchaseOrderSummary[];
    canManage: boolean;
    canViewCost: boolean;
}

export default function SupplierShow({
    supplier,
    linkedProducts,
    availableProducts,
    purchaseOrders,
    canManage,
    canViewCost,
}: Props) {
    const [showLinkModal, setShowLinkModal] = useState(false);
    const [editingLink, setEditingLink] = useState<LinkedProduct | null>(null);
    const [busyAction, setBusyAction] = useState<string | null>(null);

    const linkForm = useForm({
        product_id: availableProducts[0]?.id ? String(availableProducts[0].id) : '',
        supplier_sku: '',
        unit_cost: '',
        lead_time_days: '',
        min_order_qty: '1',
        is_preferred: false,
    });

    const editForm = useForm({
        supplier_sku: '',
        unit_cost: '',
        lead_time_days: '',
        min_order_qty: '1',
        is_preferred: false,
    });

    const handleToggleStatus = () => {
        const action = supplier.status === 'active' ? 'deactivate' : 'activate';
        if (!window.confirm(`Are you sure you want to ${action} "${supplier.name}"?`)) {
            return;
        }
        setBusyAction('toggle');
        router.patch(`/admin/suppliers/${supplier.id}/toggle-status`, {}, {
            preserveScroll: true,
            onFinish: () => setBusyAction(null),
        });
    };

    const handleLinkSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        linkForm.post(`/admin/suppliers/${supplier.id}/products`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowLinkModal(false);
                linkForm.reset();
            },
        });
    };

    const openEditModal = (link: LinkedProduct) => {
        setEditingLink(link);
        editForm.setData({
            supplier_sku: link.supplier_sku ?? '',
            unit_cost: link.unit_cost ?? '0.00',
            lead_time_days: link.lead_time_days !== null ? String(link.lead_time_days) : '',
            min_order_qty: String(link.min_order_qty),
            is_preferred: link.is_preferred,
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingLink) return;
        editForm.put(`/admin/suppliers/${supplier.id}/products/${editingLink.product_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingLink(null);
            },
        });
    };

    const handleSetPreferred = (link: LinkedProduct) => {
        setBusyAction(`pref-${link.product_id}`);
        router.patch(`/admin/suppliers/${supplier.id}/products/${link.product_id}/preferred`, {}, {
            preserveScroll: true,
            onFinish: () => setBusyAction(null),
        });
    };

    const handleUnlink = (link: LinkedProduct) => {
        if (!window.confirm(`Remove "${link.product_name}" from ${supplier.name}? Historical POs will not be affected.`)) {
            return;
        }
        setBusyAction(`unlink-${link.product_id}`);
        router.delete(`/admin/suppliers/${supplier.id}/products/${link.product_id}`, {
            preserveScroll: true,
            onFinish: () => setBusyAction(null),
        });
    };

    const inputClass =
        'w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-white';

    return (
        <AdminLayout>
            <Head title={`${supplier.name} | Suppliers | Aura Luxe`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <Link href="/admin/suppliers" className="text-xs text-slate-500 hover:underline">
                            &larr; Back to Suppliers
                        </Link>
                        <div className="flex items-center gap-3 mt-1">
                            <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">{supplier.name}</h1>
                            <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${
                                supplier.status === 'active'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                            }`}>
                                {supplier.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <p className="text-xs font-mono text-slate-500 dark:text-slate-400 mt-0.5">{supplier.code}</p>
                    </div>

                    {canManage && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Link
                                href={`/admin/suppliers/${supplier.id}/edit`}
                                className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
                            >
                                Edit Supplier
                            </Link>
                            <button
                                type="button"
                                disabled={busyAction === 'toggle'}
                                onClick={handleToggleStatus}
                                className="px-4 py-2 rounded-xl bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold hover:bg-slate-800 disabled:opacity-50"
                            >
                                {busyAction === 'toggle'
                                    ? 'Updating...'
                                    : supplier.status === 'active'
                                        ? 'Deactivate'
                                        : 'Activate'}
                            </button>
                        </div>
                    )}
                </div>

                {/* Profile Details */}
                <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span className="text-slate-400 block">Contact Person</span>
                        <span className="font-medium text-slate-900 dark:text-white">{supplier.contact_person || '—'}</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Email</span>
                        <span className="font-medium text-slate-900 dark:text-white">{supplier.email || '—'}</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Phone</span>
                        <span className="font-medium text-slate-900 dark:text-white">{supplier.phone || '—'}</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Payment Terms</span>
                        <span className="font-medium text-slate-900 dark:text-white uppercase">{supplier.payment_terms.replace('_', ' ')}</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Default Lead Time</span>
                        <span className="font-medium text-slate-900 dark:text-white">{supplier.default_lead_time_days} days</span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Tax / Registration ID</span>
                        <span className="font-medium text-slate-900 dark:text-white">{supplier.tax_id || '—'}</span>
                    </div>
                    <div className="md:col-span-2">
                        <span className="text-slate-400 block">Address</span>
                        <span className="font-medium text-slate-900 dark:text-white">
                            {[supplier.address, supplier.city, supplier.country].filter(Boolean).join(', ') || '—'}
                        </span>
                    </div>
                    <div>
                        <span className="text-slate-400 block">Notes</span>
                        <span className="font-medium text-slate-900 dark:text-white">{supplier.notes || '—'}</span>
                    </div>
                </div>

                {/* Linked Products */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-xs">
                    <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Linked Products</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Products sourced from this supplier. Only one supplier can be marked preferred per product.
                            </p>
                        </div>
                        {canManage && availableProducts.length > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    linkForm.setData('product_id', String(availableProducts[0].id));
                                    setShowLinkModal(true);
                                }}
                                className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-xl hover:bg-slate-800"
                            >
                                + Link Product
                            </button>
                        )}
                    </div>

                    {linkedProducts.length === 0 ? (
                        <div className="p-10 text-center text-xs text-slate-500 dark:text-slate-400">
                            No products linked to this supplier yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/70 dark:bg-slate-900/50">
                                        <th className="py-3 px-4 font-semibold">Product</th>
                                        <th className="py-3 px-4 font-semibold">Supplier SKU</th>
                                        {canViewCost && <th className="py-3 px-4 font-semibold text-right">Unit Cost (LKR)</th>}
                                        <th className="py-3 px-4 font-semibold text-center">Lead Time</th>
                                        <th className="py-3 px-4 font-semibold text-center">MOQ</th>
                                        <th className="py-3 px-4 font-semibold text-center">Preferred</th>
                                        {canManage && <th className="py-3 px-4 font-semibold text-right">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    {linkedProducts.map((link) => (
                                        <tr key={link.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/40">
                                            <td className="py-3.5 px-4">
                                                <div className="font-semibold text-slate-900 dark:text-white">{link.product_name}</div>
                                                <div className="text-[11px] font-mono text-slate-400">{link.product_sku || '—'}</div>
                                            </td>
                                            <td className="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-300">{link.supplier_sku || '—'}</td>
                                            {canViewCost && (
                                                <td className="py-3.5 px-4 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                                    {link.unit_cost !== null ? Number(link.unit_cost).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}
                                                </td>
                                            )}
                                            <td className="py-3.5 px-4 text-center text-slate-600 dark:text-slate-300">
                                                {link.lead_time_days !== null ? `${link.lead_time_days}d` : `${supplier.default_lead_time_days}d (default)`}
                                            </td>
                                            <td className="py-3.5 px-4 text-center font-mono text-slate-600 dark:text-slate-300">{link.min_order_qty}</td>
                                            <td className="py-3.5 px-4 text-center">
                                                {link.is_preferred ? (
                                                    <span className="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                        Preferred
                                                    </span>
                                                ) : canManage ? (
                                                    <button
                                                        type="button"
                                                        disabled={busyAction === `pref-${link.product_id}`}
                                                        onClick={() => handleSetPreferred(link)}
                                                        className="text-[11px] text-blue-600 dark:text-blue-400 hover:underline font-medium disabled:opacity-50"
                                                    >
                                                        {busyAction === `pref-${link.product_id}` ? 'Setting...' : 'Set Preferred'}
                                                    </button>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditModal(link)}
                                                        className="text-slate-600 dark:text-slate-300 hover:underline font-medium"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={busyAction === `unlink-${link.product_id}`}
                                                        onClick={() => handleUnlink(link)}
                                                        className="text-rose-600 dark:text-rose-400 hover:underline font-medium disabled:opacity-50"
                                                    >
                                                        Remove
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Purchasing History */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-xs">
                    <div className="p-5 border-b border-slate-100 dark:border-slate-800">
                        <h2 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Purchasing History</h2>
                    </div>
                    {purchaseOrders.length === 0 ? (
                        <div className="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                            No purchase orders recorded for this supplier yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/70 dark:bg-slate-900/50">
                                        <th className="py-3 px-4 font-semibold">PO Number</th>
                                        <th className="py-3 px-4 font-semibold">Status</th>
                                        <th className="py-3 px-4 font-semibold">Expected Delivery</th>
                                        {canViewCost && <th className="py-3 px-4 font-semibold text-right">Total Cost (LKR)</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    {purchaseOrders.map((po) => (
                                        <tr key={po.id}>
                                            <td className="py-3 px-4 font-mono font-semibold text-slate-900 dark:text-white">{po.po_number}</td>
                                            <td className="py-3 px-4 capitalize text-slate-600 dark:text-slate-300">{po.status.replace('_', ' ')}</td>
                                            <td className="py-3 px-4 text-slate-600 dark:text-slate-300">{po.expected_delivery_date || '—'}</td>
                                            {canViewCost && (
                                                <td className="py-3 px-4 text-right font-mono text-slate-900 dark:text-white">
                                                    {po.total_cost !== null ? Number(po.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Link Product Modal */}
            {showLinkModal && (
                <div className="fixed inset-0 z-50 bg-slate-950/60 flex items-center justify-center p-4">
                    <form onSubmit={handleLinkSubmit} className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h3 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Link Product to {supplier.name}</h3>
                        <div>
                            <label className="block text-xs font-semibold mb-1">Product</label>
                            <select
                                value={linkForm.data.product_id}
                                onChange={(e) => linkForm.setData('product_id', e.target.value)}
                                className={inputClass}
                                required
                            >
                                {availableProducts.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name_en} ({p.sku || 'No SKU'})</option>
                                ))}
                            </select>
                            {linkForm.errors.product_id && <p className="text-[11px] text-rose-600 mt-1">{linkForm.errors.product_id}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Supplier SKU</label>
                                <input type="text" value={linkForm.data.supplier_sku} onChange={(e) => linkForm.setData('supplier_sku', e.target.value)} className={inputClass} />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Unit Cost (LKR)</label>
                                <input type="number" step="0.01" min="0" value={linkForm.data.unit_cost} onChange={(e) => linkForm.setData('unit_cost', e.target.value)} className={inputClass} required />
                                {linkForm.errors.unit_cost && <p className="text-[11px] text-rose-600 mt-1">{linkForm.errors.unit_cost}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Lead Time (Days)</label>
                                <input type="number" min="0" value={linkForm.data.lead_time_days} onChange={(e) => linkForm.setData('lead_time_days', e.target.value)} placeholder="Use default" className={inputClass} />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Min Order Qty</label>
                                <input type="number" min="1" value={linkForm.data.min_order_qty} onChange={(e) => linkForm.setData('min_order_qty', e.target.value)} className={inputClass} required />
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                            <input type="checkbox" checked={linkForm.data.is_preferred} onChange={(e) => linkForm.setData('is_preferred', e.target.checked)} />
                            Set as preferred supplier for this product
                        </label>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => setShowLinkModal(false)} className="px-4 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                            <button type="submit" disabled={linkForm.processing} className="px-4 py-2 text-xs font-semibold bg-slate-900 text-white rounded-xl disabled:opacity-50">
                                {linkForm.processing ? 'Linking...' : 'Link Product'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Edit Link Modal */}
            {editingLink && (
                <div className="fixed inset-0 z-50 bg-slate-950/60 flex items-center justify-center p-4">
                    <form onSubmit={handleEditSubmit} className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h3 className="font-serif text-lg font-semibold text-slate-900 dark:text-white">Edit Terms: {editingLink.product_name}</h3>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Supplier SKU</label>
                                <input type="text" value={editForm.data.supplier_sku} onChange={(e) => editForm.setData('supplier_sku', e.target.value)} className={inputClass} />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Unit Cost (LKR)</label>
                                <input type="number" step="0.01" min="0" value={editForm.data.unit_cost} onChange={(e) => editForm.setData('unit_cost', e.target.value)} className={inputClass} required />
                                {editForm.errors.unit_cost && <p className="text-[11px] text-rose-600 mt-1">{editForm.errors.unit_cost}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Lead Time (Days)</label>
                                <input type="number" min="0" value={editForm.data.lead_time_days} onChange={(e) => editForm.setData('lead_time_days', e.target.value)} className={inputClass} />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Min Order Qty</label>
                                <input type="number" min="1" value={editForm.data.min_order_qty} onChange={(e) => editForm.setData('min_order_qty', e.target.value)} className={inputClass} required />
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                            <input type="checkbox" checked={editForm.data.is_preferred} onChange={(e) => editForm.setData('is_preferred', e.target.checked)} />
                            Preferred supplier for this product
                        </label>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => setEditingLink(null)} className="px-4 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                            <button type="submit" disabled={editForm.processing} className="px-4 py-2 text-xs font-semibold bg-slate-900 text-white rounded-xl disabled:opacity-50">
                                {editForm.processing ? 'Saving...' : 'Save Terms'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AdminLayout>
    );
}

