import FlashMessages from '@/Components/FlashMessages';
import ShopNavbar from '@/Components/ShopNavbar';
import ShopFooter from '@/Components/ShopFooter';
import StorefrontDesignTokens from '@/Components/Storefront/StorefrontDesignTokens';
import { Head, usePage } from '@inertiajs/react';

export default function ShopLayout({ children, previewMode = null }) {
    const { storefront, website_info } = usePage().props;
    const faviconUrl = storefront?.identity?.favicon_url || website_info?.favicon_url;
    const isPreview = Boolean(previewMode);
    const mode = previewMode?.mode === 'mobile' ? 'mobile' : 'desktop';
    return (
        <>
            <StorefrontDesignTokens />
            <Head>{faviconUrl && <link rel="icon" href={faviconUrl} />}</Head>
            <FlashMessages />
            <div className={`storefront-root ${isPreview && mode === 'mobile' ? 'max-w-[390px] mx-auto shadow-2xl' : ''} min-h-screen flex flex-col overflow-x-hidden bg-gray-50 dark:bg-gray-950`}>
                {isPreview && <div className="sticky top-0 z-[60] flex items-center justify-between gap-2 bg-amber-100 px-3 py-2 text-xs text-amber-900"><strong>PREVIEW MODE{previewMode.revision_number ? ` · Revision #${previewMode.revision_number}` : ''}</strong><span className="hidden sm:inline">Not published</span><span className="flex items-center gap-2"><a href="?viewport=desktop" className={mode === 'desktop' ? 'font-bold underline' : 'underline'}>Desktop</a><a href="?viewport=mobile" className={mode === 'mobile' ? 'font-bold underline' : 'underline'}>Mobile</a><a href={previewMode.admin_url || '/admin/storefront'} className="font-semibold underline">Return to Admin</a></span></div>}
                <ShopNavbar />
                <main className="flex-1">
                    <div className="py-2">
                        {children}
                    </div>
                </main>
                <ShopFooter />
            </div>
        </>
    );
}
