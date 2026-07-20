import { useState, useCallback } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

function SkeletonRow() {
    return (
        <tr className="animate-pulse">
            <td className="px-5 py-4"><div className="h-3.5 w-40 bg-gray-200 rounded" /></td>
            <td className="px-5 py-4"><div className="h-5 w-16 bg-gray-200 rounded-full" /></td>
            <td className="px-5 py-4"><div className="h-3.5 w-28 bg-gray-200 rounded" /></td>
            <td className="px-5 py-4"><div className="h-3.5 w-20 bg-gray-200 rounded" /></td>
            <td className="px-5 py-4"><div className="h-3.5 w-20 bg-gray-200 rounded" /></td>
            <td className="px-5 py-4"><div className="h-5 w-16 bg-gray-200 rounded-full" /></td>
            <td className="px-5 py-4"><div className="flex justify-end gap-2"><div className="h-7 w-7 bg-gray-200 rounded-lg" /><div className="h-7 w-7 bg-gray-200 rounded-lg" /></div></td>
        </tr>
    );
}

function LoadingSkeleton({ rows = 5 }) {
    return (
        <tbody className="divide-y divide-gray-100">
            {Array.from({ length: rows }).map((_, i) => <SkeletonRow key={i} />)}
        </tbody>
    );
}

function EmptyState() {
    return (
        <div className="text-center py-20">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-100 mb-4">
                <i className="bi bi-envelope-paper text-3xl text-gray-300"></i>
            </div>
            <p className="text-sm font-medium text-gray-600">No pending invitations</p>
            <p className="text-xs text-gray-400 mt-1">Invite team members from the Team dashboard.</p>
        </div>
    );
}

function RoleBadge({ role }) {
    const styles = {
        admin: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        staff: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        customer: 'bg-gray-50 text-gray-600 ring-gray-500/20',
    };
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ring-1 ring-inset ${styles[role] || 'bg-gray-50 text-gray-600 ring-gray-500/20'}`}>
            {role ? role.charAt(0).toUpperCase() + role.slice(1) : '—'}
        </span>
    );
}

function StatusBadge({ isExpired, isPending }) {
    if (isExpired) {
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">Expired</span>;
    }
    if (isPending) {
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20">Pending</span>;
    }
    return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/20">—</span>;
}

export default function TeamInvitations({ invitations, filters }) {
    const { can } = usePermission();
    const canManage = can('users.view');

    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [loading, setLoading] = useState(false);

    const handleSearch = useCallback((e) => {
        e.preventDefault();
        setLoading(true);
        router.get(adminUrl('/admin/team/invitations'), {
            search, status: statusFilter,
        }, {
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, [search, statusFilter]);

    const handleFilter = useCallback((value) => {
        setStatusFilter(value);
        setLoading(true);
        router.get(adminUrl('/admin/team/invitations'), {
            search, status: value,
        }, {
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, [search]);

    function handleResend(invitation) {
        if (confirm(`Resend invitation to ${invitation.email}?`)) {
            router.post(adminUrl(`/admin/team/invitations/${invitation.id}/resend`));
        }
    }

    function handleCancel(invitation) {
        if (confirm(`Cancel invitation for ${invitation.email}? They will no longer be able to accept it.`)) {
            router.delete(adminUrl(`/admin/team/invitations/${invitation.id}`));
        }
    }

    const data = invitations?.data || [];
    const showSkeleton = loading;
    const isEmpty = !loading && data.length === 0;

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Pending Invitations</h2>}>
            <Head title="Pending Invitations" />

            <div className="p-6 lg:p-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Invitations</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {invitations?.total ? `${invitations.total} pending invitation${invitations.total !== 1 ? 's' : ''}` : 'Manage pending team invitations'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={adminUrl('/admin/team')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors"
                        >
                            <i className="bi bi-arrow-left"></i>
                            Back to Team
                        </Link>
                        {canManage && (
                            <Link
                                href={adminUrl('/admin/team')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors shadow-sm"
                            >
                                <i className="bi bi-plus-lg"></i>
                                Invite Member
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div className="p-4 border-b border-gray-100">
                        <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                            <div className="flex-1 relative">
                                <i className="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by email..."
                                    className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow"
                                />
                            </div>
                            <div className="flex gap-2">
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilter(e.target.value)}
                                    className="px-3 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-[120px]"
                                >
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="expired">Expired</option>
                                    <option value="revoked">Revoked</option>
                                    <option value="accepted">Accepted</option>
                                </select>
                                <button
                                    type="submit"
                                    className="px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors"
                                >
                                    <i className="bi bi-search mr-1"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Table */}
                    <div>
                        {showSkeleton && (
                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="text-left text-xs text-gray-500 uppercase tracking-wider bg-gray-50/80">
                                            <th className="px-5 py-3 font-semibold">Email</th>
                                            <th className="px-5 py-3 font-semibold">Role</th>
                                            <th className="px-5 py-3 font-semibold">Invited By</th>
                                            <th className="px-5 py-3 font-semibold">Sent</th>
                                            <th className="px-5 py-3 font-semibold">Expires</th>
                                            <th className="px-5 py-3 font-semibold">Status</th>
                                            <th className="px-5 py-3 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <LoadingSkeleton rows={5} />
                                </table>
                            </div>
                        )}

                        {showSkeleton && (
                            <div className="md:hidden space-y-3 p-4">
                                {[1, 2, 3].map(i => (
                                    <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />
                                ))}
                            </div>
                        )}

                        {!showSkeleton && isEmpty && <EmptyState />}

                        {!showSkeleton && !isEmpty && (
                            <>
                                {/* Desktop Table */}
                                <div className="hidden md:block overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="text-left text-xs text-gray-500 uppercase tracking-wider bg-gray-50/80">
                                                <th className="px-5 py-3 font-semibold">Email</th>
                                                <th className="px-5 py-3 font-semibold">Role</th>
                                                <th className="px-5 py-3 font-semibold">Invited By</th>
                                                <th className="px-5 py-3 font-semibold">Sent</th>
                                                <th className="px-5 py-3 font-semibold">Expires</th>
                                                <th className="px-5 py-3 font-semibold">Status</th>
                                                <th className="px-5 py-3 font-semibold text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {data.map((invitation) => (
                                                <tr key={invitation.id} className="hover:bg-gray-50/60 transition-colors group">
                                                    <td className="px-5 py-3.5">
                                                        <div className="flex items-center gap-3">
                                                            <div className="w-9 h-9 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white">
                                                                <i className="bi bi-envelope text-sm"></i>
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-medium text-gray-900 truncate">{invitation.email}</p>
                                                                {invitation.is_expired && (
                                                                    <p className="text-[11px] text-red-500 flex items-center gap-1 mt-0.5"><i className="bi bi-clock"></i> Expired</p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-3.5"><RoleBadge role={invitation.role} /></td>
                                                    <td className="px-5 py-3.5 text-sm text-gray-500 whitespace-nowrap">{invitation.invited_by || '—'}</td>
                                                    <td className="px-5 py-3.5 text-sm text-gray-500 whitespace-nowrap">{invitation.invited_at || '—'}</td>
                                                    <td className="px-5 py-3.5 text-sm whitespace-nowrap">
                                                        <span className={invitation.is_expired ? 'text-red-500' : 'text-gray-500'}>{invitation.expires_at || '—'}</span>
                                                    </td>
                                                    <td className="px-5 py-3.5"><StatusBadge isExpired={invitation.is_expired} isPending={!invitation.is_expired} /></td>
                                                    <td className="px-5 py-3.5 text-right">
                                                        {canManage && (
                                                            <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                {!invitation.is_expired && (
                                                                    <button onClick={() => handleResend(invitation)} className="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Resend">
                                                                        <i className="bi bi-send text-sm"></i>
                                                                    </button>
                                                                )}
                                                                <button onClick={() => handleCancel(invitation)} className="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Cancel">
                                                                    <i className="bi bi-x-circle text-sm"></i>
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
                                    {data.map((invitation) => (
                                        <div key={invitation.id} className="p-4 hover:bg-gray-50 transition-colors">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white flex-shrink-0">
                                                        <i className="bi bi-envelope text-sm"></i>
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium text-gray-900 truncate">{invitation.email}</p>
                                                        <div className="flex items-center gap-2 mt-1">
                                                            <RoleBadge role={invitation.role} />
                                                            <StatusBadge isExpired={invitation.is_expired} isPending={!invitation.is_expired} />
                                                        </div>
                                                    </div>
                                                </div>
                                                {canManage && (
                                                    <div className="flex items-center gap-1 flex-shrink-0">
                                                        {!invitation.is_expired && (
                                                            <button onClick={() => handleResend(invitation)} className="p-2 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                                                <i className="bi bi-send text-sm"></i>
                                                            </button>
                                                        )}
                                                        <button onClick={() => handleCancel(invitation)} className="p-2 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                                                            <i className="bi bi-x-circle text-sm"></i>
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-3 mt-2 text-[11px] text-gray-400">
                                                <span>By {invitation.invited_by || '—'}</span>
                                                <span>•</span>
                                                <span>Sent {invitation.invited_at || '—'}</span>
                                                <span>•</span>
                                                <span className={invitation.is_expired ? 'text-red-400' : ''}>Exp {invitation.expires_at || '—'}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}
                    </div>

                    {/* Pagination */}
                    {invitations?.links && invitations.links.length > 3 && (
                        <div className="px-5 py-4 border-t border-gray-100 flex flex-wrap items-center gap-1.5">
                            {invitations.links.map((link, i) => (
                                <button
                                    key={i}
                                    onClick={() => router.get(link.url, {}, { preserveState: true })}
                                    disabled={!link.url}
                                    className={`min-w-[36px] h-9 px-3 text-sm rounded-lg font-medium transition-colors ${
                                        link.active
                                            ? 'bg-blue-600 text-white shadow-sm'
                                            : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                                    } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                            {invitations.total > 0 && (
                                <span className="ml-auto text-xs text-gray-400">
                                    Showing {invitations.from}–{invitations.to} of {invitations.total}
                                </span>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
