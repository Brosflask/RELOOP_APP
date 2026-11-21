// Developer 4. Settle disputed orders.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import { Head, Link, useForm } from '@inertiajs/react';

function Resolve({ order }) {
    const form = useForm({ outcome: 'complete', note: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('admin.disputes.resolve', order.id), { preserveScroll: true }); }}
            className="mt-3 flex flex-wrap items-end gap-2">
            <select value={form.data.outcome} onChange={(e) => form.setData('outcome', e.target.value)} className="rounded-md border-gray-300 text-sm">
                <option value="complete">Complete (seller keeps payment)</option>
                <option value="cancel">Cancel (buyer to be refunded)</option>
            </select>
            <input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Reason" required
                className="min-w-0 flex-1 rounded-md border-gray-300 text-sm" />
            <button disabled={form.processing} className="rounded-md bg-green-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Resolve</button>
            {(form.errors.note || form.errors.status) && <p className="w-full text-xs text-red-600">{form.errors.note ?? form.errors.status}</p>}
        </form>
    );
}

export default function Disputes({ orders }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Disputes</h2>}>
            <Head title="Disputes" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                {orders.data.length === 0 && <p className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">No open disputes.</p>}
                {orders.data.map((o) => (
                    <section key={o.id} className="rounded-lg bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Link href={route('orders.show', o.id)} className="font-semibold text-green-700">{o.code} · {o.title}</Link>
                            <Money amount={o.total_amount} currency={o.currency} className="font-semibold" />
                        </div>
                        <p className="text-sm text-gray-600">Buyer {o.buyer} · Seller {o.seller}</p>
                        <ul className="mt-2 text-sm">
                            {o.complaints.map((c) => (
                                <li key={c.id}><Link href={route('admin.complaints.show', c.id)} className="text-green-700">Complaint #{c.id}</Link>: {c.subject} ({c.status})</li>
                            ))}
                        </ul>
                        <Resolve order={o} />
                    </section>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
