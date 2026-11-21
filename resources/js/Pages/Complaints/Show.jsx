// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function ComplaintsShow({ complaint }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Complaint #{complaint.id}</h2>}>
            <Head title={`Complaint #${complaint.id}`} />
            <div className="mx-auto max-w-2xl space-y-4 px-4 py-6">
                <div className="space-y-3 rounded-lg bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between gap-2">
                        <p className="font-semibold text-gray-900">{complaint.subject}</p>
                        <OrderStatusBadge status={complaint.status} />
                    </div>
                    {complaint.order && (
                        <Link href={route('orders.show', complaint.order.id)} className="text-sm text-green-700">Order {complaint.order.code}</Link>
                    )}
                    <p className="whitespace-pre-wrap text-sm text-gray-800">{complaint.description}</p>
                </div>
                {complaint.resolution && (
                    <div className="rounded-lg bg-green-50 p-5 text-sm text-green-900">
                        <p className="font-semibold">Admin response</p>
                        <p className="mt-1 whitespace-pre-wrap">{complaint.resolution}</p>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
