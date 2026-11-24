// Developer 2. Admin: every pickup, assign collectors, settle weight disputes.
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
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AdminCollectionsIndex({ requests, filters, statuses, counts, collectors, disputes }) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const apply = (next) => router.get(route('admin.collections.index'), { ...filters, ...next }, { preserveState: true });

    return (
        <AuthenticatedLayout header={<PageHeader title="Collections" subtitle="Every pickup request on Reloop" />}>
            <Head title="Collections" />
            <div className="mx-auto max-w-6xl space-y-6 px-4 py-6">
                <div className="flex flex-wrap gap-2">
                    <FilterPill active={!filters.status} onClick={() => apply({ status: '' })}>All</FilterPill>
                    {statuses.map((s) => (
                        <FilterPill key={s.value} active={filters.status === s.value} onClick={() => apply({ status: s.value })}>
                            {s.label} ({counts[s.value]})
                        </FilterPill>
                    ))}
                </div>
                <form onSubmit={(e) => { e.preventDefault(); apply({ search }); }} className="flex gap-2">
                    <TextInput aria-label="Search" placeholder="Reference, area or address" className="w-full max-w-sm" value={search} onChange={(e) => setSearch(e.target.value)} />
                    <SecondaryButton type="submit">Search</SecondaryButton>
                </form>
                <InputError message={errors.collector_id ?? errors.general} />

                {requests.data.length === 0 ? (
                    <EmptyState title="No pickups match" />
                ) : (
                    <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th className="px-4 py-2">Request</th>
                                    <th className="px-4 py-2">Household</th>
                                    <th className="px-4 py-2">Area</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Collector</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {requests.data.map((r) => (
                                    <tr key={r.id} className="align-top">
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <Link href={route('collections.show', r.id)} className="font-medium text-green-700 hover:underline">{r.reference}</Link>
                                            <p className="text-xs text-gray-500"><DateTime value={r.created_at} /></p>
                                        </td>
                                        <td className="px-4 py-3">{r.household?.name}</td>
                                        <td className="px-4 py-3">{r.area ?? '—'}</td>
                                        <td className="px-4 py-3"><StatusBadge status={r.status} label={r.status_label} /></td>
                                        <td className="px-4 py-3">
                                            {['pending', 'assigned'].includes(r.status)
                                                ? <AssignForm request={r} collectors={collectors} />
                                                : (r.collector?.name ?? '—')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination links={requests.links} />

                <Card title={`Weight disputes (${disputes.length})`} padded={disputes.length === 0}>
                    {disputes.length === 0 ? (
                        <p className="text-sm text-gray-500">No open disputes.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">{disputes.map((d) => <DisputeRow key={d.id} dispute={d} />)}</ul>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function FilterPill({ active, onClick, children }) {
    return (
        <button type="button" onClick={onClick}
            className={`rounded-full px-3 py-1 text-sm ${active ? 'bg-green-700 text-white' : 'bg-white text-gray-700 shadow-sm hover:bg-gray-50'}`}>
            {children}
        </button>
    );
}

function AssignForm({ request, collectors }) {
    const form = useForm({ collector_id: request.collector?.id ?? '' });
    return (
        <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); form.post(route('admin.collections.assign', request.id), { preserveScroll: true }); }}>
            <SelectInput aria-label={`Collector for ${request.reference}`} className="w-auto text-sm" placeholder="Choose collector" options={collectors}
                value={form.data.collector_id} onChange={(e) => form.setData('collector_id', e.target.value)} />
            <SecondaryButton type="submit" disabled={!form.data.collector_id || form.processing}>{request.collector ? 'Reassign' : 'Assign'}</SecondaryButton>
        </form>
    );
}

function DisputeRow({ dispute: d }) {
    const form = useForm({ actual_weight_kg: '', note: '' });
    return (
        <li className="space-y-3 px-4 py-4 sm:px-6">
            <div className="text-sm">
                <p className="font-medium text-gray-900">{d.reference} · {d.material_label} · <Quantity value={d.actual_weight_kg} /></p>
                <p className="text-gray-500">Collector {d.collector_name} · Household {d.household_name ?? '—'}</p>
                <p className="mt-1 text-red-700">“{d.dispute_reason}”</p>
            </div>
            <form className="grid gap-2 sm:grid-cols-[10rem_1fr_auto] sm:items-start"
                onSubmit={(e) => { e.preventDefault(); form.post(route('admin.collections.resolve', d.id), { preserveScroll: true }); }}>
                <div>
                    <TextInput type="number" step="0.001" min="0" placeholder="Correct kg (optional)" aria-label="Corrected weight" className="block w-full text-sm"
                        value={form.data.actual_weight_kg} onChange={(e) => form.setData('actual_weight_kg', e.target.value)} />
                    <InputError message={form.errors.actual_weight_kg} className="mt-1" />
                </div>
                <div>
                    <TextInput placeholder="What you decided and why" aria-label="Resolution note" className="block w-full text-sm"
                        value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} required />
                    <InputError message={form.errors.note} className="mt-1" />
                </div>
                <PrimaryButton disabled={form.processing}>Resolve</PrimaryButton>
            </form>
        </li>
    );
}
