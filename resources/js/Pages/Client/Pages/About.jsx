import { Head, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function About({ about_title, about_description, mission_title, mission_description, vision_title, vision_description, websiteInfo }) {
    const { props } = usePage();
    const settings = websiteInfo || props.website_info || {};

    return (
        <ShopLayout>
            <Head title={`About - ${settings.site_name || 'Us'}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-4xl mx-auto">
                    <h1 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        {about_title || settings.about_title || 'About Us'}
                    </h1>
                    <p className="text-gray-600 leading-relaxed mb-12">
                        {about_description || settings.about_description || 'Learn more about our company, mission, and vision.'}
                    </p>

                    <div className="grid md:grid-cols-2 gap-8 mb-12">
                        <div className="bg-white rounded-xl border border-gray-200 p-8">
                            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                                <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h2 className="text-xl font-bold text-gray-900 mb-3">
                                {mission_title || settings.mission_title || 'Our Mission'}
                            </h2>
                            <p className="text-gray-600 leading-relaxed">
                                {mission_description || settings.mission_description || 'Our mission is to provide the best service to our customers.'}
                            </p>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200 p-8">
                            <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                            <h2 className="text-xl font-bold text-gray-900 mb-3">
                                {vision_title || settings.vision_title || 'Our Vision'}
                            </h2>
                            <p className="text-gray-600 leading-relaxed">
                                {vision_description || settings.vision_description || 'Our vision is to be the leading provider in our industry.'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}