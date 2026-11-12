import AdminLayout from '@/Layouts/AdminLayout';
import { Category, PaginatedData, Product } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    products: PaginatedData<Product>;
    categories: Category[];
    filters: {
        search?: string;
        category_id?: string;
        low_stock?: boolean;
    };
    canViewCostPrice: boolean;
}

export default function ProductIndex({ products, categories, filters, canViewCostPrice }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '');
    const [lowStock, setLowStock] = useState(filters.low_stock ?? false);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/products', {
            search: search || undefined,
            category_id: categoryId || undefined,
            low_stock: lowStock ? '1' : undefined,
        }, { preserveState: true });
    };

    const handleClear = () => {
        setSearch('');
        setCategoryId('');
        setLowStock(false);
        router.get('/admin/products');
    };

    return (
        <AdminLayout>
            <Head title="Product Catalog Management | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Products Catalog</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage cosmetic formulas, inventory thresholds, and multi-image galleries.</p>
                    </div>
                    <Link
                        href="/admin/products/create"
                        className="inline-flex items-center gap-1.5 bg-rose-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2.5 rounded-xl hover:bg-rose-800 dark:hover:bg-blue-500 transition-colors shadow-xs"
                    >
                        + Add New Product
                    </Link>
                </div>

                {/* Filter Toolbar */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-wrap items-center gap-3 text-xs">
                    <input
                        type="text"
                        placeholder="Search product name, SKU..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs px-3 py-1.5 w-60 focus:border-blue-500 focus:ring-blue-500"
                    />

                    <select
                        value={categoryId}
                        onChange={(e) => setCategoryId(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">All Categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.name_en}</option>
                        ))}
                    </select>

                    <label className="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={lowStock}
                            onChange={(e) => setLowStock(e.target.checked)}
                            className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                        />
                        <span>Low Stock Only</span>
                    </label>

                    <div className="flex items-center gap-2 ml-auto">
                        <button
                            type="submit"
                            className="bg-slate-900 dark:bg-blue-600 text-white px-3.5 py-1.5 rounded-xl font-semibold hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer"
                        >
                            Filter
                        </button>
                        <button
                            type="button"
                            onClick={handleClear}
                            className="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white px-2 py-1.5 transition-colors cursor-pointer"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                {/* Product Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Product</th>
                                <th className="px-6 py-3.5">Category</th>
                                <th className="px-6 py-3.5">Selling Price</th>
                                {canViewCostPrice && <th className="px-6 py-3.5">Cost Price</th>}
                                <th className="px-6 py-3.5">Stock Level</th>
                                <th className="px-6 py-3.5">Status</th>
                                <th className="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                            {products.data.length === 0 ? (
                                <tr>
                                    <td colSpan={canViewCostPrice ? 7 : 6} className="px-6 py-8 text-center text-slate-400 dark:text-slate-500">
                                        No products found matching filters.
                                    </td>
                                </tr>
                            ) : (
                                products.data.map((p) => {
                                    const isLow = p.stock_quantity <= p.low_stock_threshold;
                                    return (
                                        <tr key={p.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden shrink-0 flex items-center justify-center">
                                                        {p.primary_image?.path ? (
                                                            <img
                                                                src={`/storage/${p.primary_image.path}`}
                                                                alt={p.name_en}
                                                                className="w-full h-full object-cover"
                                                            />
                                                        ) : (
                                                            <span className="text-[10px] text-slate-400 dark:text-slate-500 font-mono">IMG</span>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <Link href={`/admin/products/${p.id}/edit`} className="font-semibold text-slate-900 dark:text-white hover:text-rose-700 dark:hover:text-blue-400 block transition-colors">
                                                            {p.name_en}
                                                        </Link>
                                                        <span className="text-slate-400 dark:text-slate-500 text-[10px] font-mono block">
                                                            {p.sku ?? 'NO-SKU'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-slate-600 dark:text-slate-300">
                                                {p.category?.name_en ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 font-mono font-semibold text-slate-900 dark:text-white">
                                                LKR {Number(p.price).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                            </td>
                                            {canViewCostPrice && (
                                                <td className="px-6 py-4 font-mono text-slate-500 dark:text-slate-400">
                                                    {p.cost_price ? `LKR ${Number(p.cost_price).toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '—'}
                                                </td>
                                            )}
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1 font-mono font-semibold px-2 py-0.5 rounded text-[11px] ${
                                                    p.stock_quantity <= 0
                                                        ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60'
                                                        : isLow
                                                        ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60'
                                                        : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                }`}>
                                                    {p.stock_quantity} units
                                                    {isLow && <span title="Below low-stock threshold">⚠️</span>}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                                                    p.is_active
                                                        ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
                                                }`}>
                                                    {p.is_active ? 'Active' : 'Disabled'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right space-x-3">
                                                <Link
                                                    href={`/products/${p.slug}`}
                                                    target="_blank"
                                                    className="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                                                    title="View in Storefront"
                                                >
                                                    ↗
                                                </Link>
                                                <Link
                                                    href={`/admin/products/${p.id}/edit`}
                                                    className="text-rose-900 dark:text-blue-400 hover:underline font-semibold"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {products.last_page > 1 && (
                        <div className="px-6 py-3.5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Showing page {products.current_page} of {products.last_page}</span>
                            <div className="flex gap-1">
                                {products.links.map((link, idx) => (
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
