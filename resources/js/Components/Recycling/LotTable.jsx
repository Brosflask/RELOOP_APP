// Developer 3. Rows of material lots, used on the dashboard, processing record and lots pages.
import Quantity from '@/Components/UI/Quantity';
import StatusBadge from '@/Components/UI/StatusBadge';
import { Link } from '@inertiajs/react';

export default function LotTable({ lots }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th className="px-4 py-2">Lot</th>
                        <th className="px-4 py-2">Material</th>
                        <th className="px-4 py-2 text-right">Available</th>
                        <th className="px-4 py-2 text-right">Started with</th>
                        <th className="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {lots.map((lot) => (
                        <tr key={lot.id} className="hover:bg-gray-50">
                            <td className="whitespace-nowrap px-4 py-3">
                                <Link href={route('lots.show', lot.id)} className="font-medium text-green-700 hover:underline">{lot.code}</Link>
                                {lot.is_bought && <span className="ml-2 text-xs text-gray-500">bought</span>}
                            </td>
                            <td className="px-4 py-3 text-gray-700">
                                {lot.material_type_label}{lot.form ? `, ${lot.form}` : ''}{lot.grade ? ` (grade ${lot.grade})` : ''}
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-medium"><Quantity value={lot.available_quantity} unit={lot.unit} /></td>
                            <td className="whitespace-nowrap px-4 py-3 text-right text-gray-500"><Quantity value={lot.initial_quantity} unit={lot.unit} /></td>
                            <td className="px-4 py-3"><StatusBadge status={lot.status} label={lot.status_label} /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
