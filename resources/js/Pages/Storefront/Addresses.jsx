import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import ShopLayout from '@/Layouts/ShopLayout';

const labelColors = {
    Home: 'bg-blue-50 text-blue-700 border-blue-200',
    Office: 'bg-purple-50 text-purple-700 border-purple-200',
    Other: 'bg-gray-50 text-gray-600 border-gray-200',
};

export default function Addresses({ tenant, addresses, cities, customer }) {
    const storeSlug = tenant.slug;
    const { props } = usePage();
    const auth = props.auth;
    const flash = props.flash || {};
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [townships, setTownships] = useState([]);
    const [loadingTownships, setLoadingTownships] = useState(false);

    const userFirstName = customer?.first_name || auth?.user?.first_name || auth?.user?.name?.split(' ')[0] || '';
    const userLastName = customer?.last_name || auth?.user?.last_name || auth?.user?.name?.split(' ').slice(1).join(' ') || '';
    const userPhone = customer?.phone || auth?.user?.phone || '';

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
        if (!cityId) { setTownships([]); return Promise.resolve([]); }
        setLoadingTownships(true);
        return axios.get(`/api/townships/${cityId}`).then((res) => {
            const d = res.data?.townships || [];
            setTownships(d);
            return d;
        }).catch(() => { setTownships([]); return []; })
          .finally(() => { setLoadingTownships(false); });
    }

    function openCreate() {
        reset();
        setTownships([]);
        setEditingId(null);
        setData({ label: 'Home', first_name: userFirstName, last_name: userLastName, phone: userPhone, address_line: '', city_id: '', township_id: '', postal_code: '', is_default: false, notes: '' });
        setShowForm(true);
    }

    function openEdit(address) {
        setData({ label: address.label, first_name: address.first_name, last_name: address.last_name, phone: address.phone, address_line: address.address_line, city_id: address.city_id?.toString() || '', township_id: address.township_id?.toString() || '', postal_code: address.postal_code || '', is_default: address.is_default, notes: address.notes || '' });
        if (address.city_id) {
            fetchTownships(address.city_id).then((list) => {
                if (address.township_id) {
                    const t = list.find(tw => tw.id == address.township_id);
                    if (t?.postal_code) setData('postal_code', t.postal_code);
                }
            });
        }
        setEditingId(address.id);
        setShowForm(true);
    }

    function closeForm() { setShowForm(false); setEditingId(null); setTownships([]); reset(); }

    function handleCityChange(cityId) {
        setData('city_id', cityId);
        setData('township_id', '');
        setData('postal_code', '');
        fetchTownships(cityId);
    }

    function handleTownshipChange(townshipId) {
        setData('township_id', townshipId);
        if (townshipId) {
            const t = townships.find(tw => String(tw.id) === String(townshipId));
            setData('postal_code', t?.postal_code || '');
        } else {
            setData('postal_code', '');
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (editingId) {
            put(route('storefront.customer.addresses.update', { store_slug: storeSlug, address: editingId }), { onSuccess: closeForm });
        } else {
            post(route('storefront.customer.addresses.store', { store_slug: storeSlug }), { onSuccess: closeForm });
        }
    }

    function handleDelete(addressId) {
        if (confirm('Delete this address?')) {
            router.delete(route('storefront.customer.addresses.destroy', { store_slug: storeSlug, address: addressId }));
        }
    }

    function handleSetDefault(addressId) {
        router.post(route('storefront.customer.addresses.default', { store_slug: storeSlug, address: addressId }));
    }

    return (
        <ShopLayout>
            <Head title={`My Addresses - ${tenant.name}`} />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">My Addresses</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage your delivery addresses for faster checkout.</p>
                    </div>
                    <Link href={route('storefront.customer.account', { store_slug: storeSlug })} className="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        &larr; Back to Account
                    </Link>
                </div>

                {flash.success && (
                    <div className="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">{flash.success}</div>
                )}

                {!showForm && (
                    <button onClick={openCreate} className="mb-6 inline-flex items-center gap-2 px-5 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-base font-medium transition-colors shadow-sm">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Add New Address
                    </button>
                )}

                {showForm && (
                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
                        <h2 className="text-xl font-semibold text-gray-900 mb-6">{editingId ? 'Edit Address' : 'Add New Address'}</h2>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Label</label>
                                    <select value={data.label} onChange={(e) => setData('label', e.target.value)} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Home">Home</option>
                                        <option value="Office">Office</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Phone</label>
                                    <input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                                    {errors.phone && <p className="text-red-500 text-xs mt-1">{errors.phone}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                                    <input type="text" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                                    {errors.first_name && <p className="text-red-500 text-xs mt-1">{errors.first_name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                                    <input type="text" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                                    {errors.last_name && <p className="text-red-500 text-xs mt-1">{errors.last_name}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">City</label>
                                    <select value={data.city_id} onChange={(e) => handleCityChange(e.target.value)} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Select city</option>
                                        {cities.map((city) => <option key={city.id} value={city.id}>{city.name}</option>)}
                                    </select>
                                    {errors.city_id && <p className="text-red-500 text-xs mt-1">{errors.city_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Township</label>
                                    <select value={data.township_id} onChange={(e) => handleTownshipChange(e.target.value)} disabled={!data.city_id} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed" required>
                                        <option value="">{loadingTownships ? 'Loading...' : (data.city_id ? 'Select township' : 'Select a City first')}</option>
                                        {townships.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </select>
                                    {errors.township_id && <p className="text-red-500 text-xs mt-1">{errors.township_id}</p>}
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">Address</label>
                                <textarea value={data.address_line} onChange={(e) => setData('address_line', e.target.value)} rows={2} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" required />
                                {errors.address_line && <p className="text-red-500 text-xs mt-1">{errors.address_line}</p>}
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Postal Code</label>
                                    <input type="text" value={data.postal_code} readOnly tabIndex={-1} className="w-full border border-gray-300 rounded-xl px-4 py-3 text-base bg-gray-50 text-gray-500 cursor-not-allowed focus:outline-none" placeholder="Auto-filled from township" />
                                </div>
                                <div className="flex items-end">
                                    <label className="flex items-center gap-2.5 py-3">
                                        <input type="checkbox" checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4" />
                                        <span className="text-base text-gray-700">Set as default address</span>
                                    </label>
                                </div>
                            </div>
                            <div className="flex gap-3 pt-2">
                                <button type="submit" disabled={processing} className="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 text-base font-medium transition-colors">
                                    {processing ? 'Saving...' : (editingId ? 'Update Address' : 'Save Address')}
                                </button>
                                <button type="button" onClick={closeForm} className="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 text-base font-medium transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {addresses.length === 0 && !showForm ? (
                    <div className="text-center py-16 bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <svg className="w-14 h-14 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <h3 className="mt-4 text-xl font-semibold text-gray-900">No addresses saved</h3>
                        <p className="mt-2 text-base text-gray-500">Add an address to make checkout faster.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {addresses.map((address) => (
                            <div key={address.id} className={`bg-white rounded-2xl border p-5 relative shadow-sm hover:shadow-md transition-shadow ${address.is_default ? 'border-blue-300 ring-1 ring-blue-100' : 'border-gray-200'}`}>
                                <div className="flex items-center gap-2 mb-3">
                                    <span className={`text-xs font-semibold uppercase tracking-wide px-2.5 py-1 rounded-full border ${labelColors[address.label] || labelColors.Other}`}>
                                        {address.label}
                                    </span>
                                    {address.is_default && (
                                        <span className="text-xs font-semibold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-200">
                                            Default
                                        </span>
                                    )}
                                </div>
                                <p className="text-base font-semibold text-gray-900">{address.first_name} {address.last_name}</p>
                                <div className="mt-2 space-y-1 text-sm text-gray-600">
                                    <p className="flex items-start gap-2">
                                        <svg className="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        {address.address_line}
                                    </p>
                                    {address.city && (
                                        <p className="flex items-start gap-2">
                                            <svg className="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                            {address.city.name}{address.township ? ` \u00b7 ${address.township.name}` : ''}
                                        </p>
                                    )}
                                    {address.postal_code && (
                                        <p className="flex items-start gap-2">
                                            <svg className="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            Postal: {address.postal_code}
                                        </p>
                                    )}
                                    <p className="flex items-start gap-2">
                                        <svg className="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        {address.phone}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100">
                                    <button onClick={() => openEdit(address)} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        Edit
                                    </button>
                                    <button onClick={() => handleDelete(address.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        Delete
                                    </button>
                                    {!address.is_default && (
                                        <button onClick={() => handleSetDefault(address.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg transition-colors ml-auto">
                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                            Set Default
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
