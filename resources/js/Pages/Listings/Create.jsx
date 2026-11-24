// Developer 3. Recyclers list a lot as raw material; artisans list a product made from lots they own.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import SelectInput from '@/Components/UI/SelectInput';
import { BasicFields, MaterialsPicker, ProductFields } from '@/Components/Marketplace/ListingFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ListingsCreate({ type, lots, selected_lot_id, categories, conditions }) {
    const raw = type === 'raw_material';
    const form = useForm(raw
        ? { material_lot_id: selected_lot_id ?? lots[0]?.id ?? '', title: '', description: '', price: '', location: '', minimum_order_quantity: '', publish: false }
        : { title: '', description: '', price: '', location: '', product_category: '', product_condition: 'new', production_quantity: '1',
            materials: [{ material_lot_id: lots[0]?.id ?? '', quantity_used: '' }], publish: false });
    const lot = raw ? lots.find((l) => String(l.id) === String(form.data.material_lot_id)) : null;

    const submit = (publish) => {
        form.transform((d) => ({ ...d, publish }));
        form.post(route(raw ? 'listings.store-raw-material' : 'listings.store-product'));
    };
    const title = raw ? 'List raw material' : 'List a product';

    if (raw && lots.length === 0) {
        return (
            <AuthenticatedLayout header={<PageHeader title={title} />}>
                <Head title={title} />
                <div className="mx-auto max-w-3xl px-4 py-6">
                    <EmptyState title="No lots to list" description="Every lot with stock is already listed, or you haven’t recorded processing yet."
                        action={<Link href={route('recycler.processing.create')} className="text-sm font-medium text-green-700 hover:underline">Record processing</Link>} />
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout header={<PageHeader title={title} />}>
            <Head title={title} />
            <form onSubmit={(e) => { e.preventDefault(); submit(false); }} className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {raw ? (
                    <Card title="Which lot are you selling?">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField id="material_lot_id" label="Lot" error={form.errors.material_lot_id} className="sm:col-span-2">
                                <SelectInput id="material_lot_id" value={form.data.material_lot_id} options={lots.map((l) => ({ value: l.id, label: l.label }))}
                                    onChange={(e) => form.setData('material_lot_id', e.target.value)} />
                            </FormField>
                            <FormField id="minimum_order_quantity" label="Minimum order (kg, optional)" error={form.errors.minimum_order_quantity}
                                hint={lot ? `Up to ${Number(lot.available_quantity)} kg in this lot` : null}>
                                <TextInput id="minimum_order_quantity" type="number" min="0" step="0.001" className="block w-full" value={form.data.minimum_order_quantity}
                                    onChange={(e) => form.setData('minimum_order_quantity', e.target.value)} />
                            </FormField>
                        </div>
                    </Card>
                ) : (
                    <Card title="Product details"><ProductFields form={form} categories={categories} conditions={conditions} withQuantity /></Card>
                )}

                <Card title="What buyers see"><BasicFields form={form} unit={raw ? 'kg' : 'piece'} /></Card>

                {!raw && (
                    <Card title="Made from">
                        <p className="mb-3 text-sm text-gray-600">Pick the material lots you used. Buyers see where the plastic came from.</p>
                        <MaterialsPicker form={form} lots={lots} />
                    </Card>
                )}

                <InputError message={form.errors.general} />
                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Link href={route('listings.index')} className="text-sm text-gray-600 hover:underline">Cancel</Link>
                    <SecondaryButton type="submit" disabled={form.processing}>Save as draft</SecondaryButton>
                    <PrimaryButton type="button" disabled={form.processing} onClick={() => submit(true)}>Publish now</PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
