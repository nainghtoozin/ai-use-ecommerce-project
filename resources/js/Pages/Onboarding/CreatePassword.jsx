import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';

export default function CreatePassword() {
    const { errors } = usePage().props;
    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [processing, setProcessing] = useState(false);
    const [show, setShow] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        if (password !== confirm) return;
        setProcessing(true);
        router.post('/password/create', {
            password,
            password_confirmation: confirm,
        }, {
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                router.visit('/onboarding/store');
            },
        });
    };

    return (
        <PlatformGuestLayout>
            <Head title="Create Your Password" />
            <div className="max-w-md mx-auto py-12">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Create Your Password</h1>
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Your Google account has been connected successfully. Create a password so you can also sign in using your email and password.
                </p>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">New Password</label>
                        <input type={show ? 'text' : 'password'} value={password} onChange={(e) => setPassword(e.target.value)} className="w-full border rounded-lg px-4 py-2" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Confirm Password</label>
                        <input type={show ? 'text' : 'password'} value={confirm} onChange={(e) => setConfirm(e.target.value)} className="w-full border rounded-lg px-4 py-2" required />
                        {password && confirm && password !== confirm && (
                            <p className="text-xs text-red-500 mt-1">Passwords do not match.</p>
                        )}
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={show} onChange={(e) => setShow(e.target.checked)} />
                        Show password
                    </label>
                    {errors?.password && <p className="text-sm text-red-500">{errors.password}</p>}
                    <button type="submit" disabled={processing} className="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg disabled:opacity-50">
                        {processing ? 'Creating...' : 'Create Password & Continue'}
                    </button>
                </form>
            </div>
        </PlatformGuestLayout>
    );
}