// Developer 3. The server recalculates output and loss; the preview here is only a guide.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import TextArea from '@/Components/UI/TextArea';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

const emptyLot = { material_type: '', form: '', grade: '', quantity_kg: '' };

export default function ProcessingCreate({ batches, selected_batch_id, process_types, forms, material_types }) {
    const form = useForm({
        material_batch_id: selected_batch_id ?? batches[0]?.id ?? '',
        process_type: 'shredding',
        input_weight_kg: '',
        processed_at: new Date().toISOString().slice(0, 10),
        notes: '',
        lots: [{ ...emptyLot }],
    });
    const { data, setData, errors, processing } = form;
    const batch = batches.find((b) => String(b.id) === String(data.material_batch_id));

    const output = data.lots.reduce((sum, lot) => sum + (Number(lot.quantity_kg) || 0), 0);
    const loss = (Number(data.input_weight_kg) || 0) - output;

    const setLot = (i, field, value) => setData('lots', data.lots.map((lot, j) => (j === i ? { ...lot, [field]: value } : lot)));
    const addLot = () => setData('lots', [...data.lots, { ...emptyLot }]);
    const removeLot = (i) => setData('lots', data.lots.filter((_, j) => j !== i));

    const submit = (e) => {
        e.preventDefault();
        form.post(route('recycler.processing.store'));
    };

    if (batches.length === 0) {
        return (
            <AuthenticatedLayout header={<PageHeader title="Record processing" />}>
                <Head title="Record processing" />
                <div className="mx-auto max-w-3xl px-4 py-6">
                    <EmptyState title="No batches to process" description="You can record processing once you have received a batch from a collector." />
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout header={<PageHeader title="Record processing" />}>
            <Head title="Record processing" />
            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <Card title="What went in">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="material_batch_id" label="Batch" error={errors.material_batch_id}
                            hint={batch ? `${Number(batch.remaining_weight_kg)} kg left to process` : null}>
                            <SelectInput id="material_batch_id" value={data.material_batch_id} onChange={(e) => setData('material_batch_id', e.target.value)}
                                options={batches.map((b) => ({ value: b.id, label: `Batch #${b.id}` }))} />
                        </FormField>
                        <FormField id="process_type" label="Process" error={errors.process_type}>
                            <SelectInput id="process_type" value={data.process_type} onChange={(e) => setData('process_type', e.target.value)} options={process_types} />
                        </FormField>
                        <FormField id="input_weight_kg" label="Weight in (kg)" error={errors.input_weight_kg}>
                            <TextInput id="input_weight_kg" type="number" step="0.001" min="0" inputMode="decimal" className="block w-full"
                                value={data.input_weight_kg} onChange={(e) => setData('input_weight_kg', e.target.value)} />
                        </FormField>
                        <FormField id="processed_at" label="Date processed" error={errors.processed_at}>
                            <TextInput id="processed_at" type="date" className="block w-full" value={data.processed_at} onChange={(e) => setData('processed_at', e.target.value)} />
                        </FormField>
                    </div>
                </Card>

                <Card title="Lots that came out" actions={<SecondaryButton type="button" onClick={addLot} disabled={data.lots.length >= 10}>Add lot</SecondaryButton>}>
                    <div className="space-y-4">
                        {data.lots.map((lot, i) => (
                            <div key={i} className="grid gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-[1fr_1fr_6rem_8rem_auto] sm:items-end">
                                <FormField id={`lot-${i}-type`} label="Material" error={errors[`lots.${i}.material_type`]}>
                                    <SelectInput id={`lot-${i}-type`} value={lot.material_type} placeholder="Choose" options={material_types}
                                        onChange={(e) => setLot(i, 'material_type', e.target.value)} />
                                </FormField>
                                <FormField id={`lot-${i}-form`} label="Form" error={errors[`lots.${i}.form`]}>
                                    <SelectInput id={`lot-${i}-form`} value={lot.form} placeholder="Choose" options={forms} onChange={(e) => setLot(i, 'form', e.target.value)} />
                                </FormField>
                                <FormField id={`lot-${i}-grade`} label="Grade" error={errors[`lots.${i}.grade`]}>
                                    <TextInput id={`lot-${i}-grade`} className="block w-full" value={lot.grade} onChange={(e) => setLot(i, 'grade', e.target.value)} />
                                </FormField>
                                <FormField id={`lot-${i}-kg`} label="Weight (kg)" error={errors[`lots.${i}.quantity_kg`]}>
                                    <TextInput id={`lot-${i}-kg`} type="number" step="0.001" min="0" inputMode="decimal" className="block w-full"
                                        value={lot.quantity_kg} onChange={(e) => setLot(i, 'quantity_kg', e.target.value)} />
                                </FormField>
                                {data.lots.length > 1 && (
                                    <button type="button" onClick={() => removeLot(i)} className="py-2 text-sm text-red-600 hover:underline">Remove</button>
                                )}
                            </div>
                        ))}
                        <InputError message={errors.lots} />
                    </div>
                    <dl className="mt-4 flex flex-wrap gap-6 border-t border-gray-100 pt-4 text-sm">
                        <div><dt className="text-gray-500">Weight out</dt><dd className="font-semibold"><Quantity value={output} /></dd></div>
                        <div><dt className="text-gray-500">Lost in processing</dt>
                            <dd className={`font-semibold ${loss < 0 ? 'text-red-600' : ''}`}>{loss < 0 ? 'Output is more than input' : <Quantity value={loss} />}</dd></div>
                    </dl>
                </Card>

                <Card>
                    <FormField id="notes" label="Notes (optional)" error={errors.notes}>
                        <TextArea id="notes" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </FormField>
                </Card>

                <InputError message={errors.general} />
                <div className="flex items-center justify-end gap-3">
                    <Link href={route('recycler.processing.index')} className="text-sm text-gray-600 hover:underline">Cancel</Link>
                    <PrimaryButton disabled={processing}>{processing ? 'Saving…' : 'Save processing record'}</PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
