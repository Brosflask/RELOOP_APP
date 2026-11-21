// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import OfferCard from '@/Components/Orders/OfferCard';
import OfferForm from '@/Components/Orders/OfferForm';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function MessagesShow({ conversation, timeline, can_participate }) {
    const { auth } = usePage().props;
    const me = auth?.user?.id;
    const [showOffer, setShowOffer] = useState(false);
    const form = useForm({ body: '' });
    const hasPendingOffer = timeline.some((t) => t.kind === 'offer' && t.status === 'pending');

    const send = (e) => {
        e.preventDefault();
        form.post(route('messages.messages.store', conversation.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const other = conversation.buyer.id === me ? conversation.seller : conversation.buyer;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Conversation with {other.name}</h2>}>
            <Head title="Conversation" />
            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6">
                {conversation.listing && (
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white p-4 shadow-sm">
                        <div>
                            <p className="font-medium text-gray-900">{conversation.listing.title}</p>
                            <p className="text-sm text-gray-600">Listed at <Money amount={conversation.listing.price} /> per unit</p>
                        </div>
                        {conversation.orders.map((o) => (
                            <Link key={o.id} href={route('orders.show', o.id)} className="flex items-center gap-2 text-sm text-green-700">
                                Order {o.code} <OrderStatusBadge status={o.status} />
                            </Link>
                        ))}
                    </div>
                )}

                <div className="space-y-3 rounded-lg bg-white p-4 shadow-sm">
                    {timeline.length === 0 && <p className="text-sm text-gray-500">No messages yet.</p>}
                    {timeline.map((item) =>
                        item.kind === 'offer' ? (
                            <OfferCard key={`o${item.id}`} offer={item} conversationId={conversation.id} isMine={item.maker.id === me} />
                        ) : (
                            <div key={`m${item.id}`} className={`max-w-md rounded-lg px-3 py-2 text-sm ${item.sender.id === me ? 'ml-auto bg-green-700 text-white' : 'bg-gray-100 text-gray-900'}`}>
                                <p className="whitespace-pre-wrap break-words">{item.body}</p>
                                <p className={`mt-1 text-xs ${item.sender.id === me ? 'text-green-100' : 'text-gray-500'}`}>
                                    {new Date(item.created_at).toLocaleString()}
                                </p>
                            </div>
                        ),
                    )}
                </div>

                {can_participate && conversation.status === 'open' && (
                    <div className="space-y-3">
                        <form onSubmit={send} className="flex gap-2">
                            <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={2} maxLength={2000}
                                placeholder="Write a message" className="flex-1 rounded-md border-gray-300 shadow-sm" required />
                            <button type="submit" disabled={form.processing}
                                className="self-end rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50">
                                Send
                            </button>
                        </form>
                        {form.errors.body && <p className="text-sm text-red-600">{form.errors.body}</p>}
                        {conversation.listing && !hasPendingOffer && (
                            showOffer
                                ? <OfferForm conversationId={conversation.id} onDone={() => setShowOffer(false)} />
                                : <button onClick={() => setShowOffer(true)} className="text-sm font-semibold text-green-700">Make an offer</button>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
