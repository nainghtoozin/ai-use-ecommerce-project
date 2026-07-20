import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * Hook for checking permissions in React components.
 * Resolves permissions through the authenticated user's tenant membership.
 */
export function usePermission() {
    const { auth } = usePage().props;
    const user = auth?.user;

    const permissions = useMemo(() => new Set(user?.permissions || []), [user?.permissions]);
    const isSuperAdmin = user?.is_superadmin || false;
    const isOwner = user?.is_owner || false;
    const roles = useMemo(() => new Set(user?.roles || []), [user?.roles]);

    const can = (permission) => {
        // SuperAdmin and Owner bypass all permission checks
        if (isSuperAdmin) return true;
        if (isOwner) return true;
        return permissions.has(permission);
    };

    const cannot = (permission) => !can(permission);

    const hasRole = (role) => {
        if (isSuperAdmin) return true;
        if (isOwner) return true; // Owner implicitly has all roles
        return roles.has(role);
    };

    const hasAnyRole = (...roleList) => {
        if (isSuperAdmin) return true;
        if (isOwner) return true;
        return roleList.some(r => roles.has(r));
    };

    const hasAllPermissions = (permissionList) => {
        if (isSuperAdmin) return true;
        if (isOwner) return true;
        return permissionList.every(p => permissions.has(p));
    };

    const hasAnyPermission = (permissionList) => {
        if (isSuperAdmin) return true;
        if (isOwner) return true;
        return permissionList.some(p => permissions.has(p));
    };

    return {
        user,
        permissions,
        roles,
        isSuperAdmin,
        isOwner,
        can,
        cannot,
        hasRole,
        hasAnyRole,
        hasAllPermissions,
        hasAnyPermission,
    };
}
