import { useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';

export default function ResetPassword({ token, email, store_slug }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
        ...(store_slug ? { store_slug } : {}),
    });

    useEffect(() => {
        return () => reset('password', 'password_confirmation');
    }, []);

    function submit(e) {
        e.preventDefault();
        post(store_slug ? `/store/${store_slug}/reset-password` : '/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <PlatformGuestLayout>
            <Head title="Reset Password" />

            <form onSubmit={submit}>
                <div>
                    <label htmlFor="email" className="block font-medium text-sm text-gray-700">Email</label>
                    <input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autoComplete="username" />
                    {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                </div>

                <div className="mt-4">
                    <label htmlFor="password" className="block font-medium text-sm text-gray-700">Password</label>
                    <input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autoComplete="new-password" />
                    {errors.password && <p className="text-red-500 text-sm mt-1">{errors.password}</p>}
                </div>

                <div className="mt-4">
                    <label htmlFor="password_confirmation" className="block font-medium text-sm text-gray-700">Confirm Password</label>
                    <input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autoComplete="new-password" />
                    {errors.password_confirmation && <p className="text-red-500 text-sm mt-1">{errors.password_confirmation}</p>}
                </div>

                <div className="flex items-center justify-end mt-4">
                    <button type="submit" disabled={processing}
                        className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                        {processing ? 'Resetting...' : 'Reset Password'}
                    </button>
                </div>
            </form>
        </PlatformGuestLayout>
    );
}
