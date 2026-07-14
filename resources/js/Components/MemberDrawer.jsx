import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';

function Badge({ children, color = 'gray' }) {
    const colors = {
        gray: 'bg-gray-50 text-gray-600 ring-gray-500/20',
        blue: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        purple: 'bg-purple-50 text-purple-700 ring-purple-600/20',
        indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        amber: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        red: 'bg-red-50 text-red-700 ring-red-600/20',
    };
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ring-1 ring-inset ${colors[color] || colors.gray}`}>
            {children}
        </span>
    );
}

function Section({ title, icon, children }) {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <i className={`bi ${icon} text-gray-400 text-sm`}></i>
                <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{title}</h3>
            </div>
            {children}
        </div>
    );
}

function InfoRow({ label, value, icon }) {
    return (
        <div className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
            {icon && (
                <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-white border border-gray-200">
                    <i className={`bi ${icon} text-gray-500 text-sm`}></i>
                </div>
            )}
            <div className="min-w-0 flex-1">
                <p className="text-[11px] text-gray-400 uppercase tracking-wider">{label}</p>
                <p className="text-sm font-medium text-gray-900 truncate">{value || '—'}</p>
            </div>
        </div>
    );
}

function PermissionChip({ name }) {
    return (
        <span className="inline-flex items-center px-2 py-1 rounded-lg bg-gray-50 border border-gray-200 text-[11px] font-medium text-gray-600">
            {name}
        </span>
    );
}

function ActivityItem({ log }) {
    return (
        <div className="flex gap-3">
            <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                <i className="bi bi-clock text-gray-400 text-xs"></i>
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-sm text-gray-700">{log.description}</p>
                <p className="text-[11px] text-gray-400 mt-0.5">{log.created_at}</p>
            </div>
        </div>
    );
}

export default function MemberDrawer({ open, onClose, memberId }) {
    const { auth } = usePage().props;
    const isOwner = auth?.user?.is_owner;
    const canManage = isOwner || auth?.user?.permissions?.includes('users.update');

    const [member, setMember] = useState(null);
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('profile');

    useEffect(() => {
        if (!open) return;
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handler);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    useEffect(() => {
        if (open && memberId) {
            setLoading(true);
            fetch(adminUrl(`/admin/team/${memberId}/json`))
                .then(r => r.json())
                .then(data => { setMember(data); setLoading(false); })
                .catch(() => setLoading(false));
        } else {
            setMember(null);
            setActiveTab('profile');
        }
    }, [open, memberId]);

    if (!open) return null;

    const roleColor = member?.is_owner ? 'purple' : { admin: 'blue', staff: 'indigo', customer: 'gray' }[member?.role] || 'gray';
    const statusColor = { active: 'emerald', suspended: 'amber', removed: 'red' }[member?.status] || 'gray';

    return (
        <>
            <div
                className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm transition-opacity duration-300"
                onClick={onClose}
            />

            <div className={`fixed z-50 bg-white shadow-2xl transform transition-transform duration-300 ease-out
                bottom-0 left-0 right-0 rounded-t-2xl max-h-[90vh]
                sm:inset-y-0 sm:left-auto sm:right-0 sm:rounded-none sm:w-[480px] lg:w-[520px] sm:max-h-none
                ${open ? 'translate-y-0 sm:translate-x-0' : 'translate-y-full sm:translate-y-0 sm:translate-x-full'}
            `}>
                <div className="flex flex-col h-full">
                    {/* Header */}
                    <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                        <div className="flex items-center gap-3">
                            {member?.avatar ? (
                                <img src={member.avatar} alt="" className="w-10 h-10 rounded-full object-cover ring-2 ring-white shadow-sm" />
                            ) : (
                                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold ring-2 ring-white shadow-sm">
                                    {member?.name?.charAt(0)?.toUpperCase() || '?'}
                                </div>
                            )}
                            <div>
                                <h2 className="text-base font-semibold text-gray-900">{member?.name || 'Loading...'}</h2>
                                <p className="text-xs text-gray-500">{member?.email}</p>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                        >
                            <i className="bi bi-x-lg text-sm"></i>
                        </button>
                    </div>

                    {/* Tabs */}
                    <div className="flex border-b border-gray-100 px-5 flex-shrink-0">
                        {[
                            { id: 'profile', label: 'Profile', icon: 'bi-person' },
                            { id: 'permissions', label: 'Permissions', icon: 'bi-shield-lock' },
                            { id: 'activity', label: 'Activity', icon: 'bi-clock-history' },
                        ].map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`flex items-center gap-1.5 px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
                                    activeTab === tab.id
                                        ? 'border-blue-600 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                <i className={`${tab.icon} text-xs`}></i>
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {/* Content */}
                    <div className="flex-1 overflow-y-auto px-5 py-5 space-y-6">
                        {loading ? (
                            <div className="space-y-4 animate-pulse">
                                {[1, 2, 3].map(i => (
                                    <div key={i} className="h-16 bg-gray-100 rounded-xl" />
                                ))}
                            </div>
                        ) : (
                            <>
                                {/* Profile Tab */}
                                {activeTab === 'profile' && member && (
                                    <>
                                        <Section title="Profile" icon="bi-person">
                                            <InfoRow icon="bi-envelope" label="Email" value={member.email} />
                                            <InfoRow icon="bi-phone" label="Phone" value={member.phone} />
                                            <InfoRow icon="bi-calendar" label="Joined" value={member.joined_at} />
                                            <InfoRow icon="bi-clock" label="Last Login" value={member.last_login_at || 'Never'} />
                                        </Section>

                                        <Section title="Membership" icon="bi-diagram-3">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="p-3 rounded-xl bg-gray-50 border border-gray-100">
                                                    <p className="text-[11px] text-gray-400 uppercase tracking-wider mb-1">Role</p>
                                                    <Badge color={roleColor}>{member.role_label}</Badge>
                                                </div>
                                                <div className="p-3 rounded-xl bg-gray-50 border border-gray-100">
                                                    <p className="text-[11px] text-gray-400 uppercase tracking-wider mb-1">Status</p>
                                                    <Badge color={statusColor}>{member.status}</Badge>
                                                </div>
                                            </div>
                                            {member.is_owner && (
                                                <div className="flex items-center gap-2.5 p-3 rounded-xl bg-purple-50 border border-purple-100">
                                                    <i className="bi bi-star-fill text-purple-500"></i>
                                                    <p className="text-sm text-purple-700 font-medium">Store Owner</p>
                                                </div>
                                            )}
                                        </Section>

                                        {canManage && !member.is_owner && (
                                            <div className="flex gap-2 pt-2">
                                                <a
                                                    href={adminUrl(`/admin/team/${member.id}/edit`)}
                                                    className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors"
                                                >
                                                    <i className="bi bi-pencil"></i> Edit Role
                                                </a>
                                                {member.status === 'active' ? (
                                                    <button
                                                        onClick={() => { if (confirm('Suspend?')) router.post(adminUrl(`/admin/team/${member.id}/suspend`)); }}
                                                        className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-amber-300 text-amber-700 text-sm font-medium rounded-xl hover:bg-amber-50 transition-colors"
                                                    >
                                                        <i className="bi bi-pause-circle"></i> Suspend
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => router.post(adminUrl(`/admin/team/${member.id}/restore`))}
                                                        className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-emerald-300 text-emerald-700 text-sm font-medium rounded-xl hover:bg-emerald-50 transition-colors"
                                                    >
                                                        <i className="bi bi-play-circle"></i> Restore
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </>
                                )}

                                {/* Permissions Tab */}
                                {activeTab === 'permissions' && member && (
                                    <>
                                        <Section title="Role Permissions" icon="bi-shield-lock">
                                            <div className="p-3 rounded-xl bg-gray-50 border border-gray-100">
                                                <div className="flex items-center gap-2 mb-3">
                                                    <Badge color={roleColor}>{member.role_label}</Badge>
                                                    <span className="text-xs text-gray-400">•</span>
                                                    <span className="text-xs text-gray-500">{member.permissions?.length || 0} permissions</span>
                                                </div>
                                                {member.is_owner ? (
                                                    <div className="flex items-center gap-2 p-2.5 rounded-lg bg-purple-50 border border-purple-100">
                                                        <i className="bi bi-infinity text-purple-500"></i>
                                                        <p className="text-sm text-purple-700">Owner has all permissions</p>
                                                    </div>
                                                ) : (
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {(member.permissions || []).map(perm => (
                                                            <PermissionChip key={perm} name={perm} />
                                                        ))}
                                                        {(!member.permissions || member.permissions.length === 0) && (
                                                            <p className="text-xs text-gray-400">No permissions assigned</p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </Section>
                                    </>
                                )}

                                {/* Activity Tab */}
                                {activeTab === 'activity' && member && (
                                    <Section title="Recent Activity" icon="bi-clock-history">
                                        {member.activity_logs?.length > 0 ? (
                                            <div className="space-y-4">
                                                {member.activity_logs.map(log => (
                                                    <ActivityItem key={log.id} log={log} />
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="text-center py-12">
                                                <i className="bi bi-clock-history text-3xl text-gray-300"></i>
                                                <p className="text-sm text-gray-500 mt-2">No recent activity</p>
                                            </div>
                                        )}
                                    </Section>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
