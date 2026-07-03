import { Clock, Sparkles, RefreshCw, CheckCircle, AlertTriangle, XCircle, Ban, Zap, ArrowRight } from 'lucide-react';

const eventConfig = {
    trial_started:    { icon: Sparkles,      color: 'text-blue-500',    bg: 'bg-blue-100' },
    trial_ended:      { icon: Clock,         color: 'text-gray-500',    bg: 'bg-gray-100' },
    trial_renewed:    { icon: RefreshCw,     color: 'text-blue-500',    bg: 'bg-blue-100' },
    plan_changed:     { icon: Zap,           color: 'text-purple-500',  bg: 'bg-purple-100' },
    renewed:          { icon: RefreshCw,     color: 'text-emerald-500', bg: 'bg-emerald-100' },
    activated:        { icon: CheckCircle,   color: 'text-emerald-500', bg: 'bg-emerald-100' },
    past_due:         { icon: AlertTriangle, color: 'text-amber-500',   bg: 'bg-amber-100' },
    expired:          { icon: XCircle,       color: 'text-red-500',     bg: 'bg-red-100' },
    canceled:         { icon: Ban,           color: 'text-gray-500',    bg: 'bg-gray-100' },
    suspended:        { icon: AlertTriangle, color: 'text-yellow-500',  bg: 'bg-yellow-100' },
};

export default function ActivityTimeline({ logs }) {
    if (!logs || logs.length === 0) {
        return (
            <div className="bg-white rounded-xl border border-gray-200">
                <div className="px-6 py-4 border-b border-gray-100">
                    <h3 className="text-base font-semibold text-gray-900">Recent Activity</h3>
                </div>
                <div className="p-8 text-center">
                    <Clock className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                    <p className="text-sm text-gray-500">No recent activity</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Recent Activity</h3>
            </div>
            <div className="p-6">
                <div className="relative">
                    <div className="absolute left-5 top-0 bottom-0 w-px bg-gray-200" />
                    <div className="space-y-0">
                        {logs.map((log, idx) => {
                            const cfg = eventConfig[log.event] || { icon: Clock, color: 'text-gray-500', bg: 'bg-gray-100' };
                            const Icon = cfg.icon;

                            const label = log.event === 'trial_started' ? 'Trial Started'
                                : log.event === 'plan_changed' ? 'Plan Changed'
                                : log.event === 'renewed' ? 'Renewed'
                                : log.event === 'activated' ? 'Activated'
                                : log.event === 'canceled' ? 'Canceled'
                                : log.event === 'suspended' ? 'Suspended'
                                : log.event === 'past_due' ? 'Past Due'
                                : log.event === 'expired' ? 'Expired'
                                : log.event === 'trial_ended' ? 'Trial Ended'
                                : log.event === 'trial_renewed' ? 'Trial Renewed'
                                : log.event.charAt(0).toUpperCase() + log.event.slice(1).replace(/_/g, ' ');

                            return (
                                <div key={log.id || idx} className="relative flex items-start gap-4 pb-6 last:pb-0">
                                    <div className={`relative z-10 w-10 h-10 rounded-full ${cfg.bg} flex items-center justify-center flex-shrink-0`}>
                                        <Icon className={`w-4 h-4 ${cfg.color}`} />
                                    </div>
                                    <div className="min-w-0 flex-1 pt-1.5">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold text-gray-900">{label}</span>
                                            <span className="text-xs text-gray-400">{log.created_at}</span>
                                        </div>
                                        {log.reason && (
                                            <p className="text-sm text-gray-500 mt-0.5">{log.reason}</p>
                                        )}
                                        {(log.old_status || log.new_status) && (
                                            <div className="flex items-center gap-1.5 mt-1">
                                                {log.old_status && (
                                                    <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-medium">{log.old_status}</span>
                                                )}
                                                {log.old_status && log.new_status && (
                                                    <ArrowRight className="w-3 h-3 text-gray-400" />
                                                )}
                                                {log.new_status && (
                                                    <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 font-medium">{log.new_status}</span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}