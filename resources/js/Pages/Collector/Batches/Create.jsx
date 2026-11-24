// Developer 2. Start a batch: choose a plastic type, then tick the records to put in it.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import SelectInput from '@/Components/UI/SelectInput';
import TextArea from '@/Components/UI/TextArea';
import RecordPicker from '@/Components/Traceability/RecordPicker';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function CollectorBatchCreate({ material_types, selected_type, records, ready }) {
    const form = useForm({ material_type: selected_type ?? '', record_ids: records.map((r) => r.id), notes: '' });
    const { data, setData, errors, processing } = form;
    const readyLabel = (t) => ready.find((r) => r.material_type === t.value);

    const chooseType = (value) => router.get(route('collector.batches.create'), value ? { material_type: value } : {}, { preserveScroll: true });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('collector.batches.store'));
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="New batch" />}>
            <Head title="New batch" />
            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <Card>
                    <FormField id="material_type" label="Plastic type" error={errors.material_type} hint="A batch holds one type of plastic.">
                        <SelectInput id="material_type" placeholder="Choose" value={data.material_type} onChange={(e) => chooseType(e.target.value)}
                            options={material_types.map((t) => ({ ...t, label: readyLabel(t) ? `${t.label} (${readyLabel(t).kg} kg ready)` : t.label }))} />
                    </FormField>
                </Card>

                {selected_type && (records.length === 0 ? (
                    <EmptyState title="Nothing ready of this type" description="Only confirmed records that are not in a batch can be added. Records waiting for a household show up once confirmed." />
                ) : (
                    <Card title="Records to include">
                        <RecordPicker records={records} selected={data.record_ids} onChange={(ids) => setData('record_ids', ids)} />
                        <InputError message={errors.record_ids} className="mt-2" />
                        <div className="mt-4">
                            <FormField id="notes" label="Notes (optional)" error={errors.notes}>
                                <TextArea id="notes" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </FormField>
                        </div>
                    </Card>
                ))}

                <InputError message={errors.general} />
                <div className="flex items-center justify-end gap-3">
                    <Link href={route('collector.batches.index')} className="text-sm text-gray-600 hover:underline">Cancel</Link>
                    <PrimaryButton disabled={processing || !selected_type || data.record_ids.length === 0}>{processing ? 'Saving…' : 'Create batch'}</PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
