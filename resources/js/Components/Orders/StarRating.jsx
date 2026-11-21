export default function StarRating({ value = 0, onChange, size = 'text-xl' }) {
    return (
        <div className={`inline-flex gap-0.5 ${size}`} role={onChange ? 'radiogroup' : undefined} aria-label={`${value} out of 5`}>
            {[1, 2, 3, 4, 5].map((n) => (
                onChange ? (
                    <button key={n} type="button" role="radio" aria-checked={value === n} aria-label={`${n} star${n > 1 ? 's' : ''}`}
                        onClick={() => onChange(n)} className={n <= value ? 'text-yellow-500' : 'text-gray-300'}>★</button>
                ) : (
                    <span key={n} aria-hidden className={n <= Math.round(value) ? 'text-yellow-500' : 'text-gray-300'}>★</span>
                )
            ))}
        </div>
    );
}
