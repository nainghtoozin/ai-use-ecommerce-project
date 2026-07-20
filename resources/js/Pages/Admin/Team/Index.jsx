import { useState, useEffect, useRef } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

export default function TeamIndex({ members, invitations, roles }) {
    const { can } = usePermission();
    const canManage = can('users.view');

    const [tab, setTab] = useState('members');
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showInviteModal, setShowInviteModal] = useState(false);
    const [inviteData, setInviteData] = useState({ email: '', role_id: '', message: '' });
    const [inviteProcessing, setInviteProcessing] = useState(false);
    const [inviteErrors, setInviteErrors] = useState({});
    const [toast, setToast] = useState(null);
    const toastTimeout = useRef(null);
    const emailInputRef = useRef(null);

    // Auto-dismiss toast
    useEffect(() => {
        if (toast) {
            toastTimeout.current = setTimeout(() => setToast(null), 4000);
            return () => clearTimeout(toastTimeout.current);
        }
    }, [toast]);

    // Focus email input when modal opens
    useEffect(() => {
        if (showInviteModal && emailInputRef.current) {
            setTimeout(() => emailInputRef.current?.focus(), 100);
        }
    }, [showInviteModal]);

    const filteredMembers = (members || []).filter(m => {
        if (search && !m.name?.toLowerCase().includes(search.toLowerCase()) && !m.email?.toLowerCase().includes(search.toLowerCase())) return false;
        if (roleFilter && m.role !== roleFilter) return false;
        if (statusFilter && m.status !== statusFilter) return false;
        return true;
    });

    const filteredInvitations = (invitations || []).filter(i => {
        if (search && !i.email?.toLowerCase().includes(search.toLowerCase())) return false;
        if (roleFilter && i.role !== roleFilter) return false;
        return true;
    });

    const stats = {
        total: members?.length || 0,
        pending: invitations?.length || 0,
        admins: members?.filter(m => m.role === 'admin' || m.is_owner)?.length || 0,
        customers: members?.filter(m => m.role === 'customer')?.length || 0,
    };

    const statusBadge = (status) => {
        const colors = {
            active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            suspended: 'bg-amber-50 text-amber-700 ring-amber-600/20',
            removed: 'bg-red-50 text-red-700 ring-red-600/20',
            pending: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        };
        return (
            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ring-1 ring-inset ${colors[status] || 'bg-gray-50 text-gray-700 ring-gray-600/20'}`}>
                {status}
            </span>
        );
    };

    const roleBadge = (role, isOwner) => {
        if (isOwner) {
            return <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20">Owner</span>;
        }
        const colors = {
            admin: 'bg-blue-50 text-blue-700 ring-blue-600/20',
            staff: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
            customer: 'bg-gray-50 text-gray-700 ring-gray-600/20',
        };
        return (
            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ring-1 ring-inset ${colors[role] || 'bg-gray-50 text-gray-700 ring-gray-600/20'}`}>
                {role ? role.charAt(0).toUpperCase() + role.slice(1) : 'Unknown'}
            </span>
        );
    };

    function handleInvite(e) {
        e.preventDefault();
        setInviteErrors({});
        setInviteProcessing(true);
        router.post(adminUrl('/admin/team/invite'), inviteData, {
            onSuccess: () => {
                setShowInviteModal(false);
                setInviteData({ email: '', role_id: '', message: '' });
                setToast({ type: 'success', message: `Invitation sent to ${inviteData.email}` });
            },
            onError: (errors) => {
                setInviteErrors(errors);
                setToast({ type: 'error', message: 'Failed to send invitation. Please check the form.' });
            },
            onFinish: () => setInviteProcessing(false),
        });
    }

    function closeInviteModal() {
        setShowInviteModal(false);
        setInviteErrors({});
        setInviteData({ email: '', role_id: '', message: '' });
    }

    function handleSuspend(member) {
        if (confirm(`Suspend ${member.name}?`)) {
            router.post(adminUrl(`/admin/team/${member.id}/suspend`));
        }
    }

    function handleRestore(member) {
        router.post(adminUrl(`/admin/team/${member.id}/restore`));
    }

    function handleRemove(member) {
        if (confirm(`Remove ${member.name} from the team?`)) {
            router.delete(adminUrl(`/admin/team/${member.id}`));
        }
    }

    function handleRevokeInvitation(invitation) {
        if (confirm(`Revoke invitation for ${invitation.email}?`)) {
            router.delete(adminUrl(`/admin/team/invitations/${invitation.id}`));
        }
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Team Management</h2>}>
            <Head title="Team Management" />

            <div className="p-6 lg:p-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Team</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage your store team members and invitations</p>
                    </div>
                    {canManage && (
                        <button
                            onClick={() => setShowInviteModal(true)}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors shadow-sm"
                        >
                            <i className="bi bi-plus-lg"></i>
                            Invite Member
                        </button>
                    )}
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                        <div className="flex items-center gap-4">
                            <div className="p-2.5 rounded-lg bg-blue-50">
                                <i className="bi bi-people text-lg text-blue-600"></i>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
                                <p className="text-xs text-gray-500">Members</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                        <div className="flex items-center gap-4">
                            <div className="p-2.5 rounded-lg bg-amber-50">
                                <i className="bi bi-clock text-lg text-amber-600"></i>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{stats.pending}</p>
                                <p className="text-xs text-gray-500">Pending Invitations</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                        <div className="flex items-center gap-4">
                            <div className="p-2.5 rounded-lg bg-purple-50">
                                <i className="bi bi-shield-check text-lg text-purple-600"></i>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{stats.admins}</p>
                                <p className="text-xs text-gray-500">Admins</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                        <div className="flex items-center gap-4">
                            <div className="p-2.5 rounded-lg bg-emerald-50">
                                <i className="bi bi-person text-lg text-emerald-600"></i>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{stats.customers}</p>
                                <p className="text-xs text-gray-500">Customers</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tabs + Search + Filters */}
                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="border-b border-gray-200">
                        <div className="flex">
                            <button
                                onClick={() => setTab('members')}
                                className={`px-6 py-3 text-sm font-medium border-b-2 transition-colors ${
                                    tab === 'members'
                                        ? 'border-blue-600 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                Members ({members?.length || 0})
                            </button>
                            <button
                                onClick={() => setTab('invitations')}
                                className={`px-6 py-3 text-sm font-medium border-b-2 transition-colors ${
                                    tab === 'invitations'
                                        ? 'border-blue-600 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                Invitations ({invitations?.length || 0})
                            </button>
                        </div>
                    </div>

                    <div className="p-4 border-b border-gray-100">
                        <div className="flex flex-col sm:flex-row gap-3">
                            <div className="flex-1 relative">
                                <i className="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name or email..."
                                    className="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                            <select
                                value={roleFilter}
                                onChange={(e) => setRoleFilter(e.target.value)}
                                className="px-3 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Roles</option>
                                {roles?.map(r => (
                                    <option key={r.id} value={r.name}>{r.label}</option>
                                ))}
                                <option value="customer">Customer</option>
                            </select>
                            {tab === 'members' && (
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="px-3 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            )}
                        </div>
                    </div>

                    {/* Members Table */}
                    {tab === 'members' && (
                        <div>
                            {filteredMembers.length === 0 ? (
                                <div className="text-center py-16">
                                    <i className="bi bi-people text-5xl text-gray-300"></i>
                                    <p className="text-sm text-gray-500 mt-3">No members found</p>
                                </div>
                            ) : (
                                <>
                                    {/* Desktop Table */}
                                    <div className="hidden md:block overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="text-left text-xs text-gray-500 uppercase tracking-wider bg-gray-50">
                                                    <th className="px-5 py-3 font-medium">Member</th>
                                                    <th className="px-5 py-3 font-medium">Role</th>
                                                    <th className="px-5 py-3 font-medium">Status</th>
                                                    <th className="px-5 py-3 font-medium">Joined</th>
                                                    <th className="px-5 py-3 font-medium text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {filteredMembers.map((member) => (
                                                    <tr key={member.id} className="hover:bg-gray-50 transition-colors">
                                                        <td className="px-5 py-4">
                                                            <div className="flex items-center gap-3">
                                                                {member.avatar ? (
                                                                    <img src={member.avatar} alt="" className="w-9 h-9 rounded-full object-cover" />
                                                                ) : (
                                                                    <div className="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                                                                        {member.name?.charAt(0)?.toUpperCase() || '?'}
                                                                    </div>
                                                                )}
                                                                <div>
                                                                    <p className="text-sm font-medium text-gray-900">{member.name}</p>
                                                                    <p className="text-xs text-gray-500">{member.email}</p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-5 py-4">{roleBadge(member.role, member.is_owner)}</td>
                                                        <td className="px-5 py-4">{statusBadge(member.status)}</td>
                                                        <td className="px-5 py-4 text-sm text-gray-500">{member.joined_at || '—'}</td>
                                                        <td className="px-5 py-4 text-right">
                                                            {canManage && !member.is_owner && (
                                                                <div className="flex items-center justify-end gap-2">
                                                                    {member.status === 'active' ? (
                                                                        <button onClick={() => handleSuspend(member)} className="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors" title="Suspend">
                                                                            <i className="bi bi-pause-circle"></i>
                                                                        </button>
                                                                    ) : (
                                                                        <button onClick={() => handleRestore(member)} className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors" title="Restore">
                                                                            <i className="bi bi-play-circle"></i>
                                                                        </button>
                                                                    )}
                                                                    <button onClick={() => handleRemove(member)} className="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition-colors" title="Remove">
                                                                        <i className="bi bi-trash"></i>
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Mobile Cards */}
                                    <div className="md:hidden divide-y divide-gray-100">
                                        {filteredMembers.map((member) => (
                                            <div key={member.id} className="p-4 hover:bg-gray-50 transition-colors">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="flex items-center gap-3 min-w-0">
                                                        {member.avatar ? (
                                                            <img src={member.avatar} alt="" className="w-10 h-10 rounded-full object-cover flex-shrink-0" />
                                                        ) : (
                                                            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                                                {member.name?.charAt(0)?.toUpperCase() || '?'}
                                                            </div>
                                                        )}
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-medium text-gray-900 truncate">{member.name}</p>
                                                            <p className="text-xs text-gray-500 truncate">{member.email}</p>
                                                            <div className="flex items-center gap-2 mt-1.5">
                                                                {roleBadge(member.role, member.is_owner)}
                                                                {statusBadge(member.status)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {canManage && !member.is_owner && (
                                                        <div className="flex items-center gap-1 flex-shrink-0">
                                                            {member.status === 'active' ? (
                                                                <button onClick={() => handleSuspend(member)} className="p-2 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors">
                                                                    <i className="bi bi-pause-circle"></i>
                                                                </button>
                                                            ) : (
                                                                <button onClick={() => handleRestore(member)} className="p-2 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors">
                                                                    <i className="bi bi-play-circle"></i>
                                                                </button>
                                                            )}
                                                            <button onClick={() => handleRemove(member)} className="p-2 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                                                                <i className="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="text-[11px] text-gray-400 mt-2">Joined {member.joined_at || '—'}</p>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {/* Invitations Table */}
                    {tab === 'invitations' && (
                        <div>
                            {filteredInvitations.length === 0 ? (
                                <div className="text-center py-16">
                                    <i className="bi bi-envelope text-5xl text-gray-300"></i>
                                    <p className="text-sm text-gray-500 mt-3">No pending invitations</p>
                                </div>
                            ) : (
                                <>
                                    {/* Desktop Table */}
                                    <div className="hidden md:block overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="text-left text-xs text-gray-500 uppercase tracking-wider bg-gray-50">
                                                    <th className="px-5 py-3 font-medium">Email</th>
                                                    <th className="px-5 py-3 font-medium">Role</th>
                                                    <th className="px-5 py-3 font-medium">Invited</th>
                                                    <th className="px-5 py-3 font-medium">Expires</th>
                                                    <th className="px-5 py-3 font-medium text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {filteredInvitations.map((invitation) => (
                                                    <tr key={invitation.id} className="hover:bg-gray-50 transition-colors">
                                                        <td className="px-5 py-4">
                                                            <div className="flex items-center gap-3">
                                                                <div className="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center">
                                                                    <i className="bi bi-envelope text-gray-400"></i>
                                                                </div>
                                                                <div>
                                                                    <p className="text-sm font-medium text-gray-900">{invitation.email}</p>
                                                                    {invitation.is_expired && <p className="text-xs text-red-500">Expired</p>}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-5 py-4">{roleBadge(invitation.role, false)}</td>
                                                        <td className="px-5 py-4 text-sm text-gray-500">{invitation.invited_at || '—'}</td>
                                                        <td className="px-5 py-4 text-sm text-gray-500">{invitation.expires_at || '—'}</td>
                                                        <td className="px-5 py-4 text-right">
                                                            {canManage && (
                                                                <button onClick={() => handleRevokeInvitation(invitation)} className="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition-colors" title="Revoke">
                                                                    <i className="bi bi-x-circle"></i>
                                                                </button>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Mobile Cards */}
                                    <div className="md:hidden divide-y divide-gray-100">
                                        {filteredInvitations.map((invitation) => (
                                            <div key={invitation.id} className="p-4 hover:bg-gray-50 transition-colors">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="flex items-center gap-3 min-w-0">
                                                        <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                            <i className="bi bi-envelope text-gray-400"></i>
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-medium text-gray-900 truncate">{invitation.email}</p>
                                                            <div className="flex items-center gap-2 mt-1">
                                                                {roleBadge(invitation.role, false)}
                                                                {invitation.is_expired && <span className="text-[11px] text-red-500">Expired</span>}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {canManage && (
                                                        <button onClick={() => handleRevokeInvitation(invitation)} className="p-2 rounded-lg text-red-600 hover:bg-red-50 transition-colors flex-shrink-0">
                                                            <i className="bi bi-x-circle"></i>
                                                        </button>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-4 mt-2 text-[11px] text-gray-400">
                                                    <span>Sent {invitation.invited_at || '—'}</span>
                                                    <span>Expires {invitation.expires_at || '—'}</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Invite Modal */}
            {showInviteModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={closeInviteModal} />
                    <div className="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                        {/* Header */}
                        <div className="px-6 pt-6 pb-4 border-b border-gray-100">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                                        <i className="bi bi-person-plus text-blue-600 text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">Invite Team Member</h3>
                                        <p className="text-xs text-gray-500 mt-0.5">They'll receive an email with a join link</p>
                                    </div>
                                </div>
                                <button
                                    onClick={closeInviteModal}
                                    className="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                                >
                                    <i className="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <form onSubmit={handleInvite} className="p-6 space-y-4">
                            {/* Email */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Email Address <span className="text-red-500">*</span>
                                </label>
                                <div className="relative">
                                    <i className="bi bi-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <input
                                        ref={emailInputRef}
                                        type="email"
                                        value={inviteData.email}
                                        onChange={(e) => {
                                            setInviteData({ ...inviteData, email: e.target.value });
                                            if (inviteErrors.email) setInviteErrors({ ...inviteErrors, email: null });
                                        }}
                                        placeholder="colleague@example.com"
                                        required
                                        className={`w-full pl-10 pr-4 py-2.5 rounded-lg border text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${
                                            inviteErrors.email ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                        }`}
                                    />
                                </div>
                                {inviteErrors.email && (
                                    <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                        <i className="bi bi-exclamation-circle"></i>
                                        {inviteErrors.email}
                                    </p>
                                )}
                            </div>

                            {/* Role */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Role <span className="text-red-500">*</span>
                                </label>
                                <div className="relative">
                                    <i className="bi bi-shield absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <select
                                        value={inviteData.role_id}
                                        onChange={(e) => {
                                            setInviteData({ ...inviteData, role_id: e.target.value });
                                            if (inviteErrors.role_id) setInviteErrors({ ...inviteErrors, role_id: null });
                                        }}
                                        required
                                        className={`w-full pl-10 pr-4 py-2.5 rounded-lg border text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white transition-colors ${
                                            inviteErrors.role_id ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                        }`}
                                    >
                                        <option value="">Select a role...</option>
                                        {roles?.map(r => (
                                            <option key={r.id} value={r.id}>{r.label}</option>
                                        ))}
                                    </select>
                                    <i className="bi bi-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                </div>
                                {inviteErrors.role_id && (
                                    <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                        <i className="bi bi-exclamation-circle"></i>
                                        {inviteErrors.role_id}
                                    </p>
                                )}
                            </div>

                            {/* Message (optional) */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Personal Message <span className="text-gray-400 font-normal">(optional)</span>
                                </label>
                                <textarea
                                    value={inviteData.message}
                                    onChange={(e) => setInviteData({ ...inviteData, message: e.target.value })}
                                    placeholder="Add a note to the invitation email..."
                                    rows={3}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none transition-colors"
                                />
                            </div>

                            {/* Role hint */}
                            {inviteData.role_id && (
                                <div className="flex items-start gap-2.5 p-3 rounded-lg bg-blue-50 border border-blue-100">
                                    <i className="bi bi-info-circle text-blue-500 mt-0.5"></i>
                                    <p className="text-xs text-blue-700">
                                        {(() => {
                                            const role = roles?.find(r => r.id == inviteData.role_id);
                                            if (!role) return '';
                                            const hints = {
                                                admin: 'Admins can manage products, orders, and most store settings.',
                                                staff: 'Staff members can handle day-to-day operations like orders and products.',
                                            };
                                            return hints[role.name] || `This person will join as ${role.label}.`;
                                        })()}
                                    </p>
                                </div>
                            )}

                            {/* Actions */}
                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={closeInviteModal}
                                    className="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={inviteProcessing || !inviteData.email || !inviteData.role_id}
                                    className="flex-1 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center justify-center gap-2"
                                >
                                    {inviteProcessing ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Sending...
                                        </>
                                    ) : (
                                        <>
                                            <i className="bi bi-send"></i>
                                            Send Invitation
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Toast Notifications */}
            {toast && (
                <div className="fixed bottom-6 right-6 z-[60] animate-in slide-in-from-bottom-5">
                    <div className={`flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-lg border ${
                        toast.type === 'success'
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                            : 'bg-red-50 border-red-200 text-red-800'
                    }`}>
                        <i className={`bi ${toast.type === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill'} text-lg`}></i>
                        <p className="text-sm font-medium">{toast.message}</p>
                        <button
                            onClick={() => setToast(null)}
                            className="ml-2 p-1 rounded hover:bg-black/5 transition-colors"
                        >
                            <i className="bi bi-x text-sm"></i>
                        </button>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
