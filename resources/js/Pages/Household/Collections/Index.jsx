// Developer 2. A household's pickup requests.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import StatusBadge from '@/Components/UI/StatusBadge';
import MaterialChips from '@/Components/Collection/MaterialChips';
import PickupWhen from '@/Components/Collection/PickupWhen';
import { Head, Link } from '@inertiajs/react';

export default function HouseholdCollectionsIndex({ requests, open_count, kg_collected }) {
    const newRequest = (
        <Link href={route('household.collections.create')} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
            Request a pickup
        </Link>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="My pickups" actions={newRequest} />}>
            <Head title="My pickups" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                <dl className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <dt className="text-sm text-gray-500">Open requests</dt>
                        <dd className="mt-1 text-2xl font-semibold text-gray-900">{open_count}</dd>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <dt className="text-sm text-gray-500">Plastic you have recycled</dt>
                        <dd className="mt-1 text-2xl font-semibold text-green-700"><Quantity value={kg_collected} /></dd>
                    </div>
                </dl>

                {requests.data.length === 0 ? (
                    <EmptyState title="No pickups yet" description="Tell us which plastics you have and when to come, and a nearby collector will pick them up." action={newRequest} />
                ) : (
                    <ul className="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white shadow-sm">
                        {requests.data.map((r) => (
                            <li key={r.id}>
                                <Link href={route('collections.show', r.id)} className="flex flex-wrap items-start justify-between gap-3 px-4 py-4 hover:bg-gray-50 sm:px-6">
                                    <div className="min-w-0 space-y-1">
                                        <p className="font-medium text-green-700">{r.reference}</p>
                                        <p className="truncate text-sm text-gray-600">{r.pickup_address}</p>
                                        <MaterialChips items={r.items} />
                                    </div>
                                    <div className="space-y-1 text-right text-sm">
                                        <StatusBadge status={r.status} label={r.status_label} />
                                        <p className="text-gray-500"><PickupWhen date={r.preferred_date} slot={r.preferred_time_slot} /></p>
                                        {r.collector && <p className="text-gray-500">Collector: {r.collector.name}</p>}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination links={requests.links} />
            </div>
        </AuthenticatedLayout>
    );
}
