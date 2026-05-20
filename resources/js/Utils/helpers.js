export function assetUrl(path, placeholder = true) {
    if (!path) {
        if (placeholder) {
            return 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><rect fill="%23e5e7eb" width="400" height="400"/><g fill="%239ca3af"><path d="M175 130h50v50h-50z"/><circle cx="140" cy="140" r="20"/><path d="M150 200h100v20h-100zM150 240h80v20h-80zM150 280h60v20h-60z"/></g><text x="200" y="350" text-anchor="middle" fill="%239ca3af" font-family="sans-serif" font-size="14">No Image</text></svg>');
        }
        return null;
    }
    if (path instanceof File) {
        return URL.createObjectURL(path);
    }
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/storage/') || path.startsWith('data:') || path.startsWith('blob:')) return path;
    return `/storage/${path}`;
}

export function formatCurrency(amount, currency = 'MMK') {
    if (!amount) return `0 ${currency}`;
    return `${Number(amount).toLocaleString()} ${currency}`;
}

export function formatDate(date, options = {}) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...options,
    });
}

export function timeAgo(date) {
    if (!date) return '';
    const now = new Date();
    const past = new Date(date);
    const diffMs = now - past;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 10) return 'just now';
    if (diffSec < 60) return `${diffSec}s ago`;
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHour < 24) return `${diffHour}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    if (diffDay < 30) return `${Math.floor(diffDay / 7)}w ago`;
    return formatDate(date);
}

export function notificationIcon(title) {
    if (!title) return '🔔';
    const emoji = title.match(/^(\p{Emoji}|[\u{1F000}-\u{1FFFF}]|[\u{2700}-\u{27BF}])/u);
    if (emoji) return emoji[1];
    if (title.includes('New Order') || title.includes('Order Received')) return '🛒';
    if (title.includes('Order Confirmed') || title.includes('placed')) return '✅';
    if (title.includes('Payment Verified')) return '💳';
    if (title.includes('Payment Rejected')) return '❌';
    if (title.includes('Payment Confirmed')) return '💳';
    if (title.includes('Shipped')) return '🚚';
    if (title.includes('Delivered')) return '📦';
    if (title.includes('Cancelled')) return '❌';
    return '🔔';
}

export function notificationType(title) {
    if (!title) return 'general';
    if (title.includes('Order Received') || title.includes('New Order')) return 'new_order';
    if (title.includes('Order Confirmed') || title.includes('placed')) return 'order_placed';
    if (title.includes('Payment Verified')) return 'payment_verified';
    if (title.includes('Payment Rejected')) return 'payment_rejected';
    if (title.includes('Payment Confirmed')) return 'payment_confirmed';
    if (title.includes('Shipped')) return 'shipped';
    if (title.includes('Delivered')) return 'delivered';
    if (title.includes('Cancelled')) return 'cancelled';
    return 'general';
}

export function notificationColor(type) {
    const colors = {
        new_order: 'bg-blue-500',
        order_placed: 'bg-green-500',
        payment_verified: 'bg-emerald-500',
        payment_rejected: 'bg-red-500',
        payment_confirmed: 'bg-indigo-500',
        shipped: 'bg-purple-500',
        delivered: 'bg-green-600',
        cancelled: 'bg-gray-500',
        general: 'bg-blue-400',
    };
    return colors[type] || colors.general;
}

export function groupNotificationsByDate(notifications) {
    const groups = { Today: [], Yesterday: [], Earlier: [] };
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    notifications.forEach((n) => {
        const created = new Date(n.createdAt || n.created_at);
        const createdDay = new Date(created.getFullYear(), created.getMonth(), created.getDate());
        const diffDays = Math.floor((today - createdDay) / (1000 * 60 * 60 * 24));

        if (diffDays === 0) groups.Today.push(n);
        else if (diffDays === 1) groups.Yesterday.push(n);
        else groups.Earlier.push(n);
    });

    return Object.entries(groups).filter(([, items]) => items.length > 0);
}
