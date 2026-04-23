<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $websiteInfo->name ?? 'Store' }} - My Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    @if (!empty($websiteInfo->logo))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $websiteInfo->logo) }}">
    @endif
    <style>
        .order-card { transition: box-shadow 0.2s; }
        .order-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    @include('Client.components.navbar')

    <main class="container py-4 flex-grow-1">
        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-box-seam me-2"></i>My Orders</h2>
                <p class="text-muted mb-0">Track your recent purchases and payment status.</p>
            </div>
            <a href="{{ route('client.home') }}" class="btn btn-primary">
                <i class="bi bi-shop me-2"></i>Continue Shopping
            </a>
        </div>

        <!-- Orders Loop -->
        <div class="row g-4">
            @forelse($orders as $order)
                @php
                    $orderBadge = match($order->order_status) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary'
                    };
                    $paymentBadge = match($order->payment_status) {
                        'verified' => 'success',
                        'paid' => 'info',
                        'unpaid' => 'secondary',
                        'rejected' => 'danger',
                        default => 'secondary'
                    };
                @endphp

                <div class="col-12">
                    <div class="card shadow-sm border-0 order-card">
                        <!-- Order Header -->
                        <div class="card-header bg-white border-0">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0">Order #{{ $order->id }}</h5>
                                    <small class="text-muted">{{ $order->created_at->format('F d, Y • h:i A') }}</small>
                                </div>
                                <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                    <span class="badge bg-{{ $orderBadge }} me-1">{{ ucfirst($order->order_status) }}</span>
                                    <span class="badge bg-{{ $paymentBadge }}">{{ ucfirst($order->payment_status) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Order Body -->
                        <div class="card-body">
                            <!-- Order Items Table -->
                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                            <tr>
                                                <td>{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-end">{{ number_format($item->price, 2) }}</td>
                                                <td class="text-end">{{ number_format($item->price * $item->quantity, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Order Summary -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="text-muted small">Payment Method</div>
                                    <div class="fw-semibold">{{ $order->paymentMethod->name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <div class="row">
                                        <div class="col-12">
                                            <span class="text-muted">Subtotal:</span>
                                            <span class="ms-2">{{ number_format($order->subtotal, 2) }}</span>
                                        </div>
                                        <div class="col-12">
                                            <span class="text-muted">Delivery:</span>
                                            <span class="ms-2">{{ number_format($order->delivery_fee ?? 0, 2) }}</span>
                                        </div>
                                        <div class="col-12 fw-bold mt-1">
                                            <span>Total:</span>
                                            <span class="ms-2">{{ number_format($order->total_amount, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Footer -->
                        <div class="card-footer bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('client.orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <!-- Empty State -->
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

        <!-- Pagination -->
        @if($orders->hasPages())
            <div class="mt-4 d-flex justify-content-center">
                {{ $orders->links() }}
            </div>
        @endif
    </main>

    @include('Client.components.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>