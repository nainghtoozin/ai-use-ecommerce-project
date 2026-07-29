import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { adminUrl } from '@/Utils/adminUrl';

const ThemeContext = createContext(undefined);

function getSystemTheme() {
    if (typeof window === 'undefined') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function getResolvedTheme(theme) {
    if (theme === 'system') return getSystemTheme();
    return theme;
}

export function ThemeProvider({ children, initialTheme = 'system' }) {
    const [theme, setTheme] = useState(initialTheme);
    const [resolvedTheme, setResolvedTheme] = useState(() => getResolvedTheme(initialTheme));

    // Apply theme to document
    const applyTheme = useCallback((resolved) => {
        const html = document.documentElement;
        html.classList.add('transitioning');

        if (resolved === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        // Remove transition class after animation completes
        setTimeout(() => {
            html.classList.remove('transitioning');
        }, 300);
    }, []);

    // Initialize theme on mount
    useEffect(() => {
        const resolved = getResolvedTheme(theme);
        setResolvedTheme(resolved);
        applyTheme(resolved);
    }, []);

    // Listen for OS theme changes when in system mode
    useEffect(() => {
        if (theme !== 'system') return;

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = (e) => {
            const resolved = e.matches ? 'dark' : 'light';
            setResolvedTheme(resolved);
            applyTheme(resolved);
        };

        mediaQuery.addEventListener('change', handler);
        return () => mediaQuery.removeEventListener('change', handler);
    }, [theme, applyTheme]);

    const switchTheme = useCallback((newTheme) => {
        setTheme(newTheme);
        const resolved = getResolvedTheme(newTheme);
        setResolvedTheme(resolved);
        applyTheme(resolved);

        // Persist to server silently — no navigation, no URL change
        const url = adminUrl('/admin/theme/switch');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ theme: newTheme }),
        }).catch(() => {
            // Silently fail — theme is already applied locally
        });
    }, [applyTheme]);

    const value = {
        theme,
        resolvedTheme,
        switchTheme,
        isDark: resolvedTheme === 'dark',
    };

    return (
        <ThemeContext.Provider value={value}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (context === undefined) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
