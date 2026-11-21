import Money from './Money';
import OrderStatusBadge from './OrderStatusBadge';
import { Link } from '@inertiajs/react';

export default function OrderList({ orders, empty, otherLabel = 'Seller' }) {
    if (!orders.length) return <p className="text-sm text-gray-500">{empty}</p>;
    return (
        <ul className="divide-y divide-gray-100">
            {orders.map((o) => (
                <li key={o.id}>
                    <Link href={route('orders.show', o.id)} className="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-gray-50">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-gray-900">{o.code} · {o.title}</p>
                            <p className="text-xs text-gray-500">{otherLabel}: {o.other_party.name}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Money amount={o.total_amount} currency={o.currency} className="text-sm" />
                            <OrderStatusBadge status={o.status} label={o.status_label} />
                        </div>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
