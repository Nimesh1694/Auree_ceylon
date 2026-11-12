import AdminLayout from '@/Layouts/AdminLayout';
import { InventoryMovement, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    movements: PaginatedData<InventoryMovement>;
    products: { id: number; name_en: string; sku?: string | null }[];
    filters: {
        product_id?: string;
        type?: string;
    };
}

export default function InventoryMovements({ movements, products, filters }: Props) {
    const [productId, setProductId] = useState(filters.product_id ?? '');
    const [type, setType] = useState(filters.type ?? '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/inventory/movements', {
            product_id: productId || undefined,
            type: type || undefined,
        }, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Inventory Movement Ledger | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Stock Movement Ledger</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Immutable audit trail of all warehouse additions, order deductions, returns, and manual corrections.</p>
                    </div>
                    <Link href="/admin/inventory" className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        &larr; Back to Inventory
                    </Link>
                </div>

                {/* Filter Toolbar */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-wrap items-center gap-3 text-xs">
                    <select
                        value={productId}
                        onChange={(e) => setProductId(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">All Products</option>
                        {products.map((p) => (
                            <option key={p.id} value={p.id}>{p.name_en} ({p.sku ?? 'NO-SKU'})</option>
                        ))}
                    </select>

                    <select
                        value={type}
                        onChange={(e) => setType(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">All Movement Types</option>
                        <option value="opening_stock">Opening Stock</option>
                        <option value="restock">Restock</option>
                        <option value="order_deduction">Order Deduction</option>
                        <option value="cancellation_restock">Cancellation Restock</option>
                        <option value="customer_return">Customer Return</option>
                        <option value="adjustment">Manual Adjustment</option>
                        <option value="damage_loss">Damage Loss</option>
                        <option value="expired">Expired</option>
                    </select>

                    <button type="submit" className="bg-slate-900 dark:bg-blue-600 text-white px-3.5 py-1.5 rounded-xl font-semibold hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer">
                        Filter
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setProductId('');
                            setType('');
                            router.get('/admin/inventory/movements');
                        }}
                        className="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white px-2 py-1.5 transition-colors cursor-pointer"
                    >
                        Reset
                    </button>
                </form>

                {/* Movements Ledger Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Timestamp</th>
                                <th className="px-6 py-3.5">Product</th>
                                <th className="px-6 py-3.5">Movement Type</th>
                                <th className="px-6 py-3.5">Delta</th>
                                <th className="px-6 py-3.5">Stock Before &rarr; After</th>
                                <th className="px-6 py-3.5">Reason / Order</th>
                                <th className="px-6 py-3.5">User</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                            {movements.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-8 text-center text-slate-400 dark:text-slate-500">
                                        No movements recorded matching filters.
                                    </td>
                                </tr>
                            ) : (
                                movements.data.map((m) => (
                                    <tr key={m.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-6 py-4 font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            {new Date(m.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="font-semibold text-slate-900 dark:text-white block">{m.product?.name_en ?? 'Unknown'}</span>
                                            <span className="text-slate-400 dark:text-slate-500 text-[10px] font-mono">{m.product?.sku}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                                                m.movement_type === 'order_deduction' || m.movement_type === 'damage_loss' || m.movement_type === 'expired'
                                                    ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60'
                                                    : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                            }`}>
                                                {m.movement_type.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 font-mono font-bold">
                                            <span className={m.quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}>
                                                {m.quantity >= 0 ? `+${m.quantity}` : m.quantity}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                                            {m.previous_stock} &rarr; <span className="font-bold text-slate-950 dark:text-white">{m.new_stock}</span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                                            {m.reason ?? '—'}
                                            {m.order && (
                                                <Link href={`/admin/orders/${m.order.id}`} className="block text-rose-700 dark:text-blue-400 hover:underline font-mono text-[11px] mt-0.5">
                                                    #{m.order.order_number}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-slate-600 dark:text-slate-400">
                                            {m.user?.name ?? 'System'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {movements.last_page > 1 && (
                        <div className="px-6 py-3.5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Showing page {movements.current_page} of {movements.last_page}</span>
                            <div className="flex gap-1">
                                {movements.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url ?? '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 rounded-lg transition-colors ${
                                            link.active
                                                ? 'bg-slate-900 dark:bg-blue-600 text-white font-semibold'
                                                : link.url
                                                ? 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                : 'text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
