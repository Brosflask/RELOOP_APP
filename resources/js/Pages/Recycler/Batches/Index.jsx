// Developer 2. Batches collectors have sent to this recycler.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import StatusBadge from '@/Components/UI/StatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function RecyclerBatchesIndex({ incoming, history }) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Incoming batches" subtitle="Plastic collectors have sent you. Weigh it and confirm, or reject it." />}>
            <Head title="Incoming batches" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <Card title={`On the way (${incoming.length})`} padded={incoming.length === 0}>
                    {incoming.length === 0 ? (
                        <EmptyState title="Nothing on the way" description="When a collector sends you a batch, it shows up here." />
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {incoming.map((b) => (
                                <li key={b.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                                    <div>
                                        <p className="font-medium text-gray-900">{b.code} · {b.material_label}</p>
                                        <p className="text-sm text-gray-500">From {b.collector?.name} · sent <DateTime value={b.dispatched_at} /> · {b.records_count} record{b.records_count === 1 ? '' : 's'}</p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <Quantity value={b.total_weight_kg} className="font-semibold" />
                                        <Link href={route('batches.show', b.id)} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Receive</Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Received and rejected" padded={false}>
                    {history.data.length === 0 ? (
                        <p className="p-4 text-sm text-gray-500 sm:p-6">No batches yet.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead>
                                    <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                        <th className="px-4 py-2">Batch</th>
                                        <th className="px-4 py-2">Plastic</th>
                                        <th className="px-4 py-2 text-right">Sent</th>
                                        <th className="px-4 py-2 text-right">Received</th>
                                        <th className="px-4 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {history.data.map((b) => (
                                        <tr key={b.id}>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <Link href={route('batches.show', b.id)} className="font-medium text-green-700 hover:underline">{b.code}</Link>
                                                <p className="text-xs text-gray-500">{b.collector?.name}</p>
                                            </td>
                                            <td className="px-4 py-3">{b.material_label}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right"><Quantity value={b.total_weight_kg} /></td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right">{b.received_weight_kg ? <Quantity value={b.received_weight_kg} /> : '—'}</td>
                                            <td className="px-4 py-3"><StatusBadge status={b.status} label={b.status_label} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
                <Pagination links={history.links} />
            </div>
        </AuthenticatedLayout>
    );
}
