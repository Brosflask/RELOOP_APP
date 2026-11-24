// Developer 2. One pickup, seen by the household, the collector or an admin.
// Which buttons show comes from `can`, worked out on the server; the server checks again on every action.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import ConfirmButton from '@/Components/UI/ConfirmButton';
import DateTime from '@/Components/UI/DateTime';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import StatusBadge from '@/Components/UI/StatusBadge';
import TextArea from '@/Components/UI/TextArea';
import MaterialChips from '@/Components/Collection/MaterialChips';
import PickupWhen from '@/Components/Collection/PickupWhen';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function CollectionShow({ collection: c, records, material_types, can }) {
    const { errors } = usePage().props;
    const post = (name) => router.post(route(name, c.id), {}, { preserveScroll: true });

    const actions = (
        <>
            {can.edit && <Link href={route('household.collections.edit', c.id)} className="text-sm font-medium text-green-700 hover:underline">Edit</Link>}
            {can.accept && <PrimaryButton onClick={() => post('collector.jobs.accept')}>Accept job</PrimaryButton>}
            {can.start && <PrimaryButton onClick={() => post('collector.jobs.start')}>Start pickup</PrimaryButton>}
            {can.release && (
                <ConfirmButton title="Give this job back?" message="Another collector will be able to take it." confirmLabel="Give back"
                    onConfirm={() => post('collector.jobs.release')}>Give back</ConfirmButton>
            )}
            {can.cancel && (
                <ConfirmButton title="Cancel this pickup?" message="The collector will be told. This can’t be undone." confirmLabel="Cancel pickup"
                    onConfirm={() => post('collections.cancel')}>Cancel pickup</ConfirmButton>
            )}
        </>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title={c.reference} subtitle={<StatusBadge status={c.status} label={c.status_label} />} actions={actions} />}>
            <Head title={c.reference} />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <InputError message={errors.general} />

                <div className="grid gap-6 md:grid-cols-2">
                    <Card title="Pickup">
                        <dl className="space-y-3 text-sm">
                            <Row label="Address">{c.pickup_address}{c.area && <span className="text-gray-500"> · {c.area}</span>}</Row>
                            {c.gps_address && <Row label="GPS address">{c.gps_address}</Row>}
                            <Row label="When"><PickupWhen date={c.preferred_date} slot={c.preferred_time_slot} /></Row>
                            {can.see_contact && <Row label="Phone"><a href={`tel:${c.contact_phone}`} className="text-green-700 hover:underline">{c.contact_phone}</a></Row>}
                            {c.household && can.see_contact && <Row label="Household">{c.household.name}</Row>}
                            <Row label="Collector">{c.collector?.name ?? <span className="text-gray-500">Not assigned yet</span>}</Row>
                            {c.notes && <Row label="Notes"><span className="whitespace-pre-line">{c.notes}</span></Row>}
                            {c.cancellation_reason && <Row label="Cancelled because">{c.cancellation_reason}</Row>}
                        </dl>
                    </Card>

                    <Card title="Plastics listed">
                        <MaterialChips items={c.items} prefix="about " />
                        {c.estimated_weight_kg && <p className="mt-3 text-sm text-gray-500">Estimated total: <Quantity value={c.estimated_weight_kg} /></p>}
                        <ol className="mt-4 space-y-1 border-t border-gray-100 pt-4 text-sm text-gray-600">
                            <Step label="Requested" at={c.created_at} />
                            <Step label="Collector assigned" at={c.assigned_at} />
                            <Step label="Pickup started" at={c.started_at} />
                            <Step label="Collected" at={c.completed_at} />
                            {c.cancelled_at && <Step label="Cancelled" at={c.cancelled_at} />}
                        </ol>
                    </Card>
                </div>

                {can.complete && <CompleteForm collection={c} material_types={material_types} />}

                {records.length > 0 && <RecordsCard records={records} canRespond={can.respond} />}
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

function CompleteForm({ collection, material_types }) {
    const start = collection.items.length ? collection.items.map((i) => ({ material_type: i.material_type, actual_weight_kg: '' })) : [{ material_type: 'pet', actual_weight_kg: '' }];
    const form = useForm({ lines: start, notes: '' });
    const { data, setData, errors, processing } = form;
    const setLine = (i, field, value) => setData('lines', data.lines.map((l, j) => (j === i ? { ...l, [field]: value } : l)));
    const total = data.lines.reduce((sum, l) => sum + (Number(l.actual_weight_kg) || 0), 0);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('collector.jobs.complete', collection.id), { preserveScroll: true });
    };

    return (
        <Card title="Record what you collected"
            actions={<SecondaryButton type="button" disabled={data.lines.length >= 8} onClick={() => setData('lines', [...data.lines, { material_type: 'other', actual_weight_kg: '' }])}>Add type</SecondaryButton>}>
            <form onSubmit={submit} className="space-y-4">
                <p className="text-sm text-gray-500">Weigh each type of plastic. Leave a type at 0 if there was none. The household will be asked to confirm.</p>
                {data.lines.map((line, i) => (
                    <div key={i} className="grid gap-3 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
                        <FormField id={`line-${i}-type`} label="Plastic" error={errors[`lines.${i}.material_type`]}>
                            <SelectInput id={`line-${i}-type`} options={material_types} value={line.material_type} onChange={(e) => setLine(i, 'material_type', e.target.value)} />
                        </FormField>
                        <FormField id={`line-${i}-kg`} label="Weight (kg)" error={errors[`lines.${i}.actual_weight_kg`]}>
                            <TextInput id={`line-${i}-kg`} type="number" step="0.001" min="0" inputMode="decimal" className="block w-full"
                                value={line.actual_weight_kg} onChange={(e) => setLine(i, 'actual_weight_kg', e.target.value)} required />
                        </FormField>
                        {data.lines.length > 1 && (
                            <button type="button" className="py-2 text-sm text-red-600 hover:underline" onClick={() => setData('lines', data.lines.filter((_, j) => j !== i))}>Remove</button>
                        )}
                    </div>
                ))}
                <InputError message={errors.lines} />
                <FormField id="complete-notes" label="Notes (optional)" error={errors.notes}>
                    <TextArea id="complete-notes" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                </FormField>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                    <p className="text-sm">Total: <strong><Quantity value={total} /></strong></p>
                    <PrimaryButton disabled={processing || total <= 0}>{processing ? 'Saving…' : 'Complete pickup'}</PrimaryButton>
                </div>
            </form>
        </Card>
    );
}

function RecordsCard({ records, canRespond }) {
    return (
        <Card title="What was collected" padded={false}>
            {canRespond && (
                <p className="border-b border-gray-100 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:px-6">
                    Please check each weight. If you don’t answer within 3 days, the weights are confirmed for you.
                </p>
            )}
            <ul className="divide-y divide-gray-100">
                {records.map((r) => <RecordRow key={r.id} record={r} canRespond={canRespond} />)}
            </ul>
        </Card>
    );
}

function RecordRow({ record: r, canRespond }) {
    const [disputing, setDisputing] = useState(false);
    const form = useForm({ reason: '' });
    const waiting = r.status === 'pending_confirmation';

    const dispute = (e) => {
        e.preventDefault();
        form.post(route('collection-records.dispute', r.id), { preserveScroll: true, onSuccess: () => setDisputing(false) });
    };

    return (
        <li className="space-y-3 px-4 py-4 sm:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="font-medium text-gray-900">{r.material_label} · <Quantity value={r.actual_weight_kg} /></p>
                    <p className="text-xs text-gray-500">{r.reference} · <DateTime value={r.collected_at} withTime /></p>
                    {r.dispute_reason && <p className="mt-1 text-sm text-red-700">Disputed: {r.dispute_reason}</p>}
                </div>
                <div className="flex items-center gap-2">
                    <StatusBadge status={r.status} label={r.status_label} />
                    {canRespond && waiting && !disputing && (
                        <>
                            <PrimaryButton onClick={() => router.post(route('collection-records.confirm', r.id), {}, { preserveScroll: true })}>Confirm</PrimaryButton>
                            <SecondaryButton onClick={() => setDisputing(true)}>Something’s wrong</SecondaryButton>
                        </>
                    )}
                </div>
            </div>
            {disputing && (
                <form onSubmit={dispute} className="space-y-2">
                    <FormField id={`dispute-${r.id}`} label="What’s wrong?" error={form.errors.reason}>
                        <TextArea id={`dispute-${r.id}`} rows={2} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)}
                            placeholder="For example: there were two full bags, about 6 kg" required />
                    </FormField>
                    <div className="flex gap-2">
                        <PrimaryButton disabled={form.processing}>Send</PrimaryButton>
                        <SecondaryButton type="button" onClick={() => setDisputing(false)}>Back</SecondaryButton>
                    </div>
                </form>
            )}
        </li>
    );
}
