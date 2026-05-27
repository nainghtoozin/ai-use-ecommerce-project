import { useState, useEffect, useRef } from 'react';
import { X, Printer, Copy, Check, Package, User, CreditCard, Wallet, Image, Link, AlertCircle, Maximize2 } from 'lucide-react';

const styleId = 'order-detail-modal-styles';
if (!document.getElementById(styleId)) {
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
        @keyframes modal-scale-in {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes lightbox-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @media print {
            body * { visibility: hidden; }
            #printable-order, #printable-order * { visibility: visible; }
            #printable-order {
                position: fixed; top: 0; left: 0;
                width: 100%; height: auto;
                padding: 24px; background: white;
                overflow: visible;
            }
            #printable-order .print-card {
                border: 1px solid #e5e7eb !important;
                box-shadow: none !important;
                border-radius: 8px !important;
                padding: 16px !important;
                margin-bottom: 16px !important;
                background: white !important;
            }
            #printable-order .print-header {
                text-align: center; margin-bottom: 24px;
                border-bottom: 2px solid #111827;
                padding-bottom: 16px;
            }
            #printable-order .print-header h2 {
                font-size: 18px; font-weight: 700; color: #111827; margin: 0;
            }
            #printable-order .print-header p {
                font-size: 11px; color: #6b7280; margin: 4px 0 0 0;
            }
            #printable-order table { width: 100%; border-collapse: collapse; }
            #printable-order th {
                text-align: left; font-size: 10px; text-transform: uppercase;
                color: #6b7280; padding: 8px 12px; border-bottom: 1px solid #e5e7eb;
            }
            #printable-order td {
                font-size: 11px; padding: 8px 12px; border-bottom: 1px solid #f3f4f6;
            }
            #printable-order .print-summary { margin-top: 16px; }
            #printable-order .print-summary table { width: auto; margin-left: auto; }
            #printable-order .print-summary td {
                padding: 4px 8px; border: none; font-size: 11px;
            }
            #printable-order .print-summary td:last-child {
                text-align: right; font-weight: 600;
            }
            #printable-order .print-summary tr:last-child td {
                font-size: 13px; font-weight: 700; padding-top: 8px;
                border-top: 2px solid #111827;
            }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
    `;
    document.head.appendChild(style);
}

const statusLabels = {
    pending: 'Pending',
    verified: 'Verified',
    rejected: 'Rejected',
    confirmed: 'Confirmed',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

const statusBadgeColors = {
    pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    verified: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-gray-50 text-gray-600 border-gray-200',
    confirmed: 'bg-blue-50 text-blue-700 border-blue-200',
    shipped: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    delivered: 'bg-green-50 text-green-700 border-green-200',
    cancelled: 'bg-red-50 text-red-700 border-red-200',
};

const paymentStatusLabels = {
    unpaid: 'Unpaid',
    pending: 'Pending',
    paid: 'Paid',
    verified: 'Verified',
    rejected: 'Rejected',
};

const paymentStatusBadgeColors = {
    unpaid: 'bg-gray-50 text-gray-600 border-gray-200',
    pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    paid: 'bg-green-50 text-green-700 border-green-200',
    verified: 'bg-blue-50 text-blue-700 border-blue-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
};

function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString() + ' MMK';
}

function StatusBadge({ status, colorMap, labelMap }) {
    const colors = colorMap || statusBadgeColors;
    const labels = labelMap || statusLabels;
    const colorClass = colors[status] || 'bg-gray-50 text-gray-600 border-gray-200';
    const label = labels[status] || status;
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${colorClass}`}>
            {label}
        </span>
    );
}

const paymentLabelMap = {
    unpaid: 'Unpaid',
    pending: 'Pending',
    paid: 'Paid',
    verified: 'Verified',
    rejected: 'Rejected',
};
const paymentColorMap = {
    unpaid: 'bg-gray-50 text-gray-600 border-gray-200',
    pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    paid: 'bg-green-50 text-green-700 border-green-200',
    verified: 'bg-blue-50 text-blue-700 border-blue-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
};

function SectionCard({ icon: Icon, title, children, className = '' }) {
    return (
        <div className={`bg-white rounded-xl border border-gray-200 shadow-sm ${className}`}>
            <div className="flex items-center gap-2 px-5 py-3.5 border-b border-gray-100">
                {Icon && <Icon className="w-4 h-4 text-gray-400" />}
                <h3 className="text-sm font-semibold text-gray-700">{title}</h3>
            </div>
            <div className="px-5 py-4">{children}</div>
        </div>
    );
}

function ProductImage({ src, name }) {
    const [error, setError] = useState(false);
    const initial = (name || '?').charAt(0).toUpperCase();
    const colors = [
        'bg-blue-100 text-blue-600',
        'bg-emerald-100 text-emerald-600',
        'bg-amber-100 text-amber-600',
        'bg-violet-100 text-violet-600',
        'bg-rose-100 text-rose-600',
        'bg-cyan-100 text-cyan-600',
    ];
    const colorIndex = (name || '').length % colors.length;

    if (src && !error) {
        return (
            <img
                src={src}
                alt={name}
                className="w-10 h-10 sm:w-12 sm:h-12 rounded-lg object-cover border border-gray-100 flex-shrink-0"
                onError={() => setError(true)}
                loading="lazy"
            />
        );
    }

    return (
        <div className={`w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0 ${colors[colorIndex]}`}>
            {initial}
        </div>
    );
}

function InfoRow({ label, value, fullWidth }) {
    return (
        <div className={fullWidth ? 'col-span-full' : ''}>
            <p className="text-xs text-gray-400 mb-0.5">{label}</p>
            <p className="text-sm font-medium text-gray-900 break-words">{value || '-'}</p>
        </div>
    );
}

export default function OrderDetailModal({ orderId, onClose }) {
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [linkCopied, setLinkCopied] = useState(false);
    const [lightboxImage, setLightboxImage] = useState(null);

    const contentRef = useRef(null);

    const handlePrint = () => {
        window.print();
    };

    const generateInvoiceToken = (orderData) => {
        const seed = `${orderData.id}-${orderData.created_at}-s3cur3`;
        let hash = 0;
        for (let i = 0; i < seed.length; i++) {
            const char = seed.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        const prefix = orderData.id.toString(16).padStart(4, '0');
        const suffix = Math.abs(hash).toString(36).substring(0, 8);
        return `${prefix}${suffix}`;
    };

    const handleCopyInvoiceLink = () => {
        if (!order) return;
        const token = generateInvoiceToken(order);
        const link = `${window.location.origin}/invoice/${token}`;
        navigator.clipboard.writeText(link).then(() => {
            setLinkCopied(true);
            setTimeout(() => setLinkCopied(false), 2000);
        }).catch(() => {
            const ta = document.createElement('textarea');
            ta.value = link;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            setLinkCopied(true);
            setTimeout(() => setLinkCopied(false), 2000);
        });
    };

    useEffect(() => {
        if (!orderId) return;
        setLoading(true);
        setError(null);
        setLightboxImage(null);

        fetch(`/admin/reports/sales/order/${orderId}`)
            .then((res) => {
                if (!res.ok) throw new Error('Failed to load order details');
                return res.json();
            })
            .then((data) => { setOrder(data); setLoading(false); })
            .catch((err) => { setError(err.message); setLoading(false); });
    }, [orderId]);

    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    useEffect(() => {
        if (!orderId) return;
        const original = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = original;
        };
    }, [orderId]);

    useEffect(() => {
        if (!orderId) return;
        const handler = (e) => {
            if (e.key === 'Escape') {
                if (lightboxImage) {
                    setLightboxImage(null);
                } else {
                    onCloseRef.current();
                }
            }
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [orderId, lightboxImage]);

    if (!orderId) return null;

    const paymentMethodName = order?.payment_method?.name || '';
    const isCOD = paymentMethodName.toUpperCase() === 'CASH_ON_DELIVERY';
    const screenshotUrl = order?.payment_screenshot_url;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm no-print" onClick={() => lightboxImage ? setLightboxImage(null) : onClose()} />

            {lightboxImage && (
                <div
                    className="fixed inset-0 z-[60] flex items-center justify-center p-4 no-print"
                    style={{ animation: 'lightbox-fade-in 200ms ease-out' }}
                >
                    <div className="absolute inset-0 bg-black/80" onClick={() => setLightboxImage(null)} />
                    <div className="relative max-w-3xl max-h-[85vh] w-full h-full flex items-center justify-center">
                        <button
                            onClick={() => setLightboxImage(null)}
                            className="absolute top-2 right-2 z-10 p-2 bg-black/50 hover:bg-black/70 rounded-full transition-colors"
                        >
                            <X className="w-5 h-5 text-white" />
                        </button>
                        <img
                            src={lightboxImage}
                            alt="Payment screenshot"
                            className="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
                        />
                    </div>
                </div>
            )}

            <div
                ref={contentRef}
                className="relative bg-white rounded-2xl shadow-2xl w-full max-h-[90vh] mx-3 sm:mx-6 max-w-3xl overflow-hidden flex flex-col"
                style={{ animation: 'modal-scale-in 200ms ease-out' }}
            >
                <div className="no-print flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h2 className="text-base sm:text-lg font-bold text-gray-900">Order Details</h2>
                    <button
                        onClick={onClose}
                        className="p-1.5 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <X className="w-5 h-5 text-gray-400" />
                    </button>
                </div>

                <div id="printable-order" className="overflow-y-auto px-5 sm:px-6 py-5 space-y-4 flex-1">
                    {loading && (
                        <div className="flex items-center justify-center py-20 no-print">
                            <div className="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
                            <span className="ml-3 text-sm text-gray-500">Loading...</span>
                        </div>
                    )}

                    {error && (
                        <div className="text-center py-20 no-print">
                            <p className="text-sm text-red-500">{error}</p>
                        </div>
                    )}

                    {order && (
                        <>
                            <div className="hidden print:block print-header">
                                <h2>INVOICE</h2>
                                <p>Order #{order.id} &middot; {order.created_at?.substring(0, 10)}</p>
                            </div>

                            <div className="print-card bg-gradient-to-br from-gray-50 to-white rounded-xl border border-gray-200 p-5 shadow-sm">
                                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div>
                                        <p className="text-xs text-gray-400 mb-1">Order Number</p>
                                        <h2 className="text-xl sm:text-2xl font-bold text-gray-900">#{order.id}</h2>
                                    </div>
                                    <div className="flex flex-col sm:items-end gap-2">
                                        <StatusBadge status={order.order_status} />
                                        <p className="text-xs sm:text-sm text-gray-500">{order.created_at?.substring(0, 10)}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <SectionCard icon={User} title="Customer Information">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <InfoRow label="Name" value={order.user?.name || `${order.first_name} ${order.last_name}`} />
                                        <InfoRow label="Phone" value={order.phone} />
                                        <InfoRow label="Email" value={order.email} fullWidth />
                                        <InfoRow label="Address" value={order.address} fullWidth />
                                    </div>
                                </SectionCard>

                                <SectionCard icon={CreditCard} title="Payment Summary">
                                    <div className="space-y-2.5">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-gray-500">Subtotal</span>
                                            <span className="font-medium text-gray-700">{formatCurrency(order.subtotal)}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-gray-500">Delivery Fee</span>
                                            <span className="font-medium text-gray-700">{formatCurrency(order.delivery_fee)}</span>
                                        </div>
                                        {Number(order.discount_amount) > 0 && (
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-gray-500">Discount</span>
                                                <span className="font-medium text-red-500">-{formatCurrency(order.discount_amount)}</span>
                                            </div>
                                        )}
                                        <div className="border-t border-gray-200 pt-2.5 mt-2.5">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-semibold text-gray-900">Grand Total</span>
                                                <span className="text-base font-bold text-gray-900">{formatCurrency(order.total_amount)}</span>
                                            </div>
                                        </div>
                                    </div>
                                </SectionCard>
                            </div>

                            <SectionCard icon={Wallet} title="Payment Information">
                                {isCOD ? (
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-gray-400">Method</span>
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-emerald-50 text-emerald-700 border-emerald-200">
                                                Cash on Delivery
                                            </span>
                                        </div>
                                        <div className="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 border border-amber-200">
                                            <AlertCircle className="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" />
                                            <p className="text-xs sm:text-sm text-amber-800">
                                                No online payment uploaded yet. Payment will be collected upon delivery.
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            {order.payment_method?.name && (
                                                <InfoRow label="Payment Method" value={order.payment_method.name} />
                                            )}
                                            {order.payment_method?.account_name && (
                                                <InfoRow label="Account Holder" value={order.payment_method.account_name} />
                                            )}
                                            {order.transaction_id && (
                                                <InfoRow label="Transaction No" value={order.transaction_id} />
                                            )}
                                            <div>
                                                <p className="text-xs text-gray-400 mb-0.5">Payment Status</p>
                                                <StatusBadge
                                                    status={order.payment_status}
                                                    colorMap={paymentColorMap}
                                                    labelMap={paymentLabelMap}
                                                />
                                            </div>
                                        </div>
                                        {screenshotUrl && (
                                            <div>
                                                <p className="text-xs text-gray-400 mb-2">Payment Screenshot</p>
                                                <button
                                                    onClick={() => setLightboxImage(screenshotUrl)}
                                                    className="group relative w-16 h-16 sm:w-20 sm:h-20 rounded-lg overflow-hidden border border-gray-200 hover:border-blue-400 transition-colors"
                                                >
                                                    <img
                                                        src={screenshotUrl}
                                                        alt="Payment screenshot"
                                                        className="w-full h-full object-cover"
                                                        loading="lazy"
                                                    />
                                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                                                        <Maximize2 className="w-5 h-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                                    </div>
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </SectionCard>

                            <SectionCard icon={Package} title={`Order Items (${order.items?.length || 0})`}>
                                <div className="overflow-x-auto -mx-5 sm:mx-0">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-100">
                                                <th className="pb-2.5 px-5 sm:pl-0 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Product</th>
                                                <th className="pb-2.5 px-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Qty</th>
                                                <th className="pb-2.5 px-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Price</th>
                                                <th className="pb-2.5 pr-5 sm:pr-0 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50">
                                            {order.items?.map((item) => (
                                                <tr key={item.id}>
                                                    <td className="py-3 px-5 sm:pl-0">
                                                        <div className="flex items-center gap-3">
                                                            <ProductImage
                                                                src={item.product?.photo1_url}
                                                                name={item.product?.name || `Product #${item.product_id}`}
                                                            />
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-medium text-gray-900 truncate max-w-[160px] sm:max-w-[220px]">
                                                                    {item.product?.name || `Product #${item.product_id}`}
                                                                </p>
                                                                {item.variant && (
                                                                    <p className="text-xs text-gray-500 truncate max-w-[160px] sm:max-w-[220px]">
                                                                        {item.variant.label || `Variant #${item.variant_id}`}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-3 text-right text-sm text-gray-600 tabular-nums">
                                                        {item.quantity}
                                                    </td>
                                                    <td className="py-3 px-3 text-right text-sm text-gray-600 tabular-nums">
                                                        {formatCurrency(item.price)}
                                                    </td>
                                                    <td className="py-3 pr-5 sm:pr-0 text-right text-sm font-semibold text-gray-900 tabular-nums">
                                                        {formatCurrency(item.price * item.quantity)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </SectionCard>

                            <div className="hidden print:block print-summary">
                                <div className="border-t border-gray-200 pt-4 mt-4 space-y-2">
                                    <div className="flex justify-between text-sm"><span>Subtotal</span><span>{formatCurrency(order.subtotal)}</span></div>
                                    <div className="flex justify-between text-sm"><span>Delivery Fee</span><span>{formatCurrency(order.delivery_fee)}</span></div>
                                    {Number(order.discount_amount) > 0 && (
                                        <div className="flex justify-between text-sm"><span>Discount</span><span>-{formatCurrency(order.discount_amount)}</span></div>
                                    )}
                                    <div className="flex justify-between text-sm font-bold border-t border-gray-900 pt-2"><span>Grand Total</span><span>{formatCurrency(order.total_amount)}</span></div>
                                </div>
                            </div>
                        </>
                    )}
                </div>

                <div className="no-print flex flex-col sm:flex-row items-center justify-between gap-3 px-5 sm:px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50">
                    <div className="text-xs text-gray-400">
                        {order && (
                            <span className="hidden sm:inline">Order #{order.id} &middot; {order.created_at?.substring(0, 10)}</span>
                        )}
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <button
                            onClick={handleCopyInvoiceLink}
                            disabled={!order}
                            className="flex-1 sm:flex-none px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {linkCopied ? (
                                <><Check className="w-4 h-4 text-green-500" /> Copied</>
                            ) : (
                                <><Link className="w-4 h-4" /> Copy Invoice Link</>
                            )}
                        </button>
                        <button
                            onClick={handlePrint}
                            disabled={!order}
                            className="flex-1 sm:flex-none px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Printer className="w-4 h-4" />
                            Print
                        </button>
                        <button
                            onClick={onClose}
                            className="flex-1 sm:flex-none px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
