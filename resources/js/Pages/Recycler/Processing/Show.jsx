// Developer 3.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import LotTable from '@/Components/Recycling/LotTable';
import { Head } from '@inertiajs/react';

export default function ProcessingShow({ record, lots }) {
    const items = [
        ['Batch', `#${record.material_batch_id}`],
        ['Process', <span className="capitalize">{record.process_type}</span>],
        ['Date', <DateTime value={record.processed_at} />],
        ['Weight in', <Quantity value={record.input_weight_kg} />],
        ['Weight out', <Quantity value={record.output_weight_kg} />],
        ['Lost', <Quantity value={record.loss_weight_kg} />],
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title={record.reference} subtitle="Processing record" />}>
            <Head title={record.reference} />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <Card>
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        {items.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</dt>
                                <dd className="mt-1 text-gray-900">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {record.notes && <p className="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm text-gray-700">{record.notes}</p>}
                </Card>
                <Card title={`Lots produced (${lots.length})`} padded={false}>
                    <LotTable lots={lots} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
