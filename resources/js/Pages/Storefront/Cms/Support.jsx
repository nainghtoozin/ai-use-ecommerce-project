import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { Mail, Phone, MessageCircle, Clock, CircleHelp, UserRound, Package, CreditCard, Truck, ArrowRight, ChevronRight } from 'lucide-react';

function MethodTile({ icon: Icon, label, value, href, external }) {
    if (!value) return null;
    const Wrapper = href ? 'a' : 'div';
    const wrapperProps = href ? { href, ...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {}) } : {};

    return (
        <Wrapper
            {...wrapperProps}
            className="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-sm transition-all min-w-0"
        >
            <span className="flex-shrink-0 w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                <Icon className="w-4 h-4 text-blue-600 dark:text-blue-400" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide leading-tight">{label}</span>
                <span className="block text-[13px] font-semibold text-gray-900 dark:text-gray-100 truncate leading-tight mt-0.5">{value}</span>
            </span>
            {href && <ArrowRight className="w-3.5 h-3.5 flex-shrink-0 text-gray-300 dark:text-gray-600" />}
        </Wrapper>
    );
}

const CATEGORIES = [
    { icon: UserRound, label: 'Account & Login', hint: 'Sign-in and account access', href: null },
    { icon: Package, label: 'My Orders', hint: 'Track and manage orders', href: 'orders' },
    { icon: CreditCard, label: 'Payment', hint: 'Payment questions', href: null },
    { icon: Truck, label: 'Delivery', hint: 'Shipping help', href: null },
];

export default function Support({ tenant, support }) {
    const hasSupportInfo = support.email || support.phone || support.whatsapp || support.telegram;
    const storeBase = `/store/${tenant.slug}`;

    return (
        <ShopLayout>
            <Head title={`Help & Support - ${tenant?.name || 'Store'}`} />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 py-5 sm:py-7">
                <nav className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2.5" aria-label="Breadcrumb">
                    <Link href={storeBase} className="hover:text-gray-700 dark:hover:text-gray-200 transition-colors">Home</Link>
                    <ChevronRight className="w-3 h-3" />
                    <span className="text-gray-700 dark:text-gray-200 font-medium">Help & Support</span>
                </nav>

                <div className="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-700 px-4 py-4 sm:px-5 text-white shadow-sm">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                        <div className="flex items-center gap-2.5 flex-shrink-0">
                            <span className="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center">
                                <CircleHelp className="w-5 h-5 text-white" />
                            </span>
                            <div className="sm:hidden">
                                <h1 className="text-lg font-bold leading-tight">Help & Support</h1>
                                <p className="text-xs text-blue-100">How can we help you?</p>
                            </div>
                        </div>
                        <div className="hidden sm:block min-w-0">
                            <h1 className="text-xl font-bold leading-tight">Help & Support</h1>
                            <p className="text-[13px] text-blue-100">How can we help you?</p>
                        </div>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5 flex-1 w-full">
                            {CATEGORIES.map(({ icon: Icon, label, hint, href }) => {
                                const inner = (
                                    <>
                                        <Icon className="w-4 h-4 text-blue-100 flex-shrink-0" />
                                        <span className="min-w-0">
                                            <span className="block text-xs font-semibold leading-tight truncate">{label}</span>
                                            <span className="hidden sm:block text-[11px] text-blue-100/80 truncate mt-px">{hint}</span>
                                        </span>
                                    </>
                                );
                                const cls = 'flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 border border-white/10 px-2.5 py-2 transition-colors min-w-0';
                                return href === 'orders' ? (
                                    <Link key={label} href={`${storeBase}/customer/orders`} className={cls}>{inner}</Link>
                                ) : (
                                    <div key={label} className={cls}>{inner}</div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">
                    <div className="lg:col-span-3 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30 p-4">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Need more help?</h2>
                        {support.message && (
                            <p className="mt-1 text-[13px] text-gray-600 dark:text-gray-400">{support.message}</p>
                        )}
                        {hasSupportInfo ? (
                            <div className="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <MethodTile icon={Mail} label="Email" value={support.email} href={support.email ? `mailto:${support.email}` : null} />
                                <MethodTile icon={Phone} label="Phone" value={support.phone} href={support.phone ? `tel:${support.phone.replace(/\s+/g, '')}` : null} />
                                <MethodTile icon={MessageCircle} label="WhatsApp" value={support.whatsapp} href={support.whatsapp ? `https://wa.me/${support.whatsapp.replace(/\D/g, '')}` : null} external />
                                {support.telegram && (
                                    <MethodTile icon={MessageCircle} label="Telegram" value={`@${support.telegram}`} href={`https://t.me/${support.telegram}`} external />
                                )}
                            </div>
                        ) : (
                            <p className="mt-2 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 px-3 py-3 text-[13px] text-gray-500 dark:text-gray-400 text-center">
                                Support contact information is not available yet.
                            </p>
                        )}
                        {support.hours && (
                            <p className="mt-2.5 flex items-center gap-1.5 text-[13px] text-gray-500 dark:text-gray-400">
                                <Clock className="w-3.5 h-3.5" />
                                {support.hours}
                            </p>
                        )}
                    </div>

                    <div className="lg:col-span-2 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Quick actions</h2>
                        <div className="mt-2.5 flex flex-col gap-2">
                            <Link
                                href={`${storeBase}/customer/orders`}
                                className="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                My Orders
                            </Link>
                            <Link
                                href={route('storefront.faq', { store_slug: tenant.slug })}
                                className="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                Browse FAQs
                            </Link>
                            <Link
                                href={storeBase}
                                className="inline-flex items-center justify-center px-4 py-2 text-gray-600 dark:text-gray-400 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            >
                                Back to Store
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
