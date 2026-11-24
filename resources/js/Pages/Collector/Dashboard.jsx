// Developer 2. A collector's jobs.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import SelectInput from '@/Components/UI/SelectInput';
import StatusBadge from '@/Components/UI/StatusBadge';
import MaterialChips from '@/Components/Collection/MaterialChips';
import PickupWhen from '@/Components/Collection/PickupWhen';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, router } from '@inertiajs/react';

export default function CollectorDashboard({ available, mine, areas, filters, stats }) {
    const filterArea = (area) => router.get(route('collector.dashboard'), area ? { area } : {}, { preserveState: true, preserveScroll: true });

    const actions = (
        <>
            <Link href={route('collector.records.index')} className="text-sm font-medium text-green-700 hover:underline">My records</Link>
            <Link href={route('collector.records.create')} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Record a drop-off</Link>
        </>
    );

    return (
        <AuthenticatedLayout header={<PageHeader title="Collector dashboard" actions={actions} />}>
            <Head title="Collector dashboard" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <dl className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat label="Collected this month"><Quantity value={stats.kg_this_month} /></Stat>
                    <Stat label="Ready to batch"><Quantity value={stats.unbatched_kg} /></Stat>
                    <Stat label="Waiting for households">{stats.awaiting_confirmation}</Stat>
                    <Stat label="Disputed" warn={stats.disputed > 0}>{stats.disputed}</Stat>
                </dl>

                <Card title="My jobs" padded={mine.length === 0}>
                    {mine.length === 0 ? (
                        <p className="text-sm text-gray-500">You have no jobs right now. Take one from the list below.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {mine.map((job) => (
                                <li key={job.id}>
                                    <Link href={route('collections.show', job.id)} className="flex flex-wrap items-start justify-between gap-3 px-4 py-4 hover:bg-gray-50 sm:px-6">
                                        <JobSummary job={job} />
                                        <StatusBadge status={job.status} label={job.status_label} />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Pickups waiting for a collector" padded={false}
                    actions={areas.length > 0 && (
                        <SelectInput aria-label="Filter by area" className="text-sm" placeholder="All areas" value={filters.area ?? ''}
                            options={areas.map((a) => ({ value: a, label: a }))} onChange={(e) => filterArea(e.target.value)} />
                    )}>
                    {available.length === 0 ? (
                        <div className="p-4 sm:p-6"><EmptyState title="No open pickups" description="New requests from households will show here." /></div>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {available.map((job) => (
                                <li key={job.id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6">
                                    <JobSummary job={job} />
                                    <div className="flex items-center gap-3">
                                        <Link href={route('collections.show', job.id)} className="text-sm text-gray-600 hover:underline">Details</Link>
                                        <PrimaryButton onClick={() => router.post(route('collector.jobs.accept', job.id))}>Accept</PrimaryButton>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function Stat({ label, children, warn = false }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className={`mt-1 text-2xl font-semibold ${warn ? 'text-red-600' : 'text-gray-900'}`}>{children}</dd>
        </div>
    );
}

function JobSummary({ job }) {
    return (
        <div className="min-w-0 space-y-1">
            <p className="font-medium text-gray-900">{job.area ?? 'Area not given'} <span className="text-sm font-normal text-gray-500">· {job.reference}</span></p>
            <p className="text-sm text-gray-600"><PickupWhen date={job.preferred_date} slot={job.preferred_time_slot} /></p>
            <MaterialChips items={job.items} prefix="about " />
        </div>
    );
}
