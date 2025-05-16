// Toggle sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Check for saved state
        const savedState = localStorage.getItem('sidebar-toggle-state');
        if (savedState === 'collapsed') {
            document.body.classList.add('sb-sidenav-toggled');
        }

        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            // Save state
            localStorage.setItem(
                'sidebar-toggle-state',
                document.body.classList.contains('sb-sidenav-toggled') ? 'collapsed' : 'expanded'
            );
        });
    }
});

// Confirmation dialog function
function confirmAction(options) {
    return Swal.fire({
        title: options.title || 'Are you sure?',
        text: options.text || "This action cannot be undone.",
        icon: options.icon || 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: options.confirmButtonText || 'Yes, proceed!',
        cancelButtonText: options.cancelButtonText || 'Cancel'
    });
}

// Handle mobile responsiveness
document.addEventListener('DOMContentLoaded', function() {
    const handleResize = () => {
        if (window.innerWidth < 768) {
            document.body.classList.add('sb-sidenav-toggled');
        } else {
            const savedState = localStorage.getItem('sidebar-toggle-state');
            if (savedState === 'expanded') {
                document.body.classList.remove('sb-sidenav-toggled');
            }
        }
    };

    // Initial check
    handleResize();

    // Listen for window resize
    window.addEventListener('resize', handleResize);
});

// Add active class to current page in sidebar
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const sidebarLinks = document.querySelectorAll('.list-group-item');
    
    sidebarLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});

// Sales page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize basket from session storage or empty array
    let basket = JSON.parse(sessionStorage.getItem('basket') || '[]');
    updateBasketDisplay();

    // Product search functionality
    const searchInput = document.getElementById('searchProduct');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterProducts();
        });
    }

    // Category filter functionality
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            filterProducts();
        });
    }

    // Quick add modal elements
    const quickAddModal = new bootstrap.Modal(document.getElementById('quickAddModal'));
    const quickAddProductName = document.getElementById('quickAddProductName');
    const quickAddPrice = document.getElementById('quickAddPrice');
    const quickAddStock = document.getElementById('quickAddStock');
    const quickAddQuantity = document.getElementById('quickAddQuantity');
    let currentProduct = null;

    // Product card click handling
    document.querySelectorAll('.product-item').forEach(card => {
        card.addEventListener('click', function() {
            const productData = JSON.parse(this.getAttribute('data-product'));
            
            // Don't show modal for out of stock items
            if (productData.stock <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Out of Stock',
                    text: 'This item is currently not available.'
                });
                return;
            }

            // Set current product and update modal
            currentProduct = productData;
            quickAddProductName.textContent = productData.name;
            quickAddPrice.textContent = `₱${parseFloat(productData.price).toFixed(2)}`;
            quickAddStock.textContent = `${productData.stock} items available`;
            quickAddQuantity.value = '1';
            quickAddQuantity.max = productData.stock;

            quickAddModal.show();
        });
    });

    // Quick add quantity controls
    document.getElementById('quickAddDecrease').addEventListener('click', function() {
        const currentValue = parseInt(quickAddQuantity.value);
        if (currentValue > 1) {
            quickAddQuantity.value = currentValue - 1;
        }
    });

    document.getElementById('quickAddIncrease').addEventListener('click', function() {
        const currentValue = parseInt(quickAddQuantity.value);
        const maxValue = parseInt(quickAddQuantity.max);
        if (currentValue < maxValue) {
            quickAddQuantity.value = currentValue + 1;
        }
    });

    // Validate quantity input
    quickAddQuantity.addEventListener('change', function() {
        let value = parseInt(this.value);
        const max = parseInt(this.max);
        
        if (isNaN(value) || value < 1) {
            value = 1;
        } else if (value > max) {
            value = max;
            Swal.fire({
                icon: 'warning',
                title: 'Maximum Stock Reached',
                text: `Only ${max} items available`
            });
        }
        
        this.value = value;
    });

    // Confirm quick add
    document.getElementById('confirmQuickAdd').addEventListener('click', function() {
        if (!currentProduct) return;

        const quantity = parseInt(quickAddQuantity.value);
        
        if (quantity > currentProduct.stock) {
            Swal.fire({
                icon: 'error',
                title: 'Not enough stock',
                text: `Only ${currentProduct.stock} items available!`
            });
            return;
        }

        addToBasket(currentProduct, quantity);
        
        // Show success message
        Swal.fire({
            title: 'Added to Order!',
            text: `${quantity}x ${currentProduct.name} added to your order`,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });

        quickAddModal.hide();
    });

    // Product card click handling
    document.querySelectorAll('.product-card').forEach(card => {
        const cardBody = card.querySelector('.card-body');
        const quantityControls = card.querySelector('.quantity-controls');
        const qtyInput = card.querySelector('.product-qty');
        
        if (!quantityControls) return; // Skip if no quantity controls (out of stock)
        
        // Show quantity controls when clicking anywhere on the card except controls
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking inside quantity controls or if item is out of stock
            if (!e.target.closest('.quantity-controls') && quantityControls) {
                // Hide all other quantity controls
                document.querySelectorAll('.quantity-controls').forEach(controls => {
                    if (controls !== quantityControls) {
                        controls.style.display = 'none';
                    }
                });
                // Show this card's quantity controls
                quantityControls.style.display = 'block';
            }
        });
    });

    // Quantity control buttons
    document.querySelectorAll('.decrease-qty').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click
            const input = this.parentElement.querySelector('.product-qty');
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        });
    });

    document.querySelectorAll('.increase-qty').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click
            const input = this.parentElement.querySelector('.product-qty');
            const currentValue = parseInt(input.value);
            const maxValue = parseInt(input.getAttribute('max'));
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
            }
        });
    });

    // Add to basket functionality
    document.querySelectorAll('.add-to-basket').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click
            const productData = JSON.parse(this.getAttribute('data-product'));
            const quantityInput = this.closest('.card-body').querySelector('.product-qty');
            const quantity = parseInt(quantityInput.value);
            
            if (quantity > productData.stock) {
                Swal.fire({
                    icon: 'error',
                    title: 'Not enough stock',
                    text: `Only ${productData.stock} items available!`
                });
                return;
            }
            
            addToBasket(productData, quantity);
            
            // Show success message
            Swal.fire({
                title: 'Added to Order!',
                text: `${quantity}x ${productData.name} added to your order`,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
            
            // Hide quantity controls after adding to basket
            this.closest('.quantity-controls').style.display = 'none';
            quantityInput.value = 1;
        });
    });

    // Close quantity controls when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.product-card')) {
            document.querySelectorAll('.quantity-controls').forEach(controls => {
                controls.style.display = 'none';
            });
        }
    });

    // Checkout button
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            if (basket.length === 0) return;
            
            const checkoutModal = new bootstrap.Modal(document.getElementById('checkoutModal'));
            updateModalTotal();
            checkoutModal.show();
        });
    }

    // Confirm order button
    const confirmOrderBtn = document.getElementById('confirmOrder');
    if (confirmOrderBtn) {
        confirmOrderBtn.addEventListener('click', function() {
            processOrder();
        });
    }

    // Cash amount input
    const cashAmountInput = document.getElementById('cashAmount');
    if (cashAmountInput) {
        cashAmountInput.addEventListener('input', function() {
            updateChange();
        });
    }
});

function filterProducts() {
    const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    const selectedCategory = document.getElementById('categoryFilter').value;
    
    document.querySelectorAll('.product-card').forEach(card => {
        const productName = card.querySelector('.card-title').textContent.toLowerCase();
        const productCategory = card.getAttribute('data-category');
        
        const matchesSearch = productName.includes(searchTerm);
        const matchesCategory = selectedCategory === '' || productCategory === selectedCategory;
        
        card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
    });
}

function addToBasket(product, quantity) {
    let basket = JSON.parse(sessionStorage.getItem('basket') || '[]');
    
    // Check if product already exists in basket
    const existingItemIndex = basket.findIndex(item => item.product_ID === product.product_ID);
    
    if (existingItemIndex > -1) {
        // Update quantity if product exists
        basket[existingItemIndex].quantity += quantity;
    } else {
        // Add new product to basket
        basket.push({
            product_ID: product.product_ID,
            name: product.name,
            price: product.price,
            quantity: quantity,
            category: product.category
        });
    }
    
    sessionStorage.setItem('basket', JSON.stringify(basket));
    updateBasketDisplay();
}

function removeFromBasket(productId) {
    let basket = JSON.parse(sessionStorage.getItem('basket') || '[]');
    basket = basket.filter(item => item.product_ID !== productId);
    sessionStorage.setItem('basket', JSON.stringify(basket));
    updateBasketDisplay();
}

function updateBasketDisplay() {
    const basket = JSON.parse(sessionStorage.getItem('basket') || '[]');
    const basketItems = document.getElementById('basketItems');
    const emptyBasketMessage = document.getElementById('emptyBasketMessage');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (basket.length === 0) {
        emptyBasketMessage.style.display = 'block';
        checkoutBtn.disabled = true;
        basketItems.innerHTML = emptyBasketMessage.outerHTML;
        updateTotals(0);
        return;
    }
    
    emptyBasketMessage.style.display = 'none';
    checkoutBtn.disabled = false;
    
    let html = '';
    let total = 0;
    
    basket.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        html += `
            <div class="basket-item mb-2 p-2 border rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">${item.name}</h6>
                        <small class="text-muted">${item.quantity} × ₱${item.price.toFixed(2)}</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="me-3">₱${itemTotal.toFixed(2)}</span>
                        <button class="btn btn-sm btn-danger" onclick="removeFromBasket(${item.product_ID})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    basketItems.innerHTML = html;
    updateTotals(total);
}

function updateTotals(subtotal) {
    document.getElementById('basketSubtotal').textContent = `₱${subtotal.toFixed(2)}`;
    document.getElementById('basketTotal').textContent = `₱${subtotal.toFixed(2)}`;
    
    if (document.getElementById('modalTotal')) {
        document.getElementById('modalTotal').value = subtotal.toFixed(2);
    }
}

function updateModalTotal() {
    const subtotal = parseFloat(document.getElementById('basketTotal').textContent.replace('₱', ''));
    const discountType = document.getElementById('discountType').value;
    let total = subtotal;
    
    if (discountType === 'Senior' || discountType === 'PWD') {
        total = subtotal * 0.8; // 20% discount
    }
    
    document.getElementById('modalTotal').value = total.toFixed(2);
    updateChange();
}

function updateChange() {
    const total = parseFloat(document.getElementById('modalTotal').value);
    const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
    const change = cashAmount - total;
    
    document.getElementById('changeAmount').value = change.toFixed(2);
    document.getElementById('confirmOrder').disabled = cashAmount < total;
}

function processOrder() {
    const basket = JSON.parse(sessionStorage.getItem('basket') || '[]');
    const orderType = document.querySelector('select[name="orderType"]').value;
    const discountType = document.getElementById('discountType').value;
    const total = parseFloat(document.getElementById('modalTotal').value);
    const cashAmount = parseFloat(document.getElementById('cashAmount').value);
    const change = parseFloat(document.getElementById('changeAmount').value);
    
    // Generate receipt HTML
    const receiptContent = document.getElementById('receiptContent');
    let receiptHtml = `
        <div class="receipt-header text-center mb-3">
            <h4>Café POS</h4>
            <p class="mb-1">Order Receipt</p>
            <small>${new Date().toLocaleString()}</small>
        </div>
        <div class="receipt-body">
            <div class="text-start mb-3">
                <p class="mb-1">Order Type: ${orderType}</p>
                <p class="mb-1">Discount: ${discountType}</p>
            </div>
            <div class="items-list mb-3">
                <hr>
                ${basket.map(item => `
                    <div class="d-flex justify-content-between mb-1">
                        <span>${item.quantity}x ${item.name}</span>
                        <span>₱${(item.price * item.quantity).toFixed(2)}</span>
                    </div>
                `).join('')}
                <hr>
            </div>
            <div class="totals text-end">
                <p class="mb-1">Subtotal: ₱${document.getElementById('basketSubtotal').textContent.replace('₱', '')}</p>
                <p class="mb-1">Total: ₱${total.toFixed(2)}</p>
                <p class="mb-1">Cash: ₱${cashAmount.toFixed(2)}</p>
                <p class="mb-1">Change: ₱${change.toFixed(2)}</p>
            </div>
        </div>
        <div class="receipt-footer text-center mt-3">
            <p class="mb-1">Thank you for your order!</p>
            <small>Please come again</small>
        </div>
    `;
    
    receiptContent.innerHTML = receiptHtml;
    
    // Hide checkout modal and show receipt modal
    const checkoutModal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
    checkoutModal.hide();
    
    const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    receiptModal.show();
    
    // Clear basket
    sessionStorage.removeItem('basket');
    updateBasketDisplay();
}

function printReceipt() {
    const receiptContent = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '', 'height=600,width=800');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Receipt</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    @media print {
                        body { width: 80mm; }
                    }
                </style>
            </head>
            <body>
                ${receiptContent}
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        }
                    }
                </script>
            </body>
        </html>
    `);
    
    printWindow.document.close();
}

function downloadReceipt() {
    const receiptContent = document.getElementById('receiptContent');
    
    html2canvas(receiptContent).then(canvas => {
        const link = document.createElement('a');
        link.download = 'receipt.png';
        link.href = canvas.toDataURL();
        link.click();
    });
}
