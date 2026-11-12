import AdminLayout from '@/Layouts/AdminLayout';
import { Category, Product, ProductImage } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    product: Product & { images: ProductImage[] };
    categories: Category[];
    canViewCostPrice: boolean;
}

export default function ProductEdit({ product, categories, canViewCostPrice }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        category_id: product.category_id ? String(product.category_id) : '',
        name_en: product.name_en,
        name_si: product.name_si,
        slug: product.slug,
        sku: product.sku ?? '',
        short_description_en: product.short_description_en ?? '',
        short_description_si: product.short_description_si ?? '',
        description_en: product.description_en ?? '',
        description_si: product.description_si ?? '',
        ingredients_en: product.ingredients_en ?? '',
        ingredients_si: product.ingredients_si ?? '',
        directions_en: product.directions_en ?? '',
        directions_si: product.directions_si ?? '',
        price: String(product.price),
        compare_at_price: product.compare_at_price ? String(product.compare_at_price) : '',
        cost_price: product.cost_price ? String(product.cost_price) : '',
        low_stock_threshold: product.low_stock_threshold,
        expiry_date: product.expiry_date ?? '',
        is_active: product.is_active,
        is_featured: product.is_featured,
        is_best_seller: product.is_best_seller,
        is_new_arrival: product.is_new_arrival,
    });

    const [uploadingImage, setUploadingImage] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/products/${product.id}`);
    };

    const handleUploadImage = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files?.[0]) return;
        const file = e.target.files[0];
        setUploadingImage(true);

        const formData = new FormData();
        formData.append('image', file);

        router.post(`/admin/products/${product.id}/images`, formData, {
            onFinish: () => {
                setUploadingImage(false);
                e.target.value = '';
            },
        });
    };

    const handleSetPrimary = (imageId: number) => {
        router.patch(`/admin/products/${product.id}/images/${imageId}/primary`);
    };

    const handleDeleteImage = (imageId: number) => {
        if (confirm('Are you sure you want to delete this image?')) {
            router.delete(`/admin/products/${product.id}/images/${imageId}`);
        }
    };

    return (
        <AdminLayout>
            <Head title={`Edit Product: ${product.name_en} | Aura Luxe`} />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Edit Product</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{product.name_en} &bull; SKU: {product.sku ?? 'N/A'}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={`/products/${product.slug}`}
                            target="_blank"
                            className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium"
                        >
                            View on Storefront ↗
                        </Link>
                        <Link href="/admin/products" className="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                            &larr; Products
                        </Link>
                    </div>
                </div>

                {/* Edit Form */}
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
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">URL Slug</label>
                                <input
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Pricing & Thresholds */}
                    <div className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <h2 className="font-semibold text-slate-900 dark:text-white text-sm border-b border-slate-100 dark:border-slate-800/80 pb-2">2. Pricing & Low Stock Alert</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Selling Price (LKR) *</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Compare-at Price (LKR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.compare_at_price}
                                    onChange={(e) => setData('compare_at_price', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>

                            {canViewCostPrice && (
                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Cost Price (Internal Only)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={data.cost_price}
                                        onChange={(e) => setData('cost_price', e.target.value)}
                                        className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Current Stock Quantity</label>
                                <div className="mt-1 p-2 bg-slate-100 dark:bg-slate-800 rounded-xl font-mono font-semibold text-slate-800 dark:text-slate-200">
                                    {product.stock_quantity} units
                                </div>
                                <span className="text-[10px] text-slate-500 dark:text-slate-400">
                                    Use <Link href="/admin/inventory" className="text-rose-700 dark:text-blue-400 underline">Inventory Adjustment</Link> to restock or deduct stock with audit logging.
                                </span>
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
                        <h2 className="font-semibold text-slate-900 dark:text-white text-sm border-b border-slate-100 dark:border-slate-800/80 pb-2">3. Ingredients & Usage</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Ingredients (English)</label>
                                <textarea
                                    rows={3}
                                    value={data.ingredients_en}
                                    onChange={(e) => setData('ingredients_en', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Ingredients (Sinhala)</label>
                                <textarea
                                    rows={3}
                                    value={data.ingredients_si}
                                    onChange={(e) => setData('ingredients_si', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Directions (English)</label>
                                <textarea
                                    rows={2}
                                    value={data.directions_en}
                                    onChange={(e) => setData('directions_en', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700 dark:text-slate-300">Directions (Sinhala)</label>
                                <textarea
                                    rows={2}
                                    value={data.directions_si}
                                    onChange={(e) => setData('directions_si', e.target.value)}
                                    className="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
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
                            <span className="font-medium text-slate-700 dark:text-slate-300">Active</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_best_seller}
                                onChange={(e) => setData('is_best_seller', e.target.checked)}
                                className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                            />
                            <span className="font-medium text-slate-700 dark:text-slate-300">Best Seller</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_new_arrival}
                                onChange={(e) => setData('is_new_arrival', e.target.checked)}
                                className="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-rose-900 dark:text-blue-500 focus:ring-rose-800 dark:focus:ring-blue-500"
                            />
                            <span className="font-medium text-slate-700 dark:text-slate-300">New Arrival</span>
                        </label>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <Link href="/admin/products" className="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-slate-900 dark:bg-blue-600 text-white font-semibold px-6 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors cursor-pointer"
                        >
                            Update Product
                        </button>
                    </div>
                </form>

                {/* Product Images Management Gallery */}
                <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
                    <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800/80">
                        <div>
                            <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">Product Images Gallery</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Upload multiple photos, appoint the primary hero image, or delete outdated images.</p>
                        </div>
                        <label className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-xl cursor-pointer hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors">
                            {uploadingImage ? 'Uploading...' : '+ Upload New Image'}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={handleUploadImage}
                                disabled={uploadingImage}
                                className="hidden"
                            />
                        </label>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        {product.images && product.images.length > 0 ? (
                            product.images.map((img) => (
                                <div key={img.id} className="relative rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden group bg-slate-50 dark:bg-slate-800">
                                    <div className="aspect-square">
                                        <img src={`/storage/${img.path}`} alt="" className="w-full h-full object-cover" />
                                    </div>
                                    <div className="p-2 flex items-center justify-between bg-white dark:bg-[#1E293B] border-t border-slate-100 dark:border-slate-700/80 text-xs">
                                        {img.is_primary ? (
                                            <span className="text-[10px] bg-rose-900 dark:bg-blue-600 text-white px-2 py-0.5 rounded font-semibold uppercase">
                                                Primary
                                            </span>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() => handleSetPrimary(img.id)}
                                                className="text-[11px] text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-medium cursor-pointer"
                                            >
                                                Make Primary
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => handleDeleteImage(img.id)}
                                            className="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 text-[11px] font-semibold cursor-pointer"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="col-span-4 py-8 text-center text-xs text-slate-400 dark:text-slate-500">
                                No images uploaded yet. The storefront will use the botanical beauty illustration fallback.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
