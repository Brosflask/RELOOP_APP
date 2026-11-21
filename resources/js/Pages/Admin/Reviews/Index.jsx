// Developer 4. Review moderation.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StarRating from '@/Components/Orders/StarRating';
import { Head, Link, router } from '@inertiajs/react';

export default function AdminReviews({ reviews, filters }) {
    const toggle = (r) => router.patch(route('admin.reviews.update', r.id), { is_hidden: !r.is_hidden }, { preserveScroll: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Reviews</h2>}>
            <Head title="Reviews" />
            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={filters.low} onChange={(e) => router.get(route('admin.reviews.index'), e.target.checked ? { low: 1 } : {})} />
                    Only 1–2 star reviews
                </label>
                <ul className="space-y-3">
                    {reviews.data.map((r) => (
                        <li key={r.id} className={`rounded-lg p-4 shadow-sm ${r.is_hidden ? 'bg-gray-100' : 'bg-white'}`}>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <StarRating value={r.rating} size="text-base" />
                                <button onClick={() => toggle(r)} className="rounded-md border border-gray-300 px-3 py-1 text-sm">{r.is_hidden ? 'Show' : 'Hide'}</button>
                            </div>
                            {r.comment && <p className="mt-2 text-sm text-gray-800">{r.comment}</p>}
                            <p className="mt-1 text-xs text-gray-500">{r.reviewer} reviewed {r.reviewee} · Order {r.order}{r.is_hidden ? ' · hidden' : ''}</p>
                        </li>
                    ))}
                </ul>
                <div className="flex justify-between text-sm">
                    {reviews.prev_page_url ? <Link href={reviews.prev_page_url} className="text-green-700">Newer</Link> : <span />}
                    {reviews.next_page_url && <Link href={reviews.next_page_url} className="text-green-700">Older</Link>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
