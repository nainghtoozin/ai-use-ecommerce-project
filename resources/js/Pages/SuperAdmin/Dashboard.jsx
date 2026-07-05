import { Link, Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';

function StatCard({ label, value, icon, color, subtitle }) {
    const colors = {
        blue:    { bg: 'bg-blue-50', icon: 'text-blue-600', ring: 'ring-blue-100' },
        emerald: { bg: 'bg-emerald-50', icon: 'text-emerald-600', ring: 'ring-emerald-100' },
        amber:   { bg: 'bg-amber-50', icon: 'text-amber-600', ring: 'ring-amber-100' },
        red:     { bg: 'bg-red-50', icon: 'text-red-600', ring: 'ring-red-100' },
        violet:  { bg: 'bg-violet-50', icon: 'text-violet-600', ring: 'ring-violet-100' },
        slate:   { bg: 'bg-slate-50', icon: 'text-slate-600', ring: 'ring-slate-100' },
    };
    const c = colors[color] || colors.slate;
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow duration-200">
            <div className="flex items-center gap-4">
                <div className={`p-3 rounded-lg shrink-0 ${c.bg}`}>
                    <i className={`bi ${icon} text-xl ${c.icon}`}></i>
                </div>
                <div className="min-w-0">
                    <p className="text-2xl font-bold text-gray-900">{value}</p>
                    <p className="text-sm text-gray-500 mt-0.5">{label}</p>
                    {subtitle && <p className="text-xs text-gray-400 mt-0.5">{subtitle}</p>}
                </div>
            </div>
        </div>
    );
}

export default function SuperAdminDashboard({ tenantStats, totalSubscriptions, monthlyRevenue, yearlyRevenue, recentTenants, subscriptionsByPlan }) {
    const { platform_setting } = usePage().props;
    const pc = getPlatformCurrencyConfig(platform_setting);
    const formatMoney = (amount) => formatCurrency(amount, pc);
    const now = new Date();
    const monthName = now.toLocaleString('default', { month: 'long' });

    const statusColors = {
        active:    'bg-emerald-100 text-emerald-700',
        suspended: 'bg-yellow-100 text-yellow-700',
        expired:   'bg-red-100 text-red-700',
        trialing:  'bg-blue-100 text-blue-700',
        canceled:  'bg-gray-100 text-gray-700',
        past_due:  'bg-amber-100 text-amber-700',
    };

    return (
        <AdminLayout>
            <Head title="SuperAdmin Dashboard" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Platform Overview</h1>
                        <p className="text-sm text-gray-500 mt-1">SaaS management dashboard</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                    <StatCard label="Total Merchants" value={tenantStats.total} icon="bi-building" color="blue" subtitle="All registered stores" />
                    <StatCard label="Active Merchants" value={tenantStats.active} icon="bi-check-circle" color="emerald" subtitle="Active subscription" />
                    <StatCard label="Suspended Merchants" value={tenantStats.suspended} icon="bi-pause-circle" color="amber" subtitle="Manually suspended" />
                    <StatCard label="Expired Merchants" value={tenantStats.expired} icon="bi-x-circle" color="red" subtitle="Subscription expired" />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <StatCard label="Total Subscriptions" value={totalSubscriptions} icon="bi-credit-card" color="violet" subtitle="All time" />
                    <StatCard label={`Revenue (${monthName})`} value={formatMoney(monthlyRevenue)} icon="bi-graph-up-arrow" color="emerald" subtitle="Confirmed + delivered orders" />
                    <StatCard label={`Revenue (${now.getFullYear()})`} value={formatMoney(yearlyRevenue)} icon="bi-bar-chart-fill" color="blue" subtitle="Year-to-date" />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-700">Recent Merchants</h2>
                            <Link href="/superadmin/tenants" className="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                View All <i className="bi bi-arrow-right ml-1"></i>
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="text-left text-xs text-gray-500 uppercase tracking-wider">
                                        <th className="px-5 py-3 font-medium">Store</th>
                                        <th className="px-5 py-3 font-medium">Plan</th>
                                        <th className="px-5 py-3 font-medium">Status</th>
                                        <th className="px-5 py-3 font-medium">Users</th>
                                        <th className="px-5 py-3 font-medium">Created</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {recentTenants.map((t) => (
                                        <tr key={t.id} className="hover:bg-gray-50 transition-colors cursor-pointer" onClick={() => window.location.href = `/superadmin/tenants/${t.id}`}>
                                            <td className="px-5 py-3">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{t.name}</p>
                                                    <p className="text-xs text-gray-500">{t.slug}</p>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="text-sm text-gray-600">{t.plan_name || '—'}</span>
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[t.status] || 'bg-gray-100 text-gray-700'}`}>
                                                    {t.status}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="text-sm text-gray-600">{t.users_count}</span>
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="text-sm text-gray-500">{t.created_at}</span>
                                            </td>
                                        </tr>
                                    ))}
                                    {recentTenants.length === 0 && (
                                        <tr>
                                            <td colSpan="5" className="px-5 py-8 text-center text-sm text-gray-500">No merchants yet</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-700">Subscriptions by Plan</h2>
                        </div>
                        <div className="p-5 space-y-4">
                            {subscriptionsByPlan.map((plan) => {
                                const pct = totalSubscriptions > 0
                                    ? Math.round((plan.count / totalSubscriptions) * 100)
                                    : 0;
                                return (
                                    <div key={plan.name} className="space-y-1.5">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium text-gray-900">{plan.name}</span>
                                            <span className="text-gray-500">{plan.count} ({pct}%)</span>
                                        </div>
                                        <div className="w-full bg-gray-100 rounded-full h-2">
                                            <div
                                                className="h-2 rounded-full transition-all duration-500"
                                                style={{ width: `${Math.max(pct, 2)}%`, backgroundColor: '#3B82F6' }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                            {subscriptionsByPlan.length === 0 && (
                                <p className="text-sm text-gray-500 text-center py-4">No active subscriptions</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
