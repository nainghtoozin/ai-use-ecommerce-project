const variants = {
    active:    { label: 'Active',    bg: 'bg-emerald-50', text: 'text-emerald-700', dot: 'bg-emerald-500' },
    trialing:  { label: 'Trial',     bg: 'bg-blue-50',    text: 'text-blue-700',    dot: 'bg-blue-500' },
    past_due:  { label: 'Past Due',  bg: 'bg-amber-50',   text: 'text-amber-700',   dot: 'bg-amber-500' },
    expired:   { label: 'Expired',   bg: 'bg-red-50',     text: 'text-red-700',     dot: 'bg-red-500' },
    canceled:  { label: 'Canceled',  bg: 'bg-gray-50',    text: 'text-gray-600',    dot: 'bg-gray-400' },
    suspended: { label: 'Suspended', bg: 'bg-yellow-50',  text: 'text-yellow-700',  dot: 'bg-yellow-500' },
};

export default function StatusBadge({ status, size = 'md' }) {
    const cfg = variants[status] || variants.expired;
    const sizeClasses = size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-0.5 text-xs';

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full font-semibold ${sizeClasses} ${cfg.bg} ${cfg.text}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot}`} />
            {cfg.label}
        </span>
    );
}
