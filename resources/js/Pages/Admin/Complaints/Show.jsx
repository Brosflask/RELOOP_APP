// Developer 4. Admin view of one complaint.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, useForm } from '@inertiajs/react';

export default function AdminComplaintShow({ complaint }) {
    const form = useForm({ status: complaint.status, resolution: complaint.resolution ?? '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Complaint #{complaint.id}</h2>}>
            <Head title={`Complaint #${complaint.id}`} />
            <div className="mx-auto grid max-w-5xl gap-6 px-4 py-6 lg:grid-cols-3">
                <section className="space-y-3 rounded-lg bg-white p-5 shadow-sm lg:col-span-2">
                    <div className="flex items-center justify-between gap-2">
                        <p className="font-semibold text-gray-900">{complaint.subject}</p>
                        <OrderStatusBadge status={complaint.status} />
                    </div>
                    <p className="text-sm text-gray-600">
                        From {complaint.complainant.name}{complaint.against ? ` about ${complaint.against.name}` : ''} · {complaint.category.replaceAll('_', ' ')}
                    </p>
                    {complaint.order && (
                        <p className="text-sm">
                            <Link href={route('orders.show', complaint.order.id)} className="text-green-700">Order {complaint.order.code}</Link> is {complaint.order.status.replaceAll('_', ' ')}.
                            {complaint.order.status === 'disputed' && <> Settle it on the <Link href={route('admin.disputes.index')} className="text-green-700">disputes page</Link>.</>}
                        </p>
                    )}
                    <p className="whitespace-pre-wrap text-sm text-gray-800">{complaint.description}</p>
                </section>
                <form onSubmit={(e) => { e.preventDefault(); form.patch(route('admin.complaints.update', complaint.id)); }}
                    className="space-y-3 rounded-lg bg-white p-5 shadow-sm">
                    <label className="block text-sm">Status
                        <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} className="mt-1 w-full rounded-md border-gray-300">
                            <option value="open">Open</option>
                            <option value="under_review">Under review</option>
                            <option value="resolved">Resolved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </label>
                    <label className="block text-sm">Response to the complainant
                        <textarea rows={5} value={form.data.resolution} onChange={(e) => form.setData('resolution', e.target.value)} className="mt-1 w-full rounded-md border-gray-300" />
                        {form.errors.resolution && <p className="mt-1 text-xs text-red-600">{form.errors.resolution}</p>}
                    </label>
                    <button disabled={form.processing} className="w-full rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save</button>
                    {complaint.resolver && <p className="text-xs text-gray-500">Last resolved by {complaint.resolver}</p>}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
