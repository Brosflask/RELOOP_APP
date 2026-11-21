// Developer 4. Admin complaint queue.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, router } from '@inertiajs/react';

export default function AdminComplaints({ complaints, filters, statuses }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Complaints</h2>}>
            <Head title="Complaints" />
            <div className="mx-auto max-w-6xl space-y-4 px-4 py-6">
                <select value={filters.status ?? ''} onChange={(e) => router.get(route('admin.complaints.index'), e.target.value ? { status: e.target.value } : {})}
                    className="rounded-md border-gray-300 text-sm">
                    <option value="">Open and under review</option>
                    {statuses.map((s) => <option key={s} value={s}>{s.replaceAll('_', ' ')}</option>)}
                </select>
                <ul className="divide-y divide-gray-100 rounded-lg bg-white shadow-sm">
                    {complaints.data.map((c) => (
                        <li key={c.id}>
                            <Link href={route('admin.complaints.show', c.id)} className="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-gray-50">
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-gray-900">#{c.id} {c.subject}</p>
                                    <p className="text-sm text-gray-600">{c.complainant}{c.against ? ` about ${c.against}` : ''}{c.order ? ` · Order ${c.order.code}` : ''}</p>
                                </div>
                                <OrderStatusBadge status={c.status} />
                            </Link>
                        </li>
                    ))}
                    {complaints.data.length === 0 && <li className="p-6 text-sm text-gray-500">No complaints here.</li>}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
