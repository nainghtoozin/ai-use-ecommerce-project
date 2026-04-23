<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', ($websiteInfo->name ?? '')) - Checkout Page</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    @if (!empty($websiteInfo->logo))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $websiteInfo->logo) }}?v={{ time() }}">
    @endif
    
    @php
        $themeFile = $websiteInfo->theme_fullname ?? 'client-base.css';
        $themeFile = str_replace('\\', '/', $themeFile);
        $themeFile = ltrim($themeFile, '/');
        $themeFile = basename($themeFile);
    @endphp

    <link href="{{ asset('css/client_themes/' . $themeFile) }}" rel="stylesheet">
    <link href="{{ asset('css/checkout.css') }}" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">
    @include('Client.components.navbar')

    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}"><i class="bi bi-house-door me-1"></i>Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('client.cart') }}">Cart</a></li>
                <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            </ol>
        </nav>
    </div>

    <main class="container flex-grow-1 mb-5">
        <div class="checkout-container">
            <h1 class="checkout-title mb-4">
                <i class="bi bi-credit-card me-2"></i>Checkout
            </h1>

            <div class="row g-4">
                <div class="col-lg-7">
                    <!-- Billing Information -->
                    <div class="checkout-section">
                        <h4 class="section-header">
                            <i class="bi bi-person-circle me-2"></i>Billing Information
                        </h4>

                        <form id="checkoutForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstName" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lastName" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="tel" class="form-control" id="phone" placeholder="+216 XX XXX XXX" required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" placeholder="your@email.com">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Full Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address" rows="3" placeholder="Street, Building, Floor, Apartment..." required></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <select class="form-select" id="citySelect" required>
                                        <option value="">Select City</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" data-delivery-fee="{{ $city->delivery_fee }}">
                                                {{ $city->name }} (+{{ number_format($city->delivery_fee) }} MMK)
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Township <span class="text-danger">*</span></label>
                                    <select class="form-select" id="townshipSelect" required disabled>
                                        <option value="">Select City First</option>
                                    </select>
                                </div>
                                <script>const locationData = @json($cities);</script>
                                <div class="col-md-6">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postalCode" placeholder="Auto-filled" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Order Notes (Optional)</label>
                                    <textarea class="form-control" id="orderNotes" rows="2" placeholder="Special instructions for delivery..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <script>
                    // Location dropdown logic
                    const citySelect = document.getElementById('citySelect');
                    const townshipSelect = document.getElementById('townshipSelect');
                    const postalCodeInput = document.getElementById('postalCode');
                    let currentDeliveryFee = 0;

                    citySelect.addEventListener('change', function() {
                        const cityId = parseInt(this.value);
                        const selectedOption = this.options[this.selectedIndex];
                        currentDeliveryFee = parseFloat(selectedOption.dataset.deliveryFee) || 0;
                        
                        const city = locationData.find(c => c.id === cityId);
                        townshipSelect.innerHTML = '<option value="">Select Township</option>';
                        
                        if (city && city.townships && city.townships.length > 0) {
                            city.townships.forEach(t => {
                                const opt = document.createElement('option');
                                opt.value = t.id;
                                opt.textContent = t.name;
                                opt.dataset.postalCode = t.postal_code || '';
                                townshipSelect.appendChild(opt);
                            });
                            townshipSelect.disabled = false;
                        } else {
                            townshipSelect.disabled = true;
                        }
                        updateDeliveryDisplay();
                    });

                    townshipSelect.addEventListener('change', function() {
                        const opt = this.options[this.selectedIndex];
                        postalCodeInput.value = opt.dataset.postalCode || '';
                    });

                    function updateDeliveryDisplay() {
                        const orderShipping = document.getElementById('orderShipping');
                        const orderTotal = document.getElementById('orderTotal');
                        orderShipping.textContent = currentDeliveryFee > 0 ? currentDeliveryFee.toLocaleString() + ' MMK' : 'FREE';
                        recalculateTotal();
                    }

                    function recalculateTotal() {
                        const subtotalEl = document.getElementById('orderSubtotal');
                        const totalEl = document.getElementById('orderTotal');
                        const subtotal = parseFloat(subtotalEl.dataset.subtotal) || 0;
                        totalEl.textContent = (subtotal + currentDeliveryFee).toLocaleString() + ' MMK';
                    }
                    </script>

                    <!-- Payment Method -->
                    <div class="checkout-section mt-4">
                        <h4 class="section-header">
                            <i class="bi bi-wallet2 me-2"></i>Payment Method
                        </h4>

                        <div class="payment-options" id="paymentOptions">
                            @forelse($paymentMethods as $method)
                            <div class="payment-option" data-payment-id="{{ $method->id }}">
                                <input type="radio" name="payment" id="payment_{{ $method->id }}" value="{{ $method->id }}">
                                <label for="payment_{{ $method->id }}">
                                    <i class="bi bi-wallet"></i>
                                    <div>
                                        <strong>{{ $method->name }}</strong>
                                        <small>Pay via {{ $method->name }}</small>
                                    </div>
                                </label>
                            </div>
                            @empty
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                No payment methods available. Please contact support.
                            </div>
                            @endforelse
                        </div>

                        <!-- Dynamic Payment Details -->
                        <div id="paymentDetails" class="payment-details mt-3" style="display: none;">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>Payment Information</h6>
                                    <div id="paymentInfoContent">
                                        <p class="mb-1"><strong>Account Name:</strong> <span id="detailAccountName"></span></p>
                                        <p class="mb-1"><strong>Account Number:</strong> <span id="detailAccountNumber"></span></p>
                                        <p class="mb-0" id="detailBankRow"><strong>Bank:</strong> <span id="detailBankName"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Proof Upload (Optional) -->
                        <div id="paymentProofSection" class="mt-3" style="display: none;">
                            <label class="form-label">Upload Payment Proof (Optional)</label>
                            <input type="file" class="form-control" id="paymentProof" accept="image/*">
                            <small class="text-muted">Screenshot or receipt of your payment</small>
                        </div>

                        <div id="transactionIdSection" class="mt-3" style="display: none;">
                            <label class="form-label">Transaction ID (Optional)</label>
                            <input type="text" name="transaction_id" class="form-control" id="transactionId" placeholder="Enter Transaction ID">
                        </div>
                    </div>
                </div>

                <!-- Right: Order Summary -->
                <div class="col-lg-5">
                    <div class="checkout-section order-summary sticky-top" style="top: 20px;">
                        <h4 class="section-header">
                            <i class="bi bi-bag-check me-2"></i>Order Summary
                        </h4>

                        <div id="orderItems" class="order-items">
                            <!-- Items populated by JavaScript -->
                        </div>

                        <div class="order-calculations">
                            <div class="calc-row">
                                <span>Subtotal:</span>
                                <span id="orderSubtotal">0 {{ $websiteInfo->currency ?? 'DT'}}</span>
                            </div>
                            <div class="calc-row">
                                <span>Shipping:</span>
                                <span id="orderShipping">{{ $websiteInfo->shipping_fee ?? 7 }} {{ $websiteInfo->currency ?? 'DT'}}</span>
                            </div>
                            <div class="calc-row total-row">
                                <span>Total:</span>
                                <span id="orderTotal">0 {{ $websiteInfo->currency ?? 'DT'}}</span>
                            </div>
                        </div>

                        <div class="alert alert-info alert-sm mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Free shipping on orders over {{ $websiteInfo->free_shipping_threshhold ?? 0 }} {{ $websiteInfo->currency ?? 'DT'}}</small>
                        </div>

                        <button type="submit" form="checkoutForm" class="btn btn-place-order w-100" id="placeOrderBtn">
                            <i class="bi bi-check-circle me-2"></i>Place Order
                        </button>

                        <div class="security-badges mt-3">
                            <div class="security-item">
                                <i class="bi bi-shield-check"></i>
                                <small>{{ $websiteInfo->secure_payment_info ?? 'Secure Payment'}}</small>
                            </div>
                            <div class="security-item">
                                <i class="bi bi-truck"></i>
                                <small>{{ $websiteInfo->shipping_info ?? 'Fast Delivery'}}</small>
                            </div>
                            <div class="security-item">
                                <i class="bi bi-arrow-return-left"></i>
                                <small>{{ $websiteInfo->easy_returns_info ?? 'Easy Returns'}}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('Client.components.footer')

    <script>
        // Payment methods data from server (Blade)
        const paymentMethods = @json($paymentMethods);
        
        // Payment details display logic
        document.querySelectorAll('input[name="payment"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const selectedId = parseInt(this.value);
                const method = paymentMethods.find(m => m.id === selectedId);
                
                if (method) {
                    document.getElementById('paymentDetails').style.display = 'block';
                    document.getElementById('paymentProofSection').style.display = 'block';
                    document.getElementById('transactionIdSection').style.display = 'block';
                    
                    document.getElementById('detailAccountName').textContent = method.account_name;
                    document.getElementById('detailAccountNumber').textContent = method.account_number;
                    
                    if (method.bank_name) {
                        document.getElementById('detailBankRow').style.display = 'block';
                        document.getElementById('detailBankName').textContent = method.bank_name;
                    } else {
                        document.getElementById('detailBankRow').style.display = 'none';
                    }
                }
            });
        });
    </script>
    
    <script src="{{ asset('js/checkout.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
