// Developer 4. Uses Developer 1's AuthenticatedLayout.
// Shows "paid" only when the server says the payment is successful, which
// only happens after MTN's status endpoint confirmed it.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const POLL_MS = 3000;
const POLL_LIMIT_MS = 2 * 60 * 1000;

export default function PaymentShow({ payment: initial }) {
    const [payment, setPayment] = useState(initial);
    const [timedOut, setTimedOut] = useState(false);

    useEffect(() => {
        if (payment.status !== 'pending') return undefined;
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

                    {payment.status === 'pending' && !timedOut && (
                        <div className="mt-6 space-y-2">
                            <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-yellow-400 border-t-transparent" aria-hidden />
                            <p className="font-medium text-gray-900">Approve the prompt on your phone</p>
                            <p className="text-sm text-gray-600">We sent a request to {payment.payer_msisdn}. Enter your MoMo PIN to confirm.</p>
                        </div>
                    )}
                    {payment.status === 'pending' && timedOut && (
                        <p className="mt-6 text-sm text-gray-700">
                            Still waiting for MTN to confirm. You can leave this page: the order updates automatically once MTN confirms.
                        </p>
                    )}
                    {payment.status === 'successful' && (
                        <p className="mt-6 font-semibold text-green-700">Payment confirmed by MTN. Your order is paid.</p>
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
