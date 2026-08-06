import CmsPage from '@/Components/Storefront/CmsPage';
import RichContent from '@/Components/Storefront/RichContent';

export default function About({ tenant, page }) {
    const hasMissions = page.mission_title || page.mission_description;
    const hasVision = page.vision_title || page.vision_description;

    return (
        <CmsPage
            title={page.title || 'About Us'}
            breadcrumbs={[{ label: 'About Us' }]}
        >
            {page.description ? (
                <RichContent content={page.description} />
            ) : (
                <div className="text-center py-12">
                    <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <i className="bi bi-info-circle text-2xl text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Information about this store will be available soon.
                    </p>
                </div>
            )}

            {hasMissions && (
                <div className="mt-10 pt-10 border-t border-gray-200 dark:border-gray-800">
                    <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                        {page.mission_title || 'Our Mission'}
                    </h2>
                    {page.mission_description && (
                        <RichContent content={page.mission_description} />
                    )}
                </div>
            )}

            {hasVision && (
                <div className="mt-10 pt-10 border-t border-gray-200 dark:border-gray-800">
                    <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                        {page.vision_title || 'Our Vision'}
                    </h2>
                    {page.vision_description && (
                        <RichContent content={page.vision_description} />
                    )}
                </div>
            )}
        </CmsPage>
    );
}
