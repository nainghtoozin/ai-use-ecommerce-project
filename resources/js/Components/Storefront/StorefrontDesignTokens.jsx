import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function StorefrontDesignTokens() {
    const { storefront } = usePage().props;

    useEffect(() => {
        const tokens = storefront?.design || {};
        const root = document.documentElement;
        const values = {
            '--storefront-color-primary': tokens.color?.primary,
            '--storefront-color-secondary': tokens.color?.secondary,
            '--storefront-color-accent': tokens.color?.accent,
            '--storefront-color-surface': tokens.color?.surface,
            '--storefront-color-background': tokens.color?.background,
            '--storefront-color-surface-muted': tokens.color?.surface_muted,
            '--storefront-color-text': tokens.color?.text,
            '--storefront-color-muted': tokens.color?.muted,
            '--storefront-color-text-muted': tokens.color?.text_muted,
            '--storefront-color-border': tokens.color?.border,
            '--storefront-color-success': tokens.color?.success,
            '--storefront-color-warning': tokens.color?.warning,
            '--storefront-color-danger': tokens.color?.danger,
            '--storefront-font-family': tokens.typography?.font_family,
            '--storefront-heading-weight': tokens.typography?.heading_weight,
            '--storefront-body-weight': tokens.typography?.body_weight,
            '--storefront-body-size': tokens.typography?.body_size,
            '--storefront-line-height': tokens.typography?.line_height,
            '--storefront-page-width': tokens.layout?.page_width,
            '--storefront-spacing-content': tokens.layout?.content_spacing,
            '--storefront-grid-gap': tokens.layout?.grid_gap,
            '--storefront-radius-button': tokens.radius?.button,
            '--storefront-radius-card': tokens.radius?.card,
            '--storefront-radius-input': tokens.radius?.input,
            '--storefront-border-width': tokens.radius?.border_width || tokens.borders?.width,
            '--storefront-shadow-card': tokens.elevation?.card || tokens.shadows?.card,
            '--storefront-shadow-dropdown': tokens.elevation?.dropdown,
            '--storefront-shadow-modal': tokens.elevation?.modal,
            '--storefront-button-style': tokens.buttons?.primary_style,
            '--storefront-card-style': tokens.cards?.style,
            '--storefront-product-card-variant': tokens.product_cards?.variant,
            '--storefront-spacing-section': tokens.layout?.section_spacing || tokens.spacing?.section,
            '--theme-color': tokens.color?.primary,
        };

        Object.entries(values).forEach(([name, value]) => {
            if (value) root.style.setProperty(name, value);
        });
        const previousFont = document.body.style.fontFamily;
        if (tokens.typography?.font_family) document.body.style.fontFamily = tokens.typography.font_family;

        return () => {
            Object.keys(values).forEach((name) => root.style.removeProperty(name));
            document.body.style.fontFamily = previousFont;
        };
    }, [storefront?.design]);

    return null;
}
