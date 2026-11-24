// Developer 3. Uses Developer 1's AuthenticatedLayout and Developer 4's SellerOrdersWidget.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import LotTable from '@/Components/Recycling/LotTable';
import SellerOrdersWidget from '@/Components/Orders/SellerOrdersWidget';
import { Head, Link } from '@inertiajs/react';

function Stat({ label, children }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{children}</p>
        </div>
    );
}

export default function RecyclerDashboard({ stats, batches_waiting, recent_records, lots, orders }) {
    const newRecord = (
        <Link href={route('recycler.processing.create')} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
            Record processing
        </Link>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="Recycler dashboard" actions={newRecord} />}>
            <Head title="Recycler dashboard" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Stat label="In stock"><Quantity value={stats.available_kg} unit="kg" /></Stat>
                    <Stat label="Lots in stock">{stats.lots_in_stock}</Stat>
                    <Stat label="Processed this month"><Quantity value={stats.processed_this_month_kg} unit="kg" /></Stat>
                    <Stat label="Loss this month">{stats.loss_rate_percent === null ? '—' : `${stats.loss_rate_percent}%`}</Stat>
                </div>

                <Card title="Batches waiting to be processed">
                    {batches_waiting.length === 0 ? (
                        <p className="text-sm text-gray-500">No received batches are waiting. Batches appear here once you confirm a delivery from a collector.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {batches_waiting.map((b) => (
                                <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <div>
                                        <p className="font-medium text-gray-900">Batch #{b.id}</p>
                                        <p className="text-sm text-gray-500">
                                            <Quantity value={b.remaining_weight_kg} /> left of <Quantity value={b.received_weight_kg} /> · received <DateTime value={b.received_at} />
                                        </p>
                                    </div>
                                    <Link href={route('recycler.processing.create', { batch: b.id })} className="text-sm font-medium text-green-700 hover:underline">Process</Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card title="Recent processing" actions={<Link href={route('recycler.processing.index')} className="text-sm text-green-700">All</Link>}>
                        {recent_records.length === 0 ? (
                            <p className="text-sm text-gray-500">Nothing recorded yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {recent_records.map((r) => (
                                    <li key={r.id} className="py-2">
                                        <Link href={route('recycler.processing.show', r.id)} className="flex justify-between gap-2 text-sm hover:underline">
                                            <span className="font-medium text-gray-900">{r.reference} · {r.process_type}</span>
                                            <span className="text-gray-500"><Quantity value={r.input_weight_kg} /> → <Quantity value={r.output_weight_kg} /></span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    <SellerOrdersWidget summary={orders} />
                </div>

                <Card title="Lots in stock" padded={false} actions={<Link href={route('lots.index')} className="text-sm text-green-700">All lots</Link>}>
                    {lots.length === 0 ? (
                        <div className="p-4"><EmptyState title="No lots yet" description="Record a processing run to create your first lot." action={newRecord} /></div>
                    ) : (
                        <LotTable lots={lots} />
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
