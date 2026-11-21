// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function OrdersShow({ order, viewer_role, can }) {
    const { errors } = usePage().props;

    const cancel = () => {
        const reason = window.prompt('Why are you cancelling this order? (optional)') ?? null;
        if (reason === null) return;
        router.post(route('orders.cancel', order.id), { reason }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Order {order.code}</h2>}>
            <Head title={`Order ${order.code}`} />
            <div className="mx-auto grid max-w-5xl gap-4 px-4 py-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <section className="rounded-lg bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <OrderStatusBadge status={order.status} label={order.status_label} />
                            <p className="text-sm text-gray-600">
                                {viewer_role === 'buyer' ? `Seller: ${order.seller.name}` : `Buyer: ${order.buyer.name}`}
                            </p>
                        </div>
                        <table className="mt-4 w-full text-sm">
                            <thead className="text-left text-gray-500">
                                <tr><th className="py-2">Item</th><th>Qty</th><th>Price</th><th className="text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {order.items.map((i) => (
                                    <tr key={i.id}>
                                        <td className="py-2">{i.title}</td>
                                        <td>{i.quantity} {i.unit}</td>
                                        <td><Money amount={i.unit_price} currency={order.currency} /></td>
                                        <td className="text-right"><Money amount={i.line_total} currency={order.currency} /></td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr><td colSpan={3} className="pt-3 font-semibold">Total</td>
                                    <td className="pt-3 text-right font-semibold"><Money amount={order.total_amount} currency={order.currency} /></td></tr>
                            </tfoot>
                        </table>
                        {order.delivery_address && <p className="mt-4 text-sm text-gray-700">Deliver to: {order.delivery_address}</p>}
                        {order.delivery_notes && <p className="text-sm text-gray-600">{order.delivery_notes}</p>}
                    </section>

                    {Object.values(errors ?? {}).length > 0 && (
                        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700">{Object.values(errors).join(' ')}</div>
                    )}

                    <section className="flex flex-wrap gap-3">
                        {can.pay && (
                            // The payment page arrives with the payments module.
                            route().has('payments.create') && (
                                <Link href={route('payments.create', order.id)} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">
                                    Pay with MTN MoMo
                                </Link>
                            )
                        )}
                        {can.cancel && (
                            <button onClick={cancel} className="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700">Cancel order</button>
                        )}
                        {order.conversation_id && (
                            <Link href={route('messages.show', order.conversation_id)} className="rounded-md border border-gray-300 px-4 py-2 text-sm">
                                Open conversation
                            </Link>
                        )}
                    </section>
                </div>

                <aside className="rounded-lg bg-white p-5 shadow-sm">
                    <h3 className="font-semibold text-gray-900">History</h3>
                    <ol className="mt-3 space-y-3 border-l border-gray-200 pl-4">
                        {order.history.map((h, idx) => (
                            <li key={idx} className="text-sm">
                                <p className="font-medium text-gray-900">{h.label}</p>
                                <p className="text-gray-500">{new Date(h.at).toLocaleString()}{h.by ? ` · ${h.by}` : ''}</p>
                                {h.note && <p className="text-gray-600">{h.note}</p>}
                            </li>
                        ))}
                    </ol>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
