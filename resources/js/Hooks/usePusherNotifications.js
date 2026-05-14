import { useState, useEffect, useCallback, useRef } from 'react';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import { timeAgo, notificationIcon, notificationType, notificationColor } from '@/Utils/helpers';

function normalizeNotification(n, overrides = {}) {
    const title = overrides.title || n.title || 'Notification';
    const type = overrides.type || notificationType(title);
    return {
        id: n.id || `pusher_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
        title,
        message: overrides.message || n.message || '',
        orderId: overrides.orderId || n.orderId || n.order_id || null,
        actionUrl: overrides.actionUrl || n.actionUrl || n.action_url || null,
        productId: overrides.productId || n.productId || null,
        isRead: overrides.isRead ?? n.isRead ?? false,
        isPersisted: overrides.isPersisted ?? n.isPersisted ?? false,
        icon: overrides.icon || n.icon || notificationIcon(title),
        type,
        color: notificationColor(type),
        createdAt: overrides.createdAt || n.createdAt || n.created_at || new Date().toISOString(),
        timeAgo: overrides.timeAgo || n.timeAgo || n.created_at_human || timeAgo(n.createdAt || n.created_at || new Date()),
    };
}

function playNotificationSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = ctx.createOscillator();
        const gainNode = ctx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(ctx.destination);

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(880, ctx.currentTime);
        oscillator.frequency.setValueAtTime(660, ctx.currentTime + 0.1);

        gainNode.gain.setValueAtTime(0.15, ctx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);

        oscillator.start(ctx.currentTime);
        oscillator.stop(ctx.currentTime + 0.3);
    } catch {
    }
}

export default function usePusherNotifications() {
    const { auth } = usePage().props;
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isTyping, setIsTyping] = useState({});
    const [loaded, setLoaded] = useState(false);
    const [preferences, setPreferences] = useState(null);
    const typingTimeouts = useRef({});
    const unreadCountRef = useRef(unreadCount);

    useEffect(() => {
        unreadCountRef.current = unreadCount;
    }, [unreadCount]);

    const fetchPreferences = useCallback(async () => {
        if (!auth?.user) return;
        try {
            const { data } = await axios.get('/notifications/preferences');
            setPreferences(data.preferences || {});
        } catch {
            setPreferences(null);
        }
    }, [auth?.user]);

    const fetchNotifications = useCallback(async () => {
        if (!auth?.user) return;
        try {
            const { data } = await axios.get('/notifications/fetch?per_page=50');
            setUnreadCount(data.unread_count);
            setNotifications(
                (data.notifications || []).map((n) =>
                    normalizeNotification({
                        id: n.id,
                        title: n.title,
                        message: n.message,
                        orderId: n.order_id,
                        actionUrl: n.action_url,
                        isRead: n.read_at !== null,
                        isPersisted: true,
                        createdAt: n.created_at,
                        timeAgo: n.created_at_human || timeAgo(n.created_at),
                    })
                )
            );
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
        } finally {
            setLoaded(true);
        }
    }, [auth?.user]);

    useEffect(() => {
        fetchPreferences();
        fetchNotifications();
    }, [fetchPreferences, fetchNotifications]);

    const addNotification = useCallback((data) => {
        const notification = normalizeNotification({}, { isRead: false, isPersisted: false, ...data });
        setNotifications((prev) => [notification, ...prev].slice(0, 50));
        setUnreadCount((prev) => prev + 1);
        if (preferences?.notification_sound) {
            playNotificationSound();
        }
    }, [preferences]);

    useEffect(() => {
        if (!auth?.user?.id || !window.Echo) return;
        const userId = auth.user.id;
        const channel = window.Echo.private(`notifications.user.${userId}`);

        channel.listen('.order.placed', (e) => {
            addNotification({ title: e.title, message: e.message, orderId: e.id, icon: '🛒', createdAt: new Date().toISOString() });
        });

        channel.listen('.order.status_changed', (e) => {
            addNotification({ title: e.title, message: e.message, orderId: e.order_id, createdAt: new Date().toISOString() });
        });

        channel.listen('.payment.verified', (e) => {
            addNotification({ title: e.title, message: e.message, orderId: e.order_id, createdAt: new Date().toISOString() });
        });

        channel.listen('.payment.rejected', (e) => {
            addNotification({ title: e.title, message: e.message, orderId: e.order_id, createdAt: new Date().toISOString() });
        });

        channel.listen('.payment.proof_uploaded', (e) => {
            addNotification({ title: e.title, message: e.message, orderId: e.order_id, createdAt: new Date().toISOString() });
        });

        channel.listen('.stock.low', (e) => {
            addNotification({ title: e.title, message: e.message, productId: e.id, createdAt: new Date().toISOString() });
        });

        channel.listen('.message.sent', (e) => {
            if (e.receiver_id === userId) {
                setUnreadCount((prev) => prev + 1);
                if (preferences?.notification_sound) {
                    playNotificationSound();
                }
            }
        });

        channel.listen('.typing', (e) => {
            if (e.receiver_id === userId) {
                setIsTyping((prev) => ({ ...prev, [e.sender_id]: e.is_typing }));
                if (typingTimeouts.current[e.sender_id]) clearTimeout(typingTimeouts.current[e.sender_id]);
                if (e.is_typing) {
                    typingTimeouts.current[e.sender_id] = setTimeout(() => {
                        setIsTyping((prev) => ({ ...prev, [e.sender_id]: false }));
                    }, 3000);
                }
            }
        });

        return () => {
            window.Echo.leave(`notifications.user.${userId}`);
            Object.values(typingTimeouts.current).forEach(clearTimeout);
        };
    }, [auth?.user?.id, addNotification, preferences]);

    const navigateToNotification = useCallback((notification) => {
        if (notification.actionUrl) {
            router.visit(notification.actionUrl);
        } else if (notification.orderId) {
            const isAdmin = window.location.pathname.startsWith('/admin');
            router.visit(isAdmin ? `/admin/orders/${notification.orderId}` : `/client/orders/${notification.orderId}`);
        }
    }, []);

    const markAsRead = useCallback(async (id) => {
        const notification = notifications.find((n) => n.id === id);
        if (!notification) return;
        setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, isRead: true } : n)));
        setUnreadCount((prev) => Math.max(0, prev - 1));
        if (notification.isPersisted) {
            try {
                await axios.patch(`/notifications/${id}/read`);
            } catch (err) {
                setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, isRead: false } : n)));
                setUnreadCount((prev) => prev + 1);
            }
        }
    }, [notifications]);

    const markAllAsRead = useCallback(async () => {
        const prevNotifications = [...notifications];
        const prevCount = unreadCount;
        setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })));
        setUnreadCount(0);
        try {
            await axios.patch('/notifications/read-all');
        } catch (err) {
            setNotifications(prevNotifications);
            setUnreadCount(prevCount);
        }
    }, [notifications, unreadCount]);

    const clearAll = useCallback(() => {
        setNotifications([]);
        setUnreadCount(0);
    }, []);

    return {
        notifications,
        unreadCount,
        isTyping,
        loaded,
        preferences,
        markAsRead,
        markAllAsRead,
        clearAll,
        navigateToNotification,
        refresh: fetchNotifications,
    };
}
