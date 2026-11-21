// For Developer 3's recycler and artisan dashboards.
// Pass the array from App\Services\Orders\OrderSummary::forSeller($user) as `summary`.
import OrderList from './OrderList';
import { Link } from '@inertiajs/react';

export default function SellerOrdersWidget({ summary }) {
    const toShip = summary.counts.paid ?? 0;
    const shipped = summary.counts.shipped ?? 0;
    return (
        <section className="rounded-lg bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <h3 className="font-semibold text-gray-900">Orders to fulfil</h3>
                <Link href={route('orders.index', { tab: 'selling' })} className="text-sm text-green-700">All sales</Link>
            </div>
            <p className="mt-1 text-sm text-gray-600">{toShip} to ship · {shipped} on the way</p>
            <div className="mt-2">
                <OrderList orders={summary.to_fulfil} otherLabel="Buyer" empty="Nothing to fulfil right now." />
            </div>
        </section>
    );
}
