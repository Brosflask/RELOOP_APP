// Developer 2. Everything this collector has collected.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateTime from '@/Components/UI/DateTime';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import StatusBadge from '@/Components/UI/StatusBadge';
import { Head, Link, router } from '@inertiajs/react';

export default function CollectorRecordsIndex({ records, filters, statuses, material_types }) {
    const filter = (key, value) => router.get(route('collector.records.index'), { ...filters, [key]: value || undefined }, { preserveState: true });

    const newRecord = (
        <Link href={route('collector.records.create')} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Record a drop-off</Link>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="My collection records" actions={newRecord} />}>
            <Head title="My collection records" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                <div className="flex flex-wrap gap-3">
                    <SelectInput aria-label="Status" className="w-auto" placeholder="All statuses" options={statuses} value={filters.status} onChange={(e) => filter('status', e.target.value)} />
                    <SelectInput aria-label="Plastic" className="w-auto" placeholder="All plastics" options={material_types} value={filters.material_type} onChange={(e) => filter('material_type', e.target.value)} />
                </div>

                {records.data.length === 0 ? (
                    <EmptyState title="No records" description="Records appear here when you complete a pickup or record a drop-off." />
                ) : (
                    <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th className="px-4 py-2">Record</th>
                                    <th className="px-4 py-2">Collected</th>
                                    <th className="px-4 py-2">Plastic</th>
                                    <th className="px-4 py-2 text-right">Weight</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Batch</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            {r.collection_request_id
                                                ? <Link href={route('collections.show', r.collection_request_id)} className="font-medium text-green-700 hover:underline">{r.reference}</Link>
                                                : <span className="font-medium">{r.reference}</span>}
                                            {!r.collection_request_id && <p className="text-xs text-gray-500">Drop-off</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-gray-700"><DateTime value={r.collected_at} withTime /></td>
                                        <td className="px-4 py-3">{r.material_label}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Quantity value={r.actual_weight_kg} /></td>
                                        <td className="px-4 py-3"><StatusBadge status={r.status} label={r.status_label} /></td>
                                        <td className="px-4 py-3 text-gray-500">{r.in_batch ? 'In a batch' : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination links={records.links} />
            </div>
        </AuthenticatedLayout>
    );
}
