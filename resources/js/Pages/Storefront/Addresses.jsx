import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Addresses({ tenant, addresses, cities }) {
    const storeSlug = tenant.slug;
    const { props } = usePage();
    const flash = props.flash || {};
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [townships, setTownships] = useState([]);
    const [loadingTownships, setLoadingTownships] = useState(false);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        label: 'Home',
        first_name: '',
        last_name: '',
        phone: '',
        address_line: '',
        city_id: '',
        township_id: '',
        postal_code: '',
        is_default: false,
        notes: '',
    });

    function fetchTownships(cityId) {
        if (!cityId) {
            setTownships([]);
            setData('township_id', '');
            return;
        }
        setLoadingTownships(true);
        axios.get(`/api/townships/${cityId}`).then((res) => {
            setTownships(res.data?.townships || []);
        }).catch(() => {
            setTownships([]);
        }).finally(() => {
            setLoadingTownships(false);
        });
    }

    function openCreate() {
        reset();
        setTownships([]);
        setEditingId(null);
        setShowForm(true);
    }

    function openEdit(address) {
        setData({
            label: address.label,
            first_name: address.first_name,
            last_name: address.last_name,
            phone: address.phone,
            address_line: address.address_line,
            city_id: address.city_id?.toString() || '',
            township_id: address.township_id?.toString() || '',
            postal_code: address.postal_code || '',
            is_default: address.is_default,
            notes: address.notes || '',
        });
        if (address.city_id) {
            axios.get(`/api/townships/${address.city_id}`).then((res) => {
                setTownships(res.data?.townships || []);
            }).catch(() => {
                setTownships([]);
            });
        }
        setEditingId(address.id);
        setShowForm(true);
    }

    function closeForm() {
        setShowForm(false);
        setEditingId(null);
        setTownships([]);
        reset();
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (editingId) {
            put(route('storefront.customer.addresses.update', { store_slug: storeSlug, address: editingId }), {
                onSuccess: () => closeForm(),
            });
        } else {
            post(route('storefront.customer.addresses.store', { store_slug: storeSlug }), {
                onSuccess: () => closeForm(),
            });
        }
    }

    function handleDelete(addressId) {
        if (confirm('Delete this address?')) {
            router.delete(route('storefront.customer.addresses.destroy', { store_slug: storeSlug, address: addressId }));
        }
    }

    return (
        <ShopLayout>
            <Head title={`My Addresses - ${tenant.name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between mb-6 sm:mb-8">
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">My Addresses</h1>
                    <Link
                        href={route('storefront.customer.account', { store_slug: storeSlug })}
                        className="text-sm text-blue-600 hover:underline"
                    >
                        &larr; Back to Account
                    </Link>
                </div>

                {flash.success && (
                    <div className="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{flash.success}</div>
                )}

                {!showForm && (
                    <button
                        onClick={openCreate}
                        className="mb-6 inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                    >
                        <i className="bi bi-plus-lg"></i>
                        Add New Address
                    </button>
                )}

                {showForm && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 mb-8">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">
                            {editingId ? 'Edit Address' : 'Add New Address'}
                        </h2>
                        <form onSubmit={handleSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Label</label>
                                <select
                                    value={data.label}
                                    onChange={(e) => setData('label', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="Home">Home</option>
                                    <option value="Office">Office</option>
                                    <option value="Other">Other</option>
                                </select>
                                {errors.label && <p className="text-red-500 text-sm mt-1">{errors.label}</p>}
                            </div>

                            <div className="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input
                                        type="text"
                                        value={data.first_name}
                                        onChange={(e) => setData('first_name', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required
                                    />
                                    {errors.first_name && <p className="text-red-500 text-sm mt-1">{errors.first_name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input
                                        type="text"
                                        value={data.last_name}
                                        onChange={(e) => setData('last_name', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required
                                    />
                                    {errors.last_name && <p className="text-red-500 text-sm mt-1">{errors.last_name}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {errors.phone && <p className="text-red-500 text-sm mt-1">{errors.phone}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                                <input
                                    type="text"
                                    value={data.postal_code}
                                    onChange={(e) => setData('postal_code', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                {errors.postal_code && <p className="text-red-500 text-sm mt-1">{errors.postal_code}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <textarea
                                    value={data.address_line}
                                    onChange={(e) => setData('address_line', e.target.value)}
                                    rows={2}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {errors.address_line && <p className="text-red-500 text-sm mt-1">{errors.address_line}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <select
                                    value={data.city_id}
                                    onChange={(e) => {
                                        setData('city_id', e.target.value);
                                        setData('township_id', '');
                                        fetchTownships(e.target.value);
                                    }}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select city</option>
                                    {cities.map((city) => (
                                        <option key={city.id} value={city.id}>{city.name}</option>
                                    ))}
                                </select>
                                {errors.city_id && <p className="text-red-500 text-sm mt-1">{errors.city_id}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Township</label>
                                <select
                                    value={data.township_id}
                                    onChange={(e) => setData('township_id', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{loadingTownships ? 'Loading...' : 'Select township'}</option>
                                    {townships.map((t) => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                                {errors.township_id && <p className="text-red-500 text-sm mt-1">{errors.township_id}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.is_default}
                                        onChange={(e) => setData('is_default', e.target.checked)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-700">Set as default address</span>
                                </label>
                            </div>

                            <div className="sm:col-span-2 flex gap-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                                >
                                    {processing ? 'Saving...' : (editingId ? 'Update Address' : 'Save Address')}
                                </button>
                                <button
                                    type="button"
                                    onClick={closeForm}
                                    className="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {addresses.length === 0 && !showForm ? (
                    <div className="text-center py-12 sm:py-16 bg-white rounded-xl border border-gray-200">
                        <i className="bi bi-geo-alt text-5xl text-gray-300"></i>
                        <h3 className="mt-4 text-lg font-medium text-gray-900">No addresses saved</h3>
                        <p className="mt-2 text-gray-500">Add an address to make checkout faster.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                        {addresses.map((address) => (
                            <div
                                key={address.id}
                                className={`bg-white rounded-xl border p-5 relative ${
                                    address.is_default ? 'border-blue-400 ring-1 ring-blue-400' : 'border-gray-200'
                                }`}
                            >
                                {address.is_default && (
                                    <span className="absolute top-3 right-3 bg-blue-100 text-blue-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                        Default
                                    </span>
                                )}
                                <div className="flex items-center gap-2 mb-3">
                                    <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">{address.label}</span>
                                </div>
                                <p className="font-medium text-gray-900">{address.first_name} {address.last_name}</p>
                                <p className="text-sm text-gray-600 mt-1">{address.address_line}</p>
                                {address.city && <p className="text-sm text-gray-600">{address.city.name}{address.township ? `, ${address.township.name}` : ''}</p>}
                                {address.postal_code && <p className="text-sm text-gray-500">{address.postal_code}</p>}
                                <p className="text-sm text-gray-600 mt-1">{address.phone}</p>

                                <div className="flex gap-2 mt-4 pt-3 border-t border-gray-100">
                                    <button
                                        onClick={() => openEdit(address)}
                                        className="text-sm text-blue-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDelete(address.id)}
                                        className="text-sm text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
