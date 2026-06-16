import { useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';
import ShopLayout from '@/Layouts/ShopLayout';

const NOTIFICATION_LABELS = {
    order_placed: { label: 'Order Placed', desc: 'When your order is successfully placed' },
    order_status_changed: { label: 'Order Status Changed', desc: 'When your order is confirmed, shipped, delivered, or cancelled' },
    payment_verified: { label: 'Payment Verified', desc: 'When your payment has been verified by admin' },
    payment_rejected: { label: 'Payment Rejected', desc: 'When your payment has been rejected' },
    new_order: { label: 'New Order', desc: 'When a customer places a new order' },
    payment_proof_uploaded: { label: 'Payment Proof Uploaded', desc: 'When a customer uploads payment proof' },
    low_stock: { label: 'Low Stock Alert', desc: 'When a product runs low on stock' },
    order_cancelled: { label: 'Order Cancelled', desc: 'When an order is cancelled by the customer' },
    new_message: { label: 'New Message', desc: 'When you receive a new chat message' },
    notification_sound: { label: 'Notification Sound', desc: 'Play a sound when new notifications arrive' },
};

export default function ProfileEdit({ mustVerifyEmail, status, notificationPreferences, allowedNotificationTypes }) {
    const { auth } = usePage().props;

    const profileForm = useForm({
        name: auth.user.name || '',
        email: auth.user.email || '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const deleteForm = useForm({ password: '' });
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [preferences, setPreferences] = useState(notificationPreferences || {});
    const [savingPrefs, setSavingPrefs] = useState(false);
    const [prefSuccess, setPrefSuccess] = useState(false);

    function submitProfile(e) {
        e.preventDefault();
        profileForm.patch(adminUrl('/profile'), {
            onSuccess: () => profileForm.reset(),
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
        deleteForm.delete(adminUrl('/profile'), {
            onSuccess: () => deleteForm.reset(),
        });
    }

    function togglePreference(type) {
        setPreferences((prev) => ({
            ...prev,
            [type]: !prev[type],
        }));
        setPrefSuccess(false);
    }

    function savePreferences() {
        setSavingPrefs(true);
        setPrefSuccess(false);
        fetch('/notifications/preferences', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ preferences }),
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.success) {
                    setPreferences(data.preferences);
                    setPrefSuccess(true);
                    setTimeout(() => setPrefSuccess(false), 3000);
                }
            })
            .catch(() => alert('Failed to save preferences'))
            .finally(() => setSavingPrefs(false));
    }

    const customerTypes = ['order_placed', 'order_status_changed', 'payment_verified', 'payment_rejected'];
    const adminTypes = ['new_order', 'payment_proof_uploaded', 'low_stock', 'order_cancelled'];

    return (
        <ShopLayout>
            <Head title="Profile" />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                <h1 className="text-2xl font-bold text-gray-900">Profile</h1>

                {status === 'profile-updated' && (
                    <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm">Profile updated successfully.</div>
                )}
                {status === 'password-updated' && (
                    <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm">Password updated successfully.</div>
                )}

                {/* Profile Information */}
                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-1">Profile Information</h2>
                    <p className="text-sm text-gray-500 mb-6">Update your name and email address.</p>

                    <form onSubmit={submitProfile}>
                        <div className="mb-4">
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input
                                id="name"
                                type="text"
                                value={profileForm.data.name}
                                onChange={(e) => profileForm.setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            />
                            {profileForm.errors.name && <p className="text-red-500 text-sm mt-1">{profileForm.errors.name}</p>}
                        </div>

                        <div className="mb-4">
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                id="email"
                                type="email"
                                value={profileForm.data.email}
                                onChange={(e) => profileForm.setData('email', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            />
                            {profileForm.errors.email && <p className="text-red-500 text-sm mt-1">{profileForm.errors.email}</p>}
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div className="mb-4">
                                <p className="text-sm text-yellow-600">Your email address is unverified.</p>
                                <button type="button" className="text-sm text-blue-600 hover:underline mt-1">Click here to re-send the verification email.</button>
                            </div>
                        )}

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={profileForm.processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                            >
                                {profileForm.processing ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Update Password */}
                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-1">Update Password</h2>
                    <p className="text-sm text-gray-500 mb-6">Ensure your account is using a long, random password to stay secure.</p>

                    <form onSubmit={submitPassword}>
                        <div className="mb-4">
                            <label htmlFor="current_password" className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                            <input
                                id="current_password"
                                type="password"
                                value={passwordForm.data.current_password}
                                onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autoComplete="current-password"
                                required
                            />
                            {passwordForm.errors.current_password && <p className="text-red-500 text-sm mt-1">{passwordForm.errors.current_password}</p>}
                        </div>

                        <div className="mb-4">
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input
                                id="password"
                                type="password"
                                value={passwordForm.data.password}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autoComplete="new-password"
                                required
                            />
                            {passwordForm.errors.password && <p className="text-red-500 text-sm mt-1">{passwordForm.errors.password}</p>}
                        </div>

                        <div className="mb-4">
                            <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autoComplete="new-password"
                                required
                            />
                            {passwordForm.errors.password_confirmation && <p className="text-red-500 text-sm mt-1">{passwordForm.errors.password_confirmation}</p>}
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={passwordForm.processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                            >
                                {passwordForm.processing ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Notification Preferences */}
                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-1">Notification Preferences</h2>
                    <p className="text-sm text-gray-500 mb-6">Choose which notifications you want to receive.</p>

                    {prefSuccess && (
                        <div className="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm">
                            Notification preferences saved successfully.
                        </div>
                    )}

                    <div className="space-y-1 divide-y divide-gray-100">
                        {allowedNotificationTypes.map((type) => {
                            const info = NOTIFICATION_LABELS[type] || { label: type, desc: '' };
                            return (
                                <div key={type} className="flex items-center justify-between py-3">
                                    <div className="pr-4">
                                        <p className="text-sm font-medium text-gray-900">{info.label}</p>
                                        <p className="text-xs text-gray-500">{info.desc}</p>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={preferences[type] ?? true}
                                        onClick={() => togglePreference(type)}
                                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex-shrink-0 ${
                                            preferences[type] ?? true ? 'bg-blue-600' : 'bg-gray-200'
                                        }`}
                                    >
                                        <span
                                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                preferences[type] ?? true ? 'translate-x-6' : 'translate-x-1'
                                            }`}
                                        />
                                    </button>
                                </div>
                            );
                        })}
                    </div>

                    <div className="mt-6 flex justify-end">
                        <button
                            type="button"
                            onClick={savePreferences}
                            disabled={savingPrefs}
                            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                        >
                            {savingPrefs ? 'Saving...' : 'Save Preferences'}
                        </button>
                    </div>
                </div>

                {/* Delete Account */}
                <div className="bg-white rounded-lg border border-red-200 p-6">
                    <h2 className="text-lg font-semibold text-red-600 mb-1">Delete Account</h2>
                    <p className="text-sm text-gray-500 mb-6">Once your account is deleted, all of its resources and data will be permanently deleted.</p>

                    {!showDeleteConfirm ? (
                        <div className="flex justify-end">
                            <button onClick={() => setShowDeleteConfirm(true)} className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                                Delete Account
                            </button>
                        </div>
                    ) : (
                        <form onSubmit={submitDelete}>
                            <div className="mb-4">
                                <label htmlFor="delete_password" className="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input
                                    id="delete_password"
                                    type="password"
                                    value={deleteForm.data.password}
                                    onChange={(e) => deleteForm.setData('password', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
                                    autoComplete="current-password"
                                    placeholder="Enter your password to confirm"
                                    required
                                />
                                {deleteForm.errors.password && <p className="text-red-500 text-sm mt-1">{deleteForm.errors.password}</p>}
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm font-medium">
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={deleteForm.processing}
                                    className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 text-sm font-medium"
                                >
                                    {deleteForm.processing ? 'Deleting...' : 'Confirm Delete'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
