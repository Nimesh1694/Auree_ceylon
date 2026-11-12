import AdminLayout from '@/Layouts/AdminLayout';
import { Category } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    categories: Category[];
}

export default function CategoryIndex({ categories }: Props) {
    const [isCreating, setIsCreating] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
        name_en: '',
        name_si: '',
        slug: '',
        description_en: '',
        description_si: '',
        sort_order: 0,
        is_active: true,
        image: null as File | null,
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/categories', {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setIsCreating(false);
            },
        });
    };

    const handleUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCategory) return;

        router.post(`/admin/categories/${editingCategory.id}`, {
            _method: 'put',
            ...data,
        }, {
            forceFormData: true,
            onSuccess: () => {
                setEditingCategory(null);
                reset();
            },
        });
    };

    const startEdit = (cat: Category) => {
        setEditingCategory(cat);
        setData({
            name_en: cat.name_en,
            name_si: cat.name_si,
            slug: cat.slug,
            description_en: cat.description_en ?? '',
            description_si: cat.description_si ?? '',
            sort_order: cat.sort_order ?? 0,
            is_active: cat.is_active,
            image: null,
        });
    };

    const handleDelete = (id: number, name: string) => {
        if (confirm(`Are you sure you want to delete category "${name}"?`)) {
            router.delete(`/admin/categories/${id}`);
        }
    };

    return (
        <AdminLayout>
            <Head title="Category Management | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Categories</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Organize botanical cosmetic product lines and navigation ordering.</p>
                    </div>
                    {!isCreating && !editingCategory && (
                        <button
                            type="button"
                            onClick={() => {
                                reset();
                                setIsCreating(true);
                            }}
                            className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors shadow-xs cursor-pointer"
                        >
                            + Add New Category
                        </button>
                    )}
                </div>

                {/* Create or Edit Form Panel */}
                {(isCreating || editingCategory) && (
                    <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-rose-200/80 dark:border-slate-800/80 shadow-sm space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800/80">
                            <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                {editingCategory ? `Edit Category: ${editingCategory.name_en}` : 'Create New Category'}
                            </h2>
                            <button
                                type="button"
                                onClick={() => {
                                    setIsCreating(false);
                                    setEditingCategory(null);
                                    reset();
                                }}
                                className="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xs font-bold cursor-pointer"
                            >
                                ✕ Cancel
                            </button>
                        </div>

                        <form onSubmit={editingCategory ? handleUpdate : handleCreate} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">English Name *</label>
                                    <input
                                        type="text"
                                        value={data.name_en}
                                        onChange={(e) => setData('name_en', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="e.g. Botanical Skincare"
                                        required
                                    />
                                    {errors.name_en && <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{errors.name_en}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">Sinhala Name *</label>
                                    <input
                                        type="text"
                                        value={data.name_si}
                                        onChange={(e) => setData('name_si', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="e.g. සම රැකවරණ ආලේපන"
                                        required
                                    />
                                    {errors.name_si && <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{errors.name_si}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">Slug (URL)</label>
                                    <input
                                        type="text"
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="auto-generated-if-blank"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">Sort Order</label>
                                    <input
                                        type="number"
                                        value={data.sort_order}
                                        onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">Status</label>
                                    <select
                                        value={data.is_active ? '1' : '0'}
                                        onChange={(e) => setData('is_active', e.target.value === '1')}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Disabled</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">Description (English)</label>
                                    <textarea
                                        rows={2}
                                        value={data.description_en}
                                        onChange={(e) => setData('description_en', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">Description (Sinhala)</label>
                                    <textarea
                                        rows={2}
                                        value={data.description_si}
                                        onChange={(e) => setData('description_si', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsCreating(false);
                                        setEditingCategory(null);
                                    }}
                                    className="px-4 py-2 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2 text-xs font-semibold bg-slate-900 dark:bg-blue-600 text-white rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors cursor-pointer"
                                >
                                    {editingCategory ? 'Update Category' : 'Save Category'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Categories Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Order</th>
                                <th className="px-6 py-3.5">Name (EN / SI)</th>
                                <th className="px-6 py-3.5">Slug</th>
                                <th className="px-6 py-3.5">Products</th>
                                <th className="px-6 py-3.5">Status</th>
                                <th className="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                            {categories.map((cat) => (
                                <tr key={cat.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                    <td className="px-6 py-4 font-mono font-medium text-slate-500 dark:text-slate-400">{cat.sort_order ?? 0}</td>
                                    <td className="px-6 py-4">
                                        <span className="font-semibold text-slate-900 dark:text-white block">{cat.name_en}</span>
                                        <span className="text-slate-500 dark:text-slate-400 text-[11px] block mt-0.5">{cat.name_si}</span>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-[11px] text-slate-500 dark:text-slate-400">{cat.slug}</td>
                                    <td className="px-6 py-4">
                                        <span className="inline-block px-2.5 py-0.5 font-mono text-[11px] bg-slate-100 dark:bg-slate-800 rounded-lg text-slate-700 dark:text-slate-300 font-semibold">
                                            {cat.products_count ?? 0} items
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                                            cat.is_active
                                                ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
                                        }`}>
                                            {cat.is_active ? 'Active' : 'Disabled'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right space-x-3">
                                        <button
                                            type="button"
                                            onClick={() => startEdit(cat)}
                                            className="text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold cursor-pointer"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => handleDelete(cat.id, cat.name_en)}
                                            className="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 font-semibold cursor-pointer"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
