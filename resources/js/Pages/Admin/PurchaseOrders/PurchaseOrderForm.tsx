import { Link, useForm } from '@inertiajs/react';
import React from 'react';

export interface SupplierProductOption {
    product_id: number;
    name: string;
    sku: string | null;
    supplier_sku: string | null;
    unit_cost: string | null;
    min_order_qty: number;
    is_preferred: boolean;
}

export interface SupplierOption {
    id: number;
    code: string;
    name: string;
    default_lead_time_days: number;
    products: SupplierProductOption[];
}

export interface PoItemInput {
    product_id: number | '';
    ordered_qty: number;
    unit_cost: string | null;
}

interface Props {
    suppliers: SupplierOption[];
    initialData?: {
        supplier_id: number | '';
        expected_delivery_date: string;
        tax_amount: string | null;
        shipping_cost: string | null;
        notes: string;
        items: PoItemInput[];
    };
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
    cancelHref: string;
    canViewCost: boolean;
}

export default function PurchaseOrderForm({
    suppliers,
    initialData,
    submitUrl,
    method,
    submitLabel,
    cancelHref,
    canViewCost,
}: Props) {
    const firstSupplier = suppliers[0];
    const { data, setData, post, put, processing, errors } = useForm({
        supplier_id: initialData?.supplier_id ?? (firstSupplier?.id ?? ''),
        expected_delivery_date: initialData?.expected_delivery_date ?? '',
        tax_amount: initialData?.tax_amount ?? '0.00',
        shipping_cost: initialData?.shipping_cost ?? '0.00',
        notes: initialData?.notes ?? '',
        items: initialData?.items ?? (firstSupplier?.products[0]
            ? [{
                product_id: firstSupplier.products[0].product_id,
                ordered_qty: firstSupplier.products[0].min_order_qty || 1,
                unit_cost: firstSupplier.products[0].unit_cost,
            }]
            : []),
    });

    const selectedSupplier = suppliers.find((s) => Number(s.id) === Number(data.supplier_id));
    const availableProducts = selectedSupplier?.products ?? [];

    const handleSupplierChange = (supplierIdValue: string) => {
        const nextSupplier = suppliers.find((s) => Number(s.id) === Number(supplierIdValue));
        const firstProduct = nextSupplier?.products[0];
        setData({
            ...data,
            supplier_id: supplierIdValue ? Number(supplierIdValue) : '',
            items: firstProduct
                ? [{
                    product_id: firstProduct.product_id,
                    ordered_qty: firstProduct.min_order_qty || 1,
                    unit_cost: firstProduct.unit_cost,
                }]
                : [],
        });
    };

    const addLine = () => {
        const usedIds = new Set(data.items.map((i) => Number(i.product_id)));
        const nextProduct = availableProducts.find((p) => !usedIds.has(p.product_id)) ?? availableProducts[0];
        if (!nextProduct) return;

        setData('items', [
            ...data.items,
            {
                product_id: nextProduct.product_id,
                ordered_qty: nextProduct.min_order_qty || 1,
                unit_cost: nextProduct.unit_cost,
            },
        ]);
    };

    const removeLine = (index: number) => {
        setData('items', data.items.filter((_, idx) => idx !== index));
    };

    const updateLine = (index: number, patch: Partial<PoItemInput>) => {
        const next = data.items.map((item, idx) => {
            if (idx !== index) return item;
            if (patch.product_id !== undefined) {
                const matched = availableProducts.find((p) => p.product_id === Number(patch.product_id));
                return {
                    ...item,
                    product_id: Number(patch.product_id),
                    unit_cost: matched ? matched.unit_cost : item.unit_cost,
                    ordered_qty: matched ? Math.max(1, matched.min_order_qty) : item.ordered_qty,
                };
            }
            return { ...item, ...patch };
        });
        setData('items', next);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') {
            put(submitUrl);
        } else {
            post(submitUrl);
        }
    };

    const inputClass =
        'w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-white';

    return (
        <form onSubmit={handleSubmit} className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Active Supplier <span className="text-rose-500">*</span>
                    </label>
                    <select
                        value={data.supplier_id}
                        onChange={(e) => handleSupplierChange(e.target.value)}
                        className={inputClass}
                        required
                    >
                        <option value="">Select a supplier...</option>
                        {suppliers.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name} ({s.code})
                            </option>
                        ))}
                    </select>
                    {errors.supplier_id && <p className="text-[11px] text-rose-600 mt-1">{errors.supplier_id}</p>}
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Expected Delivery Date
                    </label>
                    <input
                        type="date"
                        value={data.expected_delivery_date}
                        onChange={(e) => setData('expected_delivery_date', e.target.value)}
                        className={inputClass}
                    />
                    {errors.expected_delivery_date && <p className="text-[11px] text-rose-600 mt-1">{errors.expected_delivery_date}</p>}
                </div>
            </div>

            {/* Line Items */}
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Supplier-Linked Products</h2>
                    <button
                        type="button"
                        disabled={availableProducts.length === 0}
                        onClick={addLine}
                        className="text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 disabled:opacity-50"
                    >
                        + Add Line Item
                    </button>
                </div>

                {availableProducts.length === 0 ? (
                    <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 text-xs text-amber-800 dark:text-amber-300">
                        This supplier has no linked active products. Link products in Supplier Management before creating a PO.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {data.items.map((item, idx) => (
                            <div key={idx} className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/50 border border-slate-200/70 dark:border-slate-800">
                                <div className="sm:col-span-5">
                                    <label className="block text-[11px] font-semibold text-slate-500 mb-1">Product</label>
                                    <select
                                        value={item.product_id}
                                        onChange={(e) => updateLine(idx, { product_id: Number(e.target.value) })}
                                        className={inputClass}
                                        required
                                    >
                                        {availableProducts.map((p) => (
                                            <option key={p.product_id} value={p.product_id}>
                                                {p.name} {p.is_preferred ? '(Preferred)' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {(errors as Record<string, string>)[`items.${idx}.product_id`] && (
                                        <p className="text-[11px] text-rose-600 mt-1">{(errors as Record<string, string>)[`items.${idx}.product_id`]}</p>
                                    )}
                                </div>
                                <div className="sm:col-span-3">
                                    <label className="block text-[11px] font-semibold text-slate-500 mb-1">Ordered Qty</label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={item.ordered_qty}
                                        onChange={(e) => updateLine(idx, { ordered_qty: Number(e.target.value) })}
                                        className={inputClass}
                                        required
                                    />
                                    {(errors as Record<string, string>)[`items.${idx}.ordered_qty`] && (
                                        <p className="text-[11px] text-rose-600 mt-1">{(errors as Record<string, string>)[`items.${idx}.ordered_qty`]}</p>
                                    )}
                                </div>
                                {canViewCost && (
                                    <div className="sm:col-span-3">
                                        <label className="block text-[11px] font-semibold text-slate-500 mb-1">Unit Cost (LKR)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={item.unit_cost ?? ''}
                                            onChange={(e) => updateLine(idx, { unit_cost: e.target.value })}
                                            className={inputClass}
                                            required
                                        />
                                    </div>
                                )}
                                <div className="sm:col-span-1 text-right">
                                    {data.items.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeLine(idx)}
                                            className="text-xs text-rose-600 hover:underline py-2"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {errors.items && <p className="text-[11px] text-rose-600">{errors.items}</p>}
            </div>

            {canViewCost && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tax Amount (LKR)</label>
                        <input
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.tax_amount ?? ''}
                            onChange={(e) => setData('tax_amount', e.target.value)}
                            className={inputClass}
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Shipping Cost (LKR)</label>
                        <input
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.shipping_cost ?? ''}
                            onChange={(e) => setData('shipping_cost', e.target.value)}
                            className={inputClass}
                        />
                    </div>
                </div>
            )}

            <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                <textarea
                    rows={3}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className={inputClass}
                />
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <Link href={cancelHref} className="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">
                    Cancel
                </Link>
                <button
                    type="submit"
                    disabled={processing || data.items.length === 0}
                    className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-5 py-2.5 rounded-xl hover:bg-slate-800 disabled:opacity-50"
                >
                    {processing ? 'Saving...' : submitLabel}
                </button>
            </div>
        </form>
    );
}

