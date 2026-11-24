// Developer 3. Filters live in the URL so the page can be shared and the back button works.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Pagination from '@/Components/UI/Pagination';
import SelectInput from '@/Components/UI/SelectInput';
import TextInput from '@/Components/TextInput';
import LotTable from '@/Components/Recycling/LotTable';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function LotsIndex({ lots, filters, statuses, material_types }) {
    const [q, setQ] = useState(filters.q ?? '');
    const first = useRef(true);

    const go = (changes) => router.get(route('lots.index'), { ...filters, ...changes, page: undefined }, { preserveState: true, replace: true });

    useEffect(() => {
        if (first.current) { first.current = false; return; }
        const t = setTimeout(() => go({ q: q || undefined }), 300);
        return () => clearTimeout(t);
    }, [q]);

    const filtered = filters.q || filters.status || filters.material_type;

    return (
        <AuthenticatedLayout header={<PageHeader title="Material lots" subtitle="Stock you produced or bought" />}>
            <Head title="Material lots" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                <div className="flex flex-wrap gap-3">
                    <TextInput type="search" placeholder="Search lot code" aria-label="Search lot code" value={q} onChange={(e) => setQ(e.target.value)} className="w-full sm:w-56" />
                    <SelectInput aria-label="Material" className="w-full sm:w-48" value={filters.material_type ?? ''} placeholder="All materials"
                        options={material_types} onChange={(e) => go({ material_type: e.target.value || undefined })} />
                    <SelectInput aria-label="Status" className="w-full sm:w-40" value={filters.status ?? ''} placeholder="All statuses"
                        options={statuses} onChange={(e) => go({ status: e.target.value || undefined })} />
                </div>

                {lots.data.length === 0 ? (
                    <EmptyState title={filtered ? 'No lots match these filters' : 'No lots yet'}
                        description={filtered ? 'Try another material or status.' : 'Lots appear here when you record processing or buy raw material.'} />
                ) : (
                    <div className="rounded-lg bg-white shadow-sm"><LotTable lots={lots.data} /></div>
                )}
                <Pagination links={lots.links} />
            </div>
        </AuthenticatedLayout>
    );
}
