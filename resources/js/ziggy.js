const routes = {
    'login': '/login',
    'logout': '/logout',
    'storefront.index': '/store/{store_slug}',
    'storefront.products': '/store/{store_slug}/products',
    'storefront.products.show': '/store/{store_slug}/products/{product}',
    'storefront.register': '/store/{store_slug}/register',
    'storefront.login': '/store/{store_slug}/login',
    'storefront.password.request': '/store/{store_slug}/forgot-password',
    'storefront.password.email': '/store/{store_slug}/forgot-password',
    'storefront.password.reset': '/store/{store_slug}/reset-password/{token}',
    'storefront.password.store': '/store/{store_slug}/reset-password',
    'storefront.cart': '/store/{store_slug}/cart',
    'storefront.checkout': '/store/{store_slug}/checkout',
    'storefront.checkout.store': '/store/{store_slug}/checkout',
    'storefront.customer.account': '/store/{store_slug}/customer/account',
    'storefront.customer.orders': '/store/{store_slug}/customer/orders',
    'storefront.customer.orders.show': '/store/{store_slug}/customer/orders/{order}',
    'storefront.customer.orders.cancel': '/store/{store_slug}/customer/orders/{order}/cancel',
    'storefront.customer.orders.upload-payment': '/store/{store_slug}/customer/orders/{order}/upload-payment',
    'storefront.customer.addresses': '/store/{store_slug}/customer/addresses',
    'storefront.customer.addresses.store': '/store/{store_slug}/customer/addresses',
    'storefront.customer.addresses.update': '/store/{store_slug}/customer/addresses/{address}',
    'storefront.customer.addresses.destroy': '/store/{store_slug}/customer/addresses/{address}',
    'orders.cancel': '/orders/{order}/cancel',
    'orders.upload-payment': '/orders/{order}/upload-payment',
    'orders.confirm-payment': '/orders/{order}/confirm-payment',
    'admin.billing': '/admin/billing',
    'admin.billing.renew': '/admin/billing/renew',
    'storefront.admin.billing': '/store/{store_slug}/admin/billing',
    'storefront.admin.billing.renew': '/store/{store_slug}/admin/billing/renew',
    'storefront.admin.expired': '/store/{store_slug}/admin/expired',
    'storefront.admin.suspended': '/store/{store_slug}/admin/suspended',
    'admin.expired': '/admin/expired',
    'admin.suspended': '/admin/suspended',
    'storefront.team.invite.show': '/store/{store_slug}/team/invite/{token}',
    'storefront.team.invite.accept': '/store/{store_slug}/team/invite/{token}',
};

export function route(name, params) {
    const pattern = routes[name];
    if (!pattern) {
        console.warn(`Route "${name}" not found.`);
        return `/${name}`;
    }

    let url = pattern;
    if (params) {
        for (const [key, value] of Object.entries(params)) {
            url = url.replace(`{${key}}`, encodeURIComponent(value));
        }
    }

    return url;
}
