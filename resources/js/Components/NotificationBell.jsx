import { useState, useRef, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import usePusherNotifications from '@/Hooks/usePusherNotifications';
import { timeAgo, groupNotificationsByDate } from '@/Utils/helpers';
import { adminUrl } from '@/Utils/adminUrl';

function BellIcon({ hasUnread }) {
    return (
        <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            {hasUnread && <circle cx="19" cy="5" r="2.5" fill="currentColor" stroke="none" />}
        </svg>
    );
}

export default function NotificationBell({ isAdmin = false }) {
    const { notifications, unreadCount, loaded, markAsRead, markAllAsRead, clearAll, navigateToNotification } = usePusherNotifications();
    const [open, setOpen] = useState(false);
    const dropdownRef = useRef(null);

    useEffect(() => {
        function handleClickOutside(e) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setOpen(false);
            }
        }
        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    const handleClick = (notification) => {
        if (!notification.isRead) markAsRead(notification.id);
        navigateToNotification(notification);
        setOpen(false);
    };

    const grouped = groupNotificationsByDate(notifications);

    const displayNotifications = notifications.slice(0, 10);

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setOpen(!open)}
                className={`relative p-2 rounded-lg transition-all duration-200 ${
                    open
                        ? 'bg-blue-50 text-blue-600'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                }`}
                title="Notifications"
                aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
            >
                <BellIcon hasUnread={unreadCount > 0} />
                {unreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-0.5 shadow-sm ring-2 ring-white">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-[22rem] sm:w-96 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden origin-top-right">
                    <div className="px-4 py-3 border-b border-gray-100 bg-white flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <h3 className="font-semibold text-gray-900 text-sm">Notifications</h3>
                            {unreadCount > 0 && (
                                <span className="text-[11px] font-medium bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">
                                    {unreadCount} new
                                </span>
                            )}
                        </div>
                        <div className="flex items-center gap-1">
                            {!loaded && (
                                <span className="text-[11px] text-gray-400 animate-pulse">Loading...</span>
                            )}
                            {notifications.length > 0 && (
                                <button
                                    onClick={markAllAsRead}
                                    className="text-[11px] font-medium text-blue-600 hover:text-blue-700 px-2 py-1 rounded-md hover:bg-blue-50 transition-colors"
                                >
                                    Mark all read
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="max-h-[28rem] overflow-y-auto divide-y divide-gray-50">
                        {!loaded ? (
                            <div className="py-16 text-center">
                                <div className="inline-block w-8 h-8 border-[3px] border-blue-200 border-t-blue-500 rounded-full animate-spin" />
                                <p className="mt-3 text-sm text-gray-400">Loading notifications...</p>
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="py-16 text-center">
                                <div className="mx-auto w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                    </svg>
                                </div>
                                <p className="text-sm font-medium text-gray-500">No notifications yet</p>
                                <p className="text-xs text-gray-400 mt-1">We'll notify you when something arrives.</p>
                            </div>
                        ) : (
                            <>
                                {groupNotificationsByDate(displayNotifications).map(([label, items]) => (
                                    <div key={label}>
                                        <div className="px-4 pt-3 pb-1">
                                            <span className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">{label}</span>
                                        </div>
                                        {items.map((notification) => (
                                            <div
                                                key={notification.id}
                                                onClick={() => handleClick(notification)}
                                                className={`flex items-start gap-3 px-4 py-3 cursor-pointer transition-all duration-150 hover:bg-gray-50 active:bg-gray-100 ${
                                                    !notification.isRead ? 'bg-blue-50/40' : ''
                                                }`}
                                            >
                                                <div className={`relative flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-base ${
                                                    !notification.isRead ? 'bg-white shadow-sm ring-1 ring-gray-200' : 'bg-gray-50'
                                                }`}>
                                                    {notification.icon}
                                                </div>
                                                <div className="flex-1 min-w-0 pt-0.5">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <p className={`text-sm leading-tight ${!notification.isRead ? 'font-semibold text-gray-900' : 'text-gray-600'}`}>
                                                            {notification.title}
                                                        </p>
                                                        {!notification.isRead && (
                                                            <span className="w-1.5 h-1.5 bg-blue-500 rounded-full flex-shrink-0 mt-1.5"></span>
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-gray-400 mt-0.5 line-clamp-2 leading-relaxed">
                                                        {notification.message}
                                                    </p>
                                                    <p className="text-[11px] text-gray-400 mt-1.5">
                                                        {timeAgo(notification.createdAt)}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ))}
                                {notifications.length > 10 && (
                                    <div className="px-4 py-2.5 text-center border-t border-gray-50">
                                        <span className="text-xs text-gray-400">
                                            +{notifications.length - 10} more notification{notifications.length - 10 !== 1 ? 's' : ''}
                                        </span>
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    <div className="px-4 py-2.5 border-t border-gray-100 bg-gray-50/80 flex items-center justify-between">
                        <Link
                            href={isAdmin ? adminUrl('/admin/notifications') : '/notifications'}
                            onClick={() => setOpen(false)}
                            className="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors flex items-center gap-1"
                        >
                            View all notifications
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </Link>
                        <div className="flex items-center gap-2">
                            {notifications.length > 0 && (
                                <button onClick={clearAll} className="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                                    Clear all
                                </button>
                            )}
                            <Link
                                href={isAdmin ? adminUrl('/admin/orders') : '/orders'}
                                onClick={() => setOpen(false)}
                                className="text-xs text-gray-400 hover:text-gray-600 transition-colors"
                            >
                                {isAdmin ? 'Manage orders' : 'My orders'}
                            </Link>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
