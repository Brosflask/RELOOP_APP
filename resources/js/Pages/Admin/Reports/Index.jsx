// Developer 4. Admin analytics. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function Tile({ label, value, href, tone = 'text-gray-900' }) {
    const body = (
        <>
            <p className="text-sm text-gray-600">{label}</p>
            <p className={`mt-1 text-2xl font-semibold ${tone}`}>{value}</p>
        </>
    );
    return href
        ? <Link href={href} className="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-green-600">{body}</Link>
        : <div className="rounded-lg bg-white p-4 shadow-sm">{body}</div>;
}

const label = (s) => s.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase());

export default function Reports({ filters, summary, sales_by_day, top_sellers, materials_sold, impact }) {
    const [range, setRange] = useState(filters);
    const apply = (e) => {
        e.preventDefault();
        router.get(route('admin.reports.index'), range, { preserveState: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Reports</h2>}>
            <Head title="Reports" />
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-6">
                <form onSubmit={apply} className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <label className="text-sm">From<input type="date" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} className="mt-1 block rounded-md border-gray-300" /></label>
                    <label className="text-sm">To<input type="date" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} className="mt-1 block rounded-md border-gray-300" /></label>
                    <button className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">Apply</button>
                    <a href={route('admin.reports.transactions.export', filters)} className="ml-auto rounded-md border border-gray-300 px-4 py-2 text-sm">Export transactions (CSV)</a>
                </form>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Tile label="Verified sales" value={<Money amount={summary.gmv} />} />
                    <Tile label="Paid orders" value={summary.paid_orders} />
                    <Tile label="Failed payment rate" value={summary.failed_payment_rate === null ? '–' : `${summary.failed_payment_rate}%`} />
                    <Tile label="Payments needing attention" value={summary.payments_needing_attention}
                        tone={summary.payments_needing_attention ? 'text-red-700' : 'text-gray-900'}
                        href={route('admin.transactions.index', { attention: 1 })} />
                    <Tile label="Open complaints" value={summary.open_complaints} href={route('admin.complaints.index')} />
                    <Tile label="Open disputes" value={summary.open_disputes} href={route('admin.disputes.index')} />
                    <Tile label="Kg collected" value={impact.kg_collected ?? 'Not available yet'} />
                    <Tile label="Kg sold" value={impact.kg_sold} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-lg bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Orders by status</h3>
                        <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                            {Object.entries(summary.orders_by_status).map(([k, v]) => (
                                <div key={k} className="flex justify-between rounded bg-gray-50 px-3 py-2"><dt>{label(k)}</dt><dd className="font-semibold">{v}</dd></div>
                            ))}
                        </dl>
                    </section>
                    <section className="rounded-lg bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Payments by status</h3>
                        <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                            {Object.entries(summary.payments_by_status).map(([k, v]) => (
                                <div key={k} className="flex justify-between rounded bg-gray-50 px-3 py-2"><dt>{label(k)}</dt><dd className="font-semibold">{v}</dd></div>
                            ))}
                        </dl>
                    </section>
                </div>

                <section className="overflow-x-auto rounded-lg bg-white p-5 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Verified sales by day</h3>
                    {sales_by_day.length === 0 ? <p className="mt-2 text-sm text-gray-500">No verified sales in this period.</p> : (
                        <table className="mt-3 w-full text-sm">
                            <thead className="text-left text-gray-500"><tr><th className="py-2">Day</th><th>Raw materials</th><th>Products</th><th>Orders</th></tr></thead>
                            <tbody className="divide-y divide-gray-100">
                                {sales_by_day.map((d) => (
                                    <tr key={d.day}><td className="py-2">{d.day}</td><td><Money amount={d.raw_material} /></td><td><Money amount={d.finished_product} /></td><td>{d.orders}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="overflow-x-auto rounded-lg bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Top sellers</h3>
                        <table className="mt-3 w-full text-sm">
                            <thead className="text-left text-gray-500"><tr><th className="py-2">Seller</th><th>Sales</th><th>Orders</th><th>Rating</th></tr></thead>
                            <tbody className="divide-y divide-gray-100">
                                {top_sellers.map((s) => (
                                    <tr key={s.id}><td className="py-2">{s.name}</td><td><Money amount={s.total} /></td><td>{s.orders}</td><td>{s.average_rating ?? '–'}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                    <section className="overflow-x-auto rounded-lg bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Top materials sold</h3>
                        <table className="mt-3 w-full text-sm">
                            <thead className="text-left text-gray-500"><tr><th className="py-2">Listing</th><th>Kg</th><th>Sales</th></tr></thead>
                            <tbody className="divide-y divide-gray-100">
                                {materials_sold.map((m) => (
                                    <tr key={m.title}><td className="py-2">{m.title}</td><td>{m.kg}</td><td><Money amount={m.total} /></td></tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
