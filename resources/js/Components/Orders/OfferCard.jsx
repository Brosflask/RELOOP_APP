import { router } from '@inertiajs/react';
import { useState } from 'react';
import Money from './Money';
import OfferForm from './OfferForm';
import OrderStatusBadge from './OrderStatusBadge';

export default function OfferCard({ offer, conversationId, isMine }) {
    const [countering, setCountering] = useState(false);
    const total = Number(offer.unit_price) * Number(offer.quantity);
    const act = (name) => router.post(route(`offers.${name}`, offer.id), {}, { preserveScroll: true });

    return (
        <div className={`max-w-md rounded-lg border p-4 ${isMine ? 'ml-auto border-green-200 bg-green-50' : 'border-gray-200 bg-white'}`}>
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold text-gray-900">{isMine ? 'Your offer' : `Offer from ${offer.maker.name}`}</p>
                <OrderStatusBadge status={offer.status} />
            </div>
            <p className="mt-2 text-sm text-gray-700">
                {offer.quantity} × <Money amount={offer.unit_price} /> = <Money amount={total} className="font-semibold" />
            </p>
            {offer.message && <p className="mt-1 text-sm italic text-gray-600">“{offer.message}”</p>}
            {(offer.can_respond || offer.can_withdraw) && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {offer.can_respond && (
                        <>
                            <button onClick={() => act('accept')} className="rounded-md bg-green-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-800">Accept</button>
                            <button onClick={() => setCountering((v) => !v)} className="rounded-md border border-gray-300 px-3 py-1.5 text-sm">Counter</button>
                            <button onClick={() => act('reject')} className="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700">Reject</button>
                        </>
                    )}
                    {offer.can_withdraw && (
                        <button onClick={() => act('withdraw')} className="rounded-md border border-gray-300 px-3 py-1.5 text-sm">Withdraw</button>
                    )}
                </div>
            )}
            {countering && (
                <div className="mt-3">
                    <OfferForm conversationId={conversationId} parentOfferId={offer.id}
                        defaults={{ unit_price: offer.unit_price, quantity: offer.quantity }} onDone={() => setCountering(false)} />
                </div>
            )}
        </div>
    );
}
