<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $websiteInfo->name ?? 'Store' }} - Order #{{ $order->id }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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

        @php
            $cityRelation = $order->getRelation('city');
            $townshipRelation = $order->getRelation('township');
            $cityLabel = $cityRelation?->name ?? $order->getAttribute('city');
            $townshipLabel = $townshipRelation?->name;
        @endphp

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Order #{{ $order->id }}</h2>
                <p class="text-muted mb-0">{{ $order->created_at->format('M d, Y h:i A') }}</p>
            </div>
            <a href="{{ route('client.orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Orders
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Items</h5>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($order->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->product->name ?? 'Product #' . $item->product_id }}</div>
                                            </td>
                                            <td class="text-end">{{ number_format($item->price, 2) }}</td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ number_format($item->price * $item->quantity, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No items found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end">Subtotal</td>
                                        <td class="text-end">{{ number_format($order->subtotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end">Delivery Fee</td>
                                        <td class="text-end">{{ number_format($order->delivery_fee ?? 0, 2) }}</td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td colspan="3" class="text-end">Total</td>
                                        <td class="text-end">{{ number_format($order->total_amount, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                @if($order->payment_status === \App\Models\Order::PAYMENT_STATUS_UNPAID)
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Upload Payment Proof</h5>
                            <form action="{{ route('client.orders.upload-payment', $order->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Transaction ID</label>
                                    <input type="text" name="transaction_id" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Proof</label>
                                    <input type="file" name="payment_proof" class="form-control" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload me-2"></i>Submit Payment Proof
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Order Status</h5>
                        <div class="mb-3">
                            <div class="text-muted small">Order Status</div>
                            @php
                                $orderBadge = match ($order->order_status) {
                                    'pending' => 'warning',
                                    'confirmed' => 'info',
                                    'shipped' => 'primary',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $orderBadge }}">{{ ucfirst($order->order_status) }}</span>
                        </div>
                        <div>
                            <div class="text-muted small">Payment Status</div>
                            @php
                                $paymentBadge = match ($order->payment_status) {
                                    'verified' => 'success',
                                    'paid' => 'info',
                                    'unpaid' => 'secondary',
                                    'rejected' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $paymentBadge }}">{{ ucfirst($order->payment_status) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Payment Information</h5>
                        <div class="mb-2">
                            <div class="text-muted small">Payment Method</div>
                            <div class="fw-semibold">{{ $order->paymentMethod->name ?? 'N/A' }}</div>
                        </div>
                        @if($order->transaction_id)
                        <div class="mb-2">
                            <div class="text-muted small">Transaction ID</div>
                            <div class="fw-semibold">{{ $order->transaction_id }}</div>
                        </div>
                        @endif
                        @if($order->paid_amount)
                        <div class="mb-2">
                            <div class="text-muted small">Paid Amount</div>
                            <div class="fw-semibold text-success">{{ number_format($order->paid_amount, 2) }}</div>
                        </div>
                        @endif
                        @if($order->payment_proof)
                        <div>
                            <div class="text-muted small">Payment Proof</div>
                            <a href="{{ asset('storage/' . $order->payment_proof) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-image me-1"></i>View Proof
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Delivery Information</h5>
                        <div class="mb-2">
                            <div class="text-muted small">Name</div>
                            <div class="fw-semibold">{{ $order->first_name }} {{ $order->last_name }}</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-muted small">Phone</div>
                            <div class="fw-semibold">{{ $order->phone }}</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-muted small">Address</div>
                            <div class="fw-semibold">{{ $order->address }}</div>
                        </div>
                        @if($cityLabel || $townshipLabel)
                            <div class="mb-2">
                                <div class="text-muted small">City / Township</div>
                                <div class="fw-semibold">{{ $cityLabel }}{{ $townshipLabel ? ', ' . $townshipLabel : '' }}</div>
                            </div>
                        @endif
                        @if($order->postal_code)
                            <div>
                                <div class="text-muted small">Postal Code</div>
                                <div class="fw-semibold">{{ $order->postal_code }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Actions</h5>
                        @if($order->canCancel())
                            <form action="{{ route('client.orders.cancel', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Cancel this order?')">
                                    <i class="bi bi-x-circle me-2"></i>Cancel Order
                                </button>
                            </form>
                        @else
                            <div class="text-muted small">No actions available for this order.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('Client.components.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
