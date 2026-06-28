import { useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    useEffect(() => {
        return () => reset('password');
    }, []);

    function submit(e) {
        e.preventDefault();
        post('/confirm-password', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <PlatformGuestLayout>
            <Head title="Confirm Password" />

            <p className="text-sm text-gray-600 mb-6">This is a secure area. Please confirm your password before continuing.</p>

            <form onSubmit={submit}>
                <div>
                    <label htmlFor="password" className="block font-medium text-sm text-gray-700">Password</label>
                    <input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autoComplete="current-password" />
                    {errors.password && <p className="text-red-500 text-sm mt-1">{errors.password}</p>}
                </div>

                <div className="flex items-center justify-end mt-4">
                    <button type="submit" disabled={processing}
                        className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                        {processing ? 'Confirming...' : 'Confirm'}
                    </button>
                </div>
            </form>
        </PlatformGuestLayout>
    );
}
