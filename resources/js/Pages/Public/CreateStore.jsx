import { Head, Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import {
    ArrowRight,
    CheckCircle2,
    Store,
    Mail,
    Settings,
    ShoppingBag,
    Shield,
    Smartphone,
    Zap,
    Package,
    BarChart3,
    Lock,
} from 'lucide-react';

const steps = [
    { icon: Store, label: 'Create Account', description: 'Sign up with your email and password' },
    { icon: Mail, label: 'Verify Email', description: 'Confirm your email address' },
    { icon: Settings, label: 'Setup Store', description: 'Configure your store details' },
    { icon: ShoppingBag, label: 'Start Selling', description: 'Add products and take orders' },
];

const features = [
    { icon: Shield, title: 'Multi-Tenant SaaS', description: 'Each store runs independently with complete data isolation.' },
    { icon: Package, title: 'Inventory Management', description: 'Track stock levels, low stock alerts, and multi-warehouse support.' },
    { icon: BarChart3, title: 'Order Management', description: 'Full order lifecycle from placement to delivery with payment tracking.' },
    { icon: Lock, title: 'Secure Authentication', description: 'Email verification, role-based access, and session security.' },
    { icon: Smartphone, title: 'Mobile Friendly', description: 'Responsive design that works beautifully on any device.' },
    { icon: Zap, title: 'Fast Setup', description: 'Launch your store in minutes. No technical skills required.' },
];

export default function CreateStore() {
    const { siteName, logoUrl } = usePage().props;
    const logo = assetUrl(logoUrl);

    return (
        <>
            <Head title="Start Your Online Store" />

            <div className="min-h-screen bg-white dark:bg-gray-950">
                {/* Header */}
                <header className="border-b border-gray-100 dark:border-gray-800">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                        <Link href="/" className="flex items-center gap-2.5">
                            {logo && <img src={logo} alt={siteName} className="h-8 w-auto" />}
                            <span className="text-lg font-bold text-gray-900 dark:text-gray-100">{siteName}</span>
                        </Link>
                        <div className="flex items-center gap-3">
                            <Link
                                href="/login"
                                className="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 font-medium transition-colors"
                            >
                                Sign In
                            </Link>
                            <Link
                                href="/register"
                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                Get Started <ArrowRight className="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="relative overflow-hidden">
                    <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-violet-50 dark:from-indigo-950/20 dark:via-gray-950 dark:to-violet-950/20 pointer-events-none" />
                    <div className="relative max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-20 sm:pt-24 sm:pb-28">
                        <div className="text-center max-w-3xl mx-auto">
                            <div className="inline-flex items-center gap-2 px-3 py-1 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-full text-xs text-indigo-700 dark:text-indigo-300 font-medium mb-6">
                                <Zap className="w-3 h-3" />
                                Free to start — no credit card required
                            </div>

                            <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-gray-900 dark:text-gray-100 leading-tight">
                                Start Your Online Store
                            </h1>

                            <p className="mt-4 text-lg text-gray-500 dark:text-gray-400 max-w-xl mx-auto leading-relaxed">
                                Create your account and launch your store in just a few minutes.
                            </p>

                            <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                                <Link
                                    href="/register"
                                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-indigo-600 text-white font-semibold text-base rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 dark:shadow-indigo-900/30 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    Create Account
                                    <ArrowRight className="w-4 h-4" />
                                </Link>
                                <Link
                                    href="/login"
                                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 border-2 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-semibold text-base rounded-xl hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900 transition-all focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                                >
                                    Sign In
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Onboarding Steps */}
                <section className="py-16 sm:py-20 bg-gray-50 dark:bg-gray-900/50">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6">
                        <div className="text-center mb-12">
                            <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">
                                How it works
                            </h2>
                            <p className="mt-2 text-gray-500 dark:text-gray-400">
                                Four simple steps to launch your store
                            </p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {steps.map((step, i) => {
                                const Icon = step.icon;
                                return (
                                    <div key={step.label} className="relative">
                                        {i < steps.length - 1 && (
                                            <div className="hidden lg:block absolute top-8 left-full w-full h-0.5 bg-gray-200 dark:bg-gray-800 -translate-x-1/2 z-0" />
                                        )}
                                        <div className="relative bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 text-center hover:shadow-md transition-shadow">
                                            <div className="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center mx-auto mb-4">
                                                <Icon className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                            </div>
                                            <div className="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-2">
                                                Step {i + 1}
                                            </div>
                                            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">
                                                {step.label}
                                            </h3>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                {step.description}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Feature Highlights */}
                <section className="py-16 sm:py-20">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6">
                        <div className="text-center mb-12">
                            <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">
                                Everything you need
                            </h2>
                            <p className="mt-2 text-gray-500 dark:text-gray-400">
                                Powerful features to run your online business
                            </p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {features.map((feature) => {
                                const Icon = feature.icon;
                                return (
                                    <div
                                        key={feature.title}
                                        className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 hover:shadow-md transition-shadow"
                                    >
                                        <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mb-4">
                                            <Icon className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                        </div>
                                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">
                                            {feature.title}
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                            {feature.description}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="py-16 sm:py-20 bg-gray-50 dark:bg-gray-900/50">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 text-center">
                        <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">
                            Ready to start selling?
                        </h2>
                        <p className="mt-3 text-gray-500 dark:text-gray-400 max-w-lg mx-auto">
                            Create your free account today and launch your store in minutes.
                        </p>
                        <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <Link
                                href="/register"
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-indigo-600 text-white font-semibold text-base rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 dark:shadow-indigo-900/30"
                            >
                                Create Account <ArrowRight className="w-4 h-4" />
                            </Link>
                            <Link
                                href="/login"
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 border-2 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-semibold text-base rounded-xl hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900 transition-all"
                            >
                                Already have an account? Sign In
                            </Link>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-950">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-gray-400 dark:text-gray-500">
                        <span>&copy; {new Date().getFullYear()} {siteName}. All rights reserved.</span>
                        <div className="flex gap-4">
                            <Link href="/client/privacy" className="hover:text-gray-600 dark:hover:text-gray-400 transition-colors">Privacy</Link>
                            <Link href="/client/terms" className="hover:text-gray-600 dark:hover:text-gray-400 transition-colors">Terms</Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
