// Developer 4. Admin transaction monitoring.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Money from '@/Components/Orders/Money';
import OrderStatusBadge from '@/Components/Orders/OrderStatusBadge';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function TransactionsIndex({ payments, filters, statuses }) {
    const [f, setF] = useState({ ...filters, search: filters.search ?? '' });
    const go = (next) => router.get(route('admin.transactions.index'), Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== false && v != null)), { preserveState: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Transactions</h2>}>
            <Head title="Transactions" />
            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6">
                <form onSubmit={(e) => { e.preventDefault(); go(f); }} className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <label className="text-sm">Status
                        <select value={f.status ?? ''} onChange={(e) => setF({ ...f, status: e.target.value })} className="mt-1 block rounded-md border-gray-300">
                            <option value="">All</option>{statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </label>
                    <label className="text-sm">Search
                        <input value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} placeholder="Order code, reference, phone"
                            className="mt-1 block rounded-md border-gray-300" />
                    </label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!f.attention} onChange={(e) => setF({ ...f, attention: e.target.checked ? 1 : '' })} /> Needs attention</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!f.stuck} onChange={(e) => setF({ ...f, stuck: e.target.checked ? 1 : '' })} /> Pending over 1 hour</label>
                    <button className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </form>

                <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-left text-gray-500">
                            <tr><th className="px-4 py-2">#</th><th>Date</th><th>Order</th><th>Payer</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {payments.data.map((p) => (
                                <tr key={p.id} className={p.requires_attention ? 'bg-red-50' : ''}>
                                    <td className="px-4 py-2"><Link href={route('admin.transactions.show', p.id)} className="text-green-700">{p.id}</Link></td>
                                    <td>{new Date(p.created_at).toLocaleString()}</td>
                                    <td>{p.order?.code}</td>
                                    <td>{p.payer}</td>
                                    <td>{p.payee}</td>
                                    <td><Money amount={p.amount} currency={p.currency} /></td>
                                    <td><OrderStatusBadge status={p.status} /></td>
                                    <td>{p.requires_attention && <span className="text-xs font-semibold text-red-700">Attention</span>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {payments.data.length === 0 && <p className="p-6 text-sm text-gray-500">No payments match.</p>}
                </div>
                <div className="flex justify-between text-sm">
                    {payments.prev_page_url ? <Link href={payments.prev_page_url} className="text-green-700">Previous</Link> : <span />}
                    {payments.next_page_url && <Link href={payments.next_page_url} className="text-green-700">Next</Link>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
