import { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import ShopLayout from '@/Layouts/ShopLayout';
import { timeAgo, notificationIcon, groupNotificationsByDate } from '@/Utils/helpers';

function MarkReadIcon() {
    return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
        </svg>
    );
}

export default function NotificationsIndex({ notifications, unread_count }) {
    const [filter, setFilter] = useState('all');
    const [readIds, setReadIds] = useState(new Set());
    const [markingAll, setMarkingAll] = useState(false);
    const [loadingId, setLoadingId] = useState(null);

    const allNotifications = notifications?.data || [];
    const totalCount = notifications?.total || notifications?.data?.length || 0;
    const filtered = filter === 'unread'
        ? allNotifications.filter((n) => !n.read_at && !readIds.has(n.id))
        : allNotifications;

    const markAsRead = useCallback(async (id, actionUrl) => {
        setLoadingId(id);
        setReadIds((prev) => new Set(prev).add(id));
        try {
            await axios.patch(`/notifications/${id}/read`);
        } catch (err) {
            setReadIds((prev) => {
                const next = new Set(prev);
                next.delete(id);
                return next;
            });
        } finally {
            setLoadingId(null);
        }
        if (actionUrl) {
            router.visit(actionUrl);
        }
    }, []);

    const markAllAsRead = useCallback(async () => {
        setMarkingAll(true);
        const allIds = allNotifications.map((n) => n.id);
        setReadIds((prev) => new Set([...prev, ...allIds]));
        try {
            await axios.patch('/notifications/read-all');
            router.reload({ only: ['notifications', 'unread_count'] });
        } catch (err) {
            setReadIds((prev) => {
                const next = new Set(prev);
                allIds.forEach((id) => next.delete(id));
                return next;
            });
        } finally {
            setMarkingAll(false);
        }
    }, [allNotifications]);

    const isRead = (n) => n.read_at || readIds.has(n.id);
    const effectiveUnread = allNotifications.filter((n) => !isRead(n)).length;

    const grouped = groupNotificationsByDate(
        filtered.map((n) => ({
            ...n,
            createdAt: n.created_at,
            isRead: isRead(n),
        }))
    );

    const tabs = [
        { key: 'all', label: 'All', count: allNotifications.length },
        { key: 'unread', label: 'Unread', count: effectiveUnread },
    ];

    return (
        <ShopLayout>
            <Head title="Notifications" />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Notifications</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {effectiveUnread > 0
                                ? `You have ${effectiveUnread} unread notification${effectiveUnread !== 1 ? 's' : ''}`
                                : 'You\'re all caught up!'}
                        </p>
                    </div>
                    {effectiveUnread > 0 && (
                        <button
                            onClick={markAllAsRead}
                            disabled={markingAll}
                            className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-150 shadow-sm hover:shadow-md active:scale-[0.98]"
                        >
                            <MarkReadIcon />
                            {markingAll ? 'Marking...' : 'Mark all as read'}
                        </button>
                    )}
                </div>

                <div className="flex gap-1 mb-6 p-1 bg-gray-100 rounded-xl w-fit">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setFilter(tab.key)}
                            disabled={tab.key === 'unread' && tab.count === 0}
                            className={`relative px-4 py-2 text-sm font-medium rounded-lg transition-all duration-150 ${
                                filter === tab.key
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed'
                            }`}
                        >
                            {tab.label}
                            {tab.count > 0 && (
                                <span className={`ml-1.5 text-xs px-1.5 py-0.5 rounded-full ${
                                    filter === tab.key ? 'bg-blue-50 text-blue-600' : 'bg-gray-200 text-gray-500'
                                }`}>
                                    {tab.count}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    {allNotifications.length === 0 ? (
                        <div className="text-center py-20 px-4">
                            <div className="mx-auto w-20 h-20 bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl flex items-center justify-center mb-5 shadow-inner">
                                <svg className="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.2}>
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900">No notifications yet</h3>
                            <p className="text-sm text-gray-500 mt-1.5 max-w-sm mx-auto leading-relaxed">
                                When you place orders and receive updates, your notifications will show up here.
                            </p>
                            <Link
                                href="/"
                                className="inline-flex items-center gap-2 mt-6 px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-all shadow-sm hover:shadow-md active:scale-[0.98]"
                            >
                                Start shopping
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    ) : grouped.length === 0 ? (
                        <div className="text-center py-20 px-4">
                            <div className="mx-auto w-20 h-20 bg-gradient-to-br from-green-50 to-green-100 rounded-2xl flex items-center justify-center mb-5 shadow-inner">
                                <svg className="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900">All caught up!</h3>
                            <p className="text-sm text-gray-500 mt-1.5">
                                {filter === 'unread' ? 'No unread notifications.' : 'No notifications to show.'}
                            </p>
                        </div>
                    ) : (
                        <div>
                            {grouped.map(([label, items]) => (
                                <div key={label}>
                                    <div className="sticky top-0 px-4 sm:px-6 py-2.5 bg-gray-50/90 backdrop-blur-sm border-b border-gray-100">
                                        <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{label}</span>
                                    </div>
                                    {items.map((notification, idx) => {
                                        const read = notification.isRead;
                                        const loading = loadingId === notification.id;
                                        return (
                                            <div
                                                key={notification.id}
                                                onClick={() => !loading && markAsRead(notification.id, notification.action_url)}
                                                className={`relative flex items-start gap-4 sm:gap-5 px-4 sm:px-6 py-4 sm:py-5 cursor-pointer transition-all duration-150 hover:bg-gray-50/80 active:bg-gray-100 ${
                                                    !read ? 'bg-white' : 'bg-white'
                                                } ${idx < items.length - 1 ? 'border-b border-gray-100' : ''}`}
                                            >
                                                <div className={`relative flex-shrink-0 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-xl sm:text-2xl shadow-sm transition-all duration-150 ${
                                                    !read
                                                        ? 'bg-white ring-1 ring-gray-200 ring-offset-1'
                                                        : 'bg-gray-50 ring-1 ring-gray-100'
                                                }`}>
                                                    {notification.icon || notificationIcon(notification.title)}
                                                    {!read && (
                                                        <span className="absolute -top-0.5 -right-0.5 w-3 h-3 bg-blue-500 border-2 border-white rounded-full" />
                                                    )}
                                                </div>

                                                <div className="flex-1 min-w-0 pt-0.5">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex-1 min-w-0">
                                                            <p className={`text-sm sm:text-base leading-snug ${!read ? 'font-semibold text-gray-900' : 'text-gray-600'}`}>
                                                                {notification.title}
                                                            </p>
                                                            <p className="text-xs sm:text-sm text-gray-400 mt-1 leading-relaxed line-clamp-2">
                                                                {notification.message}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2 flex-shrink-0">
                                                            <span className="text-[11px] sm:text-xs text-gray-400 whitespace-nowrap">
                                                                {timeAgo(notification.created_at)}
                                                            </span>
                                                            {!read && (
                                                                <span className="w-1.5 h-1.5 bg-blue-500 rounded-full flex-shrink-0" />
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {loading && (
                                                    <div className="absolute inset-0 bg-white/60 flex items-center justify-center">
                                                        <div className="w-5 h-5 border-2 border-blue-200 border-t-blue-500 rounded-full animate-spin" />
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {notifications?.next_page_url && allNotifications.length > 0 && (
                    <div className="mt-8 flex justify-center">
                        <Link
                            href={notifications.next_page_url}
                            preserveScroll
                            className="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-xl transition-all duration-150 hover:shadow-sm active:scale-[0.98]"
                        >
                            Load more notifications
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                                <path d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </Link>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
