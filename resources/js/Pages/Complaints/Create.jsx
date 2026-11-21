// Developer 4. Uses Developer 1's AuthenticatedLayout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ComplaintsCreate({ order, categories }) {
    const form = useForm({ order_id: order?.id ?? '', category: categories[0]?.value ?? '', subject: '', description: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('complaints.store'));
    };
    const field = 'mt-1 w-full rounded-md border-gray-300 shadow-sm';

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Raise a complaint</h2>}>
            <Head title="Raise a complaint" />
            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-4 px-4 py-6">
                <div className="space-y-4 rounded-lg bg-white p-5 shadow-sm">
                    {order && (
                        <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                            About order {order.code}. If it has been paid, it will be put on hold until an admin reviews your complaint.
                        </p>
                    )}
                    <label className="block text-sm">
                        <span className="font-medium text-gray-700">Category</span>
                        <select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className={field}>
                            {categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="font-medium text-gray-700">Subject</span>
                        <input type="text" maxLength={150} value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} className={field} required />
                        {form.errors.subject && <p className="mt-1 text-xs text-red-600">{form.errors.subject}</p>}
                    </label>
                    <label className="block text-sm">
                        <span className="font-medium text-gray-700">What happened?</span>
                        <textarea rows={6} maxLength={5000} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className={field} required />
                        {form.errors.description && <p className="mt-1 text-xs text-red-600">{form.errors.description}</p>}
                    </label>
                    {form.errors.order_id && <p className="text-sm text-red-600">{form.errors.order_id}</p>}
                    <button type="submit" disabled={form.processing} className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                        Submit complaint
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
