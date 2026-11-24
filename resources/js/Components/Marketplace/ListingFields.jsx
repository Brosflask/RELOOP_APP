// Developer 3. Fields shared by the create and edit forms. `form` is an Inertia useForm object.
import FormField from '@/Components/UI/FormField';
import SelectInput from '@/Components/UI/SelectInput';
import TextArea from '@/Components/UI/TextArea';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

export function BasicFields({ form, unit }) {
    const { data, setData, errors } = form;
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <FormField id="title" label="Title" error={errors.title} className="sm:col-span-2">
                <TextInput id="title" className="block w-full" maxLength={120} value={data.title} onChange={(e) => setData('title', e.target.value)} />
            </FormField>
            <FormField id="description" label="Description" error={errors.description} className="sm:col-span-2">
                <TextArea id="description" rows={4} value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} />
            </FormField>
            <FormField id="price" label={`Price per ${unit} (GHS)`} error={errors.price}>
                <TextInput id="price" type="number" min="0" step="0.01" inputMode="decimal" className="block w-full" value={data.price} onChange={(e) => setData('price', e.target.value)} />
            </FormField>
            <FormField id="location" label="Location" hint="Town or region buyers collect from or you ship from" error={errors.location}>
                <TextInput id="location" className="block w-full" value={data.location ?? ''} onChange={(e) => setData('location', e.target.value)} />
            </FormField>
        </div>
    );
}

export function ProductFields({ form, categories, conditions, withQuantity = false }) {
    const { data, setData, errors } = form;
    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <FormField id="product_category" label="Category" error={errors.product_category}>
                <SelectInput id="product_category" value={data.product_category} placeholder="Choose" options={categories} onChange={(e) => setData('product_category', e.target.value)} />
            </FormField>
            <FormField id="product_condition" label="Condition" error={errors.product_condition}>
                <SelectInput id="product_condition" value={data.product_condition} options={conditions} onChange={(e) => setData('product_condition', e.target.value)} />
            </FormField>
            {withQuantity && (
                <FormField id="production_quantity" label="Pieces made" error={errors.production_quantity}>
                    <TextInput id="production_quantity" type="number" min="1" step="1" className="block w-full" value={data.production_quantity} onChange={(e) => setData('production_quantity', e.target.value)} />
                </FormField>
            )}
        </div>
    );
}

/** Lots a product was made from. Required, so every product traces back to a lot. */
export function MaterialsPicker({ form, lots }) {
    const { data, setData, errors } = form;
    const rows = data.materials;
    const setRow = (i, field, value) => setData('materials', rows.map((r, j) => (j === i ? { ...r, [field]: value } : r)));

    if (lots.length === 0) {
        return <p className="text-sm text-gray-600">You don’t have any material lots yet. Buy raw material on the marketplace first; the lot appears here once your payment is confirmed.</p>;
    }

    return (
        <div className="space-y-3">
            {rows.map((row, i) => (
                <div key={i} className="grid gap-3 sm:grid-cols-[1fr_9rem_auto] sm:items-end">
                    <FormField id={`material-${i}`} label="Lot" error={errors[`materials.${i}.material_lot_id`]}>
                        <SelectInput id={`material-${i}`} value={row.material_lot_id} placeholder="Choose a lot" options={lots.map((l) => ({ value: l.id, label: l.label }))}
                            onChange={(e) => setRow(i, 'material_lot_id', e.target.value)} />
                    </FormField>
                    <FormField id={`material-${i}-kg`} label="Kg used (optional)" error={errors[`materials.${i}.quantity_used`]}>
                        <TextInput id={`material-${i}-kg`} type="number" min="0" step="0.001" className="block w-full" value={row.quantity_used} onChange={(e) => setRow(i, 'quantity_used', e.target.value)} />
                    </FormField>
                    {rows.length > 1 && <button type="button" className="py-2 text-sm text-red-600 hover:underline" onClick={() => setData('materials', rows.filter((_, j) => j !== i))}>Remove</button>}
                </div>
            ))}
            <InputError message={errors.materials} />
            {rows.length < 10 && (
                <button type="button" className="text-sm font-medium text-green-700 hover:underline" onClick={() => setData('materials', [...rows, { material_lot_id: '', quantity_used: '' }])}>
                    Add another lot
                </button>
            )}
        </div>
    );
}
