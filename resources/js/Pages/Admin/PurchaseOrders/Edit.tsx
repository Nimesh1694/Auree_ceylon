import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import PurchaseOrderForm, { PoItemInput, SupplierOption } from './PurchaseOrderForm';

interface Props {
    purchaseOrder: {
        id: number;
        po_number: string;
        supplier_id: number;
        expected_delivery_date: string;
        tax_amount: string | null;
        shipping_cost: string | null;
        notes: string;
        items: PoItemInput[];
    };
    suppliers: SupplierOption[];
    canViewCost: boolean;
}

export default function PurchaseOrderEdit({ purchaseOrder, suppliers, canViewCost }: Props) {
    return (
        <AdminLayout>
            <Head title={`Edit ${purchaseOrder.po_number} | Aura Luxe`} />
            <div className="max-w-5xl space-y-6">
                <div>
                    <Link href={`/admin/purchase-orders/${purchaseOrder.id}`} className="text-xs text-slate-500 hover:underline">
                        &larr; Back to {purchaseOrder.po_number}
                    </Link>
                    <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold mt-1">
                        Edit Draft PO: {purchaseOrder.po_number}
                    </h1>
                </div>

                <PurchaseOrderForm
                    suppliers={suppliers}
                    initialData={purchaseOrder}
                    submitUrl={`/admin/purchase-orders/${purchaseOrder.id}`}
                    method="put"
                    submitLabel="Save Draft Changes"
                    cancelHref={`/admin/purchase-orders/${purchaseOrder.id}`}
                    canViewCost={canViewCost}
                />
            </div>
        </AdminLayout>
    );
}

