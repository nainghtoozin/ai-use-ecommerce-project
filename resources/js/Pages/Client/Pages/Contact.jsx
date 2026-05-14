import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Contact({ websiteInfo }) {
    return (
        <ShopLayout>
            <Head title={`Contact - ${websiteInfo?.name || ''}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-3xl font-bold text-gray-900 mb-6">{websiteInfo?.contact_title || 'Contact'}</h1>
                    <p className="text-gray-600 leading-relaxed mb-8">{websiteInfo?.contact_description || 'Get in touch with us via phone, email or contact form.'}</p>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        {websiteInfo?.phone && (
                            <div className="bg-white rounded-lg border border-gray-200 p-6">
                                <div className="flex items-center gap-3 mb-2">
                                    <div className="p-2 bg-blue-100 rounded-lg">
                                        <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Phone</h3>
                                </div>
                                <p className="text-gray-600">{websiteInfo.phone}</p>
                            </div>
                        )}
                        {websiteInfo?.email && (
                            <div className="bg-white rounded-lg border border-gray-200 p-6">
                                <div className="flex items-center gap-3 mb-2">
                                    <div className="p-2 bg-green-100 rounded-lg">
                                        <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Email</h3>
                                </div>
                                <p className="text-gray-600">{websiteInfo.email}</p>
                            </div>
                        )}
                        {websiteInfo?.address && (
                            <div className="bg-white rounded-lg border border-gray-200 p-6 sm:col-span-2">
                                <div className="flex items-center gap-3 mb-2">
                                    <div className="p-2 bg-purple-100 rounded-lg">
                                        <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Address</h3>
                                </div>
                                <p className="text-gray-600">{websiteInfo.address}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
