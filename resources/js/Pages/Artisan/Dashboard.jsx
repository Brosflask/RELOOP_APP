// Developer 3. Uses Developer 4's SellerOrdersWidget.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import ListingCard from '@/Components/Marketplace/ListingCard';
import LotTable from '@/Components/Recycling/LotTable';
import SellerOrdersWidget from '@/Components/Orders/SellerOrdersWidget';
import { Head, Link } from '@inertiajs/react';

export default function ArtisanDashboard({ stats, products, lots, orders }) {
    const newProduct = (
        <Link href={route('listings.create', { type: 'finished_product' })} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">List a product</Link>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="Artisan dashboard" actions={newProduct} />}>
            <Head title="Artisan dashboard" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <div className="grid grid-cols-3 gap-3">
                    {[['Live products', stats.active_products], ['Drafts', stats.draft_products], ['Material lots', stats.material_lots]].map(([label, value]) => (
                        <div key={label} className="rounded-lg bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <SellerOrdersWidget summary={orders} />
                    <Card title="Raw material you bought" padded={false}
                        actions={<Link href={route('marketplace.index', { type: 'raw_material' })} className="text-sm text-green-700">Buy material</Link>}>
                        {lots.length === 0 ? <p className="p-4 text-sm text-gray-500">Lots you buy on the marketplace appear here once paid.</p> : <LotTable lots={lots} />}
                    </Card>
                </div>

                <Card title="Your products" actions={<Link href={route('listings.index')} className="text-sm text-green-700">All listings</Link>}>
                    {products.length === 0 ? (
                        <EmptyState title="No products yet" description="List something you made, and link it to the lots you used." action={newProduct} />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {products.map((p) => <ListingCard key={p.id} listing={p} href={route('listings.edit', p.id)} />)}
                        </div>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
