const statusConfig = {
    draft: { label: 'Draft', classes: 'bg-gray-100 text-gray-600' },
    pending: { label: 'Pending', classes: 'bg-blue-100 text-blue-700' },
    waiting_payment: { label: 'Waiting Payment', classes: 'bg-amber-100 text-amber-700' },
    waiting_review: { label: 'Waiting Review', classes: 'bg-purple-100 text-purple-700' },
    approved: { label: 'Approved', classes: 'bg-emerald-100 text-emerald-700' },
    paid: { label: 'Paid', classes: 'bg-emerald-100 text-emerald-700' },
    completed: { label: 'Completed', classes: 'bg-green-100 text-green-700' },
    rejected: { label: 'Rejected', classes: 'bg-red-100 text-red-700' },
    cancelled: { label: 'Cancelled', classes: 'bg-gray-100 text-gray-600' },
    expired: { label: 'Expired', classes: 'bg-gray-100 text-gray-600' },
    failed: { label: 'Failed', classes: 'bg-red-100 text-red-700' },
};

export default function PaymentIntentBadge({ status, size = 'md' }) {
    const cfg = statusConfig[status] || statusConfig.draft;
    const sizeClasses = size === 'sm' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2.5 py-0.5 text-xs';
    return (
        <span className={`inline-flex items-center rounded-full font-semibold ${sizeClasses} ${cfg.classes}`}>
            {cfg.label}
        </span>
    );
}
