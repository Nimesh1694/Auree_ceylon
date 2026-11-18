import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import SupplierForm from './SupplierForm';

export default function SupplierCreate() {
    return (
        <AdminLayout>
            <Head title="Add Supplier | Aura Luxe" />

            <div className="max-w-4xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <Link href="/admin/suppliers" className="text-xs text-slate-500 hover:underline">
                            &larr; Back to Suppliers
                        </Link>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold mt-1">Register New Supplier</h1>
                    </div>
                </div>

                <SupplierForm
                    submitUrl="/admin/suppliers"
                    method="post"
                    submitLabel="Register Supplier"
                    cancelHref="/admin/suppliers"
                />
            </div>
        </AdminLayout>
    );
}

