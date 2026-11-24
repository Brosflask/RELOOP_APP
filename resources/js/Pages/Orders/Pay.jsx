// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Pay({ order, pending_payment_id, sandbox, driver, manual_instructions, conversation_id }) {
    const manual = driver === 'manual';
    const form = useForm({ msisdn: '', delivery_address: order.delivery_address ?? '', ...(manual ? { reference: '' } : {}) });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('payments.store', order.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Pay for order {order.code}</h2>}>
            <Head title={`Pay ${order.code}`} />
            <div className="mx-auto max-w-xl space-y-4 px-4 py-6">
                {sandbox && (
                    <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                        Test mode: this uses the MTN MoMo sandbox. No real money moves.
                    </p>
                )}

                {manual && (
                    <div className="space-y-1 rounded-md bg-sky-50 p-3 text-sm text-sky-900">
                        <p className="font-semibold">How to pay</p>
                        <p>{manual_instructions}</p>
                        {conversation_id && (
                            <Link href={route('messages.show', conversation_id)} className="font-semibold underline">Open your conversation with the seller</Link>
                        )}
                    </div>
                )}

                {pending_payment_id && (
                    <p className="rounded-md bg-sky-50 p-3 text-sm text-sky-800">
                        A payment for this order is already waiting for approval.{' '}
                        <Link href={route('payments.show', pending_payment_id)} className="font-semibold underline">Check its status</Link>
                    </p>
                )}

                <section className="rounded-lg bg-white p-5 shadow-sm">
                    <ul className="divide-y divide-gray-100 text-sm">
                        {order.items.map((i) => (
                            <li key={i.id} className="flex justify-between py-2">
                                <span>{i.title} · {i.quantity} {i.unit}</span>
                                <Money amount={i.line_total} currency={order.currency} />
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 flex justify-between font-semibold">
                        <span>Total</span><Money amount={order.total_amount} currency={order.currency} />
                    </p>
                </section>

                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-5 shadow-sm">
                    <label className="block text-sm">
                        <span className="font-medium text-gray-700">{manual ? 'Number you paid from' : 'MTN Mobile Money number'}</span>
                        <input type="tel" inputMode="tel" autoComplete="tel" placeholder="024 123 4567" value={form.data.msisdn}
                            onChange={(e) => form.setData('msisdn', e.target.value)}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm" required />
                        {form.errors.msisdn && <p className="mt-1 text-xs text-red-600">{form.errors.msisdn}</p>}
                    </label>
                    {manual && (
                        <label className="block text-sm">
                            <span className="font-medium text-gray-700">Transaction ID</span>
                            <input type="text" maxLength={100} placeholder="From your MoMo confirmation SMS" value={form.data.reference}
                                onChange={(e) => form.setData('reference', e.target.value)}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm" required />
                            {form.errors.reference && <p className="mt-1 text-xs text-red-600">{form.errors.reference}</p>}
                        </label>
                    )}
                    <label className="block text-sm">
                        <span className="font-medium text-gray-700">Delivery address</span>
                        <input type="text" maxLength={255} value={form.data.delivery_address}
                            onChange={(e) => form.setData('delivery_address', e.target.value)}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm" required={!order.delivery_address} />
                        {form.errors.delivery_address && <p className="mt-1 text-xs text-red-600">{form.errors.delivery_address}</p>}
                    </label>
                    {(form.errors.order || form.errors.payment) && (
                        <p className="text-sm text-red-600">{form.errors.order ?? form.errors.payment}</p>
                    )}
                    <p className="text-xs text-gray-500">
                        {manual
                            ? 'Your order is marked paid only after the seller confirms they received the money.'
                            : "You'll get a prompt on your phone. Approve it with your MoMo PIN to complete the payment."}
                    </p>
                    <button type="submit" disabled={form.processing}
                        className="w-full rounded-md bg-yellow-400 px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-yellow-500 disabled:opacity-50">
                        {form.processing ? 'Sending…' : manual ? 'I have paid the seller' : 'Pay with MTN MoMo'}
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
