import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function CreateTenant({ plans }) {
    const [form, setForm] = useState({
        name: '',
        slug: '',
        domain: '',
        email: '',
        status: 'active',
        plan_id: '',
        create_admin: false,
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleChange(field, value) {
        setForm(prev => ({ ...prev, [field]: value }));
        if (field === 'name' && !form.slug) {
            setForm(prev => ({
                ...prev,
                name: value,
                slug: value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
            }));
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.post('/superadmin/tenants', form, {
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Merchant</h2>}>
            <Head title="Create Merchant" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Store Information</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Store Name *</label>
                                        <input
                                            type="text"
                                            value={form.name}
                                            onChange={(e) => handleChange('name', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        />
                                        {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                                        <input
                                            type="text"
                                            value={form.slug}
                                            onChange={(e) => handleChange('slug', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            placeholder="my-store"
                                            required
                                        />
                                        <p className="text-xs text-gray-400 mt-1">Used for subdomain: my-store.yourdomain.com</p>
                                        {form.slug && (
                                            <p className="text-xs text-blue-500 mt-1">
                                                Store URL: /store/{form.slug}
                                            </p>
                                        )}
                                        {errors.slug && <p className="text-xs text-red-600 mt-1">{errors.slug}</p>}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Custom Domain</label>
                                        <input
                                            type="text"
                                            value={form.domain}
                                            onChange={(e) => handleChange('domain', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            placeholder="store.example.com"
                                        />
                                        {errors.domain && <p className="text-xs text-red-600 mt-1">{errors.domain}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                                        <input
                                            type="email"
                                            value={form.email}
                                            onChange={(e) => handleChange('email', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        />
                                        {errors.email && <p className="text-xs text-red-600 mt-1">{errors.email}</p>}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <select
                                            value={form.status}
                                            onChange={(e) => handleChange('status', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        >
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="trialing">Trialing</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                                        <select
                                            value={form.plan_id}
                                            onChange={(e) => handleChange('plan_id', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        >
                                            <option value="">No Plan</option>
                                            {plans.map((plan) => (
                                                <option key={plan.id} value={plan.id}>
                                                    {plan.name} (${plan.price}/{plan.interval})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-medium text-gray-900">Merchant Admin Account</h3>
                                    <label className="flex items-center gap-2 text-sm text-gray-600">
                                        <input
                                            type="checkbox"
                                            checked={form.create_admin}
                                            onChange={(e) => handleChange('create_admin', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        Create admin user
                                    </label>
                                </div>

                                {form.create_admin && (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Admin Name *</label>
                                            <input
                                                type="text"
                                                value={form.admin_name}
                                                onChange={(e) => handleChange('admin_name', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                required={form.create_admin}
                                            />
                                            {errors.admin_name && <p className="text-xs text-red-600 mt-1">{errors.admin_name}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Admin Email *</label>
                                            <input
                                                type="email"
                                                value={form.admin_email}
                                                onChange={(e) => handleChange('admin_email', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                required={form.create_admin}
                                            />
                                            {errors.admin_email && <p className="text-xs text-red-600 mt-1">{errors.admin_email}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                            <input
                                                type="password"
                                                value={form.admin_password}
                                                onChange={(e) => handleChange('admin_password', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                required={form.create_admin}
                                                minLength={8}
                                            />
                                            {errors.admin_password && <p className="text-xs text-red-600 mt-1">{errors.admin_password}</p>}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-3">
                                <Link
                                    href="/superadmin/tenants"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Creating...' : 'Create Merchant'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
