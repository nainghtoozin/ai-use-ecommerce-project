import { usePage } from '@inertiajs/react';

interface PerPageSelectProps {
    perPageOptions?: number[];
    defaultPerPage?: number;
    total?: number;
    showTotal?: boolean;
}

export default function PerPageSelect({ 
    perPageOptions = [10, 25, 50, 100, 500],
    defaultPerPage = 10,
    total,
    showTotal = false
}: PerPageSelectProps) {
    const { url } = usePage();
    
    const getCurrentPerPage = () => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        return params.get('per_page') || String(defaultPerPage);
    };

    const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const value = e.target.value;
        const params = new URLSearchParams(url.split('?')[1] || '');
        params.set('per_page', value);
        
        const queryString = params.toString();
        window.location.href = `${window.location.pathname}${queryString ? '?' + queryString : ''}`;
    };

    const currentPerPage = getCurrentPerPage();

    return (
        <div className="flex items-center gap-3">
            <span className="text-sm text-gray-600 whitespace-nowrap">Rows per page</span>
            <div className="relative">
                <select
                    value={currentPerPage}
                    onChange={handleChange}
                    className="block w-full border-gray-300 rounded-lg py-1.5 pl-3 pr-8 text-sm font-medium text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer hover:border-gray-400 transition-colors"
                    style={{
                        appearance: 'none',
                        WebkitAppearance: 'none',
                        MozAppearance: 'none',
                    }}
                >
                    {perPageOptions.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                    <option value="all">All</option>
                </select>
                <div className="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                    <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
            {showTotal && total !== undefined && (
                <span className="text-sm text-gray-500 ml-1">• {total.toLocaleString()} total</span>
            )}
        </div>
    );
}