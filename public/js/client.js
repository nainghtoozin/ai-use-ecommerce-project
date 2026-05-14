document.addEventListener('DOMContentLoaded', () => {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];

    const cartCount = document.getElementById('cartCount');
    const cartItemsList = document.getElementById('cartItemsList');

    // ===== Toast Creation =====
    const toastContainer = document.createElement('div');
    toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
    toastContainer.style.zIndex = 1100;
    document.body.appendChild(toastContainer);

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-white bg-primary border-0';
    toastEl.role = 'alert';
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastContainer.appendChild(toastEl);

    const toastInner = document.createElement('div');
    toastInner.className = 'd-flex';
    toastEl.appendChild(toastInner);

    const toastBody = document.createElement('div');
    toastBody.className = 'toast-body';
    toastInner.appendChild(toastBody);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');
    closeBtn.setAttribute('aria-label', 'Close');
    toastInner.appendChild(closeBtn);

    const toast = new bootstrap.Toast(toastEl);

    function showToast(message) {
        toastBody.textContent = message;
        toast.show();
    }

    // ===== Helper: Safe Event Binding =====
    function safeAddEventListener(selector, event, handler) {
        const elements = document.querySelectorAll(selector);
        if (elements.length === 0) return;
        elements.forEach(el => el.addEventListener(event, handler));
    }

    // ===== Helper: Safe Single Element =====
    function safeGetElement(id) {
        return document.getElementById(id);
    }

    // ===== Update navbar cart =====
    function updateNavbarCart() {
        if (!cartCount || !cartItemsList) return;

        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
        cartCount.textContent = totalQty;

        if (cart.length === 0) {
            cartItemsList.innerHTML = '<li class="text-center text-muted">Your cart is empty</li>';
        } else {
            cartItemsList.innerHTML = cart.map(item => `
                <li class="d-flex justify-content-between align-items-center mb-1">
                    <span>${item.name}</span>
                    <span class="fw-bold">${item.qty}×${item.price} DT</span>
                </li>
            `).join('');
        }
    }

    updateNavbarCart();

    // ===== Add to Cart buttons =====
    safeAddEventListener('.add-to-cart-btn', 'click', (e) => {
        const btn = e.target;
        const card = btn.closest('.product-card');
        if (!card) return;
        
        const product = {
            id: card.dataset.id,
            name: card.querySelector('.card-title').textContent,
            price: parseFloat(card.querySelector('.text-success').textContent),
            stock: parseInt(card.querySelector('.text-muted').textContent.replace('Stock: ', '')),
            qty: 1
        };

        const existing = cart.find(p => p.id == product.id);
        if (existing) {
            if (existing.qty < product.stock) existing.qty++;
        } else {
            cart.push(product);
        }

        localStorage.setItem('cart', JSON.stringify(cart));
        updateNavbarCart();

        showToast(`${product.name} added to cart!`);
    });

    // ===== Mobile search toggle (with null check) =====
    const mobileBtn = safeGetElement('mobileSearchBtn');
    const mobileSearchContainer = safeGetElement('mobileSearchContainer');
    if (mobileBtn && mobileSearchContainer) {
        mobileBtn.addEventListener('click', () => {
            mobileSearchContainer.classList.toggle('d-none');
            const input = mobileSearchContainer.querySelector('input');
            if (input) input.focus();
        });
    }

    // ===== Category filter (with null check) =====
    const categoryButtons = document.querySelectorAll('#categoryList button');
    const productCards = document.querySelectorAll('.product-card');
    if (categoryButtons.length > 0 && productCards.length > 0) {
        categoryButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                categoryButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const categoryId = btn.getAttribute('data-category');
                productCards.forEach(card => {
                    card.style.display = (categoryId === 'all' || card.dataset.category === categoryId) ? 'block' : 'none';
                });
            });
        });
    }

    // ===== Sort products (with null check) =====
    const productGrid = safeGetElement('productGrid');
    const sortLinks = document.querySelectorAll('#sortDropdown a');
    if (productGrid && sortLinks.length > 0) {
        sortLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sortType = this.dataset.sort;

                const products = Array.from(productGrid.querySelectorAll('.product-card'));

                products.sort((a, b) => {
                    const priceA = parseFloat(a.querySelector('.text-success')?.textContent.replace(' DT','').replace(/,/g,'') || '0');
                    const priceB = parseFloat(b.querySelector('.text-success')?.textContent.replace(' DT','').replace(/,/g,'') || '0');

                    switch(sortType) {
                        case 'price-asc':
                            return priceA - priceB;
                        case 'price-desc':
                            return priceB - priceA;
                        case 'newest':
                            return parseInt(b.dataset.id) - parseInt(a.dataset.id);
                        case 'featured':
                        default:
                            return 0;
                    }
                });

                products.forEach(p => productGrid.appendChild(p));
            });
        });
    }

    // ===== Sidebar functionality (with null check) =====
    const sidebar = safeGetElement("sidebar");
    const toggleBtn = safeGetElement("sidebarToggle");
    const closeBtnSidebar = safeGetElement("closeSidebar");

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener("click", () => {
            sidebar.classList.add("sidebar-mobile-open");
            sidebar.classList.remove("d-none");
        });
    }

    if (closeBtnSidebar && sidebar) {
        closeBtnSidebar.addEventListener("click", () => {
            sidebar.classList.remove("sidebar-mobile-open");
            sidebar.classList.add("d-none");
        });
    }

    // Optional: click outside to close sidebar
    if (sidebar && toggleBtn) {
        document.addEventListener("click", (e) => {
            if (window.innerWidth < 768 && sidebar.classList.contains("sidebar-mobile-open")) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove("sidebar-mobile-open");
                    sidebar.classList.add("d-none");
                }
            }
        });
    }
});