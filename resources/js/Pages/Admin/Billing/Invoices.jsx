import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import InvoiceBadge from '@/Components/Billing/InvoiceBadge';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { Search, X, Filter, FileText, CreditCard, Check, XCircle, AlertCircle, Eye, Download, ArrowUp } from 'lucide-react';

function StatCard({ icon: Icon, label, value, color }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
            <div className={`w-12 h-12 rounded-xl ${color.bg} flex items-center justify-center flex-shrink-0`}>
                <Icon className={`w-6 h-6 ${color.text}`} />
            </div>
            <div>
                <p className="text-2xl font-bold text-gray-900">{value}</p>
                <p className="text-sm text-gray-500">{label}</p>
            </div>
        </div>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="flex items-center justify-between pt-6">
            <p className="text-sm text-gray-500">Page {links.find(l => l.active)?.label || '—'}</p>
            <div className="flex gap-1">
                {links.map((link, i) => {
                    if (!link.url) {
                        return (
                            <span key={i} className="px-3 py-1.5 text-sm text-gray-400 rounded-md cursor-not-allowed">
                                {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                            </span>
                        );
                    }
                    return (
                        <button
                            key={i}
                            onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`px-3 py-1.5 text-sm rounded-md transition-colors ${
                                link.active ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

export default function AdminBillingInvoices({ invoices, filters, plans, stats }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const [showFilters, setShowFilters] = useState(false);
    const [searchValue, setSearchValue] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');

    const hasActiveFilters = filters?.status || filters?.date_from || filters?.date_to || filters?.search;

    const applyFilters = () => {
        router.get(adminUrl('/admin/billing/invoices'), {
            status: statusFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            search: searchValue || undefined,
        }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setSearchValue('');
        setStatusFilter('');
        setDateFrom('');
        setDateTo('');
        router.get(adminUrl('/admin/billing/invoices'), {}, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const data = invoices?.data || [];
    const paginationLinks = invoices?.links || [];

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    return (
        <AdminLayout>
            <Head title="Invoices" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Invoices</h1>
                        <p className="text-sm text-gray-500 mt-1">View and download subscription invoices</p>
                    </div>
                    <button
                        onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                        className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2"
                    >
                        <ArrowUp className="w-4 h-4" />
                        Upgrade Plan
                    </button>
                </div>

                {stats && (
                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <StatCard icon={FileText} label="Total Invoices" value={stats.total || 0} color={{ bg: 'bg-blue-100', text: 'text-blue-600' }} />
                        <StatCard icon={Check} label="Paid" value={stats.paid || 0} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} />
                        <StatCard icon={AlertCircle} label="Unpaid" value={stats.unpaid || 0} color={{ bg: 'bg-amber-100', text: 'text-amber-600' }} />
                        <StatCard icon={XCircle} label="Cancelled" value={stats.cancelled || 0} color={{ bg: 'bg-red-100', text: 'text-red-600' }} />
                        <StatCard icon={CreditCard} label="Total Amount" value={formatCurrency(stats.total_amount || 0, pc)} color={{ bg: 'bg-gray-100', text: 'text-gray-600' }} />
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                        <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    type="text"
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                    placeholder="Search by invoice number or plan..."
                                    className="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    aria-label="Search invoices"
                                />
                            </div>
                            <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Search</button>
                        </form>
                        <button
                            onClick={() => setShowFilters(!showFilters)}
                            className={`px-4 py-2 text-sm font-medium rounded-lg border transition-colors flex items-center gap-2 ${
                                showFilters || hasActiveFilters ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            <Filter className="w-4 h-4" />
                            Filters
                            {hasActiveFilters && <span className="w-2 h-2 rounded-full bg-blue-500" />}
                        </button>
                        {hasActiveFilters && (
                            <button onClick={clearFilters} className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                <X className="w-3.5 h-3.5" /> Clear
                            </button>
                        )}
                    </div>

                    {showFilters && (
                        <div className="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                    <select
                                        value={statusFilter}
                                        onChange={(e) => setStatusFilter(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                        aria-label="Filter by status"
                                    >
                                        <option value="">All Statuses</option>
                                        <option value="draft">Draft</option>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="paid">Paid</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                                    <input
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) => setDateFrom(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        aria-label="Date from"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                                    <input
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) => setDateTo(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        aria-label="Date to"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <button onClick={applyFilters} className="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    )}

                    {data.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Issued</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {data.map((inv) => (
                                        <tr key={inv.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-mono font-semibold text-gray-900">{inv.invoice_number}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-medium text-gray-900">{inv.plan?.name || '—'}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDate(inv.billing_period_start)} — {formatDate(inv.billing_period_end)}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-semibold text-gray-900">{formatCurrency(inv.total, pc)}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDate(inv.issued_at)}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <InvoiceBadge status={inv.status} size="sm" />
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button
                                                        onClick={() => router.get(adminUrl(`/admin/billing/invoices/${inv.id}`))}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                        aria-label={`View invoice ${inv.invoice_number}`}
                                                    >
                                                        <Eye className="w-3.5 h-3.5" />
                                                        View
                                                    </button>
                                                    <a
                                                        href={adminUrl(`/admin/billing/invoices/${inv.id}/download`)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                                        aria-label={`Download invoice ${inv.invoice_number}`}
                                                    >
                                                        <Download className="w-3.5 h-3.5" />
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-8 text-center">
                            <div className="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <FileText className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-base font-semibold text-gray-900 mb-2">No Invoices Yet</h3>
                            <p className="text-sm text-gray-500 max-w-md mx-auto mb-6">
                                {hasActiveFilters
                                    ? 'No invoices match your current filters.'
                                    : 'Invoices will appear here after your subscription payments are processed.'}
                            </p>
                            {hasActiveFilters ? (
                                <button onClick={clearFilters} className="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Clear Filters</button>
                            ) : (
                                <button onClick={() => router.get(adminUrl('/admin/billing/upgrade'))} className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">Upgrade Plan</button>
                            )}
                        </div>
                    )}

                    {data.length > 0 && (
                        <div className="px-5 py-4 border-t border-gray-100">
                            <Pagination links={paginationLinks} />
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
