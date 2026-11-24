// Developer 2. A collector's batches, plus shortcuts to batch what is ready.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import StatusBadge from '@/Components/UI/StatusBadge';
import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function CollectorBatchesIndex({ batches, filters, statuses, ready }) {
    const merge = useForm({ batch_ids: [] });
    const openBatches = batches.data.filter((b) => b.status === 'open');
    const toggle = (id) => merge.setData('batch_ids', merge.data.batch_ids.includes(id) ? merge.data.batch_ids.filter((x) => x !== id) : [...merge.data.batch_ids, id]);

    const newBatch = (
        <Link href={route('collector.batches.create')} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">New batch</Link>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="My batches" subtitle="Group confirmed collections by plastic type and send them to a recycler." actions={newBatch} />}>
            <Head title="My batches" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                {ready.length > 0 && (
                    <Card title="Ready to batch">
                        <ul className="grid gap-3 sm:grid-cols-3">
                            {ready.map((r) => (
                                <li key={r.material_type}>
                                    <Link href={route('collector.batches.create', { material_type: r.material_type })}
                                        className="block rounded-md border border-gray-200 p-3 hover:border-green-600 hover:bg-green-50">
                                        <p className="font-medium text-gray-900">{r.material_label}</p>
                                        <p className="text-sm text-gray-500"><Quantity value={r.kg} /> · {r.records} record{r.records === 1 ? '' : 's'}</p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <SelectInput aria-label="Status" className="w-auto" placeholder="All statuses" options={statuses} value={filters.status}
                        onChange={(e) => router.get(route('collector.batches.index'), e.target.value ? { status: e.target.value } : {}, { preserveState: true })} />
                    {openBatches.length >= 2 && (
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-500">{merge.data.batch_ids.length} selected</span>
                            <SecondaryButton disabled={merge.data.batch_ids.length < 2 || merge.processing}
                                onClick={() => merge.post(route('collector.batches.consolidate'))}>Merge selected</SecondaryButton>
                        </div>
                    )}
                </div>
                <InputError message={merge.errors.batch_ids ?? merge.errors.general} />

                {batches.data.length === 0 ? (
                    <EmptyState title="No batches yet" description="Once households confirm their weights, you can batch those records here." action={newBatch} />
                ) : (
                    <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    {openBatches.length >= 2 && <th className="px-4 py-2"><span className="sr-only">Merge</span></th>}
                                    <th className="px-4 py-2">Batch</th>
                                    <th className="px-4 py-2">Plastic</th>
                                    <th className="px-4 py-2 text-right">Weight</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Recycler</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {batches.data.map((b) => (
                                    <tr key={b.id}>
                                        {openBatches.length >= 2 && (
                                            <td className="px-4 py-3">
                                                {b.status === 'open' && (
                                                    <input type="checkbox" aria-label={`Merge ${b.code}`} className="rounded border-gray-300 text-green-700 focus:ring-green-600"
                                                        checked={merge.data.batch_ids.includes(b.id)} onChange={() => toggle(b.id)} />
                                                )}
                                            </td>
                                        )}
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <Link href={route('batches.show', b.id)} className="font-medium text-green-700 hover:underline">{b.code}</Link>
                                            <p className="text-xs text-gray-500"><DateTime value={b.created_at} /> · {b.records_count} record{b.records_count === 1 ? '' : 's'}</p>
                                        </td>
                                        <td className="px-4 py-3">{b.material_label}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Quantity value={b.total_weight_kg} /></td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={b.status} label={b.status_label} />
                                            {b.consolidated_into && <p className="mt-1 text-xs text-gray-500">into {b.consolidated_into.code}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-gray-700">{b.recycler?.name ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination links={batches.links} />
            </div>
        </AuthenticatedLayout>
    );
}
