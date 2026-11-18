import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import SupplierForm, { SupplierFormData } from './SupplierForm';

interface Props {
    supplier: SupplierFormData & { id: number };
}

export default function SupplierEdit({ supplier }: Props) {
    return (
        <AdminLayout>
            <Head title={`Edit ${supplier.name} | Aura Luxe`} />

            <div className="max-w-4xl space-y-6">
                <div>
                    <Link href={`/admin/suppliers/${supplier.id}`} className="text-xs text-slate-500 hover:underline">
                        &larr; Back to Supplier Details
                    </Link>
                    <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold mt-1">
                        Edit Supplier: {supplier.name}
                    </h1>
                </div>

                <SupplierForm
                    initialData={supplier}
                    submitUrl={`/admin/suppliers/${supplier.id}`}
                    method="put"
                    submitLabel="Save Changes"
                    cancelHref={`/admin/suppliers/${supplier.id}`}
                />
            </div>
        </AdminLayout>
    );
}

