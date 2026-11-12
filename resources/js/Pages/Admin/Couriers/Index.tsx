import AdminLayout from '@/Layouts/AdminLayout';
import { Courier } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    couriers: Courier[];
}

export default function CourierIndex({ couriers }: Props) {
    const [isAdding, setIsAdding] = useState(false);

    const { data, setData, post, reset, errors, processing } = useForm({
        name: '',
        code: '',
        phone: '',
        website: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/couriers', {
            onSuccess: () => {
                reset();
                setIsAdding(false);
            },
        });
    };

    const toggleStatus = (courier: Courier) => {
        router.put(`/admin/couriers/${courier.id}`, {
            name: courier.name,
            code: courier.code,
            phone: courier.phone,
            website: courier.website,
            is_active: !courier.is_active,
        });
    };

    return (
        <AdminLayout>
            <Head title="Courier Partners | Aura Luxe Administration" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl text-stone-900 font-semibold">Courier Partners</h1>
                        <p className="text-xs text-stone-500 mt-1">Manage domestic third-party logistics (3PL) and Cash on Delivery dispatch partners.</p>
                    </div>
                    {!isAdding && (
                        <button
                            type="button"
                            onClick={() => setIsAdding(true)}
                            className="bg-stone-900 text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-stone-800 transition-colors shadow-xs"
                        >
                            + Add Courier Partner
                        </button>
                    )}
                </div>

                {/* Add Courier Form */}
                {isAdding && (
                    <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-xs space-y-4 text-xs">
                        <div className="flex items-center justify-between pb-3 border-b border-stone-100">
                            <h2 className="font-serif text-base font-semibold text-stone-900">Add New Courier</h2>
                            <button
                                type="button"
                                onClick={() => setIsAdding(false)}
                                className="text-stone-400 hover:text-stone-700 text-xs font-bold"
                            >
                                ✕ Cancel
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Company Name *</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g. Domex Courier"
                                        className="mt-1 w-full text-xs rounded-lg border-stone-300"
                                        required
                                    />
                                    {errors.name && <p className="text-rose-600 text-[11px] mt-1">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">Internal Code (Unique) *</label>
                                    <input
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value.toLowerCase())}
                                        placeholder="e.g. domex"
                                        className="mt-1 w-full text-xs rounded-lg border-stone-300 font-mono"
                                        required
                                    />
                                    {errors.code && <p className="text-rose-600 text-[11px] mt-1">{errors.code}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Customer Service Phone</label>
                                    <input
                                        type="text"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="011 7 704 704"
                                        className="mt-1 w-full text-xs rounded-lg border-stone-300 font-mono"
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">Tracking Website URL</label>
                                    <input
                                        type="url"
                                        value={data.website}
                                        onChange={(e) => setData('website', e.target.value)}
                                        placeholder="https://domex.lk"
                                        className="mt-1 w-full text-xs rounded-lg border-stone-300"
                                    />
                                    {errors.website && <p className="text-rose-600 text-[11px] mt-1">{errors.website}</p>}
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setIsAdding(false)}
                                    className="px-4 py-2 text-stone-600 hover:text-stone-900"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-rose-900 text-white font-semibold px-5 py-2 rounded-lg hover:bg-rose-800 disabled:opacity-50"
                                >
                                    Save Courier Partner
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Couriers List Table */}
                <div className="bg-white rounded-xl border border-stone-200 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-xs divide-y divide-stone-200">
                        <thead className="bg-stone-50 text-stone-600 font-semibold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-3">Courier Name</th>
                                <th className="px-6 py-3">Code</th>
                                <th className="px-6 py-3">Phone</th>
                                <th className="px-6 py-3">Website</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-stone-100">
                            {couriers.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-stone-400">
                                        No couriers added yet.
                                    </td>
                                </tr>
                            ) : (
                                couriers.map((c) => (
                                    <tr key={c.id} className="hover:bg-stone-50/80 transition-colors">
                                        <td className="px-6 py-4 font-semibold text-stone-900">
                                            {c.name}
                                        </td>
                                        <td className="px-6 py-4 font-mono text-[11px] text-stone-500">
                                            {c.code}
                                        </td>
                                        <td className="px-6 py-4 font-mono text-stone-600">
                                            {c.phone ?? '—'}
                                        </td>
                                        <td className="px-6 py-4 text-stone-600">
                                            {c.website ? (
                                                <a href={c.website} target="_blank" rel="noreferrer" className="text-rose-900 hover:underline">
                                                    {c.website}
                                                </a>
                                            ) : '—'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                                                c.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600'
                                            }`}>
                                                {c.is_active ? 'Active' : 'Disabled'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button
                                                type="button"
                                                onClick={() => toggleStatus(c)}
                                                className="text-stone-700 hover:text-stone-900 font-semibold"
                                            >
                                                {c.is_active ? 'Deactivate' : 'Activate'}
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}

