export default function Money({ amount, currency = 'GHS', className = '' }) {
    const value = Number(amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return <span className={className}>{currency} {value}</span>;
}
