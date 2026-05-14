import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echoKey = import.meta.env.VITE_PUSHER_APP_KEY;
const echoCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'ap1';

if (echoKey) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: echoKey,
        cluster: echoCluster,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        },
    });
} else {
    console.warn('Pusher key not configured. Real-time notifications disabled.');
    window.Echo = null;
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
        showSpinner: true,
    },
});
