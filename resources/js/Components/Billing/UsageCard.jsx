export default function UsageCard({ label, current, limit, isUnlimited, format }) {
    const displayCurrent = format ? format(current) : (typeof current === 'number' ? current.toLocaleString() : current);
    const displayLimit = format ? format(limit) : (limit !== null && limit !== undefined ? limit.toLocaleString() : '∞');
    const percent = (limit !== null && limit > 0) ? Math.round((current / limit) * 100) : 0;
    const remaining = (limit !== null && limit > 0) ? Math.max(0, limit - current) : null;

    const barColor = percent >= 90 ? 'bg-red-500' : percent >= 70 ? 'bg-amber-500' : 'bg-blue-500';
    const bgColor = percent >= 90 ? 'bg-red-50' : percent >= 70 ? 'bg-amber-50' : 'bg-blue-50';
    const textColor = percent >= 90 ? 'text-red-700' : percent >= 70 ? 'text-amber-700' : 'text-gray-700';

    return (
        <div className={`rounded-xl border p-5 ${percent >= 90 ? 'border-red-200' : percent >= 70 ? 'border-amber-200' : 'border-gray-200'} ${bgColor}`}>
            <div className="flex items-center justify-between mb-3">
                <span className="text-sm font-semibold text-gray-800">{label}</span>
                <span className={`text-sm font-bold ${textColor}`}>
                    {displayCurrent}
                    <span className="text-gray-400 font-normal"> / {displayLimit}</span>
                </span>
            </div>
            <div className="w-full h-2.5 bg-white rounded-full overflow-hidden" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100} aria-label={`${label}: ${current} of ${limit ?? 'unlimited'}`}>
                {isUnlimited ? (
                    <div className="h-full w-full bg-gradient-to-r from-blue-300 to-blue-200 rounded-full opacity-60" />
                ) : (
                    <div className={`h-full rounded-full transition-all duration-500 ${barColor}`} style={{ width: `${Math.min(percent, 100)}%` }} />
                )}
            </div>
            <div className="mt-2 flex items-center justify-between">
                {isUnlimited ? (
                    <span className="text-xs text-blue-600 font-medium">Unlimited</span>
                ) : remaining !== null ? (
                    <span className={`text-xs font-medium ${percent >= 70 ? textColor : 'text-gray-500'}`}>
                        {remaining} remaining
                    </span>
                ) : null}
                {!isUnlimited && limit > 0 && (
                    <span className="text-xs text-gray-400">{percent}%</span>
                )}
            </div>
        </div>
    );
}
