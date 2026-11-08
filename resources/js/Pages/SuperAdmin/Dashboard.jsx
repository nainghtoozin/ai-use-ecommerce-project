import { useState } from 'react';
import { Link, Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import {
    LayoutDashboard, Building2, CreditCard, Clock, CheckCircle2, XCircle,
    AlertTriangle, TrendingUp, Users, Activity, Eye, ChevronRight,
    Calendar, Filter, X,
} from 'lucide-react';

function StatCard({ label, value, icon: Icon, color, href }) {
    const content = (
        <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4 flex items-center gap-3 hover:shadow-sm transition-shadow">
            <div className={`w-9 h-9 rounded-lg ${color.bg} flex items-center justify-center flex-shrink-0`}>
                <Icon className={`w-4 h-4 ${color.text}`} />
            </div>
            <div className="min-w-0">
                <p className="text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight">{value}</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{label}</p>
            </div>
        </div>
    );
    if (href) {
        return <Link href={href} className="block">{content}</Link>;
    }
    return content;
}

function SectionCard({ title, children, action }) {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
            <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300">{title}</h2>
                {action}
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function RevenueChart({ series }) {
    const hasData = series && series.some(s => (s.value || 0) > 0);
    if (!series || series.length === 0 || !hasData) {
        return (
            <div className="h-[280px] flex items-center justify-center">
                <div className="text-center">
                    <TrendingUp className="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                    <p className="text-sm text-gray-400 dark:text-gray-500">No revenue recorded for this period.</p>
                </div>
            </div>
        );
    }
    const max = Math.max(...series.map(s => s.value), 1);
    const width = 720;
    const height = 240;
    const barWidth = Math.max(4, Math.floor(width / series.length) - 4);
    const showLabels = series.length <= 15;
    return (
        <div className="w-full" style={{ height: 280 }}>
            <svg viewBox={`0 0 ${width} ${height + 28}`} className="w-full h-full" preserveAspectRatio="xMidYMid meet">
                {series.map((s, i) => {
                    const h = Math.max(2, (s.value / max) * (height - 12));
                    const x = i * (width / series.length) + (width / series.length - barWidth) / 2;
                    const y = height - h;
                    return (
                        <g key={i}>
                            <rect x={x} y={y} width={barWidth} height={h} rx="3" fill="#3B82F6" opacity="0.9">
                                <title>{`${s.label}: ${s.value}`}</title>
                            </rect>
                        </g>
                    );
                })}
                {showLabels && series.map((s, i) => (
                    <text key={`l${i}`} x={i * (width / series.length) + (width / series.length) / 2} y={height + 18} textAnchor="middle" fontSize="9" fill="#94a3b8">
                        {s.label}
                    </text>
                ))}
            </svg>
        </div>
    );
}

export default function SuperAdminDashboard({
    tenantStats, subscriptionStats, paymentStats, monthlyRevenue, yearlyRevenue,
    rangeRevenue, start_date, end_date, revenueSeries, recentTenants, recentPayments,
    recentActivity, subscriptionsByPlan, pendingActivationCount, pendingPaymentCount,
}) {
    const { platform_setting } = usePage().props;
    const pc = getPlatformCurrencyConfig(platform_setting);
    const fmt = (a) => formatCurrency(a, pc);
    const [startDate, setStartDate] = useState(start_date);
    const [endDate, setEndDate] = useState(end_date);

    const applyRange = () => {
        router.get('/superadmin', { start_date: startDate, end_date: endDate }, { preserveState: true, replace: true });
    };
    const clearRange = () => {
        const today = new Date();
        const first = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
        setStartDate(first);
        setEndDate(today.toISOString().slice(0, 10));
        router.get('/superadmin', {}, { preserveState: true, replace: true });
    };
    const preset = (days) => {
        const end = new Date();
        const start = new Date();
        if (days === 'month') {
            start.setDate(1);
        } else if (days === 'year') {
            start.setMonth(0, 1);
        } else {
            start.setDate(start.getDate() - days);
        }
        setStartDate(start.toISOString().slice(0, 10));
        setEndDate(end.toISOString().slice(0, 10));
    };

    const statusColors = {
        active: 'bg-emerald-100 text-emerald-700',
        suspended: 'bg-amber-100 text-amber-700',
        expired: 'bg-red-100 text-red-700',
        trialing: 'bg-blue-100 text-blue-700',
        pending: 'bg-gray-100 text-gray-700',
        past_due: 'bg-orange-100 text-orange-700',
        canceled: 'bg-gray-100 text-gray-600',
        waiting_review: 'bg-purple-100 text-purple-700',
    };

    const totalSubs = subscriptionStats?.total || 0;

    return (
        <AdminLayout>
            <Head title="SuperAdmin Dashboard" />

            <div className="p-4 lg:p-6 space-y-4 bg-slate-50 dark:bg-gray-900 min-h-full rounded-lg">
                {/* Title + date filter */}
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">Platform Overview</h1>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">SaaS management dashboard</p>
                    </div>
                    <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                        <div className="flex flex-wrap gap-1">
                            {[
                                { label: 'Today', fn: () => preset(0) },
                                { label: '7D', fn: () => preset(7) },
                                { label: '30D', fn: () => preset(30) },
                                { label: 'Month', fn: () => preset('month') },
                                { label: 'Year', fn: () => preset('year') },
                            ].map(b => (
                                <button key={b.label} onClick={b.fn} className="px-3 py-1.5 text-xs font-medium bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800 rounded-md hover:bg-gray-50 dark:bg-gray-950 transition-colors">
                                    {b.label}
                                </button>
                            ))}
                        </div>
                        <div className="flex items-center gap-1.5 flex-wrap">
                            <Calendar className="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className="px-2.5 py-1.5 text-xs border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-900" />
                            <span className="text-xs text-gray-400 dark:text-gray-500">to</span>
                            <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} className="px-2.5 py-1.5 text-xs border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-900" />
                            <button onClick={applyRange} className="px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Apply</button>
                            <button onClick={clearRange} className="px-2 py-1.5 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 transition-colors" title="Reset">
                                <X className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                {/* Action Required */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Link href="/superadmin/billing?status=waiting_review" className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-center justify-between hover:shadow-sm transition-shadow">
                        <div>
                            <p className="text-xs font-medium text-amber-700">Payments Awaiting Review</p>
                            <p className="text-xl font-bold text-amber-800">{pendingPaymentCount || 0}</p>
                        </div>
                        <AlertTriangle className="w-5 h-5 text-amber-500" />
                    </Link>
                    <Link href="/superadmin/tenants?status=pending" className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-center justify-between hover:shadow-sm transition-shadow">
                        <div>
                            <p className="text-xs font-medium text-amber-700">Stores Awaiting Activation</p>
                            <p className="text-xl font-bold text-amber-800">{pendingActivationCount || 0}</p>
                        </div>
                        <AlertTriangle className="w-5 h-5 text-amber-500" />
                    </Link>
                    <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 dark:text-gray-400">Range Revenue</p>
                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">{fmt(rangeRevenue)}</p>
                        </div>
                        <TrendingUp className="w-5 h-5 text-blue-500" />
                    </div>
                    <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 dark:text-gray-400">Revenue This Month</p>
                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">{fmt(monthlyRevenue)}</p>
                        </div>
                        <TrendingUp className="w-5 h-5 text-emerald-500" />
                    </div>
                </div>

                {/* Platform KPIs */}
                <SectionCard title="Platform Overview (All Time)">
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <StatCard label="Total Merchants" value={tenantStats.total} icon={Building2} color={{ bg: 'bg-blue-50', text: 'text-blue-600' }} href="/superadmin/tenants" />
                        <StatCard label="Active" value={tenantStats.active} icon={CheckCircle2} color={{ bg: 'bg-emerald-50', text: 'text-emerald-600' }} href="/superadmin/tenants?status=active" />
                        <StatCard label="Trialing" value={tenantStats.trialing} icon={Clock} color={{ bg: 'bg-blue-50', text: 'text-blue-600' }} href="/superadmin/tenants?status=trialing" />
                        <StatCard label="Suspended" value={tenantStats.suspended} icon={AlertTriangle} color={{ bg: 'bg-amber-50', text: 'text-amber-600' }} href="/superadmin/tenants?status=suspended" />
                        <StatCard label="Expired" value={tenantStats.expired} icon={XCircle} color={{ bg: 'bg-red-50', text: 'text-red-600' }} href="/superadmin/tenants?status=expired" />
                        <StatCard label="Pending Activation" value={tenantStats.pending} icon={Clock} color={{ bg: 'bg-gray-100', text: 'text-gray-600' }} href="/superadmin/tenants?status=pending" />
                    </div>
                </SectionCard>

                {/* Subscription KPIs */}
                <SectionCard title="Subscription Overview (All Time)">
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <StatCard label="Total" value={subscriptionStats.total} icon={CreditCard} color={{ bg: 'bg-violet-50', text: 'text-violet-600' }} href="/superadmin/subscriptions" />
                        <StatCard label="Active" value={subscriptionStats.active} icon={CheckCircle2} color={{ bg: 'bg-emerald-50', text: 'text-emerald-600' }} href="/superadmin/subscriptions?status=active" />
                        <StatCard label="Trialing" value={subscriptionStats.trialing} icon={Clock} color={{ bg: 'bg-blue-50', text: 'text-blue-600' }} href="/superadmin/subscriptions?status=trialing" />
                        <StatCard label="Past Due" value={subscriptionStats.past_due} icon={AlertTriangle} color={{ bg: 'bg-orange-50', text: 'text-orange-600' }} href="/superadmin/subscriptions?status=past_due" />
                        <StatCard label="Expired" value={subscriptionStats.expired} icon={XCircle} color={{ bg: 'bg-red-50', text: 'text-red-600' }} href="/superadmin/subscriptions?status=expired" />
                        <StatCard label="Canceled" value={subscriptionStats.canceled} icon={XCircle} color={{ bg: 'bg-gray-100', text: 'text-gray-600' }} href="/superadmin/subscriptions?status=canceled" />
                    </div>
                </SectionCard>

                {/* Payment Overview */}
                <SectionCard title="Payment Overview (All Time)">
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <StatCard label="Pending Review" value={paymentStats.pending} icon={Clock} color={{ bg: 'bg-purple-50', text: 'text-purple-600' }} href="/superadmin/billing?status=waiting_review" />
                        <StatCard label="Approved" value={paymentStats.approved} icon={CheckCircle2} color={{ bg: 'bg-emerald-50', text: 'text-emerald-600' }} href="/superadmin/billing?status=approved" />
                        <StatCard label="Rejected" value={paymentStats.rejected} icon={XCircle} color={{ bg: 'bg-red-50', text: 'text-red-600' }} href="/superadmin/billing?status=rejected" />
                        <StatCard label="Completed" value={paymentStats.completed} icon={CreditCard} color={{ bg: 'bg-blue-50', text: 'text-blue-600' }} href="/superadmin/billing?status=completed" />
                    </div>
                </SectionCard>

                {/* Revenue */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <SectionCard title={`Revenue Overview`} action={<span className="text-xs text-gray-400 dark:text-gray-500">{new Date(start_date).toLocaleDateString()} – {new Date(end_date).toLocaleDateString()}</span>}>
                            <RevenueChart series={revenueSeries} />
                            <div className="mt-3 grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Selected Period</p>
                                    <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{fmt(rangeRevenue)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">This Month</p>
                                    <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{fmt(monthlyRevenue)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">This Year</p>
                                    <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{fmt(yearlyRevenue)}</p>
                                </div>
                            </div>
                        </SectionCard>
                    </div>
                    <SectionCard title="Subscriptions by Plan">
                        <div className="space-y-2">
                            {subscriptionsByPlan && subscriptionsByPlan.length > 0 ? subscriptionsByPlan.map(p => {
                                const pct = totalSubs > 0 ? Math.round((p.count / totalSubs) * 100) : 0;
                                return (
                                    <div key={p.name}>
                                        <div className="flex justify-between text-xs mb-1">
                                            <span className="font-medium text-gray-700 dark:text-gray-300 truncate">{p.name}</span>
                                            <span className="text-gray-500 dark:text-gray-400">{p.count} ({pct}%)</span>
                                        </div>
                                        <div className="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                                            <div className="h-1.5 rounded-full bg-blue-500" style={{ width: `${Math.max(pct, 2)}%` }} />
                                        </div>
                                    </div>
                                );
                            }) : <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-4">No data</p>}
                        </div>
                    </SectionCard>
                </div>

                {/* Recent Payments */}
                <SectionCard title="Recent Payments" action={<Link href="/superadmin/billing" className="text-xs text-blue-600 hover:text-blue-700 font-medium">View All</Link>}>
                    {recentPayments && recentPayments.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead className="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Reference</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Merchant</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {recentPayments.map(p => (
                                        <tr key={p.id} className="hover:bg-gray-50 dark:bg-gray-950/50">
                                            <td className="px-3 py-2 text-xs font-mono font-semibold text-gray-900 dark:text-gray-100">{p.reference_number}</td>
                                            <td className="px-3 py-2 text-xs text-gray-700 dark:text-gray-300 truncate max-w-[120px]">{p.tenant_name || '—'}</td>
                                            <td className="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{p.payment_type_label}</td>
                                            <td className="px-3 py-2 text-xs font-semibold text-gray-900 dark:text-gray-100">{fmt(p.amount)}</td>
                                            <td className="px-3 py-2"><span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[p.status] || 'bg-gray-100 text-gray-600'}`}>{p.status}</span></td>
                                            <td className="px-3 py-2 text-right">
                                                <Link href={`/superadmin/billing`} className="text-xs text-blue-600 hover:text-blue-700 font-medium">Review</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-6">No recent payments.</p>}
                </SectionCard>

                {/* Recent Merchants */}
                <SectionCard title="Recent Merchants" action={<Link href="/superadmin/tenants" className="text-xs text-blue-600 hover:text-blue-700 font-medium">View All</Link>}>
                    {recentTenants && recentTenants.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead className="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Store</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Created</th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {recentTenants.map(t => (
                                        <tr key={t.id} className="hover:bg-gray-50 dark:bg-gray-950/50">
                                            <td className="px-3 py-2">
                                                <p className="text-xs font-medium text-gray-900 dark:text-gray-100 truncate max-w-[140px]">{t.name}</p>
                                                <p className="text-xs text-gray-400 dark:text-gray-500 truncate max-w-[140px]">{t.slug}</p>
                                            </td>
                                            <td className="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{t.plan_name || '—'}</td>
                                            <td className="px-3 py-2"><span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[t.status] || 'bg-gray-100 text-gray-600'}`}>{t.status}</span></td>
                                            <td className="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{t.created_at}</td>
                                            <td className="px-3 py-2 text-right">
                                                <Link href={`/superadmin/tenants/${t.id}`} className="text-xs text-blue-600 hover:text-blue-700 font-medium">View</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-6">No merchants yet.</p>}
                </SectionCard>

                {/* Recent Activity */}
                <SectionCard title="Recent Platform Activity">
                    {recentActivity && recentActivity.length > 0 ? (
                        <div className="space-y-2">
                            {recentActivity.map(a => (
                                <div key={a.id} className="flex items-start gap-3 py-1.5 border-b border-gray-50 dark:border-gray-800 last:border-0">
                                    <Activity className="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-xs text-gray-700 dark:text-gray-300 truncate">{a.description}</p>
                                        <p className="text-xs text-gray-400 dark:text-gray-500">{a.created_at_human || a.created_at}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-6">No recent activity.</p>}
                </SectionCard>
            </div>
        </AdminLayout>
    );
}


