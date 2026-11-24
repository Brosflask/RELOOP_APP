// Developer 2. Plastic brought to the collector without a pickup request.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import SelectInput from '@/Components/UI/SelectInput';
import TextArea from '@/Components/UI/TextArea';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CollectorRecordCreate({ material_types }) {
    const { data, setData, post, errors, processing } = useForm({ material_type: '', actual_weight_kg: '', notes: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('collector.records.store'));
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="Record a drop-off" subtitle="Plastic someone brought to you without a pickup request." />}>
            <Head title="Record a drop-off" />
            <form onSubmit={submit} className="mx-auto max-w-xl space-y-6 px-4 py-6">
                <Card>
                    <div className="space-y-4">
                        <FormField id="material_type" label="Plastic" error={errors.material_type}>
                            <SelectInput id="material_type" placeholder="Choose" options={material_types} value={data.material_type}
                                onChange={(e) => setData('material_type', e.target.value)} required />
                        </FormField>
                        <FormField id="actual_weight_kg" label="Weight (kg)" error={errors.actual_weight_kg}>
                            <TextInput id="actual_weight_kg" type="number" step="0.001" min="0" inputMode="decimal" className="block w-full"
                                value={data.actual_weight_kg} onChange={(e) => setData('actual_weight_kg', e.target.value)} required />
                        </FormField>
                        <FormField id="notes" label="Notes (optional)" error={errors.notes}>
                            <TextArea id="notes" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </FormField>
                    </div>
                </Card>
                <div className="flex items-center justify-end gap-3">
                    <Link href={route('collector.records.index')} className="text-sm text-gray-600 hover:underline">Cancel</Link>
                    <PrimaryButton disabled={processing}>{processing ? 'Saving…' : 'Save record'}</PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
