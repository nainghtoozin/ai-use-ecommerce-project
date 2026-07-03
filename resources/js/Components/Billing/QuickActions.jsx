import { ArrowUp, RefreshCw, Receipt, LifeBuoy, Eye, ExternalLink } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

const actions = [
    { key: 'upgrade', label: 'Upgrade Plan', icon: ArrowUp, href: '/admin/billing/upgrade', color: 'bg-blue-600 hover:bg-blue-700', text: 'text-white' },
    { key: 'renew', label: 'Renew', icon: RefreshCw, href: null, color: 'bg-emerald-600 hover:bg-emerald-700', text: 'text-white', requires: 'renew' },
    { key: 'plans', label: 'View Plans', icon: Eye, href: '/admin/billing/upgrade', color: 'bg-white hover:bg-gray-50 border border-gray-300', text: 'text-gray-700' },
    { key: 'history', label: 'Payment History', icon: Receipt, href: '/admin/billing/payment-history', color: 'bg-white hover:bg-gray-50 border border-gray-300', text: 'text-gray-700' },
    { key: 'support', label: 'Contact Support', icon: LifeBuoy, href: '#', color: 'bg-white hover:bg-gray-50 border border-gray-300', text: 'text-gray-700', external: true },
];

export default function QuickActions({ subscription, onRenew, can }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Quick Actions</h3>
            </div>
            <div className="p-6">
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    {actions.map((action) => {
                        const Icon = action.icon;
                        const needsRenew = action.requires === 'renew' && can?.('billing.renew');
                        const show = action.requires === 'renew' ? needsRenew && subscription && ['expired', 'past_due', 'canceled'].includes(subscription.status) : true;

                        if (!show) return null;

                        if (action.key === 'renew') {
                            return (
                                <button
                                    key={action.key}
                                    onClick={onRenew}
                                    className={`flex flex-col items-center justify-center gap-2 px-4 py-4 rounded-xl text-sm font-medium transition-all duration-200 ${action.color} ${action.text}`}
                                >
                                    <Icon className="w-5 h-5" />
                                    <span>{action.label}</span>
                                </button>
                            );
                        }

                        if (action.external) {
                            return (
                                <a
                                    key={action.key}
                                    href={action.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={`flex flex-col items-center justify-center gap-2 px-4 py-4 rounded-xl text-sm font-medium transition-all duration-200 ${action.color} ${action.text}`}
                                >
                                    <Icon className="w-5 h-5" />
                                    <span className="flex items-center gap-1">{action.label}<ExternalLink className="w-3 h-3" /></span>
                                </a>
                            );
                        }

                        return (
                            <a
                                key={action.key}
                                href={adminUrl(action.href)}
                                className={`flex flex-col items-center justify-center gap-2 px-4 py-4 rounded-xl text-sm font-medium transition-all duration-200 ${action.color} ${action.text}`}
                            >
                                <Icon className="w-5 h-5" />
                                <span>{action.label}</span>
                            </a>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
