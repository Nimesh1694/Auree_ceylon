import { Link, useForm } from '@inertiajs/react';
import React from 'react';

export interface SupplierFormData {
    code: string;
    name: string;
    contact_person: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
    tax_id: string;
    payment_terms: string;
    default_lead_time_days: number;
    status: 'active' | 'inactive';
    notes: string;
}

interface Props {
    initialData?: Partial<SupplierFormData> & { id?: number };
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
    cancelHref: string;
}

export default function SupplierForm({ initialData, submitUrl, method, submitLabel, cancelHref }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<SupplierFormData>({
        code: initialData?.code ?? '',
        name: initialData?.name ?? '',
        contact_person: initialData?.contact_person ?? '',
        email: initialData?.email ?? '',
        phone: initialData?.phone ?? '',
        address: initialData?.address ?? '',
        city: initialData?.city ?? '',
        country: initialData?.country ?? 'Sri Lanka',
        tax_id: initialData?.tax_id ?? '',
        payment_terms: initialData?.payment_terms ?? 'net_30',
        default_lead_time_days: initialData?.default_lead_time_days ?? 7,
        status: initialData?.status ?? 'active',
        notes: initialData?.notes ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') {
            put(submitUrl);
        } else {
            post(submitUrl);
        }
    };

    const inputClass =
        'w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-blue-500';

    return (
        <form onSubmit={handleSubmit} className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Supplier Name <span className="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={inputClass}
                        required
                    />
                    {errors.name && <p className="text-[11px] text-rose-600 mt-1">{errors.name}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Supplier Code <span className="text-[11px] font-normal text-slate-400">(Auto-generated SUP-YYYY-NNNN if blank)</span>
                    </label>
                    <input
                        type="text"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                        placeholder="SUP-2026-0001"
                        className={`${inputClass} font-mono`}
                    />
                    {errors.code && <p className="text-[11px] text-rose-600 mt-1">{errors.code}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Person</label>
                    <input
                        type="text"
                        value={data.contact_person}
                        onChange={(e) => setData('contact_person', e.target.value)}
                        className={inputClass}
                    />
                    {errors.contact_person && <p className="text-[11px] text-rose-600 mt-1">{errors.contact_person}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className={inputClass}
                    />
                    {errors.email && <p className="text-[11px] text-rose-600 mt-1">{errors.email}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone</label>
                    <input
                        type="text"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        className={inputClass}
                    />
                    {errors.phone && <p className="text-[11px] text-rose-600 mt-1">{errors.phone}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tax / Business Registration ID</label>
                    <input
                        type="text"
                        value={data.tax_id}
                        onChange={(e) => setData('tax_id', e.target.value)}
                        className={inputClass}
                    />
                    {errors.tax_id && <p className="text-[11px] text-rose-600 mt-1">{errors.tax_id}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">City</label>
                    <input
                        type="text"
                        value={data.city}
                        onChange={(e) => setData('city', e.target.value)}
                        className={inputClass}
                    />
                    {errors.city && <p className="text-[11px] text-rose-600 mt-1">{errors.city}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Country</label>
                    <input
                        type="text"
                        value={data.country}
                        onChange={(e) => setData('country', e.target.value)}
                        className={inputClass}
                    />
                    {errors.country && <p className="text-[11px] text-rose-600 mt-1">{errors.country}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Payment Terms <span className="text-rose-500">*</span>
                    </label>
                    <select
                        value={data.payment_terms}
                        onChange={(e) => setData('payment_terms', e.target.value)}
                        className={inputClass}
                    >
                        <option value="cod">Cash on Delivery (COD)</option>
                        <option value="advance">Advance Payment</option>
                        <option value="net_7">Net 7 Days</option>
                        <option value="net_15">Net 15 Days</option>
                        <option value="net_30">Net 30 Days</option>
                        <option value="net_60">Net 60 Days</option>
                    </select>
                    {errors.payment_terms && <p className="text-[11px] text-rose-600 mt-1">{errors.payment_terms}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Default Lead Time (Days) <span className="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        min={0}
                        max={365}
                        value={data.default_lead_time_days}
                        onChange={(e) => setData('default_lead_time_days', Number(e.target.value))}
                        className={inputClass}
                        required
                    />
                    {errors.default_lead_time_days && <p className="text-[11px] text-rose-600 mt-1">{errors.default_lead_time_days}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                    <select
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value as 'active' | 'inactive')}
                        className={inputClass}
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    {errors.status && <p className="text-[11px] text-rose-600 mt-1">{errors.status}</p>}
                </div>
            </div>

            <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                <textarea
                    rows={2}
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    className={inputClass}
                />
                {errors.address && <p className="text-[11px] text-rose-600 mt-1">{errors.address}</p>}
            </div>

            <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Internal Notes</label>
                <textarea
                    rows={3}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className={inputClass}
                />
                {errors.notes && <p className="text-[11px] text-rose-600 mt-1">{errors.notes}</p>}
            </div>

            <div className="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <Link
                    href={cancelHref}
                    className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-5 py-2.5 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors"
                >
                    {processing ? 'Saving...' : submitLabel}
                </button>
            </div>
        </form>
    );
}

