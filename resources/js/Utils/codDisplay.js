export function isCodOrder(order) {
    if (!order) return false;
    if (order.is_cod_order !== undefined && order.is_cod_order !== null) {
        return !!order.is_cod_order;
    }
    const method = order.payment_method || order.paymentMethod;
    if (method) {
        return method.type === 'cod';
    }
    return !order.payment_method_id;
}

export function isCodUnpaid(order) {
    return isCodOrder(order) && order?.payment_status === 'pending';
}

export function codPaymentStatusLabel(order) {
    if (isCodUnpaid(order)) {
        return 'Due on Delivery';
    }
    if (isCodOrder(order) && order?.payment_status === 'paid') {
        return 'Paid';
    }
    return order?.payment_status;
}

export function codPaymentMethodLabel(order, fallback = 'N/A') {
    if (isCodOrder(order)) {
        return 'Cash on Delivery';
    }
    return order?.payment_method?.name || order?.paymentMethod?.name || fallback;
}
