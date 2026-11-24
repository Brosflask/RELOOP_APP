// Developer 2. "25 Nov 2026, morning" or "Any day".
import { formatDate } from '@/lib/format';

export default function PickupWhen({ date, slot }) {
    if (!date && !slot) return <span className="text-gray-500">Any day</span>;
    return <span>{[date ? formatDate(date) : 'Any day', slot].filter(Boolean).join(', ')}</span>;
}
