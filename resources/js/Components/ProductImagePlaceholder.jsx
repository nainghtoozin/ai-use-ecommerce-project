export default function ProductImagePlaceholder({ className = '', compact = false }) {
    if (compact) {
        return (
            <div className={`flex items-center justify-center ${className}`}>
                <svg className="w-5 h-5 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
        );
    }

    return (
        <div className={`flex flex-col items-center justify-center ${className}`}>
            <div className="w-14 h-14 rounded-full flex items-center justify-center mb-2" style={{ backgroundColor: 'rgba(var(--theme-color-rgb, 59, 130, 246), 0.08)' }}>
                <svg className="w-7 h-7" style={{ color: 'var(--theme-color, #3B82F6)' }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <p className="text-xs text-gray-400 dark:text-gray-500 font-medium">No image available</p>
        </div>
    );
}
