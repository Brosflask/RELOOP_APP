// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function MessagesIndex({ conversations }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Messages</h2>}>
            <Head title="Messages" />
            <div className="mx-auto max-w-4xl px-4 py-6">
                {conversations.data.length === 0 ? (
                    <p className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">
                        No conversations yet. Open a listing in the marketplace and message the seller to start one.
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100 rounded-lg bg-white shadow-sm">
                        {conversations.data.map((c) => (
                            <li key={c.id}>
                                <Link href={route('messages.show', c.id)} className="flex items-center justify-between gap-4 p-4 hover:bg-gray-50">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-gray-900">{c.listing?.title ?? 'General enquiry'}</p>
                                        <p className="text-sm text-gray-600">
                                            {c.role === 'buyer' ? 'Seller' : 'Buyer'}: {c.other_party?.name}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {c.latest_offer_status && <OrderStatusBadge status={c.latest_offer_status} />}
                                        {c.unread_count > 0 && (
                                            <span className="rounded-full bg-green-700 px-2 py-0.5 text-xs font-semibold text-white">{c.unread_count}</span>
                                        )}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="mt-4 flex justify-between text-sm">
                    {conversations.prev_page_url ? <Link href={conversations.prev_page_url} className="text-green-700">Newer</Link> : <span />}
                    {conversations.next_page_url && <Link href={conversations.next_page_url} className="text-green-700">Older</Link>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
