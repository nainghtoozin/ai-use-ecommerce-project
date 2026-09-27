import { Head, useForm, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { useState } from 'react';
import { BackLink, CancelLink, CheckboxRow, FormCard, FormGroup, Notice, OutlineButton, PageHeader, PrimaryButton, SectionHeader, SelectInput, StatusPill, SuccessButton, TH, TextInput, TextareaInput, UnauthorizedState } from '@/Components/Admin/StorefrontUI';

const RULE_PAGE_SIZES = ['25', '50', '100', 'all'];

export default function DeliveryServiceEdit({ deliveryService, townshipsWithoutPricing = [] }) {
    const { can } = usePermission();
    const { flash } = usePage().props;
    const [showAddPricing, setShowAddPricing] = useState(false);
    const [newPricing, setNewPricing] = useState({ township_id: '', min_days: '', max_days: '', is_active: true });
    const [ruleSearch, setRuleSearch] = useState('');
    const [ruleCity, setRuleCity] = useState('');
    const [ruleStatus, setRuleStatus] = useState('');
    const [rulePage, setRulePage] = useState(1);
    const [rulePerPage, setRulePerPage] = useState('25');
    const [ruleSelected, setRuleSelected] = useState([]);
    const [selectAllMatching, setSelectAllMatching] = useState(false);
    const [daysOpen, setDaysOpen] = useState(false);
    const [daysMin, setDaysMin] = useState('');
    const [daysMax, setDaysMax] = useState('');

    const townshipRules = (deliveryService.pricing || []).filter(p => p.township_id);
    const ruleCities = [...new Set(townshipRules.map(p => p.township?.city?.name).filter(Boolean))].sort();
    const filteredRules = townshipRules.filter(p => {
        if (ruleSearch && !(p.township?.name || '').toLowerCase().includes(ruleSearch.toLowerCase())) return false;
        if (ruleCity && (p.township?.city?.name || '') !== ruleCity) return false;
        if (ruleStatus === 'active' && !p.is_active) return false;
        if (ruleStatus === 'inactive' && p.is_active) return false;
        return true;
    });
    const rulePerPageNum = rulePerPage === 'all' ? Math.max(filteredRules.length, 1) : parseInt(rulePerPage, 10);
    const ruleTotalPages = Math.max(1, Math.ceil(filteredRules.length / rulePerPageNum));
    const rulePageSafe = Math.min(rulePage, ruleTotalPages);
    const rulePageIds = filteredRules.slice((rulePageSafe - 1) * rulePerPageNum, rulePageSafe * rulePerPageNum).map(p => p.id);
    const ruleAllSelected = rulePageIds.length > 0 && rulePageIds.every(id => ruleSelected.includes(id));

    function clearRuleSelection() {
        setRuleSelected([]);
        setSelectAllMatching(false);
        setDaysOpen(false);
    }

    function handleSelectAllMatching() {
        setRuleSelected(filteredRules.map(p => p.id));
        setSelectAllMatching(true);
        setDaysOpen(false);
    }

    function resetRuleFilters(search, city, status, perPage) {
        setRuleSearch(search); setRuleCity(city); setRuleStatus(status);
        if (perPage) setRulePerPage(perPage);
        setRulePage(1);
        clearRuleSelection();
    }

    function handleRuleSelectAll() {
        setSelectAllMatching(false);
        if (ruleAllSelected) {
            setRuleSelected(ruleSelected.filter(id => !rulePageIds.includes(id)));
        } else {
            setRuleSelected([...new Set([...ruleSelected, ...rulePageIds])]);
        }
    }

    function handleRuleSelectOne(id) {
        setSelectAllMatching(false);
        if (ruleSelected.includes(id)) {
            setRuleSelected(ruleSelected.filter(i => i !== id));
        } else {
            setRuleSelected([...ruleSelected, id]);
        }
    }

    function handleRuleBulkStatus(isActive) {
        router.post(adminUrl('/admin/delivery-services/pricing/bulk-status'), { ids: ruleSelected, is_active: isActive }, {
            preserveScroll: true,
            onSuccess: () => clearRuleSelection(),
        });
    }

    function applyRuleBulkDays() {
        const min = daysMin === '' ? null : parseInt(daysMin, 10);
        const max = daysMax === '' ? null : parseInt(daysMax, 10);
        if ((min !== null && (isNaN(min) || min < 0)) || (max !== null && (isNaN(max) || max < 0))) return;
        if (min !== null && max !== null && max < min) return;
        if (ruleSelected.length === 0) return;
        const payload = { ids: ruleSelected };
        if (min !== null) payload.min_days = min;
        if (max !== null) payload.max_days = max;
        router.post(adminUrl('/admin/delivery-services/pricing/bulk-days'), payload, {
            preserveScroll: true,
            onSuccess: () => { clearRuleSelection(); setDaysMin(''); setDaysMax(''); },
        });
    }

    const { data, setData, put, processing, errors } = useForm({
        name: deliveryService.name || '',
        code: deliveryService.code || '',
        description: deliveryService.description || '',
        base_fee: deliveryService.base_fee || 0,
        fee_per_kg: deliveryService.fee_per_kg || '',
        min_days: deliveryService.min_days || 1,
        max_days: deliveryService.max_days || 3,
        is_active: deliveryService.is_active ?? true,
        sort_order: deliveryService.sort_order || 0,
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/delivery-services/${deliveryService.id}`));
    }

    function handleAddPricing(e) {
        e.preventDefault();
        router.post(adminUrl(`/admin/delivery-services/${deliveryService.id}/add-township-pricing`), newPricing, {
            onSuccess: () => {
                setShowAddPricing(false);
                setNewPricing({ township_id: '', min_days: '', max_days: '', is_active: true });
            }
        });
    }

    function handleRemovePricing(pricingId) {
        if (confirm('Remove this township delivery rule?')) {
            router.delete(adminUrl(`/admin/delivery-services/pricing/${pricingId}`));
        }
    }

    if (!can('delivery-services.update')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <UnauthorizedState message="You do not have permission to edit delivery services." />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${deliveryService.name}`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader eyebrow="Delivery Services" title="Edit Delivery Service" />

                {flash?.success && (
                    <Notice tone="success" className="mb-4">{flash.success}</Notice>
                )}

                <FormCard className="mb-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <FormGroup title="General" description="Name, code, and description shown at checkout.">
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <TextInput id="name" label="Name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />

                                    <TextInput id="code" label="Code" type="text" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required />
                                </div>

                                <TextareaInput id="description" label="Description (optional)" value={data.description} onChange={(e) => setData('description', e.target.value)} error={errors.description} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Fees" description="Base charge and optional per-kilogram rate.">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <TextInput id="base_fee" label="Base Fee" type="number" min="0" value={data.base_fee} onChange={(e) => setData('base_fee', parseInt(e.target.value) || 0)} error={errors.base_fee} required />

                                <TextInput id="fee_per_kg" label="Fee per Kg (optional)" type="number" min="0" value={data.fee_per_kg || ''} onChange={(e) => setData('fee_per_kg', e.target.value ? parseInt(e.target.value) : '')} error={errors.fee_per_kg} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Schedule" description="Delivery time window and display order.">
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <TextInput id="min_days" label="Min Delivery Days" type="number" min="0" value={data.min_days} onChange={(e) => setData('min_days', parseInt(e.target.value) || 0)} error={errors.min_days} required />

                                    <TextInput id="max_days" label="Max Delivery Days" type="number" min="0" value={data.max_days} onChange={(e) => setData('max_days', parseInt(e.target.value) || 0)} error={errors.max_days} required />
                                </div>

                                <TextInput id="sort_order" label="Sort Order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)} error={errors.sort_order} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Visibility">
                            <CheckboxRow id="is_active" label="Active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        </FormGroup>

                        <div className="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <CancelLink href={adminUrl('/admin/delivery-services')} />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving...' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>
                </FormCard>

                <FormCard>
                    <SectionHeader
                        title="Township Delivery Rules"
                        description="Choose which townships this service delivers to and their delivery windows."
                        actions={!showAddPricing && townshipsWithoutPricing?.length > 0 && (
                            <PrimaryButton size="sm" type="button" onClick={() => setShowAddPricing(true)}>
                                Add Township Rule
                            </PrimaryButton>
                        )}
                    />
                    <div className="mt-4">

                    <div className="flex flex-col sm:flex-row gap-3 mb-4">
                        <input
                            type="text"
                            value={ruleSearch}
                            onChange={e => { setRuleSearch(e.target.value); setRulePage(1); clearRuleSelection(); }}
                            placeholder="Search townships..."
                            className="flex-1 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <select
                            value={ruleCity}
                            onChange={e => { setRuleCity(e.target.value); setRulePage(1); clearRuleSelection(); }}
                            className="w-full sm:w-48 border border-gray-300 dark:border-gray-700 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Cities</option>
                            {ruleCities.map(name => (
                                <option key={name} value={name}>{name}</option>
                            ))}
                        </select>
                        <select
                            value={ruleStatus}
                            onChange={e => { setRuleStatus(e.target.value); setRulePage(1); clearRuleSelection(); }}
                            className="w-full sm:w-48 border border-gray-300 dark:border-gray-700 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select
                            value={rulePerPage}
                            onChange={e => { setRulePerPage(e.target.value); setRulePage(1); clearRuleSelection(); }}
                            aria-label="Rows per page"
                            className="w-full sm:w-auto border border-gray-300 dark:border-gray-700 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            {RULE_PAGE_SIZES.map(size => (
                                <option key={size} value={size}>{size === 'all' ? 'All' : `${size} / page`}</option>
                            ))}
                        </select>
                        {(ruleSearch || ruleCity || ruleStatus) && (
                            <button onClick={() => resetRuleFilters('', '', '', null)} className="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-200 text-sm">Clear</button>
                        )}
                    </div>

                    {(ruleSelected.length > 0 || selectAllMatching) && (
                        <div className="mb-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/40 rounded-lg px-4 py-3 flex flex-col gap-3">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
                                <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    {selectAllMatching ? (
                                        <>All {filteredRules.length} matching rule{filteredRules.length !== 1 ? 's' : ''} selected. </>
                                    ) : (
                                        <>{ruleSelected.length} selected on this page (of {filteredRules.length} matching). </>
                                    )}
                                    {!selectAllMatching && filteredRules.length > ruleSelected.length && (
                                        <button onClick={handleSelectAllMatching}
                                            className="underline underline-offset-2 hover:text-blue-900">
                                            {`Select all ${filteredRules.length} matching`}
                                        </button>
                                    )}
                                </span>
                                <div className="flex flex-wrap items-center gap-2">
                                    <button onClick={() => handleRuleBulkStatus(true)}
                                        className="px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-100 rounded-md hover:bg-emerald-200">
                                        Activate
                                    </button>
                                    <button onClick={() => handleRuleBulkStatus(false)}
                                        className="px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-100 rounded-md hover:bg-amber-200">
                                        Deactivate
                                    </button>
                                    <button onClick={() => { setDaysOpen(v => !v); setDaysMin(''); setDaysMax(''); }}
                                        className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                        Set Days
                                    </button>
                                    <button onClick={clearRuleSelection}
                                        className="px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800">
                                        Clear selection
                                    </button>
                                </div>
                            </div>
                            {daysOpen && (
                                <div className="flex flex-col sm:flex-row gap-2 sm:items-end bg-white dark:bg-gray-900 rounded-lg border border-blue-200 dark:border-blue-900/40 p-3">
                                    <div>
                                        <label htmlFor="bulk-min-days" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Min Days</label>
                                        <input id="bulk-min-days" type="number" min="0" value={daysMin}
                                            onChange={e => setDaysMin(e.target.value)}
                                            placeholder="4"
                                            className="w-full sm:w-28 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label htmlFor="bulk-max-days" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Max Days</label>
                                        <input id="bulk-max-days" type="number" min="0" value={daysMax}
                                            onChange={e => setDaysMax(e.target.value)}
                                            placeholder="7"
                                            className="w-full sm:w-28 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div className="flex gap-2">
                                        <button onClick={applyRuleBulkDays}
                                            className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                            Apply
                                        </button>
                                        <button onClick={() => setDaysOpen(false)}
                                            className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-200">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {showAddPricing && (
                        <form onSubmit={handleAddPricing} className="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <SelectInput id="township_id" label="Township" value={newPricing.township_id} onChange={(e) => setNewPricing({ ...newPricing, township_id: e.target.value })} required>
                                    <option value="">Select Township</option>
                                    {townshipsWithoutPricing.map(township => (
                                        <option key={township.id} value={township.id}>{township.city?.name ? `${township.city.name} — ${township.name}` : township.name}</option>
                                    ))}
                                </SelectInput>
                                <TextInput id="min_days" label="Min Days" type="number" min="0" value={newPricing.min_days} onChange={(e) => setNewPricing({ ...newPricing, min_days: e.target.value ? parseInt(e.target.value) : '' })} />
                                <TextInput id="max_days" label="Max Days" type="number" min="0" value={newPricing.max_days} onChange={(e) => setNewPricing({ ...newPricing, max_days: e.target.value ? parseInt(e.target.value) : '' })} />
                            </div>
                            <div className="flex gap-2">
                                <SuccessButton size="sm" type="submit">Save</SuccessButton>
                                <OutlineButton size="sm" onClick={() => setShowAddPricing(false)}>Cancel</OutlineButton>
                            </div>
                        </form>
                    )}

                    {filteredRules.length > 0 ? (
                        <div className="overflow-auto max-h-[60vh] md:max-h-[540px] -mx-4 px-4 sm:mx-0 sm:px-0 rounded-lg border border-gray-200 dark:border-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <TH>
                                        <input type="checkbox" checked={ruleAllSelected} onChange={handleRuleSelectAll}
                                            aria-label="Select all rules on this page"
                                            className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                                    </TH>
                                    <TH>Township</TH>
                                    <TH>City</TH>
                                    <TH>Days</TH>
                                    <TH align="center">Active</TH>
                                    <TH align="right">Actions</TH>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {filteredRules.slice((rulePageSafe - 1) * rulePerPageNum, rulePageSafe * rulePerPageNum).map(pricing => (
                                    <tr key={pricing.id}>
                                        <td className="px-4 py-4">
                                            <input type="checkbox" checked={ruleSelected.includes(pricing.id)} onChange={() => handleRuleSelectOne(pricing.id)}
                                                aria-label={`Select rule for ${pricing.township?.name || pricing.id}`}
                                                className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{pricing.township?.name || `Township #${pricing.township_id}`}</td>
                                        <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{pricing.township?.city?.name || '-'}</td>
                                        <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                            {pricing.min_days || deliveryService.min_days}-{pricing.max_days || deliveryService.max_days}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <StatusPill active={pricing.is_active} />
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button onClick={() => handleRemovePricing(pricing.id)} className="inline-block px-1 py-1 text-red-600 hover:text-red-800 text-sm">Remove</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500 dark:text-gray-400">No township delivery rules configured. This service is not offered for any township yet.</p>
                    )}
                    {filteredRules.length > rulePerPageNum && (
                        <div className="mt-4 flex items-center justify-between">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Showing {((rulePageSafe - 1) * rulePerPageNum) + 1} to {Math.min(rulePageSafe * rulePerPageNum, filteredRules.length)} of {filteredRules.length} rules
                            </p>
                            <div className="flex gap-1">
                                <button onClick={() => setRulePage(p => Math.max(1, p - 1))} disabled={rulePageSafe <= 1}
                                    className="px-3 py-1 text-sm rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800 disabled:opacity-40">«</button>
                                <span className="px-3 py-1 text-sm text-gray-600 dark:text-gray-300">{rulePageSafe} / {ruleTotalPages}</span>
                                <button onClick={() => setRulePage(p => Math.min(ruleTotalPages, p + 1))} disabled={rulePageSafe >= ruleTotalPages}
                                    className="px-3 py-1 text-sm rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800 disabled:opacity-40">»</button>
                            </div>
                        </div>
                    )}
                    </div>
                </FormCard>
            </div>
        </AdminLayout>
    );
}
