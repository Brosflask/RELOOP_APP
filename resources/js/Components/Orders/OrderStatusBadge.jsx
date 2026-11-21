const STYLES = {
    awaiting_payment: 'bg-amber-100 text-amber-800',
    paid: 'bg-sky-100 text-sky-800',
    shipped: 'bg-indigo-100 text-indigo-800',
    delivered: 'bg-teal-100 text-teal-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-700',
    disputed: 'bg-red-100 text-red-800',
    // offer and payment statuses share the badge
    pending: 'bg-amber-100 text-amber-800',
    accepted: 'bg-green-100 text-green-800',
    successful: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    failed: 'bg-red-100 text-red-800',
    countered: 'bg-sky-100 text-sky-800',
    withdrawn: 'bg-gray-200 text-gray-700',
    expired: 'bg-gray-200 text-gray-700',
    reversed: 'bg-gray-200 text-gray-700',
};

export default function OrderStatusBadge({ status, label }) {
    const text = label ?? status.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase());
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STYLES[status] ?? 'bg-gray-100 text-gray-700'}`}>
            {text}
        </span>
    );
}
