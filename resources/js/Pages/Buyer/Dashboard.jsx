// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderList from '@/Components/Orders/OrderList';
import { Head, Link } from '@inertiajs/react';

function Stat({ label, value, href }) {
    return (
        <Link href={href} className="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-green-600">
            <p className="text-sm text-gray-600">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
        </Link>
    );
}

export default function BuyerDashboard({ counts, awaiting_payment, in_progress, conversations, open_complaints }) {
    const active = (counts.paid ?? 0) + (counts.shipped ?? 0) + (counts.delivered ?? 0);
    const marketplace = route().has('marketplace.index') ? route('marketplace.index') : '/';

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Buyer dashboard</h2>}>
            <Head title="Buyer dashboard" />
            <div className="mx-auto max-w-6xl space-y-6 px-4 py-6">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Stat label="Waiting for payment" value={counts.awaiting_payment ?? 0} href={route('orders.index', { status: 'awaiting_payment' })} />
                    <Stat label="On the way" value={active} href={route('orders.index')} />
                    <Stat label="Completed" value={counts.completed ?? 0} href={route('orders.index', { status: 'completed' })} />
                    <Stat label="Open complaints" value={open_complaints} href={route('complaints.index')} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <section className="rounded-lg bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-gray-900">Waiting for your payment</h3>
                            <OrderList orders={awaiting_payment} empty="Nothing to pay right now." />
                        </section>
                        <section className="rounded-lg bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-gray-900">In progress</h3>
                            <OrderList orders={in_progress} empty="No orders in progress." />
                        </section>
                    </div>
                    <aside className="space-y-6">
                        <section className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-gray-900">Conversations</h3>
                                <Link href={route('messages.index')} className="text-sm text-green-700">All</Link>
                            </div>
                            {conversations.length === 0 ? (
                                <p className="mt-2 text-sm text-gray-500">No conversations yet.</p>
                            ) : (
                                <ul className="mt-2 divide-y divide-gray-100">
                                    {conversations.map((c) => (
                                        <li key={c.id}>
                                            <Link href={route('messages.show', c.id)} className="flex items-center justify-between gap-2 py-2 text-sm hover:bg-gray-50">
                                                <span className="min-w-0 truncate">{c.listing_title ?? 'Enquiry'} · {c.seller.name}</span>
                                                {c.unread_count > 0 && <span className="rounded-full bg-green-700 px-2 text-xs text-white">{c.unread_count}</span>}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        <Link href={marketplace} className="block rounded-lg bg-green-700 p-5 text-center font-semibold text-white hover:bg-green-800">
                            Browse recycled materials and products
                        </Link>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
