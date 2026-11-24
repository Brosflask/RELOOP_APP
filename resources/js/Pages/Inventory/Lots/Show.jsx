// Developer 3. A lot's balances, its full stock history, and manual adjustments.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import FormField from '@/Components/UI/FormField';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import Quantity from '@/Components/UI/Quantity';
import StatusBadge from '@/Components/UI/StatusBadge';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

function Signed({ value, unit }) {
    const n = Number(value);
    if (n === 0) return <span className="text-gray-400">—</span>;
    return <span className={n > 0 ? 'text-green-700' : 'text-red-600'}>{n > 0 ? '+' : '−'}<Quantity value={Math.abs(n)} unit={unit} /></span>;
}

export default function LotShow({ lot, balances, history, can }) {
    const form = useForm({ quantity: '', reason: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('lots.adjust', lot.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={lot.code} subtitle={`${lot.material_type_label}${lot.form ? `, ${lot.form}` : ''}`} actions={<StatusBadge status={lot.status} label={lot.status_label} />} />}>
            <Head title={lot.code} />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <div className="grid grid-cols-3 gap-3">
                    {[['Available', balances.available], ['Reserved for orders', balances.reserved], ['On hand', balances.on_hand]].map(([label, value]) => (
                        <div key={label} className="rounded-lg bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                            <p className="mt-1 text-xl font-semibold text-gray-900"><Quantity value={value} unit={lot.unit} /></p>
                        </div>
                    ))}
                </div>

                <Card title="Where it came from">
                    <ul className="space-y-1 text-sm text-gray-700">
                        <li>Started with <Quantity value={lot.initial_quantity} unit={lot.unit} /> on <DateTime value={lot.created_at} /></li>
                        {lot.processing_record && (
                            <li>Produced by <Link href={route('recycler.processing.show', lot.processing_record.id)} className="text-green-700 hover:underline">{lot.processing_record.reference}</Link></li>
                        )}
                        {lot.parent_lot_code && <li>Bought from lot {lot.parent_lot_code}</li>}
                        {lot.listing && <li>Listed for sale as “{lot.listing.title}” ({lot.listing.status.replaceAll('_', ' ')})</li>}
                    </ul>
                </Card>

                {can.adjust && (
                    <Card title="Adjust stock">
                        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-[10rem_1fr_auto] sm:items-end">
                            <FormField id="quantity" label={`Change (${lot.unit})`} error={form.errors.quantity} hint="Use a minus for stock removed, e.g. -2.5">
                                <TextInput id="quantity" type="number" step="0.001" inputMode="decimal" className="block w-full" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
                            </FormField>
                            <FormField id="reason" label="Reason" error={form.errors.reason}>
                                <TextInput id="reason" className="block w-full" placeholder="Recount, water damage…" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                            </FormField>
                            <PrimaryButton disabled={form.processing} className="sm:mb-6">{form.processing ? 'Saving…' : 'Save'}</PrimaryButton>
                        </form>
                    </Card>
                )}

                <Card title="Stock history" padded={false}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th className="px-4 py-2">Date</th>
                                    <th className="px-4 py-2">What happened</th>
                                    <th className="px-4 py-2 text-right">On hand</th>
                                    <th className="px-4 py-2 text-right">Reserved</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {history.data.map((m) => (
                                    <tr key={m.id}>
                                        <td className="whitespace-nowrap px-4 py-3 text-gray-600"><DateTime value={m.created_at} withTime /></td>
                                        <td className="px-4 py-3">
                                            <p className="text-gray-900">{m.movement_label}</p>
                                            {m.note && <p className="text-xs text-gray-500">{m.note}</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Signed value={m.quantity_change} unit={m.unit} /></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Signed value={m.reserved_change} unit={m.unit} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="p-4"><Pagination links={history.links} /></div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
