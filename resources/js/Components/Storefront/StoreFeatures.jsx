const features = [
    {
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        title: 'Fast Delivery',
        description: 'Quick and reliable shipping right to your doorstep.',
    },
    {
        icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        title: 'Secure Payment',
        description: 'Your payment information is always safe with us.',
    },
    {
        icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        title: 'Easy Returns',
        description: 'Hassle-free returns within 30 days of purchase.',
    },
    {
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        title: 'Quality Products',
        description: 'We carefully curate products to ensure top quality.',
    },
];

export default function StoreFeatures() {
    return (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
            <div className="text-center mb-3">
                <h2 className="text-2xl sm:text-3xl font-bold text-gray-900">Why Shop With Us</h2>
                <p className="mt-2 text-sm sm:text-base text-gray-500">We make shopping better</p>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                {features.map((feature, index) => (
                    <div key={index} className="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md hover:border-indigo-200 transition-all duration-200">
                        <div className="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center mx-auto mb-4">
                            <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d={feature.icon} />
                            </svg>
                        </div>
                        <h3 className="text-sm font-semibold text-gray-900">{feature.title}</h3>
                        <p className="mt-1.5 text-xs text-gray-500 leading-relaxed">{feature.description}</p>
                    </div>
                ))}
            </div>
        </section>
    );
}
