// Developer 2. One batch, seen by its collector, the recycler it was sent to, or an admin.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import StatusBadge from '@/Components/UI/StatusBadge';
import TextArea from '@/Components/UI/TextArea';
import RecordPicker from '@/Components/Traceability/RecordPicker';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function BatchShow({ batch: b, records, merged_batches, receipt, addable, recyclers, can }) {
    const { errors } = usePage().props;

    return (
        <AuthenticatedLayout header={<PageHeader title={b.code} subtitle={<StatusBadge status={b.status} label={b.status_label} />} />}>
            <Head title={b.code} />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <InputError message={errors.general} />

                <div className="grid gap-6 md:grid-cols-2">
                    <Card title="Batch">
                        <dl className="space-y-3 text-sm">
                            <Row label="Plastic">{b.material_label}</Row>
                            <Row label="Total weight"><strong><Quantity value={b.total_weight_kg} /></strong></Row>
                            <Row label="Collector">{b.collector?.name}</Row>
                            <Row label="Recycler">{b.recycler?.name ?? <span className="text-gray-500">Not sent yet</span>}</Row>
                            {b.consolidated_into && (
                                <Row label="Merged into"><Link href={route('batches.show', b.consolidated_into.id)} className="text-green-700 hover:underline">{b.consolidated_into.code}</Link></Row>
                            )}
                            {b.notes && <Row label="Notes"><span className="whitespace-pre-line">{b.notes}</span></Row>}
                            {b.rejection_reason && <Row label="Rejected because"><span className="text-red-700">{b.rejection_reason}</span></Row>}
                        </dl>
                    </Card>
                    <Card title="History">
                        <ol className="space-y-1 text-sm text-gray-600">
                            <Step label="Created" at={b.created_at} />
                            <Step label="Sent to recycler" at={b.dispatched_at} />
                            {b.rejected_at && <Step label="Rejected" at={b.rejected_at} />}
                            <Step label="Received" at={b.received_at} />
                        </ol>
                        {receipt && (
                            <dl className="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                                <Row label="Weighed on arrival"><Quantity value={receipt.received_weight_kg} /></Row>
                                {receipt.weight_difference_kg !== null && <Row label="Difference"><Quantity value={receipt.weight_difference_kg} /></Row>}
                                {receipt.condition_notes && <Row label="Recycler notes">{receipt.condition_notes}</Row>}
                            </dl>
                        )}
                    </Card>
                </div>

                {can.dispatch && <DispatchForm batch={b} recyclers={recyclers} />}

                {merged_batches.length > 0 && (
                    <Card title="Batches merged into this one" padded={false}>
                        <ul className="divide-y divide-gray-100 text-sm">
                            {merged_batches.map((m) => (
                                <li key={m.id} className="flex justify-between px-4 py-3 sm:px-6">
                                    <Link href={route('batches.show', m.id)} className="font-medium text-green-700 hover:underline">{m.code}</Link>
                                    <Quantity value={m.total_weight_kg} />
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Card title={`Collection records (${records.length})`} padded={false}>
                    {records.length === 0 ? (
                        <p className="p-4 text-sm text-gray-500 sm:p-6">{merged_batches.length ? 'The records are in the merged batches above.' : 'No records in this batch yet.'}</p>
                    ) : (
                        <ul className="divide-y divide-gray-100 text-sm">
                            {records.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                                    <span>
                                        <span className="font-medium">{r.reference}</span>{' '}
                                        <span className="text-gray-500"><DateTime value={r.collected_at} /></span>
                                    </span>
                                    <span className="flex items-center gap-4">
                                        <Quantity value={r.batched_weight_kg} />
                                        {can.manage && (
                                            <button type="button" className="text-red-600 hover:underline"
                                                onClick={() => router.delete(route('collector.batches.records.remove', [b.id, r.id]), { preserveScroll: true })}>Remove</button>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                {can.manage && addable.length > 0 && <AddRecords batch={b} records={addable} />}
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, children }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            <dt className="text-gray-500">{label}</dt>
            <dd className="col-span-2 text-gray-900">{children}</dd>
        </div>
    );
}

function Step({ label, at }) {
    return (
        <li className={`flex justify-between gap-2 ${at ? '' : 'text-gray-400'}`}>
            <span>{at ? '✓' : '○'} {label}</span>
            {at && <DateTime value={at} withTime />}
        </li>
    );
}

function AddRecords({ batch, records }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ record_ids: [] });
    if (!open) {
        return <SecondaryButton onClick={() => setOpen(true)}>Add more records ({records.length} ready)</SecondaryButton>;
    }
    return (
        <Card title="Add records">
            <RecordPicker records={records} selected={form.data.record_ids} onChange={(ids) => form.setData('record_ids', ids)} />
            <InputError message={form.errors.record_ids} className="mt-2" />
            <div className="mt-4 flex justify-end gap-2">
                <SecondaryButton onClick={() => setOpen(false)}>Close</SecondaryButton>
                <PrimaryButton disabled={form.data.record_ids.length === 0 || form.processing}
                    onClick={() => form.post(route('collector.batches.records.add', batch.id), { preserveScroll: true, onSuccess: () => setOpen(false) })}>
                    Add to batch
                </PrimaryButton>
            </div>
        </Card>
    );
}

function DispatchForm({ batch, recyclers }) {
    const form = useForm({ recycler_id: '', notes: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('collector.batches.dispatch', batch.id));
    };
    return (
        <Card title="Send to a recycler">
            {recyclers.length === 0 ? (
                <p className="text-sm text-gray-500">No recycling companies are registered yet.</p>
            ) : (
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField id="recycler_id" label="Recycling company" error={form.errors.recycler_id}>
                        <SelectInput id="recycler_id" placeholder="Choose" options={recyclers} value={form.data.recycler_id}
                            onChange={(e) => form.setData('recycler_id', e.target.value)} required />
                    </FormField>
                    <FormField id="dispatch-notes" label="Notes for the recycler (optional)" error={form.errors.notes}>
                        <TextArea id="dispatch-notes" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </FormField>
                    <div className="flex items-center justify-between gap-3 sm:col-span-2">
                        <p className="text-sm text-gray-500">After sending, the batch can’t be changed.</p>
                        <PrimaryButton disabled={form.processing || !form.data.recycler_id}>Send <Quantity value={batch.total_weight_kg} /></PrimaryButton>
                    </div>
                </form>
            )}
        </Card>
    );
}
