import CmsPage from '@/Components/Storefront/CmsPage';
import RichContent from '@/Components/Storefront/RichContent';

const POLICY_ICONS = {
    'Privacy Policy': 'bi-shield-check',
    'Terms & Conditions': 'bi-file-earmark-text',
    'Shipping Policy': 'bi-truck',
    'Return Policy': 'bi-arrow-return-left',
    'Refund Policy': 'bi-cash-stack',
};

export default function Policy({ tenant, page }) {
    const icon = POLICY_ICONS[page.title] || 'bi-file-text';

    return (
        <CmsPage
            title={page.title}
            breadcrumbs={[{ label: page.title }]}
            updatedAt={page.updated_at}
        >
            {page.content ? (
                <RichContent content={page.content} />
            ) : (
                <div className="text-center py-16">
                    <div className="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <i className={`bi ${icon} text-3xl text-gray-400 dark:text-gray-500`}></i>
                    </div>
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                        {page.title}
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                        This page is being prepared. Please check back later for the complete {page.title.toLowerCase()}.
                    </p>
                </div>
            )}
        </CmsPage>
    );
}
