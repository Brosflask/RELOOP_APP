// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, router } from '@inertiajs/react';

export default function OrdersIndex({ orders, tab, filters, statuses }) {
    const go = (params) => router.get(route('orders.index'), { tab, status: filters.status, ...params }, { preserveState: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Orders</h2>}>
            <Head title="Orders" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="inline-flex rounded-md bg-white p-1 shadow-sm">
                        {['buying', 'selling'].map((t) => (
                            <button key={t} onClick={() => go({ tab: t, page: 1 })}
                                className={`rounded px-4 py-1.5 text-sm font-medium ${tab === t ? 'bg-green-700 text-white' : 'text-gray-700'}`}>
                                {t === 'buying' ? 'Buying' : 'Selling'}
                            </button>
                        ))}
                    </div>
                    <select value={filters.status ?? ''} onChange={(e) => go({ status: e.target.value || undefined, page: 1 })}
                        className="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All statuses</option>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </div>

                {orders.data.length === 0 ? (
                    <p className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">No orders here yet.</p>
                ) : (
                    <ul className="divide-y divide-gray-100 rounded-lg bg-white shadow-sm">
                        {orders.data.map((o) => (
                            <li key={o.id}>
                                <Link href={route('orders.show', o.id)} className="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-gray-50">
                                    <div className="min-w-0">
                                        <p className="font-medium text-gray-900">{o.code} · {o.title}</p>
                                        <p className="text-sm text-gray-600">{tab === 'selling' ? 'Buyer' : 'Seller'}: {o.other_party.name}</p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Money amount={o.total_amount} currency={o.currency} className="text-sm font-semibold" />
                                        <OrderStatusBadge status={o.status} label={o.status_label} />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex justify-between text-sm">
                    {orders.prev_page_url ? <Link href={orders.prev_page_url} className="text-green-700">Previous</Link> : <span />}
                    {orders.next_page_url && <Link href={orders.next_page_url} className="text-green-700">Next</Link>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
