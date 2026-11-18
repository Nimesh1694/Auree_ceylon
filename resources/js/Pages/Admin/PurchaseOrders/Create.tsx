import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import PurchaseOrderForm, { SupplierOption } from './PurchaseOrderForm';

interface Props {
    suppliers: SupplierOption[];
    canViewCost: boolean;
}

export default function PurchaseOrderCreate({ suppliers, canViewCost }: Props) {
    return (
        <AdminLayout>
            <Head title="New Purchase Order | Aura Luxe" />
            <div className="max-w-5xl space-y-6">
                <div>
                    <Link href="/admin/purchase-orders" className="text-xs text-slate-500 hover:underline">
                        &larr; Back to Purchase Orders
                    </Link>
                    <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold mt-1">
                        Create Draft Purchase Order
                    </h1>
                </div>

                <PurchaseOrderForm
                    suppliers={suppliers}
                    submitUrl="/admin/purchase-orders"
                    method="post"
                    submitLabel="Create Draft PO"
                    cancelHref="/admin/purchase-orders"
                    canViewCost={canViewCost}
                />
            </div>
        </AdminLayout>
    );
}

