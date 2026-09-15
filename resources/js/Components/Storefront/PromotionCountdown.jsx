import { useState, useEffect } from 'react';

function remainingParts(targetMs) {
    const total = Math.max(0, Math.floor((targetMs - Date.now()) / 1000));
    return {
        total,
        d: Math.floor(total / 86400),
        h: Math.floor((total % 86400) / 3600),
        m: Math.floor((total % 3600) / 60),
        s: total % 60,
    };
}

export default function PromotionCountdown({ startsAt, endsAt, variant = 'text' }) {
    const [, setTick] = useState(0);

    useEffect(() => {
        if (!startsAt && !endsAt) return undefined;
        const id = setInterval(() => setTick((t) => t + 1), 1000);
        return () => clearInterval(id);
    }, [startsAt, endsAt]);

    if (!startsAt && !endsAt) return null;

    const startMs = startsAt ? new Date(startsAt).getTime() : null;
    const endMs = endsAt ? new Date(endsAt).getTime() : null;
    if ((startMs !== null && Number.isNaN(startMs)) || (endMs !== null && Number.isNaN(endMs))) return null;

    const now = Date.now();
    let mode = null;
    let target = null;
    if (startMs !== null && now < startMs) {
        mode = 'starts';
        target = startMs;
    } else if (endMs === null || now <= endMs) {
        mode = 'ends';
        target = endMs;
    } else {
        return null;
    }
    if (target === null) return null;

    const { total, d, h, m, s } = remainingParts(target);
    if (total <= 0) return null;

    const pad = (n) => String(n).padStart(2, '0');

    if (variant === 'boxes') {
        const units = [];
        if (d > 0) units.push([pad(d), 'Days']);
        units.push([pad(h), 'Hrs'], [pad(m), 'Min']);
        if (total < 3600) units.push([pad(s), 'Sec']);
        return (
            <div className="mt-2">
                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                    {mode === 'starts' ? 'Starts in' : 'Ends in'}
                </p>
                <div className="mt-1 flex flex-wrap gap-1">
                    {units.map(([value, label]) => (
                        <div key={label} className="min-w-[44px] px-1.5 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-center">
                            <span className="block text-[15px] font-extrabold tabular-nums leading-none text-gray-900 dark:text-gray-100">{value}</span>
                            <span className="mt-0.5 block text-[8px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</span>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    const parts = [];
    if (d > 0) parts.push(`${pad(d)}d`);
    parts.push(`${pad(h)}h`, `${pad(m)}m`);
    if (total < 3600) parts.push(`${pad(s)}s`);

    return (
        <p className="mt-1 text-[11px] font-medium text-gray-500 dark:text-gray-400">
            {mode === 'starts' ? 'Starts in' : 'Ends in'}{' '}
            <span className="font-semibold tabular-nums text-gray-700 dark:text-gray-300">{parts.join(' ')}</span>
        </p>
    );
}
