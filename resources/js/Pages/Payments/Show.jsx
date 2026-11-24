// Developer 4. Uses Developer 1's AuthenticatedLayout.
// Shows "paid" only when the server says the payment is successful, which
// only happens after MTN's status endpoint confirmed it.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const POLL_MS = 3000;
const POLL_LIMIT_MS = 2 * 60 * 1000;

export default function PaymentShow({ payment: initial, can_settle }) {
    const [payment, setPayment] = useState(initial);
    const manual = payment.provider === 'manual';
    const reject = useForm({ reason: '' });
    useEffect(() => setPayment(initial), [initial]);
    const [timedOut, setTimedOut] = useState(false);

    useEffect(() => {
        if (payment.status !== 'pending' || manual) return undefined;
        const started = Date.now();
        let stopped = false;

        const tick = async () => {
            if (stopped) return;
            if (Date.now() - started > POLL_LIMIT_MS) {
                setTimedOut(true);
                return;
            }
            try {
                const res = await fetch(route('payments.status', payment.id), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    const next = await res.json();
                    setPayment(next);
                    if (next.status !== 'pending') return;
                }
            } catch {
                // network hiccup: keep polling
            }
            setTimeout(tick, POLL_MS);
        };

        const id = setTimeout(tick, POLL_MS);
        return () => {
            stopped = true;
            clearTimeout(id);
        };
    }, [payment.id, payment.status === 'pending']);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Payment for order {payment.order.code}</h2>}>
            <Head title="Payment status" />
            <div className="mx-auto max-w-xl px-4 py-6">
                <div className="rounded-lg bg-white p-6 text-center shadow-sm">
                    <p className="text-sm text-gray-600">Amount</p>
                    <p className="text-2xl font-semibold"><Money amount={payment.amount} currency={payment.currency} /></p>

                    {manual && payment.status === 'pending' && (
                        <div className="mt-6 space-y-3">
                            <p className="font-medium text-gray-900">Waiting for the seller to confirm they received the money</p>
                            <p className="text-sm text-gray-600">Paid from {payment.payer_msisdn} · reference {payment.provider_reference}</p>
                            {can_settle && (
                                <div className="space-y-2 rounded-md bg-gray-50 p-3 text-left">
                                    <p className="text-sm text-gray-700">Check your MoMo account before confirming. Confirming marks the order as paid.</p>
                                    <button onClick={() => router.post(route('payments.confirm', payment.id))}
                                        className="w-full rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">I received this payment</button>
                                    <form onSubmit={(e) => { e.preventDefault(); reject.post(route('payments.reject', payment.id)); }} className="flex gap-2">
                                        <input value={reject.data.reason} onChange={(e) => reject.setData('reason', e.target.value)} required maxLength={200}
                                            placeholder="Why not? e.g. nothing received" className="min-w-0 flex-1 rounded-md border-gray-300 text-sm" />
                                        <button className="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700">Not received</button>
                                    </form>
                                </div>
                            )}
                        </div>
                    )}
                    {!manual && payment.status === 'pending' && !timedOut && (
                        <div className="mt-6 space-y-2">
                            <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-yellow-400 border-t-transparent" aria-hidden />
                            <p className="font-medium text-gray-900">Approve the prompt on your phone</p>
                            <p className="text-sm text-gray-600">We sent a request to {payment.payer_msisdn}. Enter your MoMo PIN to confirm.</p>
                        </div>
                    )}
                    {!manual && payment.status === 'pending' && timedOut && (
                        <p className="mt-6 text-sm text-gray-700">
                            Still waiting for MTN to confirm. You can leave this page: the order updates automatically once MTN confirms.
                        </p>
                    )}
                    {payment.status === 'successful' && (
                        <p className="mt-6 font-semibold text-green-700">
                            {manual ? 'Payment confirmed by the seller. The order is paid.' : 'Payment confirmed by MTN. Your order is paid.'}
                        </p>
                    )}
                    {payment.status === 'failed' && (
                        <div className="mt-6 space-y-2">
                            <p className="font-semibold text-red-700">The payment did not go through.</p>
                            {payment.failure_reason && <p className="text-sm text-gray-600">{payment.failure_reason}</p>}
                            {payment.order.status === 'awaiting_payment' && (
                                <Link href={route('payments.create', payment.order.id)} className="inline-block rounded-md bg-yellow-400 px-4 py-2 text-sm font-semibold">
                                    Try again
                                </Link>
                            )}
                        </div>
                    )}

                    <Link href={route('orders.show', payment.order.id)} className="mt-6 inline-block text-sm text-green-700">Back to the order</Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
