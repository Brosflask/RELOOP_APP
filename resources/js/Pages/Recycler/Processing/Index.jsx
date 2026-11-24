// Developer 3.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateTime from '@/Components/UI/DateTime';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import { Head, Link } from '@inertiajs/react';

export default function ProcessingIndex({ records }) {
    const newRecord = (
        <Link href={route('recycler.processing.create')} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
            Record processing
        </Link>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="Processing records" actions={newRecord} />}>
            <Head title="Processing records" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                {records.data.length === 0 ? (
                    <EmptyState title="No processing records yet" description="Each time you process a received batch, record the weight in and the lots that came out." action={newRecord} />
                ) : (
                    <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th className="px-4 py-2">Reference</th>
                                    <th className="px-4 py-2">Date</th>
                                    <th className="px-4 py-2">Process</th>
                                    <th className="px-4 py-2 text-right">In</th>
                                    <th className="px-4 py-2 text-right">Out</th>
                                    <th className="px-4 py-2 text-right">Lost</th>
                                    <th className="px-4 py-2 text-right">Lots</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.data.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <Link href={route('recycler.processing.show', r.id)} className="font-medium text-green-700 hover:underline">{r.reference}</Link>
                                            <p className="text-xs text-gray-500">Batch #{r.material_batch_id}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-gray-700"><DateTime value={r.processed_at} /></td>
                                        <td className="px-4 py-3 capitalize text-gray-700">{r.process_type}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Quantity value={r.input_weight_kg} /></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Quantity value={r.output_weight_kg} /></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-gray-500"><Quantity value={r.loss_weight_kg} /></td>
                                        <td className="px-4 py-3 text-right">{r.lots_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination links={records.links} />
            </div>
        </AuthenticatedLayout>
    );
}
