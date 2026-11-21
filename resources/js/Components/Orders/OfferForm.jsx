import { useForm } from '@inertiajs/react';

export default function OfferForm({ conversationId, parentOfferId = null, defaults = {}, onDone }) {
    const form = useForm({
        unit_price: defaults.unit_price ?? '',
        quantity: defaults.quantity ?? '',
        message: '',
        parent_offer_id: parentOfferId,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('messages.offers.store', conversationId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone?.();
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label className="block text-sm">
                    <span className="text-gray-700">Price per unit (GHS)</span>
                    <input type="number" step="0.01" min="0.01" inputMode="decimal" value={form.data.unit_price}
                        onChange={(e) => form.setData('unit_price', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm" required />
                    {form.errors.unit_price && <p className="mt-1 text-xs text-red-600">{form.errors.unit_price}</p>}
                </label>
                <label className="block text-sm">
                    <span className="text-gray-700">Quantity</span>
                    <input type="number" step="0.001" min="0.001" inputMode="decimal" value={form.data.quantity}
                        onChange={(e) => form.setData('quantity', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm" required />
                    {form.errors.quantity && <p className="mt-1 text-xs text-red-600">{form.errors.quantity}</p>}
                </label>
            </div>
            <label className="block text-sm">
                <span className="text-gray-700">Note (optional)</span>
                <input type="text" maxLength={500} value={form.data.message}
                    onChange={(e) => form.setData('message', e.target.value)}
                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
            </label>
            {form.errors.offer && <p className="text-sm text-red-600">{form.errors.offer}</p>}
            <button type="submit" disabled={form.processing}
                className="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50">
                {parentOfferId ? 'Send counter-offer' : 'Send offer'}
            </button>
        </form>
    );
}
