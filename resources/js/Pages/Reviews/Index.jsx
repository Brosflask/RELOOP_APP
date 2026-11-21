// Developer 4. Public reviews page. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StarRating from '@/Components/Orders/StarRating';
import { Head, Link } from '@inertiajs/react';

export default function ReviewsIndex({ user, average_rating, review_count, reviews }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Reviews for {user.name}</h2>}>
            <Head title={`Reviews for ${user.name}`} />
            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6">
                <div className="flex items-center gap-3 rounded-lg bg-white p-5 shadow-sm">
                    {average_rating ? (
                        <>
                            <StarRating value={average_rating} />
                            <p className="text-gray-700">{average_rating} out of 5 · {review_count} review{review_count === 1 ? '' : 's'}</p>
                        </>
                    ) : <p className="text-gray-600">No reviews yet.</p>}
                </div>
                <ul className="space-y-3">
                    {reviews.data.map((r) => (
                        <li key={r.id} className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="flex items-center justify-between">
                                <StarRating value={r.rating} size="text-base" />
                                <span className="text-xs text-gray-500">{new Date(r.created_at).toLocaleDateString()}</span>
                            </div>
                            {r.comment && <p className="mt-2 text-sm text-gray-800">{r.comment}</p>}
                            <p className="mt-1 text-xs text-gray-500">by {r.reviewer.name}</p>
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
