import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import InvoiceBadge from '@/Components/Billing/InvoiceBadge';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { ArrowLeft, Download, CheckCircle, XCircle, FileText, CreditCard, Calendar, Clock, Building } from 'lucide-react';

function DetailRow({ label, value }) {
    return (
        <div className="flex items-center justify-between py-2.5">
            <span className="text-sm text-gray-500">{label}</span>
            <span className="text-sm font-semibold text-gray-900">{value || '—'}</span>
        </div>
    );
}

export default function AdminBillingInvoiceDetail({ invoice }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    const lineItems = invoice?.line_items || [];

    return (
        <AdminLayout>
            <Head title={`Invoice ${invoice?.invoice_number || ''}`} />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={() => router.get(adminUrl('/admin/billing/invoices'))}
                            className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                            aria-label="Back to invoices"
                        >
                            <ArrowLeft className="w-5 h-5 text-gray-500" />
                        </button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold text-gray-900">{invoice?.invoice_number}</h1>
                                <InvoiceBadge status={invoice?.status} />
                            </div>
                            <p className="text-sm text-gray-500 mt-1">Invoice details and download</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={adminUrl(`/admin/billing/invoices/${invoice?.id}/download`)}
                            className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2"
                        >
                            <Download className="w-4 h-4" />
                            Download
                        </a>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900 flex items-center gap-2">
                                    <FileText className="w-4 h-4 text-gray-400" /> Invoice Summary
                                </h2>
                            </div>
                            <div className="px-6 py-4 space-y-2 divide-y divide-gray-50">
                                <DetailRow label="Invoice Number" value={invoice?.invoice_number} />
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-sm text-gray-500">Status</span>
                                    <InvoiceBadge status={invoice?.status} />
                                </div>
                                <DetailRow label="Plan" value={invoice?.plan?.name} />
                                <DetailRow label="Billing Cycle" value={invoice?.billing_interval ? invoice.billing_interval.charAt(0).toUpperCase() + invoice.billing_interval.slice(1) : '—'} />
                                <DetailRow
                                    label="Billing Period"
                                    value={`${formatDate(invoice?.billing_period_start)} — ${formatDate(invoice?.billing_period_end)}`}
                                />
                                <DetailRow label="Issued Date" value={formatDateTime(invoice?.issued_at)} />
                                <DetailRow label="Paid Date" value={formatDateTime(invoice?.paid_at)} />
                                <DetailRow
                                    label="Payment Reference"
                                    value={invoice?.payment_intent?.reference_number || '—'}
                                />
                                <DetailRow label="Payment Gateway" value={invoice?.payment_intent?.gateway ? invoice.payment_intent.gateway.charAt(0).toUpperCase() + invoice.payment_intent.gateway.slice(1) : '—'} />
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900 flex items-center gap-2">
                                    <CreditCard className="w-4 h-4 text-gray-400" /> Line Items
                                </h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                            <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                                            <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit Price</th>
                                            <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {lineItems.length > 0 ? lineItems.map((item, i) => (
                                            <tr key={i}>
                                                <td className="px-6 py-3 text-sm text-gray-900">{item.description}</td>
                                                <td className="px-6 py-3 text-sm text-gray-900 text-right">{item.quantity}</td>
                                                <td className="px-6 py-3 text-sm text-gray-900 text-right">{formatCurrency(item.unit_price, pc)}</td>
                                                <td className="px-6 py-3 text-sm font-semibold text-gray-900 text-right">{formatCurrency(item.amount, pc)}</td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan={4} className="px-6 py-4 text-sm text-gray-400 text-center">No line items</td>
                                            </tr>
                                        )}
                                    </tbody>
                                    <tfoot className="bg-gray-50">
                                        <tr>
                                            <td colSpan={3} className="px-6 py-3 text-sm text-right text-gray-500">Subtotal</td>
                                            <td className="px-6 py-3 text-sm font-semibold text-gray-900 text-right">{formatCurrency(invoice?.subtotal, pc)}</td>
                                        </tr>
                                        <tr>
                                            <td colSpan={3} className="px-6 py-3 text-sm text-right text-gray-500">Tax</td>
                                            <td className="px-6 py-3 text-sm text-gray-900 text-right">{formatCurrency(invoice?.tax, pc)}</td>
                                        </tr>
                                        <tr>
                                            <td colSpan={3} className="px-6 py-3 text-sm text-right font-semibold text-gray-900">Total</td>
                                            <td className="px-6 py-3 text-sm font-bold text-gray-900 text-right">{formatCurrency(invoice?.total, pc)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        {invoice?.notes && (
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h3 className="text-sm font-semibold text-gray-900">Notes</h3>
                                </div>
                                <div className="px-6 py-4">
                                    <p className="text-sm text-gray-600 whitespace-pre-wrap">{invoice.notes}</p>
                                </div>
                            </div>
                        )}

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Actions</h3>
                            </div>
                            <div className="px-6 py-4 space-y-3">
                                <a
                                    href={adminUrl(`/admin/billing/invoices/${invoice?.id}/download`)}
                                    className="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                                >
                                    <Download className="w-4 h-4" />
                                    Download Invoice
                                </a>
                                <button
                                    onClick={() => router.get(adminUrl('/admin/billing/invoices'))}
                                    className="w-full px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors flex items-center justify-center gap-2"
                                >
                                    <ArrowLeft className="w-4 h-4" />
                                    Back to Invoices
                                </button>
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Building className="w-4 h-4 text-gray-400" /> Timeline
                                </h3>
                            </div>
                            <div className="px-6 py-4 space-y-4">
                                {invoice?.issued_at && (
                                    <div className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <Calendar className="w-4 h-4 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">Issued</p>
                                            <p className="text-xs text-gray-400">{formatDateTime(invoice.issued_at)}</p>
                                        </div>
                                    </div>
                                )}
                                {invoice?.paid_at && (
                                    <div className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                            <CheckCircle className="w-4 h-4 text-emerald-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">Paid</p>
                                            <p className="text-xs text-gray-400">{formatDateTime(invoice.paid_at)}</p>
                                        </div>
                                    </div>
                                )}
                                {!invoice?.paid_at && invoice?.status === 'paid' && (
                                    <div className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                            <CheckCircle className="w-4 h-4 text-emerald-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">Paid</p>
                                            <p className="text-xs text-gray-400">Payment completed</p>
                                        </div>
                                    </div>
                                )}
                                {invoice?.status === 'cancelled' && (
                                    <div className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                            <XCircle className="w-4 h-4 text-red-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">Cancelled</p>
                                            <p className="text-xs text-gray-400">Invoice cancelled</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
