import { useState, useEffect, useRef } from 'react';
import { X, Printer } from 'lucide-react';

const styleId = 'order-detail-modal-styles';
if (!document.getElementById(styleId)) {
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
        @keyframes modal-scale-in {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
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
            #printable-order .print-section {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin-bottom: 16px !important;
            }
            #printable-order .print-section h3 {
                font-size: 11px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 8px;
            }
            #printable-order table { width: 100%; border-collapse: collapse; }
            #printable-order th {
                text-align: left; font-size: 10px; text-transform: uppercase;
                color: #6b7280; padding: 8px 12px; border-bottom: 1px solid #e5e7eb;
            }
            #printable-order td {
                font-size: 11px; padding: 8px 12px; border-bottom: 1px solid #f3f4f6;
            }
            #printable-order .print-header {
                text-align: center; margin-bottom: 24px; padding-bottom: 16px;
                border-bottom: 2px solid #111827;
            }
            #printable-order .print-header h2 {
                font-size: 18px; font-weight: 700; color: #111827; margin: 0;
            }
            #printable-order .print-header p {
                font-size: 11px; color: #6b7280; margin: 4px 0 0 0;
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

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    verified: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-gray-100 text-gray-800',
    confirmed: 'bg-blue-100 text-blue-800',
    shipped: 'bg-indigo-100 text-indigo-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString() + ' MMK';
}

function DetailRow({ label, value, highlight }) {
    return (
        <div className="flex items-start justify-between gap-4 py-2 border-b border-gray-50 last:border-0">
            <span className="text-xs sm:text-sm text-gray-500">{label}</span>
            <span className={`text-xs sm:text-sm font-medium text-right ${highlight ? 'text-gray-900' : 'text-gray-700'}`}>{value || '-'}</span>
        </div>
    );
}

export default function OrderDetailModal({ orderId, onClose }) {
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const contentRef = useRef(null);

    const handlePrint = () => {
        window.print();
    };

    useEffect(() => {
        if (!orderId) return;
        setLoading(true);
        setError(null);

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
            if (e.key === 'Escape') onCloseRef.current();
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [orderId]);

    if (!orderId) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="absolute inset-0 bg-black/50 backdrop-blur-sm no-print" onClick={onClose} />
            <div ref={contentRef} className="relative bg-white rounded-2xl shadow-2xl w-full max-h-[90vh] mx-4 sm:mx-6 max-w-2xl overflow-hidden flex flex-col" style={{ animation: 'modal-scale-in 200ms ease-out' }}>
                <div className="no-print flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h2 className="text-base sm:text-lg font-bold text-gray-900">
                        Order #{orderId}
                    </h2>
                    <button
                        onClick={onClose}
                        className="p-1.5 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <X className="w-5 h-5 text-gray-400" />
                    </button>
                </div>

                <div id="printable-order" className="overflow-y-auto px-5 sm:px-6 py-4 space-y-6 flex-1">
                    {loading && (
                        <div className="flex items-center justify-center py-16 no-print">
                            <div className="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
                            <span className="ml-3 text-sm text-gray-500">Loading...</span>
                        </div>
                    )}

                    {error && (
                        <div className="text-center py-16 no-print">
                            <p className="text-sm text-red-500">{error}</p>
                        </div>
                    )}

                    {order && (
                        <>
                            {/* Print header (visible only on print) */}
                            <div className="hidden print:block print-header">
                                <h2>INVOICE</h2>
                                <p>Order #{order.id} &middot; {order.created_at?.substring(0, 10)}</p>
                            </div>

                            <div className="print-section">
                                <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Order Information</h3>
                                <div className="bg-gray-50 rounded-xl px-4 py-3 print:bg-transparent">
                                    <DetailRow label="Order Status" value={statusLabels[order.order_status] || order.order_status} highlight />
                                    {order.order_status !== 'pending' && order.order_status !== 'cancelled' && (
                                        <DetailRow label="Payment Status" value={order.payment_status || '-'} highlight />
                                    )}
                                    <DetailRow label="Subtotal" value={formatCurrency(order.subtotal)} />
                                    <DetailRow label="Delivery Fee" value={formatCurrency(order.delivery_fee)} />
                                    {Number(order.discount_amount) > 0 && (
                                        <DetailRow label="Discount" value={`-${formatCurrency(order.discount_amount)}`} />
                                    )}
                                    <DetailRow label="Total Amount" value={formatCurrency(order.total_amount)} highlight />
                                    <DetailRow label="Date" value={order.created_at?.substring(0, 10)} />
                                </div>
                            </div>

                            <div className="print-section">
                                <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Customer Information</h3>
                                <div className="bg-gray-50 rounded-xl px-4 py-3 print:bg-transparent">
                                    <DetailRow label="Name" value={order.user?.name || `${order.first_name} ${order.last_name}`} />
                                    <DetailRow label="Phone" value={order.phone} />
                                    <DetailRow label="Email" value={order.email} />
                                    <DetailRow label="Address" value={order.address} />
                                </div>
                            </div>

                            <div className="print-section">
                                <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                                    Ordered Products ({order.items?.length || 0})
                                </h3>
                                <div className="overflow-x-auto rounded-xl border border-gray-100 print:border-0">
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="bg-gray-50 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <th className="px-3 sm:px-4 py-2.5">Product</th>
                                                <th className="px-3 sm:px-4 py-2.5 text-right">Qty</th>
                                                <th className="px-3 sm:px-4 py-2.5 text-right">Price</th>
                                                <th className="px-3 sm:px-4 py-2.5 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {order.items?.map((item) => (
                                                <tr key={item.id} className="hover:bg-gray-50 transition-colors">
                                                    <td className="px-3 sm:px-4 py-2.5">
                                                        <p className="font-medium text-gray-900 truncate max-w-[180px] sm:max-w-[240px]">
                                                            {item.product?.name || `Product #${item.product_id}`}
                                                        </p>
                                                    </td>
                                                    <td className="px-3 sm:px-4 py-2.5 text-right tabular-nums text-gray-700">
                                                        {item.quantity}
                                                    </td>
                                                    <td className="px-3 sm:px-4 py-2.5 text-right tabular-nums text-gray-700">
                                                        {formatCurrency(item.price)}
                                                    </td>
                                                    <td className="px-3 sm:px-4 py-2.5 text-right tabular-nums font-semibold text-gray-900">
                                                        {formatCurrency(item.price * item.quantity)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </>
                    )}
                </div>

                <div className="no-print flex items-center justify-end gap-3 px-5 sm:px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handlePrint}
                        disabled={!order}
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Printer className="w-4 h-4" />
                        Print
                    </button>
                </div>
            </div>
        </div>
    );
}
