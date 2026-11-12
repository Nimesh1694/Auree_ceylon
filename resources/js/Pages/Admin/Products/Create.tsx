import AdminLayout from '@/Layouts/AdminLayout';
import { Category } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import React from 'react';

interface Props {
    categories: Category[];
}

export default function ProductCreate({ categories }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        category_id: '',
        name_en: '',
        name_si: '',
        slug: '',
        sku: '',
        short_description_en: '',
        short_description_si: '',
        description_en: '',
        description_si: '',
        ingredients_en: '',
        ingredients_si: '',
        directions_en: '',
        directions_si: '',
        price: '',
        compare_at_price: '',
        cost_price: '',
        stock_quantity: 0,
        low_stock_threshold: 5,
        expiry_date: '',
        is_active: true,
        is_featured: false,
        is_best_seller: false,
        is_new_arrival: false,
        images: [] as File[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/products', {
            forceFormData: true,
        });
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            setData('images', Array.from(e.target.files));
        }
    };

    return (
        <AdminLayout>
            <Head title="Create New Product | Aura Luxe" />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">New Product</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Register a new botanical cosmetic formulation.</p>
                    </div>
                    <Link href="/admin/products" className="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                        &larr; Back to Products
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6 bg-white dark:bg-[#0F172A] p-6 sm:p-8 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs text-xs">
                    {/* Basic Info */}
                    <div className="space-y-4">
                        <h2 className="font-semibold text-slate-900 dark:text-white text-sm border-b border-slate-100 dark:border-slate-800/80 pb-2">1. Basic Information</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">English Name *</label>
                                <input
                                    type="text"
                                    value={data.name_en}
                                    onChange={(e) => setData('name_en', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g. Royal Jasmine Face Elixir"
                                    required
                                />
                                {errors.name_en && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.name_en}</p>}
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Sinhala Name *</label>
                                <input
                                    type="text"
                                    value={data.name_si}
                                    onChange={(e) => setData('name_si', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g. රෝයල් සම රැකවරණ එලික්සර්"
                                    required
                                />
                                {errors.name_si && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.name_si}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Category</label>
                                <select
                                    value={data.category_id}
                                    onChange={(e) => setData('category_id', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">Select Category</option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name_en}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">SKU Code</label>
                                <input
                                    type="text"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g. AL-ELI-009"
                                />
                                {errors.sku && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.sku}</p>}
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">URL Slug</label>
                                <input
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="auto-generated-if-empty"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Pricing and Stock */}
                    <div className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <h2 className="font-semibold text-slate-900 dark:text-white text-sm border-b border-slate-100 dark:border-slate-800/80 pb-2">2. Pricing & Inventory</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Selling Price (LKR) *</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="3500.00"
                                    required
                                />
                                {errors.price && <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors.price}</p>}
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Compare-at Price (LKR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.compare_at_price}
                                    onChange={(e) => setData('compare_at_price', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="4000.00"
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Cost Price (Internal Only)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.cost_price}
                                    onChange={(e) => setData('cost_price', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="1800.00"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Initial Stock Quantity *</label>
                                <input
                                    type="number"
                                    value={data.stock_quantity}
                                    onChange={(e) => setData('stock_quantity', parseInt(e.target.value) || 0)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    required
                                />
                                <span className="text-[10px] text-slate-400 dark:text-slate-500">Creates initial Opening Stock movement record.</span>
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Low Stock Alert Threshold *</label>
                                <input
                                    type="number"
                                    value={data.low_stock_threshold}
                                    onChange={(e) => setData('low_stock_threshold', parseInt(e.target.value) || 0)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Expiry Date (Optional)</label>
                                <input
                                    type="date"
                                    value={data.expiry_date}
                                    onChange={(e) => setData('expiry_date', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Formula Details */}
                    <div className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <h2 className="font-semibold text-slate-900 dark:text-white text-sm border-b border-slate-100 dark:border-slate-800/80 pb-2">3. Botanical Formulation Details</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Key Ingredients (English)</label>
                                <textarea
                                    rows={3}
                                    value={data.ingredients_en}
                                    onChange={(e) => setData('ingredients_en', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Organic Aloe Vera, Pure Jasmine Essential Oil..."
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Key Ingredients (Sinhala)</label>
                                <textarea
                                    rows={3}
                                    value={data.ingredients_si}
                                    onChange={(e) => setData('ingredients_si', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="ස්වභාවික කෝමාරිකා සාරය, පිච්ච මල් තෙල්..."
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Directions for Use (English)</label>
                                <textarea
                                    rows={2}
                                    value={data.directions_en}
                                    onChange={(e) => setData('directions_en', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Directions for Use (Sinhala)</label>
                                <textarea
                                    rows={2}
                                    value={data.directions_si}
                                    onChange={(e) => setData('directions_si', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Images */}
                    <div className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <h2 className="font-semibold text-slate-900 dark:text-white text-sm border-b border-slate-100 dark:border-slate-800/80 pb-2">4. Product Images</h2>
                        <div>
                            <label className="block font-medium text-slate-700 dark:text-slate-300">Upload Product Images (JPEG, PNG, WebP &le; 2MB)</label>
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                onChange={handleImageChange}
                                className="mt-2 block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-800 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-700 cursor-pointer"
                            />
                            {errors['images.*' as keyof typeof errors] && (
                                <p className="text-rose-600 dark:text-rose-400 text-[11px] mt-1">{errors['images.*' as keyof typeof errors]}</p>
                            )}
                        </div>
                    </div>

                    {/* Flags */}
                    <div className="space-y-2 pt-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap gap-6">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                            />
                            <span className="font-medium text-slate-700 dark:text-slate-300">Active (Visible in Storefront)</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_best_seller}
                                onChange={(e) => setData('is_best_seller', e.target.checked)}
                                className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                            />
                            <span className="font-medium text-slate-700 dark:text-slate-300">Best Seller Badge</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_new_arrival}
                                onChange={(e) => setData('is_new_arrival', e.target.checked)}
                                className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                            />
                            <span className="font-medium text-slate-700 dark:text-slate-300">New Arrival Badge</span>
                        </label>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <Link href="/admin/products" className="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-slate-900 dark:bg-blue-600 text-white font-semibold px-6 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors cursor-pointer"
                        >
                            Create Product
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
