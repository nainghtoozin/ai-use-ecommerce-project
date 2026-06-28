import { Head, useForm } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function submit(e) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <PlatformGuestLayout>
            <Head title="Forgot Password" />

            <p className="text-sm text-gray-600 mb-6">Forgot your password? Enter your email and we'll send you a reset link.</p>

            {status && <div className="mb-4 text-sm font-medium text-green-600">{status}</div>}

            <form onSubmit={submit}>
                <div>
                    <label htmlFor="email" className="block font-medium text-sm text-gray-700">Email</label>
                    <input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                    {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                </div>

                <div className="flex items-center justify-end mt-4">
                    <button type="submit" disabled={processing}
                        className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                        {processing ? 'Sending...' : 'Send Reset Link'}
                    </button>
                </div>
            </form>
        </PlatformGuestLayout>
    );
}
