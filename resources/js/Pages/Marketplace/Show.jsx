// Developer 3. "Buy now" and "Message seller" post to Developer 4's routes; orders and checkout are theirs.
import MarketplaceShell from '@/Components/Marketplace/MarketplaceShell';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import FormField from '@/Components/UI/FormField';
import Money from '@/Components/UI/Money';
import Quantity from '@/Components/UI/Quantity';
import StatusBadge from '@/Components/UI/StatusBadge';
import TextArea from '@/Components/UI/TextArea';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function Origin({ item }) {
    return (
        <li className="rounded-md border border-gray-200 p-3 text-sm">
            <p className="font-medium text-gray-900">
                {item.material_type_label}{item.form ? `, ${item.form}` : ''}{item.grade ? ` (grade ${item.grade})` : ''}
                {item.quantity_used && <span className="font-normal text-gray-500"> · <Quantity value={item.quantity_used} unit={item.unit} /> used</span>}
            </p>
            <p className="text-gray-600">Lot {item.lot_code}{item.original_lot_code ? `, bought from lot ${item.original_lot_code}` : ''}</p>
            {item.processing && (
                <p className="text-gray-600">
                    <span className="capitalize">{item.processing.process_type}</span> on <DateTime value={item.processing.processed_at} /> ({item.processing.reference}),
                    from collected batch #{item.processing.material_batch_id}: <Quantity value={item.processing.input_weight_kg} /> in, <Quantity value={item.processing.output_weight_kg} /> out
                </p>
            )}
        </li>
    );
}

export default function MarketplaceShow({ listing, traceability, can }) {
    const { auth } = usePage().props;
    const [photo, setPhoto] = useState(0);
    const buy = useForm({ listing_id: listing.id, quantity: listing.minimum_order_quantity ?? '1', delivery_address: '', delivery_notes: '' });
    const message = useForm({ listing_id: listing.id, body: '' });

    const total = Number(buy.data.quantity) > 0 ? Number(buy.data.quantity) * Number(listing.price) : 0;
    const image = listing.images[photo];

    return (
        <MarketplaceShell>
            <Head title={listing.title} />
            <div className="mx-auto max-w-6xl space-y-6 px-4 py-6">
                <Link href={route('marketplace.index')} className="text-sm text-green-700 hover:underline">‹ Back to marketplace</Link>

                <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="min-w-0 space-y-6">
                        <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                            <div className="aspect-[4/3] bg-gray-100">
                                {image ? <img src={image.url} alt={image.alt_text ?? listing.title} className="h-full w-full object-cover" />
                                    : <div className="flex h-full items-center justify-center text-gray-400">No photo yet</div>}
                            </div>
                            {listing.images.length > 1 && (
                                <div className="flex gap-2 overflow-x-auto p-3">
                                    {listing.images.map((img, i) => (
                                        <button key={img.id} type="button" onClick={() => setPhoto(i)} aria-label={`Photo ${i + 1}`}
                                            className={`h-16 w-20 shrink-0 overflow-hidden rounded ring-2 ${i === photo ? 'ring-green-600' : 'ring-transparent'}`}>
                                            <img src={img.url} alt="" className="h-full w-full object-cover" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <Card>
                            <p className="text-xs font-medium uppercase tracking-wide text-green-700">{listing.listing_type_label}{listing.subtitle ? ` · ${listing.subtitle}` : ''}</p>
                            <h1 className="mt-1 text-2xl font-semibold text-gray-900">{listing.title}</h1>
                            <p className="mt-1 text-sm text-gray-500">Sold by {listing.seller?.name}{listing.location ? ` · ${listing.location}` : ''}{listing.product_condition ? ` · ${listing.product_condition}` : ''}</p>
                            {listing.description && <p className="mt-4 whitespace-pre-line text-gray-700">{listing.description}</p>}
                        </Card>

                        <Card title="Where this plastic came from">
                            {traceability.length === 0 ? (
                                <p className="text-sm text-gray-500">The seller hasn’t linked this listing to a material lot yet.</p>
                            ) : (
                                <ul className="space-y-3">{traceability.map((t, i) => <Origin key={i} item={t} />)}</ul>
                            )}
                        </Card>
                    </div>

                    <aside className="space-y-4 lg:sticky lg:top-6 lg:self-start">
                        <Card>
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-2xl font-semibold text-gray-900"><Money amount={listing.price} currency={listing.currency} /></p>
                                {listing.status !== 'active' && <StatusBadge status={listing.status} label={listing.status_label} />}
                            </div>
                            <p className="text-sm text-gray-500">per {listing.unit} · <Quantity value={listing.available_quantity} unit={listing.unit} /> available</p>
                            {listing.minimum_order_quantity && <p className="text-sm text-gray-500">Minimum order <Quantity value={listing.minimum_order_quantity} unit={listing.unit} /></p>}

                            {can.buy && (
                                <form className="mt-4 space-y-3" onSubmit={(e) => { e.preventDefault(); buy.post(route('orders.store')); }}>
                                    <FormField id="quantity" label={`Quantity (${listing.unit})`} error={buy.errors.quantity}>
                                        <TextInput id="quantity" type="number" min="0" step={listing.unit === 'piece' ? '1' : '0.001'} inputMode="decimal" className="block w-full"
                                            value={buy.data.quantity} onChange={(e) => buy.setData('quantity', e.target.value)} />
                                    </FormField>
                                    <FormField id="delivery_address" label="Delivery address" error={buy.errors.delivery_address}>
                                        <TextInput id="delivery_address" className="block w-full" value={buy.data.delivery_address} onChange={(e) => buy.setData('delivery_address', e.target.value)} />
                                    </FormField>
                                    <InputError message={buy.errors.listing_id} />
                                    <p className="text-sm text-gray-600">Total about <Money amount={total} /></p>
                                    <PrimaryButton className="w-full justify-center" disabled={buy.processing}>{buy.processing ? 'Placing order…' : 'Buy now'}</PrimaryButton>
                                    <p className="text-xs text-gray-500">You pay with MTN MoMo on the next step.</p>
                                </form>
                            )}
                            {!auth?.user && (
                                <Link href={route('login')} className="mt-4 block rounded-md bg-green-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-green-800">Log in to buy</Link>
                            )}
                            {can.edit && (
                                <Link href={route('listings.edit', listing.id)} className="mt-4 block rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">Edit your listing</Link>
                            )}
                        </Card>

                        {can.message && (
                            <Card title="Ask the seller or make an offer">
                                <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); message.post(route('messages.store')); }}>
                                    <TextArea aria-label="Message" rows={3} placeholder="Is this still available? Can you deliver to Tema?" value={message.data.body}
                                        onChange={(e) => message.setData('body', e.target.value)} />
                                    <InputError message={message.errors.listing_id ?? message.errors.body} />
                                    <SecondaryButton type="submit" className="w-full justify-center" disabled={message.processing}>Message seller</SecondaryButton>
                                </form>
                            </Card>
                        )}
                    </aside>
                </div>
            </div>
        </MarketplaceShell>
    );
}
