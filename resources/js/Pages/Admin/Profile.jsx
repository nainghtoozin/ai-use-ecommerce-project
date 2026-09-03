import { useState, useRef } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { useTheme } from '@/Contexts/ThemeContext';
import { useTranslation } from '@/Utils/useTranslation';
import {
    User, Mail, Phone, Shield, Building2, Camera,
    Lock, Globe, Palette, Clock, Save, Eye, EyeOff,
    CheckCircle, AlertCircle, Loader2, Sun, Moon, Monitor,
    Calendar, Key, Activity, Globe2, BadgeCheck, ShieldCheck,
    Bell, Trash2, Info, Settings, ExternalLink, CreditCard
} from 'lucide-react';

const availableLocales = [
    { code: 'en', name: 'English', flag: '🇺🇸' },
    { code: 'my', name: 'Myanmar', flag: '🇲🇲' },
];

const tabs = [
    { key: 'profile', label: 'Profile', icon: User },
    { key: 'store', label: 'Store', icon: Building2 },
    { key: 'security', label: 'Security', icon: Lock },
    { key: 'preferences', label: 'Preferences', icon: Settings },
    { key: 'activity', label: 'Activity', icon: Activity },
    { key: 'danger', label: 'Danger Zone', icon: Trash2 },
];

function PasswordStrength({ password }) {
    if (!password) return null;
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-blue-500', 'bg-green-500'];
    const textColors = ['text-red-600 dark:text-red-400', 'text-orange-600 dark:text-orange-400', 'text-yellow-600 dark:text-yellow-400', 'text-blue-600 dark:text-blue-400', 'text-green-600 dark:text-green-400'];

    return (
        <div className="mt-2">
            <div className="flex gap-1 mb-1">
                {[1, 2, 3, 4, 5].map((i) => (
                    <div key={i} className={`h-1 flex-1 rounded-full transition-colors ${i <= strength ? colors[strength - 1] : 'bg-gray-200 dark:bg-gray-700'}`} />
                ))}
            </div>
            <p className={`text-xs ${textColors[strength - 1] || 'text-gray-400'}`}>
                {strength > 0 ? labels[strength - 1] : 'Enter a password'}
            </p>
        </div>
    );
}

function ProfileTab({ auth, profileForm, submitProfile, fileInputRef, avatarPreview, handleAvatarChange, mustVerifyEmail }) {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                        <User className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Personal Information</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Your basic account details</p>
                    </div>
                </div>
            </div>
            <form onSubmit={submitProfile} className="p-6 space-y-5">
                <div className="flex items-center gap-5">
                    <div className="relative group">
                        {avatarPreview ? (
                            <img src={avatarPreview} alt="Preview" className="w-20 h-20 rounded-2xl object-cover ring-4 ring-gray-100 dark:ring-gray-800 shadow-sm" />
                        ) : auth.user.profile_image_url ? (
                            <img src={auth.user.profile_image_url} alt="Avatar" className="w-20 h-20 rounded-2xl object-cover ring-4 ring-gray-100 dark:ring-gray-800 shadow-sm" />
                        ) : (
                            <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-2xl font-bold ring-4 ring-gray-100 dark:ring-gray-800 shadow-sm">
                                {auth.user.name?.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="absolute inset-0 flex items-center justify-center bg-black/50 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <Camera className="w-5 h-5 text-white" />
                        </button>
                        <input ref={fileInputRef} type="file" accept="image/*" onChange={handleAvatarChange} className="hidden" />
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-900 dark:text-white">Profile Photo</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">JPEG, PNG, GIF, or WebP. Max 2MB.</p>
                        <button type="button" onClick={() => fileInputRef.current?.click()} className="mt-2 text-xs text-blue-600 dark:text-blue-400 hover:underline">
                            Change photo
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Name</label>
                        <div className="relative">
                            <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                id="name"
                                type="text"
                                value={profileForm.data.name}
                                onChange={(e) => profileForm.setData('name', e.target.value)}
                                className="w-full pl-10 pr-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                required
                            />
                        </div>
                        {profileForm.errors.name && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{profileForm.errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email Address</label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                id="email"
                                type="email"
                                value={profileForm.data.email}
                                onChange={(e) => profileForm.setData('email', e.target.value)}
                                className="w-full pl-10 pr-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                required
                            />
                        </div>
                        {profileForm.errors.email && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{profileForm.errors.email}</p>}
                    </div>
                </div>

                <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Phone Number</label>
                    <div className="relative max-w-sm">
                        <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            id="phone"
                            type="tel"
                            value={profileForm.data.phone}
                            onChange={(e) => profileForm.setData('phone', e.target.value)}
                            className="w-full pl-10 pr-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="+95 9xxx xxx xxxx"
                        />
                    </div>
                    {profileForm.errors.phone && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{profileForm.errors.phone}</p>}
                </div>

                {mustVerifyEmail && auth.user.email_verified_at === null && (
                    <div className="flex items-start gap-3 px-4 py-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl">
                        <AlertCircle className="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-medium text-yellow-700 dark:text-yellow-300">Email verification required</p>
                            <p className="text-xs text-yellow-600 dark:text-yellow-400 mt-0.5">Please verify your email address to access all features.</p>
                            <button type="button" className="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1">Resend verification email</button>
                        </div>
                    </div>
                )}

                <div className="flex justify-end pt-2">
                    <button
                        type="submit"
                        disabled={profileForm.processing}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 text-sm font-medium transition-colors shadow-sm"
                    >
                        {profileForm.processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                        {profileForm.processing ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function StoreTab({ tenant, currentRole, auth, lastLoginAt }) {
    const storeUrl = tenant?.store_url || (tenant?.slug ? `/store/${tenant.slug}` : null);
    const subscriptionPlan = tenant?.subscription_plan;

    return (
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
                        <Building2 className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Business Information</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Your store and account details</p>
                    </div>
                </div>
            </div>
            <div className="p-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <Building2 className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Store Name</p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">{tenant?.name || 'Not Configured'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <Globe2 className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Store URL</p>
                            {storeUrl ? (
                                <a href={storeUrl} target="_blank" rel="noopener noreferrer" className="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                                    {tenant?.domain || storeUrl}
                                    <ExternalLink className="w-3 h-3" />
                                </a>
                            ) : (
                                <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Not Configured</p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <Shield className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Role</p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">{currentRole || auth.user.role_label || 'Not Assigned'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <CheckCircle className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Status</p>
                            <p className={`text-sm font-medium capitalize ${
                                tenant?.status === 'active' ? 'text-green-600 dark:text-green-400' :
                                tenant?.status === 'trialing' ? 'text-blue-600 dark:text-blue-400' :
                                tenant?.status === 'suspended' ? 'text-red-600 dark:text-red-400' :
                                'text-gray-600 dark:text-gray-400'
                            }`}>{tenant?.status || 'Unknown'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <Calendar className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Store Created</p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                {tenant?.created_at ? new Date(tenant.created_at).toLocaleDateString() : 'Unknown'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <Mail className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Email Verified</p>
                            <p className={`text-sm font-medium ${auth.user.email_verified_at ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'}`}>
                                {auth.user.email_verified_at ? 'Verified' : 'Pending'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <Clock className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Last Login</p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                {lastLoginAt ? new Date(lastLoginAt).toLocaleString() : 'Never Logged In'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <CreditCard className="w-5 h-5 text-gray-400" />
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Subscription Plan</p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                {subscriptionPlan?.name || 'Free Trial'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function SecurityTab({ passwordForm, submitPassword, showCurrentPassword, setShowCurrentPassword, showNewPassword, setShowNewPassword, showConfirmPassword, setShowConfirmPassword }) {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                        <Lock className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Change Password</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Keep your account secure</p>
                    </div>
                </div>
            </div>
            <form onSubmit={submitPassword} className="p-6 space-y-5">
                <div>
                    <label htmlFor="current_password" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Current Password</label>
                    <div className="relative max-w-md">
                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            id="current_password"
                            type={showCurrentPassword ? 'text' : 'password'}
                            value={passwordForm.data.current_password}
                            onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                            className="w-full pl-10 pr-10 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
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
                    {passwordForm.errors.current_password && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{passwordForm.errors.current_password}</p>}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">New Password</label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                id="password"
                                type={showNewPassword ? 'text' : 'password'}
                                value={passwordForm.data.password}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                className="w-full pl-10 pr-10 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
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
                        <PasswordStrength password={passwordForm.data.password} />
                        {passwordForm.errors.password && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{passwordForm.errors.password}</p>}
                    </div>

                    <div>
                        <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm Password</label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                id="password_confirmation"
                                type={showConfirmPassword ? 'text' : 'password'}
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                className="w-full pl-10 pr-10 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
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
                    </div>
                </div>

                <div className="flex justify-end pt-2">
                    <button
                        type="submit"
                        disabled={passwordForm.processing}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl hover:bg-gray-800 dark:hover:bg-gray-100 disabled:opacity-50 text-sm font-medium transition-colors shadow-sm"
                    >
                        {passwordForm.processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Lock className="w-4 h-4" />}
                        {passwordForm.processing ? 'Updating...' : 'Update Password'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function PreferencesTab({ theme, switchTheme, locale, handleLanguageChange, languageChanging }) {
    const themeOptions = [
        { value: 'light', label: 'Light', icon: Sun, desc: 'Light mode' },
        { value: 'dark', label: 'Dark', icon: Moon, desc: 'Dark mode' },
        { value: 'system', label: 'System', icon: Monitor, desc: 'Follow OS' },
    ];

    return (
        <div className="space-y-6">
            <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                            <Palette className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Appearance</h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Choose your preferred theme</p>
                        </div>
                    </div>
                </div>
                <div className="p-6">
                    <div className="grid grid-cols-3 gap-3">
                        {themeOptions.map((opt) => {
                            const Icon = opt.icon;
                            return (
                                <button
                                    key={opt.value}
                                    onClick={() => switchTheme(opt.value)}
                                    className={`flex flex-col items-center gap-2 px-3 py-4 rounded-xl border text-sm font-medium transition-all ${
                                        theme === opt.value
                                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 shadow-sm'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
                                    }`}
                                >
                                    <Icon className="w-5 h-5" />
                                    <span>{opt.label}</span>
                                    <span className="text-[10px] text-gray-400 dark:text-gray-500">{opt.desc}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            </div>

            <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
                            <Globe className="w-4 h-4 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Language</h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Select your preferred language</p>
                        </div>
                    </div>
                </div>
                <div className="p-6">
                    <div className="grid grid-cols-2 gap-3">
                        {availableLocales.map((loc) => (
                            <button
                                key={loc.code}
                                onClick={() => handleLanguageChange(loc.code)}
                                disabled={languageChanging}
                                className={`flex items-center justify-center gap-2 px-3 py-3 rounded-xl border text-sm font-medium transition-all ${
                                    locale === loc.code
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
                                }`}
                            >
                                <span>{loc.flag}</span>
                                <span>{loc.name}</span>
                                {locale === loc.code && <CheckCircle className="w-4 h-4 text-blue-600 dark:text-blue-400" />}
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function ActivityTab() {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center">
                        <Activity className="w-4 h-4 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Activity</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Your latest account activities</p>
                    </div>
                </div>
            </div>
            <div className="p-6">
                <div className="space-y-4">
                    <div className="flex items-start gap-3">
                        <div className="w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                            <User className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm text-gray-900 dark:text-white">Profile updated</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Recently</p>
                        </div>
                    </div>
                    <div className="flex items-start gap-3">
                        <div className="w-8 h-8 rounded-full bg-green-50 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                            <Lock className="w-3.5 h-3.5 text-green-600 dark:text-green-400" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm text-gray-900 dark:text-white">Logged in</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Today</p>
                        </div>
                    </div>
                    <div className="flex items-start gap-3">
                        <div className="w-8 h-8 rounded-full bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                            <Globe className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm text-gray-900 dark:text-white">Language changed</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Recently</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function DangerZoneTab({ deleteForm, submitDelete, showDeleteConfirm, setShowDeleteConfirm }) {
    return (
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-red-200 dark:border-red-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-red-100 dark:border-red-800 bg-red-50 dark:bg-red-900/20">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <Trash2 className="w-4 h-4 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-red-900 dark:text-red-100">Danger Zone</h3>
                        <p className="text-xs text-red-500 dark:text-red-400">Irreversible account actions</p>
                    </div>
                </div>
            </div>
            <div className="p-6">
                <div className="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 rounded-xl">
                    <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <div className="flex-1">
                        <p className="text-sm font-medium text-red-800 dark:text-red-300">Delete your account</p>
                        <p className="text-xs text-red-600 dark:text-red-400 mt-1">Once you delete your account, there is no going back. Please be certain.</p>
                        
                        {!showDeleteConfirm ? (
                            <button
                                onClick={() => setShowDeleteConfirm(true)}
                                className="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium transition-colors"
                            >
                                <Trash2 className="w-4 h-4" /> Delete Account
                            </button>
                        ) : (
                            <form onSubmit={submitDelete} className="mt-4 space-y-3">
                                <div>
                                    <label htmlFor="del_password" className="block text-sm font-medium text-red-700 dark:text-red-300 mb-1">Confirm your password</label>
                                    <input
                                        id="del_password"
                                        type="password"
                                        value={deleteForm.data.password}
                                        onChange={(e) => deleteForm.setData('password', e.target.value)}
                                        className="w-full px-3 py-2 border border-red-300 dark:border-red-700 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                        autoComplete="current-password"
                                        required
                                    />
                                    {deleteForm.errors.password && <p className="text-red-500 dark:text-red-400 text-xs mt-1">{deleteForm.errors.password}</p>}
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setShowDeleteConfirm(false)}
                                        className="px-4 py-2 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={deleteForm.processing}
                                        className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 text-sm font-medium transition-colors"
                                    >
                                        {deleteForm.processing ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Confirm Delete'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminProfile({ mustVerifyEmail, status, currentRole, currentPermissions, tenant, notificationPreferences, lastLoginAt }) {
    const { auth } = usePage().props;
    const { theme, switchTheme } = useTheme();
    const { t, locale } = useTranslation();
    const fileInputRef = useRef(null);
    const [avatarPreview, setAvatarPreview] = useState(null);
    const [activeTab, setActiveTab] = useState('profile');
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [languageChanging, setLanguageChanging] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const profileForm = useForm({
        name: auth.user.name || '',
        email: auth.user.email || '',
        phone: auth.user.phone || '',
        profile_image: null,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const deleteForm = useForm({
        password: '',
    });

    function submitProfile(e) {
        e.preventDefault();
        profileForm.post(adminUrl('/profile', tenant?.slug), {
            forceFormData: true,
            onSuccess: () => {
                profileForm.reset('profile_image');
                setAvatarPreview(null);
            },
        });
    }

    function submitPassword(e) {
        e.preventDefault();
        passwordForm.put('/password', {
            onSuccess: () => passwordForm.reset(),
        });
    }

    function submitDelete(e) {
        e.preventDefault();
        deleteForm.delete(adminUrl('/profile', tenant?.slug), {
            onSuccess: () => deleteForm.reset(),
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

    return (
        <AdminLayout>
            <Head title="Account Settings" />

            <div className="w-full max-w-[1400px] mx-auto px-4 lg:px-6 py-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">Account Settings</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage your profile, security, and preferences</p>
                    </div>
                </div>

                {/* Success Messages */}
                {status === 'profile-updated' && (
                    <div className="flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl animate-slide-up">
                        <CheckCircle className="w-5 h-5 text-green-600 dark:text-green-400" />
                        <p className="text-sm font-medium text-green-700 dark:text-green-300">Profile updated successfully.</p>
                    </div>
                )}
                {status === 'password-updated' && (
                    <div className="flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl animate-slide-up">
                        <CheckCircle className="w-5 h-5 text-green-600 dark:text-green-400" />
                        <p className="text-sm font-medium text-green-700 dark:text-green-300">Password changed successfully.</p>
                    </div>
                )}

                {/* Tabs */}
                <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl overflow-x-auto">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        return (
                            <button
                                key={tab.key}
                                onClick={() => setActiveTab(tab.key)}
                                className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition-all whitespace-nowrap ${
                                    activeTab === tab.key
                                        ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                }`}
                            >
                                <Icon className="w-4 h-4" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* Tab Content */}
                {activeTab === 'profile' && (
                    <ProfileTab
                        auth={auth}
                        profileForm={profileForm}
                        submitProfile={submitProfile}
                        fileInputRef={fileInputRef}
                        avatarPreview={avatarPreview}
                        handleAvatarChange={handleAvatarChange}
                        mustVerifyEmail={mustVerifyEmail}
                    />
                )}

                {activeTab === 'store' && (
                    <StoreTab tenant={tenant} currentRole={currentRole} auth={auth} lastLoginAt={lastLoginAt} />
                )}

                {activeTab === 'security' && (
                    <SecurityTab
                        passwordForm={passwordForm}
                        submitPassword={submitPassword}
                        showCurrentPassword={showCurrentPassword}
                        setShowCurrentPassword={setShowCurrentPassword}
                        showNewPassword={showNewPassword}
                        setShowNewPassword={setShowNewPassword}
                        showConfirmPassword={showConfirmPassword}
                        setShowConfirmPassword={setShowConfirmPassword}
                    />
                )}

                {activeTab === 'preferences' && (
                    <PreferencesTab
                        theme={theme}
                        switchTheme={switchTheme}
                        locale={locale}
                        handleLanguageChange={handleLanguageChange}
                        languageChanging={languageChanging}
                    />
                )}

                {activeTab === 'activity' && <ActivityTab />}

                {activeTab === 'danger' && (
                    <DangerZoneTab
                        deleteForm={deleteForm}
                        submitDelete={submitDelete}
                        showDeleteConfirm={showDeleteConfirm}
                        setShowDeleteConfirm={setShowDeleteConfirm}
                    />
                )}
            </div>
        </AdminLayout>
    );
}
