import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function About({ websiteInfo }) {
    return (
        <ShopLayout>
            <Head title={`About Us - ${websiteInfo?.name || ''}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-3xl font-bold text-gray-900 mb-6">{websiteInfo?.about_us_title || 'About Us'}</h1>
                    <p className="text-gray-600 leading-relaxed">{websiteInfo?.about_us_description || 'Learn more about our company, mission, and vision.'}</p>
                </div>
            </div>
        </ShopLayout>
    );
}
