import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Eye, EyeOff, Loader2, Check, X } from 'lucide-react';

function getPasswordStrength(password) {
    if (!password) return { score: 0, label: '', color: '' };

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 1) return { score, label: 'Weak', color: 'bg-red-500' };
    if (score <= 2) return { score, label: 'Fair', color: 'bg-orange-500' };
    if (score <= 3) return { score, label: 'Good', color: 'bg-yellow-500' };
    return { score, label: 'Strong', color: 'bg-green-500' };
}

function PasswordStrengthIndicator({ password }) {
    const strength = getPasswordStrength(password);
    if (!password) return null;

    const bars = 4;
    const filledBars = Math.min(Math.ceil(strength.score * bars / 5), bars);

    return (
        <div className="mt-2">
            <div className="flex gap-1 mb-1">
                {Array.from({ length: bars }).map((_, i) => (
                    <div
                        key={i}
                        className={`h-1.5 flex-1 rounded-full transition-colors duration-300 ${
                            i < filledBars ? strength.color : 'bg-gray-200 dark:bg-gray-700'
                        }`}
                    />
                ))}
            </div>
            <span className={`text-xs font-medium ${
                strength.score <= 1 ? 'text-red-600 dark:text-red-400' :
                strength.score <= 2 ? 'text-orange-600 dark:text-orange-400' :
                strength.score <= 3 ? 'text-yellow-600 dark:text-yellow-400' :
                'text-green-600 dark:text-green-400'
            }`}>
                {strength.label}
            </span>
        </div>
    );
}

function PasswordRequirements({ password }) {
    const checks = [
        { label: 'At least 8 characters', met: password.length >= 8 },
        { label: 'Contains uppercase letter', met: /[A-Z]/.test(password) },
        { label: 'Contains lowercase letter', met: /[a-z]/.test(password) },
        { label: 'Contains a number', met: /\d/.test(password) },
    ];

    if (!password) return null;

    return (
        <div className="mt-2 space-y-1">
            {checks.map((check) => (
                <div key={check.label} className="flex items-center gap-2 text-xs">
                    {check.met ? (
                        <Check className="w-3 h-3 text-green-500" />
                    ) : (
                        <X className="w-3 h-3 text-gray-400" />
                    )}
                    <span className={check.met ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'}>
                        {check.label}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function Register() {
    const { errors } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <PlatformGuestLayout>
            <Head title="Create Account" />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Create your account
                </h1>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Get started with your free account
                </p>
            </div>

            <form onSubmit={submit}>
                <div>
                    <label htmlFor="name" className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        Full Name
                    </label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoComplete="name"
                        placeholder="John Doe"
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                </div>

                <div className="mt-4">
                    <label htmlFor="email" className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        Email Address
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoComplete="username"
                        placeholder="you@example.com"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                </div>

                <div className="mt-4">
                    <label htmlFor="password" className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        Password
                    </label>
                    <div className="relative">
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                            autoComplete="new-password"
                            placeholder="Create a strong password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <button
                            type="button"
                            className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            onClick={() => setShowPassword(!showPassword)}
                            tabIndex={-1}
                        >
                            {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                    </div>
                    {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                    <PasswordStrengthIndicator password={data.password} />
                    <PasswordRequirements password={data.password} />
                </div>

                <div className="mt-4">
                    <label htmlFor="password_confirmation" className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        Confirm Password
                    </label>
                    <div className="relative">
                        <input
                            id="password_confirmation"
                            type={showConfirmPassword ? 'text' : 'password'}
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                            autoComplete="new-password"
                            placeholder="Confirm your password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <button
                            type="button"
                            className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                            tabIndex={-1}
                        >
                            {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                    </div>
                    {errors.password_confirmation && <p className="text-red-500 text-xs mt-1">{errors.password_confirmation}</p>}
                </div>

                <div className="mt-6">
                    <button
                        type="submit"
                        className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled={processing}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                Creating account...
                            </>
                        ) : (
                            'Create Account'
                        )}
                    </button>
                </div>

                <div className="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                    Already have an account?{' '}
                    <Link href="/login" className="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium">
                        Sign in
                    </Link>
                </div>
            </form>
        </PlatformGuestLayout>
    );
}
