import { Head, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Contact({
    contact_email,
    support_email,
    phone,
    whatsapp_number,
    address,
    country,
    google_maps_embed_url,
    websiteInfo,
    contact_info,
    address_info,
}) {
    const { props } = usePage();
    const settings = websiteInfo || props.website_info || {};
    const ci = contact_info || settings.contact_info || {};
    const ai = address_info || settings.address_info || {};

    const tel = ci.primary_phone || phone || settings.phone;
    const tel2 = ci.secondary_phone;
    const whatsapp = ci.whatsapp_number || whatsapp_number || settings.whatsapp_number;
    const email = ci.contact_email || contact_email || settings.contact_email;
    const support = ci.support_email || support_email || settings.support_email;
    const sales = ci.sales_email;
    const telegram = ci.telegram_username;
    const addrParts = [ai.address_line_1, ai.address_line_2, ai.city, ai.state_region, ai.postal_code, ai.country || country || settings.country].filter(Boolean);
    const addrStr = addrParts.length > 0 ? addrParts.join(', ') : (address || settings.address);
    const mapsLink = ai.google_maps_link || google_maps_embed_url || settings.google_maps_embed_url;

    return (
        <ShopLayout>
            <Head title={`Contact - ${settings.site_name || 'Us'}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-4xl mx-auto">
                    <div className="text-center mb-12">
                        <h1 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                            Contact Us
                        </h1>
                        <p className="text-gray-600 leading-relaxed">
                            Get in touch with us via phone, email, WhatsApp or visit us at our store.
                        </p>
                    </div>

                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        {(tel || tel2) && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <div className="flex items-center gap-3 mb-3">
                                    <div className="p-2 bg-blue-100 rounded-lg">
                                        <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Phone</h3>
                                </div>
                                <p className="text-gray-600">{tel}</p>
                                {tel2 && <p className="text-gray-500 text-sm mt-1">{tel2}</p>}
                            </div>
                        )}

                        {whatsapp && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <div className="flex items-center gap-3 mb-3">
                                    <div className="p-2 bg-green-100 rounded-lg">
                                        <svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">WhatsApp</h3>
                                </div>
                                <p className="text-gray-600">{whatsapp}</p>
                            </div>
                        )}

                        {(email || support || sales) && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <div className="flex items-center gap-3 mb-3">
                                    <div className="p-2 bg-red-100 rounded-lg">
                                        <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Email</h3>
                                </div>
                                {email && <p className="text-gray-600">{email}</p>}
                                {support && <p className="text-gray-500 text-sm mt-1">Support: {support}</p>}
                                {sales && <p className="text-gray-500 text-sm mt-1">Sales: {sales}</p>}
                            </div>
                        )}

                        {telegram && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <div className="flex items-center gap-3 mb-3">
                                    <div className="p-2 bg-sky-100 rounded-lg">
                                        <svg className="w-5 h-5 text-sky-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 000 24zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Telegram</h3>
                                </div>
                                <p className="text-gray-600">@{telegram}</p>
                            </div>
                        )}

                        {addrStr && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6 sm:col-span-2 lg:col-span-3">
                                <div className="flex items-center gap-3 mb-3">
                                    <div className="p-2 bg-purple-100 rounded-lg">
                                        <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-semibold text-gray-900">Address</h3>
                                </div>
                                <p className="text-gray-600">{addrStr}</p>
                            </div>
                        )}
                    </div>

                    {mapsLink && (
                        <div className="bg-white rounded-xl border border-gray-200 p-4">
                            <div className="aspect-video">
                                {mapsLink.includes('embed') ? (
                                    <iframe
                                        src={mapsLink}
                                        width="100%"
                                        height="100%"
                                        style={{ border: 0 }}
                                        allowFullScreen=""
                                        loading="lazy"
                                        referrerPolicy="no-referrer-when-downgrade"
                                        title="Store Location"
                                        className="rounded-lg"
                                    ></iframe>
                                ) : (
                                    <a
                                        href={mapsLink}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-center w-full h-full bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        <div className="text-center">
                                            <svg className="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                            </svg>
                                            <span className="text-gray-600 font-medium">View on Google Maps</span>
                                        </div>
                                    </a>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}