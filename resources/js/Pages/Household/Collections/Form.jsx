// Developer 2. Create or edit a pickup request. The server validates everything again.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import SelectInput from '@/Components/UI/SelectInput';
import TextArea from '@/Components/UI/TextArea';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CollectionForm({ collection, defaults, material_types, time_slots }) {
    const editing = Boolean(collection);
    const source = collection ?? defaults ?? {};
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

    const form = useForm({
        pickup_address: source.pickup_address ?? '',
        area: source.area ?? '',
        gps_address: source.gps_address ?? '',
        contact_phone: source.contact_phone ?? '',
        preferred_date: collection?.preferred_date ?? tomorrow,
        preferred_time_slot: collection?.preferred_time_slot ?? 'morning',
        notes: collection?.notes ?? '',
        items: collection?.items?.map((i) => ({ material_type: i.material_type, estimated_weight_kg: i.estimated_weight_kg ?? '' })) ?? [],
    });
    const { data, setData, errors, processing } = form;

    const selected = (type) => data.items.find((i) => i.material_type === type);
    const toggle = (type) => setData('items', selected(type)
        ? data.items.filter((i) => i.material_type !== type)
        : [...data.items, { material_type: type, estimated_weight_kg: '' }]);
    const setWeight = (type, value) => setData('items', data.items.map((i) => (i.material_type === type ? { ...i, estimated_weight_kg: value } : i)));

    const submit = (e) => {
        e.preventDefault();
        editing ? form.put(route('household.collections.update', collection.id)) : form.post(route('household.collections.store'));
    };

    const title = editing ? `Edit ${collection.reference}` : 'Request a pickup';

    return (
        <AuthenticatedLayout header={<PageHeader title={title} />}>
            <Head title={title} />
            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <Card title="What plastics do you have?">
                    <p className="mb-3 text-sm text-gray-500">Tick each type. A rough weight helps the collector bring the right bags (optional).</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {material_types.map((m) => {
                            const item = selected(m.value);
                            const index = data.items.findIndex((i) => i.material_type === m.value);
                            return (
                                <div key={m.value} className={`rounded-md border p-3 ${item ? 'border-green-600 bg-green-50' : 'border-gray-200'}`}>
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-800">
                                        <input type="checkbox" className="rounded border-gray-300 text-green-700 focus:ring-green-600"
                                            checked={Boolean(item)} onChange={() => toggle(m.value)} />
                                        {m.label}
                                    </label>
                                    {item && (
                                        <div className="mt-2">
                                            <TextInput type="number" step="0.001" min="0" inputMode="decimal" placeholder="About how many kg?"
                                                aria-label={`${m.label} estimated kg`} className="block w-full text-sm"
                                                value={item.estimated_weight_kg} onChange={(e) => setWeight(m.value, e.target.value)} />
                                            <InputError message={errors[`items.${index}.estimated_weight_kg`] ?? errors[`items.${index}.material_type`]} className="mt-1" />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    <InputError message={errors.items} className="mt-2" />
                </Card>

                <Card title="Where and when">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="pickup_address" label="Pickup address" error={errors.pickup_address} className="sm:col-span-2">
                            <TextInput id="pickup_address" className="block w-full" value={data.pickup_address} onChange={(e) => setData('pickup_address', e.target.value)} required />
                        </FormField>
                        <FormField id="area" label="Area or town" error={errors.area} hint="Collectors look for jobs by area.">
                            <TextInput id="area" className="block w-full" value={data.area} onChange={(e) => setData('area', e.target.value)} />
                        </FormField>
                        <FormField id="gps_address" label="GhanaPost GPS (optional)" error={errors.gps_address} hint="For example GA-123-4567">
                            <TextInput id="gps_address" className="block w-full uppercase" value={data.gps_address} onChange={(e) => setData('gps_address', e.target.value)} />
                        </FormField>
                        <FormField id="contact_phone" label="Phone number" error={errors.contact_phone}>
                            <TextInput id="contact_phone" type="tel" inputMode="tel" placeholder="0241234567" className="block w-full"
                                value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} required />
                        </FormField>
                        <FormField id="preferred_date" label="Preferred day" error={errors.preferred_date}>
                            <TextInput id="preferred_date" type="date" min={new Date().toISOString().slice(0, 10)} className="block w-full"
                                value={data.preferred_date} onChange={(e) => setData('preferred_date', e.target.value)} />
                        </FormField>
                        <FormField id="preferred_time_slot" label="Time of day" error={errors.preferred_time_slot}>
                            <SelectInput id="preferred_time_slot" placeholder="Any time" options={time_slots}
                                value={data.preferred_time_slot} onChange={(e) => setData('preferred_time_slot', e.target.value)} />
                        </FormField>
                        <FormField id="notes" label="Notes for the collector (optional)" error={errors.notes} className="sm:col-span-2">
                            <TextArea id="notes" rows={3} placeholder="For example: blue gate, call when you arrive"
                                value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </FormField>
                    </div>
                </Card>

                <InputError message={errors.general} />
                <div className="flex items-center justify-end gap-3">
                    <Link href={editing ? route('collections.show', collection.id) : route('household.collections.index')} className="text-sm text-gray-600 hover:underline">Cancel</Link>
                    <PrimaryButton disabled={processing}>{processing ? 'Saving…' : editing ? 'Save changes' : 'Request pickup'}</PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
