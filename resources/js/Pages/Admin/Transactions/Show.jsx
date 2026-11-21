// Developer 4. One payment with its full MTN log.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, router, useForm } from '@inertiajs/react';

function Row({ k, v }) {
    return <div className="flex justify-between gap-4 py-1.5 text-sm"><dt className="text-gray-500">{k}</dt><dd className="break-all text-right text-gray-900">{v ?? '–'}</dd></div>;
}

export default function TransactionShow({ payment, can_recheck }) {
    const ack = useForm({ note: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Payment #{payment.id}</h2>}>
            <Head title={`Payment #${payment.id}`} />
            <div className="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-3">
                <div className="space-y-4">
                    <section className="rounded-lg bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <Money amount={payment.amount} currency={payment.currency} className="text-xl font-semibold" />
                            <OrderStatusBadge status={payment.status} />
                        </div>
                        <dl className="mt-3 divide-y divide-gray-100">
                            <Row k="Order" v={payment.order && <Link href={route('orders.show', payment.order.id)} className="text-green-700">{payment.order.code} ({payment.order.status})</Link>} />
                            <Row k="Payer" v={`${payment.payer?.name} · ${payment.payer_msisdn}`} />
                            <Row k="Payee" v={payment.payee?.name} />
                            <Row k="Our reference" v={payment.transaction_reference} />
                            <Row k="MTN reference" v={payment.provider_reference} />
                            <Row k="Checks" v={`${payment.check_attempts} · last ${payment.last_checked_at ? new Date(payment.last_checked_at).toLocaleString() : 'never'}`} />
                            <Row k="Verified" v={payment.verified_at && new Date(payment.verified_at).toLocaleString()} />
                            <Row k="Failure" v={payment.failure_reason} />
                        </dl>
                        {can_recheck && (
                            <button onClick={() => router.post(route('admin.transactions.recheck', payment.id), {}, { preserveScroll: true })}
                                className="mt-4 w-full rounded-md bg-yellow-400 px-4 py-2 text-sm font-semibold">Re-check with MTN</button>
                        )}
                    </section>
                    {payment.requires_attention && (
                        <form onSubmit={(e) => { e.preventDefault(); ack.post(route('admin.transactions.acknowledge', payment.id)); }}
                            className="space-y-2 rounded-lg bg-red-50 p-5 text-sm">
                            <p className="font-semibold text-red-800">Needs attention</p>
                            <p className="text-red-800">{payment.attention_reason}</p>
                            <input value={ack.data.note} onChange={(e) => ack.setData('note', e.target.value)} placeholder="What did you do? (e.g. refunded on 21 Nov)"
                                className="w-full rounded-md border-gray-300" required />
                            {ack.errors.note && <p className="text-xs text-red-600">{ack.errors.note}</p>}
                            <button className="rounded-md bg-red-700 px-3 py-1.5 font-semibold text-white">Mark as handled</button>
                        </form>
                    )}
                </div>
                <section className="rounded-lg bg-white p-5 shadow-sm lg:col-span-2">
                    <h3 className="font-semibold text-gray-900">MTN log</h3>
                    <ol className="mt-3 space-y-3">
                        {payment.events.map((e) => (
                            <li key={e.id} className="rounded-md border border-gray-100 p-3">
                                <p className="text-sm font-medium text-gray-900">{e.type.replaceAll('_', ' ')} {e.http_status && <span className="text-gray-500">· HTTP {e.http_status}</span>}</p>
                                <p className="text-xs text-gray-500">{new Date(e.created_at).toLocaleString()}</p>
                                {e.payload && Object.keys(e.payload).length > 0 && (
                                    <pre className="mt-2 max-h-48 overflow-auto rounded bg-gray-50 p-2 text-xs">{JSON.stringify(e.payload, null, 2)}</pre>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
