import { usePermission } from '@/Hooks/usePermission';

/**
 * Conditional rendering component based on permissions.
 *
 * Usage:
 *   <Can permission="products.create">
 *     <button>Create Product</button>
 *   </Can>
 *
 *   <Can permission="orders.view" fallback={<p>No access</p>}>
 *     <OrderList />
 *   </Can>
 *
 *   <Can role="admin">
 *     <AdminPanel />
 *   </Can>
 *
 *   <Can owner>
 *     <OwnerSettings />
 *   </Can>
 */
export default function Can({ permission, role, owner, superadmin, children, fallback = null }) {
    const { can, hasRole, isOwner, isSuperAdmin } = usePermission();

    if (owner && !isOwner && !isSuperAdmin) {
        return fallback;
    }

    if (superadmin && !isSuperAdmin) {
        return fallback;
    }

    if (role && !hasRole(role)) {
        return fallback;
    }

    if (permission && !can(permission)) {
        return fallback;
    }

    return children;
}
