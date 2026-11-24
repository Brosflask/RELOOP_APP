// Developer 2. The journey of recycled plastic, from household pickups to this listing or lot.
// Public for listings, so it never shows household names or addresses (the server leaves them out).
import MarketplaceShell from '@/Components/Marketplace/MarketplaceShell';
import Card from '@/Components/UI/Card';
import DateTime from '@/Components/UI/DateTime';
import EmptyState from '@/Components/UI/EmptyState';
import PageHeader from '@/Components/UI/PageHeader';
import Quantity from '@/Components/UI/Quantity';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function TraceabilityShow({ title, subtitle, back, traces }) {
    const backLink = back && <Link href={route(back.route, back.id)} className="text-sm font-medium text-green-700 hover:underline">{back.label}</Link>;

    return (
        <MarketplaceShell header={<PageHeader title={`Where ${title} came from`} subtitle={subtitle} actions={backLink} />}>
            <Head title={`Traceability · ${title}`} />
            <div className="mx-auto max-w-4xl space-y-8 px-4 py-6">
                {traces.length === 0 ? (
                    <EmptyState title="No history yet" description="This item isn’t linked to recycled material lots yet." />
                ) : traces.map((t, i) => <Journey key={t.lot.id ?? i} trace={t} />)}
            </div>
        </MarketplaceShell>
    );
}

function Journey({ trace }) {
    const { lot, ownership, processing, batches } = trace;
    const collected = batches.reduce((s, b) => s + Number(b.collections.total_kg), 0);
    const households = batches.reduce((s, b) => s + b.collections.households, 0);

    return (
        <Card title={`${lot.code} · ${lot.material_label}${lot.form ? ` ${lot.form}` : ''}`}>
            {batches.length > 0 && (
                <p className="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-900">
                    Made from <strong><Quantity value={collected} /></strong> of plastic collected
                    {households > 0 && <> from <strong>{households}</strong> household{households === 1 ? '' : 's'}</>}
                    {batches.flatMap((b) => b.collections.areas).length > 0 && <> in {[...new Set(batches.flatMap((b) => b.collections.areas))].join(', ')}</>}.
                </p>
            )}
            <ol className="relative space-y-6 border-l-2 border-green-200 pl-6">
                {batches.map((b) => <BatchStep key={b.code} batch={b} />)}
                {processing && (
                    <Step title={`Processed by ${processing.recycler ?? 'a recycler'}`} at={processing.processed_at}>
                        <span className="capitalize">{processing.process_type}</span>: <Quantity value={processing.input_weight_kg} /> in,{' '}
                        <Quantity value={processing.output_weight_kg} /> out, <Quantity value={processing.loss_weight_kg} /> lost ({processing.reference}).
                    </Step>
                )}
                {ownership.map((l, i) => (
                    <Step key={l.id} title={i === 0 ? `Lot ${l.code} made` : `Bought by ${l.owner ?? 'a buyer'} as ${l.code}`} at={l.created_at}>
                        <Quantity value={l.quantity} unit={l.unit} /> of {l.material_label}{l.grade ? `, grade ${l.grade}` : ''}{i === 0 && l.owner ? `, owned by ${l.owner}` : ''}.
                    </Step>
                ))}
            </ol>
            {!processing && batches.length === 0 && <p className="mt-4 text-sm text-gray-500">This lot has no recorded collection history.</p>}
        </Card>
    );
}

function BatchStep({ batch: b }) {
    const [open, setOpen] = useState(false);
    const c = b.collections;
    return (
        <>
            <Step title={`Collected: ${c.count} pickup${c.count === 1 ? '' : 's'}`} at={c.first_collected_at}>
                <Quantity value={c.total_kg} /> of {b.material_label}
                {c.areas.length > 0 && <> in {c.areas.join(', ')}</>}
                {c.last_collected_at && c.last_collected_at !== c.first_collected_at && <> until <DateTime value={c.last_collected_at} /></>}
                {c.drop_offs > 0 && <>, including {c.drop_offs} drop-off{c.drop_offs === 1 ? '' : 's'}</>}.
                {c.records.length > 0 && (
                    <button type="button" onClick={() => setOpen(!open)} className="ml-2 text-green-700 hover:underline">{open ? 'Hide' : 'Show'} records</button>
                )}
                {open && (
                    <ul className="mt-2 divide-y divide-gray-100 rounded-md border border-gray-100 text-xs">
                        {c.records.map((r) => (
                            <li key={r.reference} className="flex justify-between gap-2 px-3 py-1.5">
                                <span>{r.reference} · {r.source}{r.area ? ` · ${r.area}` : ''}</span>
                                <span><Quantity value={r.weight_kg} /> · <DateTime value={r.collected_at} /></span>
                            </li>
                        ))}
                    </ul>
                )}
            </Step>
            <Step title={`Batched as ${b.code}${b.collector ? ` by ${b.collector}` : ''}`} at={b.dispatched_at}>
                <Quantity value={b.total_weight_kg} /> sent{b.recycler ? ` to ${b.recycler}` : ''}
                {b.merged_batches.length > 0 && <> (merged from {b.merged_batches.join(', ')})</>}.
            </Step>
            {b.received_at && (
                <Step title={`Received by ${b.recycler ?? 'the recycler'}`} at={b.received_at}>
                    Weighed <Quantity value={b.received_weight_kg} /> on arrival.
                </Step>
            )}
        </>
    );
}

function Step({ title, at, children }) {
    return (
        <li>
            <span className="absolute -left-[9px] mt-1.5 h-4 w-4 rounded-full border-2 border-white bg-green-600" aria-hidden="true" />
            <p className="font-medium text-gray-900">{title}</p>
            {at && <p className="text-xs text-gray-500"><DateTime value={at} withTime /></p>}
            <div className="mt-1 text-sm text-gray-700">{children}</div>
        </li>
    );
}
