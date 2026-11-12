import AdminLayout from '@/Layouts/AdminLayout';
import { PaginatedData, Product } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    products: PaginatedData<Product>;
    filters: {
        search?: string;
        low_stock_only?: boolean;
    };
    stats: {
        low_stock_count: number;
        out_of_stock_count: number;
        total_stock_count: number;
    };
}

export default function InventoryIndex({ products, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [lowStockOnly, setLowStockOnly] = useState(filters.low_stock_only ?? false);

    const [adjustingProduct, setAdjustingProduct] = useState<Product | null>(null);

    const { data, setData, post, reset, processing, errors } = useForm({
        product_id: 0,
        quantity_delta: 0,
        movement_type: 'restock',
        reason: '',
    });

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/inventory', {
            search: search || undefined,
            low_stock_only: lowStockOnly ? '1' : undefined,
        }, { preserveState: true });
    };

    const openAdjustModal = (product: Product) => {
        setAdjustingProduct(product);
        setData({
            product_id: product.id,
            quantity_delta: 10,
            movement_type: 'restock',
            reason: '',
        });
    };

    const handleAdjustSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/inventory/adjust', {
            onSuccess: () => {
                setAdjustingProduct(null);
                reset();
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Inventory & Stock Management | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Inventory & Stock Tracking</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Monitor real-time warehouse inventory, restock items, and log adjustments.</p>
                    </div>
                    <Link
                        href="/admin/inventory/movements"
                        className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2.5 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors shadow-xs"
                    >
                        View Movement Ledger &rarr;
                    </Link>
                </div>

                {/* Stat cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-[11px] text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">Total Units in Stock</span>
                        <span className="text-2xl font-bold text-slate-900 dark:text-white mt-1 block font-mono">{stats.total_stock_count}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-amber-200/80 dark:border-amber-900/40 bg-amber-50/20 dark:bg-amber-950/20 shadow-xs">
                        <span className="text-[11px] text-amber-800 dark:text-amber-400 uppercase tracking-wider font-semibold">Low Stock Alerts</span>
                        <span className="text-2xl font-bold text-amber-900 dark:text-amber-300 mt-1 block font-mono">{stats.low_stock_count}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-rose-200/80 dark:border-rose-900/40 bg-rose-50/20 dark:bg-rose-950/20 shadow-xs">
                        <span className="text-[11px] text-rose-800 dark:text-rose-400 uppercase tracking-wider font-semibold">Out of Stock Items</span>
                        <span className="text-2xl font-bold text-rose-900 dark:text-rose-300 mt-1 block font-mono">{stats.out_of_stock_count}</span>
                    </div>
                </div>

                {/* Filter */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-wrap items-center gap-3 text-xs">
                    <input
                        type="text"
                        placeholder="Search product name, SKU..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs px-3 py-1.5 w-60 focus:border-blue-500 focus:ring-blue-500"
                    />

                    <label className="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={lowStockOnly}
                            onChange={(e) => setLowStockOnly(e.target.checked)}
                            className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                        />
                        <span>Low Stock Only</span>
                    </label>

                    <div className="flex items-center gap-2 ml-auto">
                        <button type="submit" className="bg-slate-900 dark:bg-blue-600 text-white px-3.5 py-1.5 rounded-xl font-semibold hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer">
                            Filter
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setLowStockOnly(false);
                                router.get('/admin/inventory');
                            }}
                            className="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white px-2 py-1.5 transition-colors cursor-pointer"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                {/* Products Stock Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Product</th>
                                <th className="px-6 py-3.5">Category</th>
                                <th className="px-6 py-3.5">Current Stock</th>
                                <th className="px-6 py-3.5">Alert Threshold</th>
                                <th className="px-6 py-3.5">Status</th>
                                <th className="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                            {products.data.map((p) => {
                                const isLow = p.stock_quantity <= p.low_stock_threshold;
                                return (
                                    <tr key={p.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-6 py-4">
                                            <span className="font-semibold text-slate-900 dark:text-white block">{p.name_en}</span>
                                            <span className="text-slate-400 dark:text-slate-500 text-[10px] font-mono block">{p.sku ?? 'NO-SKU'}</span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-600 dark:text-slate-300">
                                            {p.category?.name_en ?? '—'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`font-mono text-sm font-bold ${
                                                p.stock_quantity <= 0
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : isLow
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-slate-900 dark:text-white'
                                            }`}>
                                                {p.stock_quantity} units
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 font-mono text-slate-500 dark:text-slate-400">
                                            &le; {p.low_stock_threshold} units
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                                                p.stock_quantity <= 0
                                                    ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60'
                                                    : isLow
                                                    ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60'
                                                    : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                            }`}>
                                                {p.stock_quantity <= 0 ? 'Out of Stock' : isLow ? 'Low Stock' : 'Optimal'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right space-x-3">
                                            <button
                                                type="button"
                                                onClick={() => openAdjustModal(p)}
                                                className="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold px-3 py-1.5 rounded-xl text-xs transition-colors cursor-pointer"
                                            >
                                                Adjust Stock
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Stock Adjustment Modal */}
                {adjustingProduct && (
                    <div className="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-4 text-xs">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800/80">
                                <h3 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                    Adjust Stock: {adjustingProduct.name_en}
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => setAdjustingProduct(null)}
                                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs font-bold cursor-pointer"
                                >
                                    ✕
                                </button>
                            </div>

                            <p className="text-slate-500 dark:text-slate-400 text-[11px]">
                                Current inventory: <strong className="text-slate-900 dark:text-white font-mono">{adjustingProduct.stock_quantity} units</strong>. All changes are logged into the immutable inventory movement ledger.
                            </p>

                            <form onSubmit={handleAdjustSubmit} className="space-y-4">
                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Movement Type *</label>
                                    <select
                                        value={data.movement_type}
                                        onChange={(e) => setData('movement_type', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="restock">Restock (+ Add Stock)</option>
                                        <option value="adjustment">Manual Count Adjustment (+ / -)</option>
                                        <option value="damage_loss">Damaged Stock (- Deduct)</option>
                                        <option value="expired">Expired Stock (- Deduct)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">
                                        Quantity Delta (+ positive to add, - negative to deduct) *
                                    </label>
                                    <input
                                        type="number"
                                        value={data.quantity_delta}
                                        onChange={(e) => setData('quantity_delta', parseInt(e.target.value) || 0)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="e.g. 20 or -5"
                                        required
                                    />
                                    {errors.quantity_delta && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.quantity_delta}</p>}
                                    {errors.product_id && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.product_id}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Audit Reason / Notes *</label>
                                    <input
                                        type="text"
                                        value={data.reason}
                                        onChange={(e) => setData('reason', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="e.g. Supplier Batch #BL-2026-03 arrived"
                                        required
                                    />
                                    {errors.reason && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.reason}</p>}
                                </div>

                                <div className="flex justify-end gap-3 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => setAdjustingProduct(null)}
                                        className="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-slate-900 dark:bg-blue-600 text-white font-semibold px-5 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors cursor-pointer"
                                    >
                                        Confirm Adjustment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
