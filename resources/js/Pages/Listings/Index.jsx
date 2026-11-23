// Developer 3. A seller's own listings, every status.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/UI/EmptyState';
import Money from '@/Components/UI/Money';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import StatusBadge from '@/Components/UI/StatusBadge';
import { Head, Link, router } from '@inertiajs/react';

export default function ListingsIndex({ listings, filters, statuses, can }) {
    const actions = (
        <div className="flex flex-wrap gap-2">
            {can.create_raw_material && (
                <Link href={route('listings.create', { type: 'raw_material' })} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">List raw material</Link>
            )}
            {can.create_product && (
                <Link href={route('listings.create', { type: 'finished_product' })} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">List a product</Link>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="My listings" actions={actions} />}>
            <Head title="My listings" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                <SelectInput aria-label="Status" className="w-48" value={filters.status ?? ''} placeholder="All statuses" options={statuses}
                    onChange={(e) => router.get(route('listings.index'), e.target.value ? { status: e.target.value } : {}, { preserveState: true, replace: true })} />

                {listings.data.length === 0 ? (
                    <EmptyState title={filters.status ? 'No listings with this status' : 'You haven’t listed anything yet'}
                        description="Listings you create appear here, including drafts." action={filters.status ? null : actions} />
                ) : (
                    <ul className="divide-y divide-gray-100 rounded-lg bg-white shadow-sm">
                        {listings.data.map((l) => (
                            <li key={l.id}>
                                <Link href={route('listings.edit', l.id)} className="flex flex-wrap items-center gap-4 p-4 hover:bg-gray-50">
                                    <div className="h-14 w-16 shrink-0 overflow-hidden rounded bg-gray-100">
                                        {l.image_url && <img src={l.image_url} alt="" className="h-full w-full object-cover" />}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-gray-900">{l.title}</p>
                                        <p className="text-sm text-gray-500">{l.listing_type_label}{l.subtitle ? ` · ${l.subtitle}` : ''} · <Quantity value={l.available_quantity} unit={l.unit} /> available</p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm font-semibold"><Money amount={l.price} currency={l.currency} /> / {l.unit}</span>
                                        <StatusBadge status={l.status} label={l.status_label} />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination links={listings.links} />
            </div>
        </AuthenticatedLayout>
    );
}
