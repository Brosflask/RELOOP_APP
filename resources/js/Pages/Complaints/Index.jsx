// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function ComplaintsIndex({ complaints }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">My complaints</h2>}>
            <Head title="Complaints" />
            <div className="mx-auto max-w-4xl space-y-4 px-4 py-6">
                <Link href={route('complaints.create')} className="inline-block rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">New complaint</Link>
                {complaints.data.length === 0 ? (
                    <p className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">You haven't raised any complaints.</p>
                ) : (
                    <ul className="divide-y divide-gray-100 rounded-lg bg-white shadow-sm">
                        {complaints.data.map((c) => (
                            <li key={c.id}>
                                <Link href={route('complaints.show', c.id)} className="flex items-center justify-between gap-3 p-4 hover:bg-gray-50">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-gray-900">{c.subject}</p>
                                        <p className="text-sm text-gray-600">{c.order ? `Order ${c.order.code}` : 'No order'} · {new Date(c.created_at).toLocaleDateString()}</p>
                                    </div>
                                    <OrderStatusBadge status={c.status} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
