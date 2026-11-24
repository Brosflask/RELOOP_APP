// Developer 2. Small list of plastic types with optional weights.
import { formatQuantity } from '@/lib/format';

export default function MaterialChips({ items = [], weightKey = 'estimated_weight_kg', prefix = '' }) {
    if (items.length === 0) return <span className="text-sm text-gray-400">—</span>;
    return (
        <ul className="flex flex-wrap gap-1.5">
            {items.map((item, i) => (
                <li key={`${item.material_type}-${i}`} className="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-800">
                    {item.material_label}
                    {item[weightKey] ? ` · ${prefix}${formatQuantity(item[weightKey])}` : ''}
                </li>
            ))}
        </ul>
    );
}
