import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Building2, ChevronDown, Check, ArrowRightFromLine } from 'lucide-react';

const roleLabels = {
    owner: 'Owner',
    admin: 'Admin',
    staff: 'Staff',
    customer: 'Customer',
    member: 'Member',
};

function formatRole(roleName) {
    if (!roleName) return 'Member';
    const key = roleName.toLowerCase();
    return roleLabels[key] || roleName.charAt(0).toUpperCase() + roleName.slice(1);
}

export default function WorkspaceSwitcher({ collapsed = false }) {
    const { auth } = usePage().props;
    const memberships = auth?.user?.memberships;
    const [open, setOpen] = useState(false);

    if (!memberships || memberships.length <= 1) return null;

    const current = memberships.find(m => m.is_current) || memberships[0];
    const others = memberships.filter(m => !m.is_current);

    const handleSwitch = (slug) => {
        if (slug === current?.tenant_slug) return;
        setOpen(false);
        router.post(`/workspace/switch/${slug}`);
    };

    if (collapsed) {
        return (
            <div className="relative">
                <button
                    onClick={() => setOpen(!open)}
                    className="w-full flex items-center justify-center px-2 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition-colors"
                    title={`${current?.tenant_name} — ${formatRole(current?.role_name)}`}
                >
                    <Building2 className="w-4 h-4" />
                </button>
                {open && (
                    <>
                        <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                        <div className="absolute left-full ml-2 top-0 w-56 bg-slate-800 border border-white/[0.08] rounded-lg shadow-xl z-50 py-1">
                            <div className="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500 border-b border-white/[0.06]">
                                Workspaces
                            </div>
                            {memberships.map((m) => (
                                <button
                                    key={m.tenant_id}
                                    onClick={() => handleSwitch(m.tenant_slug)}
                                    className={`w-full flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors ${
                                        m.is_current
                                            ? 'text-white bg-white/[0.08]'
                                            : 'text-slate-300 hover:bg-white/[0.04] hover:text-white'
                                    }`}
                                >
                                    <div className="w-7 h-7 rounded flex items-center justify-center text-xs font-bold flex-shrink-0"
                                        style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                        {m.tenant_name?.charAt(0).toUpperCase() || '?'}
                                    </div>
                                    <div className="flex-1 text-left min-w-0">
                                        <div className="text-sm font-medium truncate">{m.tenant_name}</div>
                                        <div className="text-[10px] text-slate-500">Role: {formatRole(m.role_name)}</div>
                                    </div>
                                    {m.is_current && <Check className="w-3.5 h-3.5 flex-shrink-0 text-blue-400" />}
                                </button>
                            ))}
                        </div>
                    </>
                )}
            </div>
        );
    }

    return (
        <div className="border-t border-white/[0.06] pt-2 pb-1.5">
            <div className="relative px-2.5">
                <button
                    onClick={() => setOpen(!open)}
                    className="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-white/[0.06] transition-colors group"
                >
                    <div className="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0"
                        style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                        {current?.tenant_name?.charAt(0).toUpperCase() || '?'}
                    </div>
                    <div className="flex-1 text-left min-w-0">
                        <div className="text-[13px] font-medium truncate leading-tight">{current?.tenant_name}</div>
                        <div className="text-[10px] text-slate-500 truncate leading-tight">Role: {formatRole(current?.role_name)}</div>
                    </div>
                    <ChevronDown className={`w-3.5 h-3.5 flex-shrink-0 text-slate-500 group-hover:text-slate-300 transition-transform ${open ? 'rotate-180' : ''}`} />
                </button>
                {open && (
                    <>
                        <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                        <div className="absolute left-2.5 right-2.5 mt-1 bg-slate-800 border border-white/[0.08] rounded-lg shadow-xl z-50 py-1">
                            <div className="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                Switch workspace
                            </div>
                            {others.map((m) => (
                                <button
                                    key={m.tenant_id}
                                    onClick={() => handleSwitch(m.tenant_slug)}
                                    className="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-300 hover:bg-white/[0.04] hover:text-white transition-colors"
                                >
                                    <div className="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0"
                                        style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                        {m.tenant_name?.charAt(0).toUpperCase() || '?'}
                                    </div>
                                    <div className="flex-1 text-left min-w-0">
                                        <div className="text-sm font-medium truncate">{m.tenant_name}</div>
                                        <div className="text-[10px] text-slate-500">Role: {formatRole(m.role_name)}</div>
                                    </div>
                                    <ArrowRightFromLine className="w-3.5 h-3.5 flex-shrink-0 text-slate-600" />
                                </button>
                            ))}
                            {others.length === 0 && (
                                <div className="px-3 py-3 text-xs text-slate-500 text-center">This is your only workspace</div>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
