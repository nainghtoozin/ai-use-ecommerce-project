import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import {
    CheckCircle2,
    Circle,
    ChevronDown,
    ChevronUp,
    Search,
    Store,
    Package,
    ShoppingCart,
    Users,
    CreditCard,
    Truck,
    LayoutTemplate,
    Tag,
    Bell,
    Receipt,
    ExternalLink,
    ArrowRight,
    Sparkles,
    Rocket,
    BookOpen,
    HelpCircle,
} from 'lucide-react';

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function ProgressBar({ percentage }) {
    const getColor = () => {
        if (percentage < 25) return 'bg-red-500';
        if (percentage < 50) return 'bg-amber-500';
        if (percentage < 75) return 'bg-blue-500';
        return 'bg-emerald-500';
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Setup Progress</span>
                <span className="text-xs font-bold text-gray-700 dark:text-gray-300">{percentage}%</span>
            </div>
            <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${getColor()}`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

function ChecklistItem({ item, index }) {
    const Icon = item.completed ? CheckCircle2 : Circle;
    const iconClass = item.completed
        ? 'text-emerald-500'
        : 'text-gray-400 dark:text-gray-500';

    const categoryColors = {
        store: 'bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-900/30',
        products: 'bg-violet-50 dark:bg-violet-900/20 border-violet-100 dark:border-violet-900/30',
        payments: 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-900/30',
        delivery: 'bg-amber-50 dark:bg-amber-900/20 border-amber-100 dark:border-amber-900/30',
        team: 'bg-rose-50 dark:bg-rose-900/20 border-rose-100 dark:border-rose-900/30',
    };

    const categoryColor = categoryColors[item.category] || 'bg-gray-50 dark:bg-gray-900/20 border-gray-100 dark:border-gray-800';

    return (
        <div className={`p-4 rounded-xl border ${item.completed ? 'bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-100 dark:border-emerald-900/30' : categoryColor} transition-colors`}>
            <div className="flex items-start gap-3">
                <Icon className={`w-5 h-5 flex-shrink-0 mt-0.5 ${iconClass}`} />
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        <span className={`text-sm font-medium ${item.completed ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-900 dark:text-gray-100'}`}>
                            {item.label}
                        </span>
                        {item.completed && (
                            <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                                Done
                            </span>
                        )}
                    </div>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{item.description}</p>
                </div>
                {item.link && !item.completed && (
                    <Link
                        href={adminUrl(item.link)}
                        className="flex-shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300 hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-sm transition-all"
                    >
                        Go
                        <ArrowRight className="w-3 h-3" />
                    </Link>
                )}
            </div>
        </div>
    );
}

const iconMap = {
    Store,
    Package,
    ShoppingCart,
    Users,
    CreditCard,
    Truck,
    LayoutTemplate,
    Tag,
    Bell,
    Receipt,
};

function HelpCard({ section }) {
    const Icon = iconMap[section.icon] || BookOpen;

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 hover:shadow-md transition-shadow">
            <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                    <Icon className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{section.title}</h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{section.description}</p>
                </div>
            </div>
            <div className="space-y-1">
                {section.links.map((link) => (
                    <Link
                        key={link.href}
                        href={adminUrl(link.href)}
                        className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200 transition-colors group"
                    >
                        <span>{link.label}</span>
                        <ExternalLink className="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </Link>
                ))}
            </div>
        </div>
    );
}

export default function SetupGuide({ onboarding }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [activeTab, setActiveTab] = useState('checklist');

    if (!onboarding) {
        return (
            <AdminLayout>
                <Head title="Setup Guide" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="text-center py-12">
                        <Rocket className="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600" />
                        <h2 className="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">Setup Complete!</h2>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Your store setup is complete. Visit the <Link href={adminUrl('/admin/dashboard')} className="text-indigo-600 hover:text-indigo-500">Dashboard</Link> to manage your store.
                        </p>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    const { items, percentage, merchant_name, store_name, subscription, help_sections } = onboarding;
    const trialEndDate = formatDate(subscription?.trial_ends_at);

    const completedCount = Object.values(items).filter(i => i.completed).length;
    const totalCount = Object.keys(items).length;

    const filteredSections = help_sections.map(section => ({
        ...section,
        links: section.links.filter(link =>
            link.label.toLowerCase().includes(searchQuery.toLowerCase()) ||
            section.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            section.description.toLowerCase().includes(searchQuery.toLowerCase())
        )
    })).filter(section => section.links.length > 0);

    return (
        <AdminLayout>
            <Head title="Setup Guide" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Setup Guide</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Complete your store setup and access help guides.
                    </p>
                </div>

                {/* Tabs */}
                <div className="flex gap-4 mb-6 border-b border-gray-200 dark:border-gray-800">
                    <button
                        onClick={() => setActiveTab('checklist')}
                        className={`pb-3 px-1 text-sm font-medium transition-colors relative ${
                            activeTab === 'checklist'
                                ? 'text-indigo-600 dark:text-indigo-400'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <Rocket className="w-4 h-4" />
                            Setup Checklist
                        </span>
                        {activeTab === 'checklist' && (
                            <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-600 dark:bg-indigo-400" />
                        )}
                    </button>
                    <button
                        onClick={() => setActiveTab('help')}
                        className={`pb-3 px-1 text-sm font-medium transition-colors relative ${
                            activeTab === 'help'
                                ? 'text-indigo-600 dark:text-indigo-400'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <BookOpen className="w-4 h-4" />
                            User Guide
                        </span>
                        {activeTab === 'help' && (
                            <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-600 dark:bg-indigo-400" />
                        )}
                    </button>
                </div>

                {/* Checklist Tab */}
                {activeTab === 'checklist' && (
                    <div className="space-y-6">
                        {/* Summary Card */}
                        <div className="rounded-xl border border-indigo-100 dark:border-indigo-900/30 bg-gradient-to-br from-indigo-50 via-white to-violet-50 dark:from-indigo-950/30 dark:via-gray-900 dark:to-violet-950/20 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                        <Rocket className="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <div>
                                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Welcome, {merchant_name}!
                                        </h2>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Let's get your store ready to sell.
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">{completedCount}/{totalCount}</div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">steps completed</div>
                                </div>
                            </div>

                            {/* Store Info */}
                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300">
                                    <span className="w-2 h-2 rounded-full bg-indigo-500" />
                                    {store_name}
                                </div>
                                {subscription && (
                                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        {subscription.on_trial ? (
                                            <Sparkles className="w-3 h-3 text-amber-500" />
                                        ) : (
                                            <CreditCard className="w-3 h-3 text-gray-400" />
                                        )}
                                        {subscription.plan_name}
                                        {subscription.on_trial && (
                                            <span className="text-amber-600 dark:text-amber-400 ml-1">
                                                ({subscription.days_left_in_trial}d left)
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Progress */}
                            <div className="mt-4">
                                <ProgressBar percentage={percentage} />
                            </div>
                        </div>

                        {/* Checklist Items */}
                        <div className="space-y-3">
                            <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300">Setup Tasks</h3>
                            {Object.entries(items).map(([key, item]) => (
                                <ChecklistItem key={key} item={item} index={key} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Help Tab */}
                {activeTab === 'help' && (
                    <div>
                        {/* Search */}
                        <div className="mb-6">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Search guides..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400"
                                />
                            </div>
                        </div>

                        {/* Help Cards */}
                        {searchQuery ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {filteredSections.map((section, idx) => (
                                    <HelpCard key={idx} section={section} />
                                ))}
                                {filteredSections.length === 0 && (
                                    <div className="col-span-full text-center py-12">
                                        <HelpCircle className="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600" />
                                        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                            No results found for "{searchQuery}"
                                        </p>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {help_sections.map((section, idx) => (
                                    <HelpCard key={idx} section={section} />
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
