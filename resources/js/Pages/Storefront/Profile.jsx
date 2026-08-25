import { useState, useRef } from 'react';
import { Head, useForm, usePage, router, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { useTheme } from '@/Contexts/ThemeContext';
import { useTranslation } from '@/Utils/useTranslation';
import {
    User, Mail, Phone, Camera, Lock, Palette, Globe,
    Save, Eye, EyeOff, CheckCircle, Loader2, ArrowLeft
} from 'lucide-react';

function SectionCard({ icon: Icon, title, description, children }) {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
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

function FormField({ label, htmlFor, error, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {label}
            </label>
            {children}
            {error && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{error}</p>}
        </div>
    );
}

const availableLocales = [
    { code: 'en', name: 'English', flag: '🇺🇸' },
    { code: 'my', name: 'Myanmar', flag: '🇲🇲' },
];

export default function CustomerProfile({ tenant, customer, mustVerifyEmail, status }) {
    const { auth, storefront } = usePage().props;
    const { theme, switchTheme } = useTheme();
    const { locale } = useTranslation();
    const fileInputRef = useRef(null);
    const [avatarPreview, setAvatarPreview] = useState(null);
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [languageChanging, setLanguageChanging] = useState(false);

    const storeSlug = tenant.slug;

    const profileForm = useForm({
        name: customer.name || '',
        email: customer.email || '',
        phone: customer.phone || '',
        profile_image: null,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submitProfile(e) {
        e.preventDefault();
        profileForm.post(route('storefront.customer.profile.update', { store_slug: storeSlug }), {
            forceFormData: true,
            onSuccess: () => {
                profileForm.reset('profile_image');
                setAvatarPreview(null);
            },
        });
    }

    function submitPassword(e) {
        e.preventDefault();
        passwordForm.put(route('storefront.customer.profile.password', { store_slug: storeSlug }), {
            onSuccess: () => passwordForm.reset(),
        });
    }

    function handleAvatarChange(e) {
        const file = e.target.files[0];
        if (file) {
            profileForm.setData('profile_image', file);
            setAvatarPreview(URL.createObjectURL(file));
        }
    }

    function handleLanguageChange(localeCode) {
        setLanguageChanging(true);
        router.post(route('language.switch'), { locale: localeCode }, {
            preserveState: true,
            onFinish: () => setLanguageChanging(false),
        });
    }

    const themeOptions = [
        { value: 'light', label: 'Light', icon: '☀️' },
        { value: 'dark', label: 'Dark', icon: '🌙' },
        { value: 'system', label: 'System', icon: '💻' },
    ];

    return (
        <ShopLayout>
            <Head title={`Profile - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {/* Back Link */}
                <Link
                    href={route('storefront.customer.account', { store_slug: storeSlug })}
                    className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-5 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Back to Account
                </Link>

                {/* Page Header */}
                <div className="mb-6">
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your personal information and preferences</p>
                </div>

                {/* Success Messages */}
                {status === 'profile-updated' && (
                    <div className="flex items-center gap-2 px-4 py-3 mb-5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                        <p className="text-sm text-green-700 dark:text-green-300">Profile updated successfully.</p>
                    </div>
                )}
                {status === 'password-updated' && (
                    <div className="flex items-center gap-2 px-4 py-3 mb-5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                        <p className="text-sm text-green-700 dark:text-green-300">Password changed successfully.</p>
                    </div>
                )}

                <div className="space-y-5">
                    {/* Personal Information */}
                    <SectionCard icon={User} title="Personal Information" description="Your basic account details">
                        <form onSubmit={submitProfile} className="space-y-4">
                            {/* Avatar */}
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
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="absolute -bottom-1 -right-1 w-7 h-7 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full flex items-center justify-center shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <Camera className="w-3.5 h-3.5 text-gray-600 dark:text-gray-400" />
                                    </button>
                                    <input ref={fileInputRef} type="file" accept="image/*" onChange={handleAvatarChange} className="hidden" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">Profile Photo</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">JPEG, PNG, GIF, or WebP. Max 2MB.</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <FormField label="Full Name" htmlFor="name" error={profileForm.errors.name}>
                                    <div className="relative">
                                        <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input
                                            id="name"
                                            type="text"
                                            value={profileForm.data.name}
                                            onChange={(e) => profileForm.setData('name', e.target.value)}
                                            className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            required
                                        />
                                    </div>
                                </FormField>

                                <FormField label="Email Address" htmlFor="email" error={profileForm.errors.email}>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input
                                            id="email"
                                            type="email"
                                            value={profileForm.data.email}
                                            onChange={(e) => profileForm.setData('email', e.target.value)}
                                            className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            required
                                        />
                                    </div>
                                </FormField>
                            </div>

                            <FormField label="Phone Number" htmlFor="phone" error={profileForm.errors.phone}>
                                <div className="relative">
                                    <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        id="phone"
                                        type="tel"
                                        value={profileForm.data.phone}
                                        onChange={(e) => profileForm.setData('phone', e.target.value)}
                                        className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="+95 9xxx xxx xxxx"
                                    />
                                </div>
                            </FormField>

                            {mustVerifyEmail && auth.user.email_verified_at === null && (
                                <div className="flex items-start gap-2 px-3 py-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                    <p className="text-sm text-yellow-700 dark:text-yellow-300">Your email is unverified.</p>
                                    <button type="button" className="text-xs text-blue-600 dark:text-blue-400 hover:underline ml-1">
                                        Resend verification
                                    </button>
                                </div>
                            )}

                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={profileForm.processing}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium transition-colors"
                                >
                                    {profileForm.processing ? (
                                        <Loader2 className="w-4 h-4 animate-spin" />
                                    ) : (
                                        <Save className="w-4 h-4" />
                                    )}
                                    {profileForm.processing ? 'Saving...' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </SectionCard>

                    {/* Preferences */}
                    <SectionCard icon={Palette} title="Preferences" description="Customize your experience">
                        <div className="space-y-5">
                            {/* Theme */}
                            <div>
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Theme</p>
                                <div className="grid grid-cols-3 gap-2">
                                    {themeOptions.map((opt) => (
                                        <button
                                            key={opt.value}
                                            onClick={() => switchTheme(opt.value)}
                                            className={`flex flex-col items-center gap-1.5 px-3 py-3 rounded-lg border text-sm font-medium transition-all ${
                                                theme === opt.value
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                                    : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
                                            }`}
                                        >
                                            <span className="text-lg">{opt.icon}</span>
                                            <span>{opt.label}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Language */}
                            <div>
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Language</p>
                                <div className="grid grid-cols-2 gap-2">
                                    {availableLocales.map((loc) => (
                                        <button
                                            key={loc.code}
                                            onClick={() => handleLanguageChange(loc.code)}
                                            disabled={languageChanging}
                                            className={`flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition-all ${
                                                locale === loc.code
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                                    : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
                                            }`}
                                        >
                                            {loc.flag && <span>{loc.flag}</span>}
                                            <span>{loc.name}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </SectionCard>

                    {/* Change Password */}
                    <SectionCard icon={Lock} title="Change Password" description="Keep your account secure">
                        <form onSubmit={submitPassword} className="space-y-4">
                            <FormField label="Current Password" htmlFor="current_password" error={passwordForm.errors.current_password}>
                                <div className="relative">
                                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        id="current_password"
                                        type={showCurrentPassword ? 'text' : 'password'}
                                        value={passwordForm.data.current_password}
                                        onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                        className="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        autoComplete="current-password"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        {showCurrentPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                            </FormField>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <FormField label="New Password" htmlFor="password" error={passwordForm.errors.password}>
                                    <div className="relative">
                                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input
                                            id="password"
                                            type={showNewPassword ? 'text' : 'password'}
                                            value={passwordForm.data.password}
                                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                                            className="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            autoComplete="new-password"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowNewPassword(!showNewPassword)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            {showNewPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                        </button>
                                    </div>
                                </FormField>

                                <FormField label="Confirm Password" htmlFor="password_confirmation" error={passwordForm.errors.password_confirmation}>
                                    <div className="relative">
                                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input
                                            id="password_confirmation"
                                            type={showConfirmPassword ? 'text' : 'password'}
                                            value={passwordForm.data.password_confirmation}
                                            onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                            className="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            autoComplete="new-password"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                        </button>
                                    </div>
                                </FormField>
                            </div>

                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium transition-colors"
                                >
                                    {passwordForm.processing ? (
                                        <Loader2 className="w-4 h-4 animate-spin" />
                                    ) : (
                                        <Save className="w-4 h-4" />
                                    )}
                                    {passwordForm.processing ? 'Saving...' : 'Update Password'}
                                </button>
                            </div>
                        </form>
                    </SectionCard>
                </div>
            </div>
        </ShopLayout>
    );
}
