// Developer 3. Public browse page. Filters live in the URL.
import MarketplaceShell from '@/Components/Marketplace/MarketplaceShell';
import ListingCard from '@/Components/Marketplace/ListingCard';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import SelectInput from '@/Components/UI/SelectInput';
import TextInput from '@/Components/TextInput';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const SORTS = [
    { value: 'newest', label: 'Newest first' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
];

export default function MarketplaceIndex({ listings, filters, types, material_types, categories }) {
    const [text, setText] = useState({ q: filters.q ?? '', location: filters.location ?? '', min_price: filters.min_price ?? '', max_price: filters.max_price ?? '' });
    const first = useRef(true);

    const go = (changes) => {
        const params = { ...filters, ...changes };
        Object.keys(params).forEach((k) => (params[k] === '' || params[k] === null) && delete params[k]);
        delete params.page;
        router.get(route('marketplace.index'), params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        if (first.current) { first.current = false; return; }
        const t = setTimeout(() => go(text), 300);
        return () => clearTimeout(t);
    }, [text.q, text.location, text.min_price, text.max_price]);

    const setField = (k) => (e) => setText((t) => ({ ...t, [k]: e.target.value }));
    const hasFilters = Object.entries(filters).some(([k, v]) => k !== 'sort' && v);

    return (
        <MarketplaceShell header={<PageHeader title="Marketplace" subtitle="Recycled plastic and products made from it, traceable to where it was collected" />}>
            <Head title="Marketplace" />
            <div className="mx-auto max-w-7xl gap-6 px-4 py-6 lg:grid lg:grid-cols-[16rem_1fr]">
                <aside className="mb-6 space-y-4 rounded-lg bg-white p-4 shadow-sm lg:mb-0 lg:self-start">
                    <TextInput type="search" aria-label="Search" placeholder="Search listings" className="block w-full" value={text.q} onChange={setField('q')} />
                    <div>
                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Type</p>
                        <SelectInput aria-label="Type" value={filters.type ?? ''} placeholder="Everything" options={types} onChange={(e) => go({ type: e.target.value, category: filters.category })} />
                    </div>
                    <div>
                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Plastic type</p>
                        <SelectInput aria-label="Plastic type" value={filters.material_type ?? ''} placeholder="Any plastic" options={material_types} onChange={(e) => go({ material_type: e.target.value })} />
                    </div>
                    {filters.type !== 'raw_material' && (
                        <div>
                            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Product category</p>
                            <SelectInput aria-label="Product category" value={filters.category ?? ''} placeholder="Any category" options={categories} onChange={(e) => go({ category: e.target.value })} />
                        </div>
                    )}
                    <div>
                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Price per unit (GHS)</p>
                        <div className="flex gap-2">
                            <TextInput type="number" min="0" inputMode="decimal" aria-label="Minimum price" placeholder="Min" className="w-full" value={text.min_price} onChange={setField('min_price')} />
                            <TextInput type="number" min="0" inputMode="decimal" aria-label="Maximum price" placeholder="Max" className="w-full" value={text.max_price} onChange={setField('max_price')} />
                        </div>
                    </div>
                    <div>
                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Location</p>
                        <TextInput aria-label="Location" placeholder="e.g. Kumasi" className="block w-full" value={text.location} onChange={setField('location')} />
                    </div>
                    {hasFilters && (
                        <button type="button" className="text-sm font-medium text-green-700 hover:underline"
                            onClick={() => { setText({ q: '', location: '', min_price: '', max_price: '' }); router.get(route('marketplace.index')); }}>
                            Clear filters
                        </button>
                    )}
                </aside>

                <section className="min-w-0 space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-gray-600">{listings.total} {listings.total === 1 ? 'listing' : 'listings'}</p>
                        <SelectInput aria-label="Sort" className="w-48" value={filters.sort ?? 'newest'} options={SORTS} onChange={(e) => go({ sort: e.target.value })} />
                    </div>
                    {listings.data.length === 0 ? (
                        <EmptyState title={hasFilters ? 'Nothing matches these filters' : 'No listings yet'}
                            description={hasFilters ? 'Try a wider price range or another plastic type.' : 'Recyclers and artisans will list their material and products here.'} />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {listings.data.map((l) => <ListingCard key={l.id} listing={l} />)}
                        </div>
                    )}
                    <Pagination links={listings.links} />
                </section>
            </div>
        </MarketplaceShell>
    );
}
