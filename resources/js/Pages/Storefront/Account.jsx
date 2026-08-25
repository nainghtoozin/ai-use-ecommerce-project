import { useState, useRef } from 'react';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { useTheme } from '@/Contexts/ThemeContext';
import { useTranslation } from '@/Utils/useTranslation';
import {
    LayoutDashboard, User, MapPin, Palette, Lock,
    ShoppingBag, Clock, Truck, CheckCircle, XCircle,
    Package, Camera, Mail, Phone, Save, Eye, EyeOff,
    Loader2, ArrowRight, Home, ChevronRight, AlertCircle,
    Globe, Bell, Shield, Trash2, Info, Sun, Moon, Monitor,
    Calendar, MailCheck, X
} from 'lucide-react';

const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { key: 'profile', label: 'Profile', icon: User },
    { key: 'addresses', label: 'Addresses', icon: MapPin },
    { key: 'preferences', label: 'Preferences', icon: Palette },
    { key: 'security', label: 'Security', icon: Shield },
];

function SectionCard({ icon: Icon, title, description, children, className = '' }) {
    return (
        <div className={`bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden ${className}`}>
            <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                {Icon && (
                    <div className="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                        <Icon className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                )}
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                    {description && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{description}</p>}
                </div>
            </div>
            <div className="px-5 py-5">{children}</div>
        </div>
    );
}

function StatCard({ label, value, color, icon: Icon }) {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 hover:shadow-md transition-shadow">
            <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${color.bg}`}>
                    <Icon className={`w-5 h-5 ${color.icon}`} />
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">{label}</p>
                    <p className="text-xl font-bold text-gray-900 dark:text-white">{value}</p>
                </div>
            </div>
        </div>
    );
}

function FormField({ label, htmlFor, error, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{label}</label>
            {children}
            {error && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{error}</p>}
        </div>
    );
}

function ToggleSwitch({ enabled, onChange, label, description }) {
    return (
        <div className="flex items-center justify-between py-3">
            <div className="pr-4">
                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{label}</p>
                {description && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{description}</p>}
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={enabled}
                onClick={() => onChange(!enabled)}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex-shrink-0 ${
                    enabled ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'
                }`}
            >
                <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${enabled ? 'translate-x-6' : 'translate-x-1'}`} />
            </button>
        </div>
    );
}

function DashboardSection({ tenant, customer, orderStats, recentOrders }) {
    const storeSlug = tenant.slug;

    return (
        <div className="space-y-5">
            <div className="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-5 text-white">
                <h2 className="text-lg font-bold">Welcome back, {customer.name.split(' ')[0]}!</h2>
                <p className="text-sm text-blue-100 mt-1">Here's what's happening with your orders.</p>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <StatCard label="Total" value={orderStats.total} color={{ bg: 'bg-gray-100 dark:bg-gray-800', icon: 'text-gray-600 dark:text-gray-400' }} icon={Package} />
                <StatCard label="Pending" value={orderStats.pending} color={{ bg: 'bg-yellow-50 dark:bg-yellow-900/20', icon: 'text-yellow-600' }} icon={Clock} />
                <StatCard label="Processing" value={orderStats.processing} color={{ bg: 'bg-blue-50 dark:bg-blue-900/20', icon: 'text-blue-600' }} icon={Package} />
                <StatCard label="Shipped" value={orderStats.shipped} color={{ bg: 'bg-indigo-50 dark:bg-indigo-900/20', icon: 'text-indigo-600' }} icon={Truck} />
                <StatCard label="Delivered" value={orderStats.delivered} color={{ bg: 'bg-green-50 dark:bg-green-900/20', icon: 'text-green-600' }} icon={CheckCircle} />
                <StatCard label="Cancelled" value={orderStats.cancelled} color={{ bg: 'bg-red-50 dark:bg-red-900/20', icon: 'text-red-600' }} icon={XCircle} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div className="lg:col-span-2">
                    <SectionCard icon={ShoppingBag} title="Recent Orders" description="Your last 5 orders">
                        {recentOrders?.length > 0 ? (
                            <div className="space-y-2">
                                {recentOrders.map((order) => (
                                    <Link
                                        key={order.id}
                                        href={route('storefront.customer.orders.show', { store_slug: storeSlug, order: order.id })}
                                        className="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div className="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                                <Package className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900 dark:text-white truncate">#{order.invoice_number}</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">{new Date(order.created_at).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${
                                                order.order_status === 'delivered' ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' :
                                                order.order_status === 'shipped' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400' :
                                                order.order_status === 'processing' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400' :
                                                order.order_status === 'cancelled' ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' :
                                                'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400'
                                            }`}>
                                                {order.order_status}
                                            </span>
                                            <ChevronRight className="w-4 h-4 text-gray-400" />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <ShoppingBag className="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                                <p className="text-sm text-gray-500 dark:text-gray-400">No orders yet</p>
                                <Link href={route('storefront.index', { store_slug: storeSlug })} className="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline mt-2">
                                    Start shopping <ArrowRight className="w-3.5 h-3.5" />
                                </Link>
                            </div>
                        )}
                    </SectionCard>
                </div>

                {/* Account Summary Card */}
                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Account Summary</h3>
                    </div>
                    <div className="p-4">
                        <div className="space-y-1">
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <User className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Name</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">{customer.name}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <Mail className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Email</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white truncate ml-2">{customer.email}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <Calendar className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Member Since</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">{new Date(customer.member_since).toLocaleDateString()}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <Clock className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Last Login</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">
                                    {customer.last_login_at ? new Date(customer.last_login_at).toLocaleDateString() : 'Current session'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <MapPin className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Addresses</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">{customer.addresses_count}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <Package className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Total Orders</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">{orderStats.total}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <CheckCircle className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Completed</span>
                                </div>
                                <span className="text-sm font-medium text-green-600 dark:text-green-400">{orderStats.delivered}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <Clock className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Pending</span>
                                </div>
                                <span className="text-sm font-medium text-yellow-600 dark:text-yellow-400">{orderStats.pending}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <Globe className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Language</span>
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">{customer.locale === 'my' ? 'Myanmar' : 'English'}</span>
                            </div>
                            <div className="flex items-center justify-between px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div className="flex items-center gap-2.5">
                                    <MailCheck className="w-4 h-4 text-gray-400" />
                                    <span className="text-sm text-gray-600 dark:text-gray-400">Verified</span>
                                </div>
                                <span className={`text-sm font-medium ${customer.email_verified ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'}`}>
                                    {customer.email_verified ? 'Yes' : 'Pending'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Link href={route('storefront.customer.orders', { store_slug: storeSlug })} className="flex items-center gap-3 p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 hover:shadow-md transition-shadow group">
                    <div className="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center"><Package className="w-5 h-5 text-blue-600 dark:text-blue-400" /></div>
                    <div className="flex-1"><p className="text-sm font-medium text-gray-900 dark:text-white">View Orders</p><p className="text-xs text-gray-500 dark:text-gray-400">Track your purchases</p></div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" />
                </Link>
                <Link href={route('storefront.customer.addresses', { store_slug: storeSlug })} className="flex items-center gap-3 p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 hover:shadow-md transition-shadow group">
                    <div className="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center"><MapPin className="w-5 h-5 text-purple-600 dark:text-purple-400" /></div>
                    <div className="flex-1"><p className="text-sm font-medium text-gray-900 dark:text-white">Addresses</p><p className="text-xs text-gray-500 dark:text-gray-400">{customer.addresses_count} saved</p></div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" />
                </Link>
                <Link href={route('storefront.index', { store_slug: storeSlug })} className="flex items-center gap-3 p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 hover:shadow-md transition-shadow group">
                    <div className="w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center"><Home className="w-5 h-5 text-green-600 dark:text-green-400" /></div>
                    <div className="flex-1"><p className="text-sm font-medium text-gray-900 dark:text-white">Back to Store</p><p className="text-xs text-gray-500 dark:text-gray-400">Continue shopping</p></div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" />
                </Link>
            </div>
        </div>
    );
}

function ProfileSection({ tenant, customer, mustVerifyEmail, status }) {
    const storeSlug = tenant.slug;
    const fileInputRef = useRef(null);
    const [avatarPreview, setAvatarPreview] = useState(null);

    const form = useForm({
        name: customer.name || '',
        email: customer.email || '',
        phone: customer.phone || '',
        profile_image: null,
    });

    function submit(e) {
        e.preventDefault();
        form.post(route('storefront.customer.profile.update', { store_slug: storeSlug }), {
            forceFormData: true,
            onSuccess: () => { form.reset('profile_image'); setAvatarPreview(null); },
        });
    }

    return (
        <SectionCard icon={User} title="Profile Information" description="Manage your personal details">
            {status === 'profile-updated' && (
                <div className="flex items-center gap-2 px-4 py-3 mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                    <p className="text-sm text-green-700 dark:text-green-300">Profile updated successfully.</p>
                </div>
            )}
            <form onSubmit={submit} className="space-y-4">
                <div className="flex items-center gap-4">
                    <div className="relative">
                        {avatarPreview ? (
                            <img src={avatarPreview} alt="Preview" className="w-16 h-16 rounded-full object-cover ring-2 ring-gray-200 dark:ring-gray-700" />
                        ) : customer.profile_image_url ? (
                            <img src={customer.profile_image_url} alt="Avatar" className="w-16 h-16 rounded-full object-cover ring-2 ring-gray-200 dark:ring-gray-700" />
                        ) : (
                            <div className="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xl font-bold ring-2 ring-gray-200 dark:ring-gray-700">
                                {customer.name?.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <button type="button" onClick={() => fileInputRef.current?.click()} className="absolute -bottom-1 -right-1 w-7 h-7 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full flex items-center justify-center shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <Camera className="w-3.5 h-3.5 text-gray-600 dark:text-gray-400" />
                        </button>
                        <input ref={fileInputRef} type="file" accept="image/*" onChange={(e) => { const f = e.target.files[0]; if (f) { form.setData('profile_image', f); setAvatarPreview(URL.createObjectURL(f)); } }} className="hidden" />
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-900 dark:text-white">Profile Photo</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">JPEG, PNG, GIF, or WebP. Max 2MB.</p>
                    </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormField label="Full Name" htmlFor="c_name" error={form.errors.name}>
                        <div className="relative"><User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" /><input id="c_name" type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required /></div>
                    </FormField>
                    <FormField label="Email Address" htmlFor="c_email" error={form.errors.email}>
                        <div className="relative"><Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" /><input id="c_email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required /></div>
                    </FormField>
                </div>
                <FormField label="Phone Number" htmlFor="c_phone" error={form.errors.phone}>
                    <div className="relative"><Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" /><input id="c_phone" type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="+95 9xxx xxx xxxx" /></div>
                </FormField>
                {mustVerifyEmail && (
                    <div className="flex items-start gap-2 px-3 py-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                        <AlertCircle className="w-4 h-4 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" />
                        <p className="text-sm text-yellow-700 dark:text-yellow-300">Your email is unverified. <button type="button" className="text-blue-600 dark:text-blue-400 hover:underline ml-1">Resend verification</button></p>
                    </div>
                )}
                <div className="flex justify-end pt-2">
                    <button type="submit" disabled={form.processing} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium transition-colors">
                        {form.processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                        {form.processing ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </form>
        </SectionCard>
    );
}

function AddressesSection({ tenant, customer }) {
    const storeSlug = tenant.slug;
    return (
        <SectionCard icon={MapPin} title="Delivery Addresses" description="Manage your shipping addresses">
            <div className="text-center py-6">
                <MapPin className="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">You have {customer.addresses_count} saved address{customer.addresses_count !== 1 ? 'es' : ''}.</p>
                <Link href={route('storefront.customer.addresses', { store_slug: storeSlug })} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
                    <MapPin className="w-4 h-4" /> Manage Addresses
                </Link>
            </div>
        </SectionCard>
    );
}

const availableLocales = [
    { code: 'en', name: 'English', flag: '🇺🇸' },
    { code: 'my', name: 'Myanmar', flag: '🇲🇲' },
];

function PreferencesSection({ tenant, customer }) {
    const { theme, switchTheme } = useTheme();
    const { locale } = useTranslation();
    const [languageChanging, setLanguageChanging] = useState(false);
    const [notifPrefs, setNotifPrefs] = useState(customer.notification_preferences || {
        order_updates: true,
        payment_updates: true,
        promotions: false,
        email_notifications: true,
    });
    const [savingNotif, setSavingNotif] = useState(false);

    const themeOptions = [
        { value: 'light', label: 'Light', icon: Sun, desc: 'Light mode' },
        { value: 'dark', label: 'Dark', icon: Moon, desc: 'Dark mode' },
        { value: 'system', label: 'System', icon: Monitor, desc: 'Follow OS' },
    ];

    function handleLanguageChange(localeCode) {
        setLanguageChanging(true);
        router.post(route('language.switch'), { locale: localeCode }, {
            preserveState: true,
            onFinish: () => setLanguageChanging(false),
        });
    }

    function handleNotifToggle(key, value) {
        const updated = { ...notifPrefs, [key]: value };
        setNotifPrefs(updated);
        setSavingNotif(true);
        router.put(route('storefront.customer.profile.update', { store_slug: tenant.slug }), { notification_preferences: updated }, {
            preserveState: true,
            onFinish: () => setSavingNotif(false),
        });
    }

    return (
        <div className="space-y-5">
            {/* Language */}
            <SectionCard icon={Globe} title="Language" description="Select your preferred language">
                <div className="grid grid-cols-2 gap-3">
                    {availableLocales.map((loc) => (
                        <button key={loc.code} onClick={() => handleLanguageChange(loc.code)} disabled={languageChanging} className={`flex items-center justify-center gap-2 px-3 py-3 rounded-xl border text-sm font-medium transition-all ${locale === loc.code ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 shadow-sm' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                            {loc.flag && <span className="text-lg">{loc.flag}</span>}
                            <span>{loc.name}</span>
                            {locale === loc.code && <CheckCircle className="w-4 h-4 text-blue-600 dark:text-blue-400" />}
                        </button>
                    ))}
                </div>
            </SectionCard>

            {/* Appearance */}
            <SectionCard icon={Palette} title="Appearance" description="Choose your preferred theme">
                <div className="grid grid-cols-3 gap-3">
                    {themeOptions.map((opt) => {
                        const Icon = opt.icon;
                        return (
                            <button key={opt.value} onClick={() => switchTheme(opt.value)} className={`flex flex-col items-center gap-2 px-3 py-4 rounded-xl border text-sm font-medium transition-all ${theme === opt.value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 shadow-sm' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <Icon className="w-6 h-6" />
                                <span>{opt.label}</span>
                                <span className="text-[10px] text-gray-400 dark:text-gray-500">{opt.desc}</span>
                            </button>
                        );
                    })}
                </div>
            </SectionCard>

            {/* Notifications */}
            <SectionCard icon={Bell} title="Notifications" description="Manage your notification preferences">
                <div className="divide-y divide-gray-100 dark:divide-gray-800">
                    <ToggleSwitch enabled={notifPrefs.order_updates} onChange={(v) => handleNotifToggle('order_updates', v)} label="Order Updates" description="Get notified about order status changes" />
                    <ToggleSwitch enabled={notifPrefs.payment_updates} onChange={(v) => handleNotifToggle('payment_updates', v)} label="Payment Updates" description="Notifications for payment verification" />
                    <ToggleSwitch enabled={notifPrefs.promotions} onChange={(v) => handleNotifToggle('promotions', v)} label="Promotions" description="Receive promotional offers and discounts" />
                    <ToggleSwitch enabled={notifPrefs.email_notifications} onChange={(v) => handleNotifToggle('email_notifications', v)} label="Email Notifications" description="Receive notifications via email" />
                </div>
                {savingNotif && <p className="text-xs text-gray-400 mt-3 flex items-center gap-1"><Loader2 className="w-3 h-3 animate-spin" /> Saving preferences...</p>}
            </SectionCard>

            {/* Privacy */}
            <SectionCard icon={Info} title="Privacy" description="Your account information (read-only)">
                <div className="space-y-3">
                    <div className="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                        <div className="flex items-center gap-2"><Calendar className="w-4 h-4 text-gray-400" /><span className="text-sm text-gray-600 dark:text-gray-400">Last Login</span></div>
                        <span className="text-sm font-medium text-gray-900 dark:text-white">{customer.last_login_at ? new Date(customer.last_login_at).toLocaleString() : 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                        <div className="flex items-center gap-2"><Calendar className="w-4 h-4 text-gray-400" /><span className="text-sm text-gray-600 dark:text-gray-400">Account Created</span></div>
                        <span className="text-sm font-medium text-gray-900 dark:text-white">{new Date(customer.member_since).toLocaleDateString()}</span>
                    </div>
                    <div className="flex items-center justify-between py-2">
                        <div className="flex items-center gap-2"><MailCheck className="w-4 h-4 text-gray-400" /><span className="text-sm text-gray-600 dark:text-gray-400">Email Verified</span></div>
                        <span className={`inline-flex items-center gap-1 text-sm font-medium ${customer.email_verified ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'}`}>
                            {customer.email_verified ? <><CheckCircle className="w-4 h-4" /> Verified</> : <><AlertCircle className="w-4 h-4" /> Unverified</>}
                        </span>
                    </div>
                </div>
            </SectionCard>
        </div>
    );
}

function DeleteAccountModal({ isOpen, onClose, storeSlug, hasOrders }) {
    const [step, setStep] = useState(1);
    const form = useForm({ password: '', understands: false });

    function handleSubmit(e) {
        e.preventDefault();
        router.delete(route('storefront.customer.account.delete', { store_slug: storeSlug }), {
            data: { password: form.data.password },
            onSuccess: () => onClose(),
        });
    }

    function handleClose() {
        setStep(1);
        form.reset();
        onClose();
    }

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={handleClose} />
            <div className="relative bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 className="text-lg font-bold text-red-600">Delete Account</h2>
                    <button onClick={handleClose} className="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <X className="w-5 h-5 text-gray-400" />
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="p-5 space-y-4">
                    {step === 1 && (
                        <>
                            <div className="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="text-sm font-medium text-red-800 dark:text-red-300">This action cannot be undone</p>
                                    <p className="text-xs text-red-600 dark:text-red-400 mt-1">Your addresses, preferences and profile will be removed. Orders and invoices will be preserved for business records.</p>
                                </div>
                            </div>
                            {hasOrders && (
                                <div className="flex items-start gap-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                    <Info className="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <p className="text-sm font-medium text-yellow-800 dark:text-yellow-300">Order history exists</p>
                                        <p className="text-xs text-yellow-600 dark:text-yellow-400 mt-1">This account contains order history. Deleting will anonymize personal information but preserve orders and invoices for business records.</p>
                                    </div>
                                </div>
                            )}
                            <button type="button" onClick={() => setStep(2)} className="w-full px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium transition-colors">
                                Continue to Delete
                            </button>
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <FormField label="Current Password" htmlFor="del_password" error={form.errors.password}>
                                <div className="relative">
                                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input id="del_password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500" autoComplete="current-password" required />
                                </div>
                            </FormField>
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" checked={form.data.understands} onChange={(e) => form.setData('understands', e.target.checked)} className="mt-1 w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500" />
                                <span className="text-sm text-gray-700 dark:text-gray-300">I understand this action cannot be undone and my personal data will be permanently removed.</span>
                            </label>
                            <div className="flex gap-3">
                                <button type="button" onClick={() => setStep(1)} className="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium transition-colors">Back</button>
                                <button type="submit" disabled={!form.data.understands || !form.data.password || form.processing} className="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium transition-colors">
                                    {form.processing ? <Loader2 className="w-4 h-4 animate-spin mx-auto" /> : 'Delete Account'}
                                </button>
                            </div>
                        </>
                    )}
                </form>
            </div>
        </div>
    );
}

function SecuritySection({ tenant, customer, status }) {
    const storeSlug = tenant.slug;
    const [showCurrent, setShowCurrent] = useState(false);
    const [showNew, setShowNew] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);

    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submit(e) {
        e.preventDefault();
        form.put(route('storefront.customer.profile.password', { store_slug: storeSlug }), {
            onSuccess: () => form.reset(),
        });
    }

    return (
        <div className="space-y-5">
            {/* Change Password */}
            <SectionCard icon={Lock} title="Change Password" description="Keep your account secure">
                {status === 'password-updated' && (
                    <div className="flex items-center gap-2 px-4 py-3 mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                        <p className="text-sm text-green-700 dark:text-green-300">Password changed successfully.</p>
                    </div>
                )}
                <form onSubmit={submit} className="space-y-4">
                    <FormField label="Current Password" htmlFor="s_current" error={form.errors.current_password}>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input id="s_current" type={showCurrent ? 'text' : 'password'} value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} className="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autoComplete="current-password" required />
                            <button type="button" onClick={() => setShowCurrent(!showCurrent)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">{showCurrent ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}</button>
                        </div>
                    </FormField>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormField label="New Password" htmlFor="s_password" error={form.errors.password}>
                            <div className="relative">
                                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input id="s_password" type={showNew ? 'text' : 'password'} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autoComplete="new-password" required />
                                <button type="button" onClick={() => setShowNew(!showNew)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">{showNew ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}</button>
                            </div>
                        </FormField>
                        <FormField label="Confirm Password" htmlFor="s_confirm" error={form.errors.password_confirmation}>
                            <div className="relative">
                                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input id="s_confirm" type={showConfirm ? 'text' : 'password'} value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} className="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autoComplete="new-password" required />
                                <button type="button" onClick={() => setShowConfirm(!showConfirm)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">{showConfirm ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}</button>
                            </div>
                        </FormField>
                    </div>
                    <div className="flex justify-end pt-2">
                        <button type="submit" disabled={form.processing} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium transition-colors">
                            {form.processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Lock className="w-4 h-4" />}
                            {form.processing ? 'Updating...' : 'Update Password'}
                        </button>
                    </div>
                </form>
            </SectionCard>

            {/* Delete Account */}
            <SectionCard icon={Trash2} title="Danger Zone" description="Irreversible account actions" className="border-red-200 dark:border-red-800">
                <div className="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <div className="flex-1">
                        <p className="text-sm font-medium text-red-800 dark:text-red-300">Delete your account</p>
                        <p className="text-xs text-red-600 dark:text-red-400 mt-1">Once you delete your account, there is no going back. Your personal data will be anonymized but orders will be preserved.</p>
                        <button onClick={() => setShowDeleteModal(true)} className="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium transition-colors">
                            <Trash2 className="w-4 h-4" /> Delete Account
                        </button>
                    </div>
                </div>
            </SectionCard>

            <DeleteAccountModal isOpen={showDeleteModal} onClose={() => setShowDeleteModal(false)} storeSlug={storeSlug} hasOrders={customer.has_orders} />
        </div>
    );
}

export default function Account({ tenant, customer, orderStats, recentOrders, mustVerifyEmail, status, defaultTab }) {
    const { auth, storefront } = usePage().props;
    const storeSlug = tenant.slug;
    const [activeTab, setActiveTab] = useState(defaultTab || 'dashboard');

    return (
        <ShopLayout>
            <Head title={`My Account - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-lg font-bold shrink-0">
                            {customer.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">{customer.name}</h1>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Member since {new Date(customer.member_since).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>

                <div className="flex gap-1 mb-6 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl overflow-x-auto">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        return (
                            <button key={tab.key} onClick={() => setActiveTab(tab.key)} className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition-all whitespace-nowrap ${activeTab === tab.key ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'}`}>
                                <Icon className="w-4 h-4" />{tab.label}
                            </button>
                        );
                    })}
                </div>

                {activeTab === 'dashboard' && <DashboardSection tenant={tenant} customer={customer} orderStats={orderStats} recentOrders={recentOrders} />}
                {activeTab === 'profile' && <ProfileSection tenant={tenant} customer={customer} mustVerifyEmail={mustVerifyEmail} status={status} />}
                {activeTab === 'addresses' && <AddressesSection tenant={tenant} customer={customer} />}
                {activeTab === 'preferences' && <PreferencesSection tenant={tenant} customer={customer} />}
                {activeTab === 'security' && <SecuritySection tenant={tenant} customer={customer} status={status} />}
            </div>
        </ShopLayout>
    );
}
