import { useState, useCallback } from 'react';
import { Link, router, Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import MemberDrawer from '@/Components/MemberDrawer';

function SkeletonRow() {
    return (
        <tr className="animate-pulse">
            <td className="px-5 py-4">
                <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-full bg-gray-200" />
                    <div className="space-y-1.5">
                        <div className="h-3.5 w-24 bg-gray-200 rounded" />
                        <div className="h-2.5 w-32 bg-gray-100 rounded" />
                    </div>
                </div>
            </td>
            <td className="px-5 py-4"><div className="h-5 w-16 bg-gray-200 rounded-full" /></td>
            <td className="px-5 py-4"><div className="h-5 w-14 bg-gray-200 rounded-full" /></td>
            <td className="px-5 py-4"><div className="h-3.5 w-20 bg-gray-200 rounded" /></td>
            <td className="px-5 py-4"><div className="h-3.5 w-20 bg-gray-200 rounded" /></td>
            <td className="px-5 py-4"><div className="flex justify-end gap-2"><div className="h-7 w-7 bg-gray-200 rounded-lg" /><div className="h-7 w-7 bg-gray-200 rounded-lg" /><div className="h-7 w-7 bg-gray-200 rounded-lg" /></div></td>
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

function EmptyState({ message = 'No members found', sub = 'Invite team members to get started.' }) {
    return (
        <div className="text-center py-20">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-100 mb-4">
                <i className="bi bi-people text-3xl text-gray-300"></i>
            </div>
            <p className="text-sm font-medium text-gray-600">{message}</p>
            <p className="text-xs text-gray-400 mt-1">{sub}</p>
        </div>
    );
}

function StatusBadge({ status }) {
    const styles = {
        active:    'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        suspended: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        removed:   'bg-red-50 text-red-700 ring-red-600/20',
        pending:   'bg-blue-50 text-blue-700 ring-blue-600/20',
    };
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ring-1 ring-inset ${styles[status] || 'bg-gray-50 text-gray-600 ring-gray-500/20'}`}>
            {status?.charAt(0).toUpperCase() + status?.slice(1)}
        </span>
    );
}

function RoleBadge({ role, isOwner }) {
    if (isOwner) {
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20">Owner</span>;
    }
    const styles = {
        admin:    'bg-blue-50 text-blue-700 ring-blue-600/20',
        staff:    'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        customer: 'bg-gray-50 text-gray-600 ring-gray-500/20',
    };
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ring-1 ring-inset ${styles[role] || 'bg-gray-50 text-gray-600 ring-gray-500/20'}`}>
            {role ? role.charAt(0).toUpperCase() + role.slice(1) : '—'}
        </span>
    );
}

function ActionDropdown({ member, canManage, onSuspend, onRestore, onRemove, onOpenDrawer }) {
    const [open, setOpen] = useState(false);

    if (!canManage || member.is_owner) return null;

    return (
        <div className="relative">
            <button
                onClick={() => setOpen(!open)}
                className="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
            >
                <i className="bi bi-three-dots-vertical text-sm"></i>
            </button>
            {open && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1.5 z-20">
                        <button
                            onClick={() => { setOpen(false); onOpenDrawer(member.id); }}
                            className="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors w-full"
                        >
                            <i className="bi bi-eye text-gray-400"></i> View Details
                        </button>
                        <Link
                            href={adminUrl(`/admin/team/${member.id}/edit`)}
                            className="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                            onClick={() => setOpen(false)}
                        >
                            <i className="bi bi-pencil text-gray-400"></i> Edit Role
                        </Link>
                        <div className="my-1 border-t border-gray-100" />
                        {member.status === 'active' ? (
                            <button
                                onClick={() => { setOpen(false); onSuspend(member); }}
                                className="flex items-center gap-2.5 px-3 py-2 text-sm text-amber-600 hover:bg-amber-50 w-full transition-colors"
                            >
                                <i className="bi bi-pause-circle"></i> Suspend
                            </button>
                        ) : (
                            <button
                                onClick={() => { setOpen(false); onRestore(member); }}
                                className="flex items-center gap-2.5 px-3 py-2 text-sm text-emerald-600 hover:bg-emerald-50 w-full transition-colors"
                            >
                                <i className="bi bi-play-circle"></i> Restore
                            </button>
                        )}
                        <div className="my-1 border-t border-gray-100" />
                        <button
                            onClick={() => { setOpen(false); onRemove(member); }}
                            className="flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 w-full transition-colors"
                        >
                            <i className="bi bi-trash"></i> Remove
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}

export default function TeamMembers({ members, filters, roles }) {
    const { auth } = usePage().props;
    const isOwner = auth?.user?.is_owner;
    const canManage = isOwner || auth?.user?.permissions?.includes('users.view');

    const [search, setSearch] = useState(filters?.search || '');
    const [roleFilter, setRoleFilter] = useState(filters?.role || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [loading, setLoading] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [selectedMemberId, setSelectedMemberId] = useState(null);

    const handleSearch = useCallback((e) => {
        e.preventDefault();
        setLoading(true);
        router.get(adminUrl('/admin/team/members'), {
            search, role: roleFilter, status: statusFilter,
        }, {
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, [search, roleFilter, statusFilter]);

    const handleFilter = useCallback((type, value) => {
        const params = { search, role: roleFilter, status: statusFilter, [type]: value };
        if (type === 'role') setRoleFilter(value);
        if (type === 'status') setStatusFilter(value);
        setLoading(true);
        router.get(adminUrl('/admin/team/members'), params, {
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, [search, roleFilter, statusFilter]);

    function handleSuspend(member) {
        if (confirm(`Suspend ${member.name}? They will not be able to log in.`)) {
            router.post(adminUrl(`/admin/team/${member.id}/suspend`));
        }
    }

    function handleRestore(member) {
        router.post(adminUrl(`/admin/team/${member.id}/restore`));
    }

    function handleRemove(member) {
        if (confirm(`Remove ${member.name} from the team? This action cannot be undone.`)) {
            router.delete(adminUrl(`/admin/team/${member.id}`));
        }
    }

    function openDrawer(memberId) {
        setSelectedMemberId(memberId);
        setDrawerOpen(true);
    }

    function closeDrawer() {
        setDrawerOpen(false);
        setSelectedMemberId(null);
    }

    const data = members?.data || [];
    const showSkeleton = loading;
    const isEmpty = !loading && data.length === 0;

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Team Members</h2>}>
            <Head title="Team Members" />

            <div className="p-6 lg:p-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Members</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {members?.total ? `${members.total} member${members.total !== 1 ? 's' : ''} total` : 'Manage your team members'}
                        </p>
                    </div>
                    {canManage && (
                        <Link
                            href={adminUrl('/admin/team')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors shadow-sm"
                        >
                            <i className="bi bi-arrow-left"></i>
                            Back to Team
                        </Link>
                    )}
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
                                    placeholder="Search by name or email..."
                                    className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow"
                                />
                            </div>
                            <div className="flex gap-2">
                                <select
                                    value={roleFilter}
                                    onChange={(e) => handleFilter('role', e.target.value)}
                                    className="px-3 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-[120px]"
                                >
                                    <option value="">All Roles</option>
                                    {roles?.map(r => (
                                        <option key={r.id} value={r.name}>{r.label}</option>
                                    ))}
                                    <option value="customer">Customer</option>
                                </select>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilter('status', e.target.value)}
                                    className="px-3 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-[120px]"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="removed">Removed</option>
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
                                            <th className="px-5 py-3 font-semibold">Member</th>
                                            <th className="px-5 py-3 font-semibold">Role</th>
                                            <th className="px-5 py-3 font-semibold">Status</th>
                                            <th className="px-5 py-3 font-semibold">Joined</th>
                                            <th className="px-5 py-3 font-semibold">Last Login</th>
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
                                                <th className="px-5 py-3 font-semibold">Member</th>
                                                <th className="px-5 py-3 font-semibold">Role</th>
                                                <th className="px-5 py-3 font-semibold">Status</th>
                                                <th className="px-5 py-3 font-semibold">Joined</th>
                                                <th className="px-5 py-3 font-semibold">Last Login</th>
                                                <th className="px-5 py-3 font-semibold text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {data.map((member) => (
                                                <tr key={member.id} className="hover:bg-gray-50/60 transition-colors group">
                                                    <td className="px-5 py-3.5">
                                                        <div className="flex items-center gap-3">
                                                            {member.avatar ? (
                                                                <img src={member.avatar} alt="" className="w-9 h-9 rounded-full object-cover ring-2 ring-white shadow-sm" />
                                                            ) : (
                                                                <div className="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-sm font-semibold ring-2 ring-white shadow-sm">
                                                                    {member.name?.charAt(0)?.toUpperCase() || '?'}
                                                                </div>
                                                            )}
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-medium text-gray-900 truncate">{member.name}</p>
                                                                <p className="text-xs text-gray-500 truncate">{member.email}</p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-3.5"><RoleBadge role={member.role} isOwner={member.is_owner} /></td>
                                                    <td className="px-5 py-3.5"><StatusBadge status={member.status} /></td>
                                                    <td className="px-5 py-3.5 text-sm text-gray-500 whitespace-nowrap">{member.joined_at || '—'}</td>
                                                    <td className="px-5 py-3.5 text-sm text-gray-500 whitespace-nowrap">{member.last_login_at || '—'}</td>
                                                    <td className="px-5 py-3.5 text-right">
                                                        <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <button onClick={() => openDrawer(member.id)} className="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="View">
                                                                <i className="bi bi-eye text-sm"></i>
                                                            </button>
                                                            {canManage && !member.is_owner && (
                                                                <>
                                                                    <Link href={adminUrl(`/admin/team/${member.id}/edit`)} className="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit">
                                                                        <i className="bi bi-pencil text-sm"></i>
                                                                    </Link>
                                                                    {member.status === 'active' ? (
                                                                        <button onClick={() => handleSuspend(member)} className="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors" title="Suspend">
                                                                            <i className="bi bi-pause-circle text-sm"></i>
                                                                        </button>
                                                                    ) : (
                                                                        <button onClick={() => handleRestore(member)} className="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Restore">
                                                                            <i className="bi bi-play-circle text-sm"></i>
                                                                        </button>
                                                                    )}
                                                                    <button onClick={() => handleRemove(member)} className="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Remove">
                                                                        <i className="bi bi-trash text-sm"></i>
                                                                    </button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Mobile Cards */}
                                <div className="md:hidden divide-y divide-gray-100">
                                    {data.map((member) => (
                                        <div key={member.id} className="p-4 hover:bg-gray-50 transition-colors">
                                            <div className="flex items-start justify-between gap-3">
                                                <button onClick={() => openDrawer(member.id)} className="flex items-center gap-3 min-w-0 text-left">
                                                    {member.avatar ? (
                                                        <img src={member.avatar} alt="" className="w-10 h-10 rounded-full object-cover ring-2 ring-white shadow-sm flex-shrink-0" />
                                                    ) : (
                                                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-sm font-semibold ring-2 ring-white shadow-sm flex-shrink-0">
                                                            {member.name?.charAt(0)?.toUpperCase() || '?'}
                                                        </div>
                                                    )}
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium text-gray-900 truncate">{member.name}</p>
                                                        <p className="text-xs text-gray-500 truncate">{member.email}</p>
                                                        <div className="flex items-center gap-2 mt-1.5">
                                                            <RoleBadge role={member.role} isOwner={member.is_owner} />
                                                            <StatusBadge status={member.status} />
                                                        </div>
                                                    </div>
                                                </button>
                                                <ActionDropdown
                                                    member={member}
                                                    canManage={canManage}
                                                    onSuspend={handleSuspend}
                                                    onRestore={handleRestore}
                                                    onRemove={handleRemove}
                                                    onOpenDrawer={openDrawer}
                                                />
                                            </div>
                                            <div className="flex items-center gap-4 mt-2 text-[11px] text-gray-400">
                                                <span>Joined {member.joined_at || '—'}</span>
                                                <span>Login {member.last_login_at || '—'}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}
                    </div>

                    {/* Pagination */}
                    {members?.links && members.links.length > 3 && (
                        <div className="px-5 py-4 border-t border-gray-100 flex flex-wrap items-center gap-1.5">
                            {members.links.map((link, i) => (
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
                            {members.total > 0 && (
                                <span className="ml-auto text-xs text-gray-400">
                                    Showing {members.from}–{members.to} of {members.total}
                                </span>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <MemberDrawer
                open={drawerOpen}
                onClose={closeDrawer}
                memberId={selectedMemberId}
            />
        </AdminLayout>
    );
}
