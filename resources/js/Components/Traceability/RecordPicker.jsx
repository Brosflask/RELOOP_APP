// Developer 2. Tick collection records to put in a batch; shows the running total.
import DateTime from '@/Components/UI/DateTime';
import Quantity from '@/Components/UI/Quantity';

export default function RecordPicker({ records, selected, onChange }) {
    const all = records.length > 0 && selected.length === records.length;
    const toggle = (id) => onChange(selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id]);
    const total = records.filter((r) => selected.includes(r.id)).reduce((sum, r) => sum + Number(r.actual_weight_kg), 0);

    return (
        <div>
            <div className="flex items-center justify-between border-b border-gray-100 pb-2 text-sm">
                <label className="flex items-center gap-2 font-medium text-gray-700">
                    <input type="checkbox" className="rounded border-gray-300 text-green-700 focus:ring-green-600" checked={all}
                        onChange={() => onChange(all ? [] : records.map((r) => r.id))} />
                    Select all ({records.length})
                </label>
                <span>Selected: <strong><Quantity value={total} /></strong></span>
            </div>
            <ul className="max-h-96 divide-y divide-gray-100 overflow-y-auto">
                {records.map((r) => (
                    <li key={r.id}>
                        <label className="flex cursor-pointer items-center justify-between gap-3 py-2 text-sm hover:bg-gray-50">
                            <span className="flex items-center gap-2">
                                <input type="checkbox" className="rounded border-gray-300 text-green-700 focus:ring-green-600"
                                    checked={selected.includes(r.id)} onChange={() => toggle(r.id)} />
                                <span className="font-medium">{r.reference}</span>
                                <span className="text-gray-500"><DateTime value={r.collected_at} /></span>
                                {!r.collection_request_id && <span className="text-xs text-gray-400">drop-off</span>}
                            </span>
                            <Quantity value={r.actual_weight_kg} />
                        </label>
                    </li>
                ))}
            </ul>
        </div>
    );
}
