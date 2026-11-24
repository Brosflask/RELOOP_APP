// Developer 3. One listing in the marketplace grid.
import Money from '@/Components/UI/Money';
import Quantity from '@/Components/UI/Quantity';
import { Link } from '@inertiajs/react';

export default function ListingCard({ listing, href }) {
    return (
        <Link href={href ?? route('marketplace.show', listing.id)}
            className="group flex flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-transparent transition hover:ring-green-600 focus:outline-none focus:ring-2 focus:ring-green-600">
            <div className="aspect-[4/3] w-full bg-gray-100">
                {listing.image_url ? (
                    <img src={listing.image_url} alt={listing.title} className="h-full w-full object-cover" loading="lazy" />
                ) : (
                    <div className="flex h-full items-center justify-center text-sm text-gray-400">No photo yet</div>
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-4">
                <p className="text-xs font-medium uppercase tracking-wide text-green-700">{listing.listing_type_label}{listing.subtitle ? ` · ${listing.subtitle}` : ''}</p>
                <h3 className="font-semibold text-gray-900 group-hover:underline">{listing.title}</h3>
                <p className="mt-auto pt-2 text-lg font-semibold text-gray-900">
                    <Money amount={listing.price} currency={listing.currency} /> <span className="text-sm font-normal text-gray-500">per {listing.unit}</span>
                </p>
                <p className="text-sm text-gray-500">
                    <Quantity value={listing.available_quantity} unit={listing.unit} /> available{listing.location ? ` · ${listing.location}` : ''}
                </p>
            </div>
        </Link>
    );
}
