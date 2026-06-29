export default function UsageProgressBar({ label, current, limit, isUnlimited, format }) {
    const displayCurrent = typeof current === 'number' ? current.toLocaleString() : current;
    const displayLimit = limit !== null && limit !== undefined ? limit.toLocaleString() : 'Unlimited';

    const percent = (limit !== null && limit > 0) ? Math.round((current / limit) * 100) : 0;

    const colorClass = percent >= 90 ? 'bg-red-500' : percent >= 70 ? 'bg-amber-500' : 'bg-blue-500';
    const textColor = percent >= 90 ? 'text-red-600' : percent >= 70 ? 'text-amber-600' : 'text-gray-700';

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">{label}</span>
                <span className={`font-medium ${textColor}`}>
                    {displayCurrent}{' '}
                    <span className="text-gray-400 font-normal">
                        / {displayLimit}
                    </span>
                </span>
            </div>
            <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100} aria-label={`${label}: ${current} of ${limit ?? 'unlimited'}`}>
                {isUnlimited ? (
                    <div className="h-full w-full bg-gradient-to-r from-blue-400 to-blue-300 rounded-full opacity-50" />
                ) : (
                    <div
                        className={`h-full rounded-full transition-all duration-500 ${colorClass}`}
                        style={{ width: `${Math.min(percent, 100)}%` }}
                    />
                )}
            </div>
        </div>
    );
}
