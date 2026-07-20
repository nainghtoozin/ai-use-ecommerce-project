const statusConfig = {
    draft: { label: 'Draft', classes: 'bg-gray-100 text-gray-600' },
    unpaid: { label: 'Unpaid', classes: 'bg-amber-100 text-amber-700' },
    paid: { label: 'Paid', classes: 'bg-emerald-100 text-emerald-700' },
    cancelled: { label: 'Cancelled', classes: 'bg-red-100 text-red-700' },
    refunded: { label: 'Refunded', classes: 'bg-purple-100 text-purple-700' },
};

export default function InvoiceBadge({ status, size = 'md' }) {
    const cfg = statusConfig[status] || statusConfig.draft;
    const sizeClasses = size === 'sm' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2.5 py-0.5 text-xs';
    return (
        <span className={`inline-flex items-center rounded-full font-semibold ${sizeClasses} ${cfg.classes}`}>
            {cfg.label}
        </span>
    );
}
