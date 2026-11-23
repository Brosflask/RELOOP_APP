// Developer 3. Edit details, manage photos, and publish, pause or close a listing.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import ConfirmButton from '@/Components/UI/ConfirmButton';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import StatusBadge from '@/Components/UI/StatusBadge';
import { BasicFields, MaterialsPicker, ProductFields } from '@/Components/Marketplace/ListingFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function ListingsEdit({ listing, lots, categories, conditions, max_images }) {
    const raw = listing.listing_type === 'raw_material';
    const form = useForm({
        title: listing.title,
        description: listing.description ?? '',
        price: listing.price,
        location: listing.location ?? '',
        minimum_order_quantity: listing.minimum_order_quantity ?? '',
        product_category: listing.product_category ?? '',
        product_condition: listing.product_condition ?? 'new',
        materials: listing.materials.length ? listing.materials : [{ material_lot_id: '', quantity_used: '' }],
    });
    const photos = useForm({ images: [] });
    const status = useForm({ status: '' });

    const setStatus = (value) => {
        status.transform(() => ({ status: value }));
        status.patch(route('listings.status', listing.id), { preserveScroll: true });
    };

    const actions = (
        <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={listing.status} label={listing.status_label} />
            {['draft', 'paused'].includes(listing.status) && <PrimaryButton type="button" disabled={status.processing} onClick={() => setStatus('active')}>{listing.status === 'draft' ? 'Publish' : 'Resume'}</PrimaryButton>}
            {listing.status === 'active' && <SecondaryButton type="button" disabled={status.processing} onClick={() => setStatus('paused')}>Pause</SecondaryButton>}
            {listing.status !== 'closed' && (
                <ConfirmButton title="Close this listing?" message="Buyers won’t see it any more and you can’t reopen it. Orders already placed carry on." confirmLabel="Close listing"
                    onConfirm={() => setStatus('closed')}>Close</ConfirmButton>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title={listing.title} subtitle={`${listing.listing_type_label} · ${Number(listing.available_quantity)} ${listing.unit} available`} actions={actions} />}>
            <Head title={`Edit ${listing.title}`} />
            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <InputError message={status.errors.status} />
                {['active', 'sold_out', 'paused'].includes(listing.status) && (
                    <Link href={route('marketplace.show', listing.id)} className="text-sm text-green-700 hover:underline">See it as buyers do ›</Link>
                )}

                <Card title={`Photos (${listing.images.length} of ${max_images})`}>
                    <div className="flex flex-wrap gap-3">
                        {listing.images.map((img) => (
                            <div key={img.id} className="relative h-24 w-28 overflow-hidden rounded bg-gray-100">
                                <img src={img.url} alt="" className="h-full w-full object-cover" />
                                <button type="button" aria-label="Remove photo" className="absolute right-1 top-1 rounded bg-white/90 px-1.5 text-sm text-red-600"
                                    onClick={() => router.delete(route('listings.images.destroy', [listing.id, img.id]), { preserveScroll: true })}>×</button>
                            </div>
                        ))}
                    </div>
                    {listing.images.length < max_images && (
                        <form className="mt-4 flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); photos.post(route('listings.images.store', listing.id), { preserveScroll: true, onSuccess: () => photos.reset() }); }}>
                            <FormField id="images" label="Add photos" hint="JPG, PNG or WebP, up to 4 MB each" error={photos.errors.images ?? photos.errors['images.0']}>
                                <input id="images" type="file" multiple accept="image/jpeg,image/png,image/webp" className="block text-sm" onChange={(e) => photos.setData('images', Array.from(e.target.files))} />
                            </FormField>
                            <SecondaryButton type="submit" disabled={photos.processing || photos.data.images.length === 0}>{photos.processing ? 'Uploading…' : 'Upload'}</SecondaryButton>
                        </form>
                    )}
                </Card>

                <form onSubmit={(e) => { e.preventDefault(); form.put(route('listings.update', listing.id), { preserveScroll: true }); }} className="space-y-6">
                    {raw ? (
                        <Card title="Lot">
                            <p className="text-sm text-gray-700">Selling lot <Link href={route('lots.show', listing.lot.id)} className="text-green-700 hover:underline">{listing.lot.code}</Link> · <Quantity value={listing.available_quantity} unit="kg" /> available</p>
                            <FormField id="minimum_order_quantity" label="Minimum order (kg, optional)" error={form.errors.minimum_order_quantity} className="mt-4 max-w-xs">
                                <TextInput id="minimum_order_quantity" type="number" min="0" step="0.001" className="block w-full" value={form.data.minimum_order_quantity ?? ''}
                                    onChange={(e) => form.setData('minimum_order_quantity', e.target.value)} />
                            </FormField>
                        </Card>
                    ) : (
                        <Card title="Product details"><ProductFields form={form} categories={categories} conditions={conditions} /></Card>
                    )}
                    <Card title="What buyers see"><BasicFields form={form} unit={listing.unit} /></Card>
                    {!raw && <Card title="Made from"><MaterialsPicker form={form} lots={lots} /></Card>}
                    <div className="flex justify-end">
                        <PrimaryButton disabled={form.processing}>{form.processing ? 'Saving…' : 'Save changes'}</PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
