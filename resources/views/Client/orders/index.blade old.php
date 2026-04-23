<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $websiteInfo->name ?? 'Store' }} - My Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    @if (!empty($websiteInfo->logo))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $websiteInfo->logo) }}?v={{ time() }}">
    @endif
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    @include('Client.components.navbar')

    <main class="container py-4 flex-grow-1">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-box-seam me-2"></i>My Orders</h2>
                <p class="text-muted mb-0">Track your recent purchases and payment status.</p>
            </div>
            <a href="{{ route('client.home') }}" class="btn btn-primary">
                <i class="bi bi-shop me-2"></i>Continue Shopping
            </a>
        </div>

        <div class="row g-4">
            @forelse($orders as $order)
                @php
                    $orderBadge = match ($order->order_status) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    };
                    $paymentBadge = match ($order->payment_status) {
                        'verified' => 'success',
                        'paid' => 'info',
                        'unpaid' => 'secondary',
                        'rejected' => 'danger',
                        default => 'secondary',
                    };
                @endphp
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h5 class="mb-1">Order #{{ $order->id }}</h5>
                                    <div class="text-muted small">{{ $order->created_at->format('M d, Y h:i A') }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-{{ $orderBadge }} me-1">{{ ucfirst($order->order_status) }}</span>
                                    <span class="badge bg-{{ $paymentBadge }}">{{ ucfirst($order->payment_status) }}</span>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="text-muted small">Items</div>
                                    <div class="fw-semibold">{{ $order->items->sum('quantity') }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Payment Method</div>
                                    <div class="fw-semibold">{{ $order->paymentMethod->name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Total</div>
                                    <div class="fw-semibold">{{ number_format($order->total_amount + ($order->delivery_fee ?? 0), 2) }}</div>
                                </div>
                            </div>

                            <div class="border-top pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="text-muted small">
                                    {{ $order->items->take(2)->pluck('product.name')->filter()->implode(', ') ?: 'No item details available' }}
                                </div>
                                <a href="{{ route('client.orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="text-center bg-white rounded-3 shadow-sm p-5">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <h3 class="mt-3">No Orders Yet</h3>
                        <p class="text-muted">You have not placed any orders yet.</p>
                        <a href="{{ route('client.home') }}" class="btn btn-primary">Start Shopping</a>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($orders->hasPages())
            <div class="mt-4 d-flex justify-content-center">
                {{ $orders->links() }}
            </div>
        @endif
    </main>

    @include('Client.components.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
