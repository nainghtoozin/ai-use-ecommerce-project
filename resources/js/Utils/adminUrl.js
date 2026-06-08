/**
 * Generate a storefront-aware admin URL.
 *
 * When the current page is under /store/{slug}/admin/*, this function
 * automatically prefixes /admin/... paths with the store slug so that
 * links, router calls, and form actions resolve to the correct storefront
 * admin URL instead of the legacy /admin/... URL.
 *
 * On legacy /admin/* pages the path is returned unchanged.
 *
 * Usage:
 *   import { adminUrl } from '@/Utils/adminUrl';
 *   router.get(adminUrl('/admin/orders'), params);
 *   <Link href={adminUrl(`/admin/orders/${order.id}`)}>
 *
 * @param {string} path - Admin-relative path, e.g. '/admin/orders' or '/admin/orders/123'
 * @param {string|null} [storeSlug=null] - Optional explicit store slug. When provided,
 *        the function always prefixes with this slug regardless of the current URL.
 * @returns {string}
 */
export function adminUrl(path, storeSlug = null) {
    const slug = storeSlug || detectStoreSlug();
    if (slug && path.startsWith('/admin/')) {
        return path.replace('/admin/', `/store/${slug}/admin/`);
    }
    return path;
}

function detectStoreSlug() {
    if (typeof window === 'undefined') return null;
    const match = window.location.pathname.match(/^\/store\/([^/]+)\//);
    return match ? match[1] : null;
}
