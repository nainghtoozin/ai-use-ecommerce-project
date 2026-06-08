import { useState, useEffect } from 'react';
import { useForm, Link, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { assetUrl } from '@/Utils/helpers';
import { adminUrl } from '@/Utils/adminUrl';

export default function UsersEdit({ user, roles }) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name || '',
        email: user.email || '',
        password: '',
        password_confirmation: '',
        role: user.roles?.[0]?.name || 'customer',
        status: user.status || 'active',
        allow_cod: user.allow_cod ?? false,
        profile_image: null,
    });

    const [preview, setPreview] = useState(null);
    const [currentImage, setCurrentImage] = useState(user.profile_image || null);

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/users/${user.id}`), {
            onSuccess: () => {
                // redirect handled by server
            },
        });
    }

    function handleImageChange(e) {
        const file = e.target.files[0];
        if (file) {
            setData('profile_image', file);
            const reader = new FileReader();
            reader.onload = (ev) => setPreview(ev.target.result);
            reader.readAsDataURL(file);
        }
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit User: {user.name}</h2>}>
            <Head title={`Edit User: ${user.name}`} />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        />
                                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        />
                                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Password <span className="text-gray-400 font-normal">(leave blank to keep current)</span>
                                        </label>
                                        <input
                                            type="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                        <input
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                        <select
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            {roles.map((role) => (
                                                <option key={role} value={role}>{role.charAt(0).toUpperCase() + role.slice(1)}</option>
                                            ))}
                                        </select>
                                        {errors.role && <p className="mt-1 text-sm text-red-600">{errors.role}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <select
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="banned">Banned</option>
                                        </select>
                                        {errors.status && <p className="mt-1 text-sm text-red-600">{errors.status}</p>}
                                </div>

                                <div className="flex items-center gap-3 mt-2">
                                    <input
                                        type="checkbox"
                                        id="allow_cod"
                                        checked={data.allow_cod}
                                        onChange={(e) => setData('allow_cod', e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <label htmlFor="allow_cod" className="text-sm font-medium text-gray-700">
                                        Allow COD
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Profile Image</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                    />
                                    {errors.profile_image && <p className="mt-1 text-sm text-red-600">{errors.profile_image}</p>}
                                    {(preview || currentImage) && (
                                        <div className="mt-4">
                                            <img
                                                src={preview || assetUrl(currentImage)}
                                                alt="Preview"
                                                className="h-32 w-32 object-cover rounded-lg"
                                            />
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center justify-end gap-4">
                                    <Link href={adminUrl('/admin/users')} className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Cancel
                                    </Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                    >
                                        {processing ? 'Updating...' : 'Update User'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
