import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Translation helper for Laravel localization in Inertia.js
 *
 * Usage:
 *   const { t } = useTranslation();
 *   t('navigation.dashboard')         // "Dashboard"
 *   t('products.product_name')        // "Product Name"
 *   t('validation.required', { attribute: 'Email' }) // "The Email field is required."
 */
export function useTranslation() {
    const { translations, locale } = usePage().props;

    // Memoize t so its reference is stable across renders.
    // This prevents downstream useMemo/useEffect dependencies from
    // re-firing on every render, which would break Inertia <Link> navigation.
    const t = useMemo(() => {
        return function t(key, replacements = {}) {
            // Guard: translations must be a non-null object (not array, not null, not undefined)
            if (!translations || typeof translations !== 'object' || Array.isArray(translations)) {
                return key;
            }

            const keys = key.split('.');
            let value = translations;

            for (const k of keys) {
                if (value && typeof value === 'object' && !Array.isArray(value) && k in value) {
                    value = value[k];
                } else {
                    // Fallback: return the key itself
                    return key;
                }
            }

            if (typeof value !== 'string') {
                return key;
            }

            // Replace :placeholders
            let result = value;
            for (const [placeholder, replacement] of Object.entries(replacements)) {
                result = result.replace(new RegExp(`:${placeholder}`, 'g'), replacement);
            }

            return result;
        };
    }, [translations, locale]);

    return { t, locale };
}

/**
 * Standalone translation function (for use outside components)
 * Must be passed translations explicitly
 */
export function createTranslator(translations) {
    return function t(key, replacements = {}) {
        if (!translations || typeof translations !== 'object' || Array.isArray(translations)) {
            return key;
        }

        const keys = key.split('.');
        let value = translations;

        for (const k of keys) {
            if (value && typeof value === 'object' && !Array.isArray(value) && k in value) {
                value = value[k];
            } else {
                return key;
            }
        }

        if (typeof value !== 'string') {
            return key;
        }

        let result = value;
        for (const [placeholder, replacement] of Object.entries(replacements)) {
            result = result.replace(new RegExp(`:${placeholder}`, 'g'), replacement);
        }

        return result;
    };
}
