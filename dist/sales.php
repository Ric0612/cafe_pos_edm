<?php
session_start();

// Verify user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

include('../includes/db-conn.php');

// Function to update stock
function updateStock($product_id, $quantity) {
    global $conn;
    $sql = "UPDATE products SET stock = stock - ? WHERE product_ID = ? AND stock >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $quantity, $product_id, $quantity);
    return $stmt->execute();
}

// Function to log transaction
function logTransaction($products, $total, $discount_type, $discount_id, $vat_amount, $discount_amount) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into transactions table
        $sql = "INSERT INTO transactions (total_amount, discount_type, discount_id, vat_amount, discount_amount, transaction_date) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dssdd", $total, $discount_type, $discount_id, $vat_amount, $discount_amount);
        $stmt->execute();
        
        $transaction_id = $conn->insert_id;
        
        // Insert transaction details
        $sql = "INSERT INTO transaction_details (transaction_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($products as $product) {
            $stmt->bind_param("iiid", $transaction_id, $product['product_ID'], $product['quantity'], $product['price']);
            $stmt->execute();
            
            // Update stock
            if (!updateStock($product['product_ID'], $product['quantity'])) {
                throw new Exception("Failed to update stock for product ID: " . $product['product_ID']);
            }
        }
        
        // Commit transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        return false;
    }
}

// Fetch all available products
$sql = "SELECT * FROM products WHERE stock > 0 ORDER BY category, name";
$result = $conn->query($sql);
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Get categories for filtering
$categories_query = "SELECT DISTINCT category FROM products WHERE stock > 0 ORDER BY category";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Sales - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <link href="css/styles.css" rel="stylesheet" />
    <style>
        .product-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
        .cart-item {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .cart-total {
            font-size: 1.2rem;
            font-weight: bold;
        }
        #cartOffcanvas {
            width: 400px;
        }
        .category-filter {
            white-space: nowrap;
            overflow-x: auto;
            padding: 10px 0;
        }
        .category-filter::-webkit-scrollbar {
            height: 6px;
        }
        .category-filter::-webkit-scrollbar-thumb {
            background-color: #6c4f3d;
            border-radius: 3px;
        }
        .category-filter::-webkit-scrollbar-track {
            background-color: #f8f9fa;
        }
        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        .quantity-input {
            width: 60px;
            text-align: center;
        }
        .product-price {
            color: #6c4f3d;
            font-weight: bold;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
<div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Page content wrapper -->
    <div id="page-content-wrapper">
            <!-- Top navigation -->
            <?php include('../includes/nav.php'); ?>
            
            <!-- Page content -->
            <div class="container-fluid px-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mt-4">Sales</h1>
                    <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                        <i class="fas fa-shopping-cart"></i> Cart <span class="badge bg-light text-dark" id="cartCount">0</span>
                    </button>
                </div>

                <!-- Category Filter -->
                <div class="category-filter mb-4">
                    <button class="btn btn-outline-primary me-2 category-btn active" data-category="all">All</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="btn btn-outline-primary me-2 category-btn" data-category="<?php echo htmlspecialchars($category); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </button>
                    <?php endforeach; ?>
                            </div>

                <!-- Search Bar -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search products...">
                        </div>
            </div>
                    </div>
                    
                <!-- Products Grid -->
                <div class="row g-4" id="productsGrid">
                    <?php foreach ($products as $product): ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 product-item" data-category="<?php echo htmlspecialchars($product['category']); ?>">
                            <div class="card product-card">
                                <span class="badge bg-primary stock-badge"><?php echo htmlspecialchars($product['stock']); ?> left</span>
                                <img src="<?php echo htmlspecialchars($product['image'] ? '../uploads/' . $product['image'] : '../img/default-product.jpg'); ?>" 
                                     class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($product['description']); ?></p>
                                    <div class="product-price mb-2">₱<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="mt-auto">
                                        <div class="input-group mb-2">
                                            <button class="btn btn-outline-secondary" onclick="updateQuantity(<?php echo $product['product_ID']; ?>, -1)">-</button>
                                            <input type="number" class="form-control quantity-input" id="quantity-<?php echo $product['product_ID']; ?>" 
                                                   value="1" min="1" max="<?php echo $product['stock']; ?>">
                                            <button class="btn btn-outline-secondary" onclick="updateQuantity(<?php echo $product['product_ID']; ?>, 1)">+</button>
                                        </div>
                                        <button class="btn btn-primary w-100" onclick="addToCart(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="fas fa-shopping-cart"></i> Cafe Cart</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div id="cartItems">
                <!-- Cart items will be dynamically added here -->
            </div>
            <div class="cart-total mt-3 text-end">
                Total: ₱<span id="cartTotal">0.00</span>
            </div>
            <button class="btn btn-primary w-100 mt-3" onclick="proceedToCheckout()" id="checkoutBtn" disabled>
                Proceed to Checkout
            </button>
            </div>
        </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                        <div class="mb-3">
                        <label class="form-label">Subtotal Amount</label>
                        <div class="form-control">₱<span id="modalSubtotal">0.00</span></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Discount Type</label>
                        <select class="form-select" id="discountType">
                            <option value="none">No Discount</option>
                            <option value="senior">Senior Citizen (20%)</option>
                            <option value="pwd">PWD (20%)</option>
                            </select>
                        </div>
                    <div class="mb-3" id="idNumberField" style="display: none;">
                        <label class="form-label">ID Number</label>
                        <input type="text" class="form-control" id="discountIdNumber" placeholder="Enter ID number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VAT (12%)</label>
                        <div class="form-control">₱<span id="vatAmount">0.00</span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount Amount (20%)</label>
                        <div class="form-control">₱<span id="discountAmount">0.00</span></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Amount</label>
                        <div class="form-control">₱<span id="modalTotal">0.00</span></div>
                        </div>
                        <div class="mb-3">
                        <label class="form-label">Amount Received</label>
                        <input type="number" class="form-control" id="amountReceived" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Change</label>
                        <div class="form-control">₱<span id="changeAmount">0.00</span></div>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processPayment()">Complete Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent">
                        <!-- Receipt content will be dynamically added here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">Print Receipt</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let cart = [];
        const formatter = new Intl.NumberFormat('en-PH', {
            style: 'decimal',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Category filter
        document.querySelectorAll('.category-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                const category = button.dataset.category;
                
                document.querySelectorAll('.product-item').forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.product-item').forEach(item => {
                const productName = item.querySelector('.card-title').textContent.toLowerCase();
                if (productName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        function updateQuantity(productId, change) {
            const input = document.getElementById(`quantity-${productId}`);
            const maxStock = parseInt(input.max);
            let newValue = parseInt(input.value) + change;
            
            // Ensure the new value is within stock limits
            newValue = Math.max(1, Math.min(newValue, maxStock));
            input.value = newValue;
            
            // Show warning if trying to exceed stock
            if (newValue === maxStock) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Maximum Stock Reached',
                    text: `Only ${maxStock} items available in stock`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }

        function addToCart(product) {
            const quantityInput = document.getElementById(`quantity-${product.product_ID}`);
            const quantity = parseInt(quantityInput.value);
            const maxStock = parseInt(quantityInput.max);
            
            // Check if adding this quantity would exceed stock
            const existingItem = cart.find(item => item.product_ID === product.product_ID);
            const currentInCart = existingItem ? existingItem.quantity : 0;
            const totalQuantity = currentInCart + quantity;
            
            if (totalQuantity > maxStock) {
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Stock',
                    text: `Only ${maxStock} items available. You already have ${currentInCart} in your cart.`,
                    confirmButtonColor: '#6c4f3d'
                });
                return;
            }
            
            if (existingItem) {
                existingItem.quantity = totalQuantity;
            } else {
                cart.push({...product, quantity});
            }
            
            // Reset quantity input to 1
            quantityInput.value = 1;
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Added to Cart',
                text: `${quantity}x ${product.name} added to cart`,
                timer: 1500,
                showConfirmButton: false
            });
            
            updateCartDisplay();
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartCount');
            const cartTotal = document.getElementById('cartTotal');
            const checkoutBtn = document.getElementById('checkoutBtn');
            
            cartItems.innerHTML = '';
            let total = 0;
        
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
            
                cartItems.innerHTML += `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                    <h6 class="mb-0">${item.name}</h6>
                                <small class="text-muted">₱${formatter.format(item.price)} × ${item.quantity}</small>
                            </div>
                            <div class="text-end">
                                <div>₱${formatter.format(itemTotal)}</div>
                                <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                                    <i class="fas fa-trash"></i>
                    </button>
                </div>
                    </div>
                </div>
            `;
            });
            
            cartCount.textContent = cart.length;
            cartTotal.textContent = formatter.format(total);
            checkoutBtn.disabled = cart.length === 0;
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function calculateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const discountType = document.getElementById('discountType').value;
            
            // Calculate VAT (12%)
            const vatRate = 0.12;
            let vatAmount = subtotal * vatRate;
            
            // Calculate discount if applicable (20%)
            const discountRate = 0.20;
            let discountAmount = 0;
            
            if (discountType !== 'none') {
                discountAmount = subtotal * discountRate;
                // For Senior/PWD, VAT is exempted
                vatAmount = 0;
            }
            
            const total = subtotal + vatAmount - discountAmount;
            
            // Update display
            document.getElementById('modalSubtotal').textContent = formatter.format(subtotal);
            document.getElementById('vatAmount').textContent = formatter.format(vatAmount);
            document.getElementById('discountAmount').textContent = formatter.format(discountAmount);
            document.getElementById('modalTotal').textContent = formatter.format(total);
            
            // Update change amount if amount received is entered
            const received = parseFloat(document.getElementById('amountReceived').value) || 0;
            document.getElementById('changeAmount').textContent = formatter.format(Math.max(0, received - total));
            
            return { subtotal, vatAmount, discountAmount, total };
        }

        // Show/hide ID number field based on discount type
        document.getElementById('discountType').addEventListener('change', function() {
            const idNumberField = document.getElementById('idNumberField');
            idNumberField.style.display = this.value !== 'none' ? 'block' : 'none';
            calculateTotals();
        });

        function proceedToCheckout() {
            document.getElementById('modalSubtotal').textContent = formatter.format(cart.reduce((sum, item) => sum + (item.price * item.quantity), 0));
            document.getElementById('discountType').value = 'none';
            document.getElementById('discountIdNumber').value = '';
            document.getElementById('amountReceived').value = '';
            document.getElementById('idNumberField').style.display = 'none';
            calculateTotals();
            
            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        }

        // Calculate change
        document.getElementById('amountReceived').addEventListener('input', function() {
            calculateTotals();
        });

        function validateIdNumber(type, idNumber) {
            // Remove any whitespace
            idNumber = idNumber.trim();
            
            if (type === 'senior') {
                // Senior Citizen ID patterns
                const scPatterns = [
                    /^SC-\d{7}$/,                  // SC-1234567
                    /^\d{10}$/,                    // 1234567890
                    /^SC-[A-Z]{3}-\d{6}$/,        // SC-BAT-202301
                    /^\d{4}-\d{7}$/               // 2022-0456789
                ];
                return scPatterns.some(pattern => pattern.test(idNumber));
            } else if (type === 'pwd') {
                // PWD ID patterns
                const pwdPatterns = [
                    /^PWD-\d{7}$/,                // PWD-4567891
                    /^PWD-[A-Z]{3}-\d{7}$/,       // PWD-BAT-0098321
                    /^DSWD-PWD-\d{7}$/,           // DSWD-PWD-0123456
                    /^PWD\d{4}-\d{5}$/            // PWD2023-00921
                ];
                return pwdPatterns.some(pattern => pattern.test(idNumber));
            }
            return false;
        }

        function processPayment() {
            const { subtotal, vatAmount, discountAmount, total } = calculateTotals();
            const received = parseFloat(document.getElementById('amountReceived').value) || 0;
            const discountType = document.getElementById('discountType').value;
            const discountIdNumber = document.getElementById('discountIdNumber').value;
            
            if (received < total) {
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Amount',
                    text: 'Please enter an amount equal to or greater than the total.',
                    confirmButtonColor: '#6c4f3d'
                });
                return;
            }
            
            if (discountType !== 'none') {
                if (!discountIdNumber) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ID Number Required',
                        text: 'Please enter a valid ID number for the discount.',
                        confirmButtonColor: '#6c4f3d'
                    });
                    return;
                }

                if (!validateIdNumber(discountType, discountIdNumber)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid ID Format',
                        html: discountType === 'senior' ? 
                            'Senior Citizen ID must be in one of these formats:<br>' +
                            '- SC-1234567<br>' +
                            '- 1234567890<br>' +
                            '- SC-BAT-202301<br>' +
                            '- 2022-0456789' :
                            'PWD ID must be in one of these formats:<br>' +
                            '- PWD-4567891<br>' +
                            '- PWD-BAT-0098321<br>' +
                            '- DSWD-PWD-0123456<br>' +
                            '- PWD2023-00921',
                        confirmButtonColor: '#6c4f3d'
                    });
                    return;
                }
            }
            
            // Process inventory update and transaction logging
            fetch('process-transaction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    products: cart,
                    total: total,
                    discount_type: discountType,
                    discount_id: discountIdNumber,
                    vat_amount: vatAmount,
                    discount_amount: discountAmount
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Generate and show receipt
                    generateReceipt(data.transaction_id);
                    
                    // Show success message
    Swal.fire({
                        icon: 'success',
                        title: 'Transaction Complete',
                        text: 'Stock has been updated successfully!',
                        confirmButtonColor: '#6c4f3d'
                    });
                    
                    // Clear cart and close payment modal
                    cart = [];
                    updateCartDisplay();
                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                } else {
                    throw new Error(data.message || 'Failed to process transaction');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Transaction Failed',
                    text: error.message || 'Failed to update inventory. Please try again.',
                    confirmButtonColor: '#6c4f3d'
                });
        });
        }

        function generateReceipt(transactionId) {
            const { subtotal, vatAmount, discountAmount, total } = calculateTotals();
            const received = parseFloat(document.getElementById('amountReceived').value) || 0;
        const discountType = document.getElementById('discountType').value;
            const discountIdNumber = document.getElementById('discountIdNumber').value;
            
            const receiptContent = document.getElementById('receiptContent');
            const date = new Date().toLocaleString();
            
            receiptContent.innerHTML = `
                <div class="text-center mb-4">
                    <h4>Café POS</h4>
                    <p class="mb-0">Date: ${date}</p>
                    <p>Transaction #: ${transactionId}</p>
                </div>
                <div class="mb-4">
                    ${cart.map(item => `
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                ${item.name} × ${item.quantity}
                            </div>
                            <div>₱${formatter.format(item.price * item.quantity)}</div>
                        </div>
                    `).join('')}
                    <hr>
                    <div class="d-flex justify-content-between">
                        <div>Subtotal</div>
                        <div>₱${formatter.format(subtotal)}</div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>VAT (12%)</div>
                        <div>₱${formatter.format(vatAmount)}</div>
                    </div>
                    ${discountType !== 'none' ? `
                        <div class="d-flex justify-content-between">
                            <div>${discountType === 'senior' ? 'Senior Citizen' : 'PWD'} Discount (20%)</div>
                            <div>₱${formatter.format(discountAmount)}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>${discountType === 'senior' ? 'Senior Citizen' : 'PWD'} ID</div>
                            <div>${discountIdNumber}</div>
                        </div>
                    ` : ''}
                    <div class="d-flex justify-content-between fw-bold">
                        <div>Total</div>
                        <div>₱${formatter.format(total)}</div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>Amount Received</div>
                        <div>₱${formatter.format(received)}</div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>Change</div>
                        <div>₱${formatter.format(received - total)}</div>
                    </div>
                </div>
                <div class="text-center">
                    <p>Thank you for your purchase!</p>
                    ${discountType !== 'none' ? `
                        <small class="text-muted">This serves as your official receipt for<br>${discountType === 'senior' ? 'Senior Citizen' : 'PWD'} discount purposes.</small>
                    ` : ''}
                </div>
            `;
            
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();
        }

        function printReceipt() {
            const receiptWindow = window.open('', '', 'width=600,height=600');
            receiptWindow.document.write('<html><head><title>Receipt</title>');
            receiptWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
            receiptWindow.document.write('</head><body class="p-4">');
            receiptWindow.document.write(document.getElementById('receiptContent').innerHTML);
            receiptWindow.document.write('</body></html>');
            receiptWindow.document.close();
            
            receiptWindow.onload = function() {
                receiptWindow.print();
                receiptWindow.close();
            };
        }

        // Add input validation and formatting for the discount ID field
        document.getElementById('discountIdNumber').addEventListener('input', function(e) {
            const discountType = document.getElementById('discountType').value;
            let value = e.target.value.toUpperCase();
            
            // Remove any invalid characters
            if (discountType === 'senior') {
                value = value.replace(/[^0-9A-Z-]/g, '');
            } else if (discountType === 'pwd') {
                value = value.replace(/[^0-9A-Z-]/g, '');
            }
            
            e.target.value = value;
            
            // Real-time validation feedback
            if (value && !validateIdNumber(discountType, value)) {
                e.target.classList.add('is-invalid');
                if (!e.target.nextElementSibling) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = discountType === 'senior' ? 
                        'Invalid Senior Citizen ID format' : 
                        'Invalid PWD ID format';
                    e.target.parentNode.appendChild(feedback);
                }
            } else {
                e.target.classList.remove('is-invalid');
                const feedback = e.target.nextElementSibling;
                if (feedback && feedback.className === 'invalid-feedback') {
                    feedback.remove();
                }
            }
        });

        // Update ID field placeholder based on discount type
        document.getElementById('discountType').addEventListener('change', function() {
            const idNumberField = document.getElementById('idNumberField');
            const discountIdInput = document.getElementById('discountIdNumber');
            
            if (this.value === 'none') {
                idNumberField.style.display = 'none';
                discountIdInput.value = '';
            } else {
                idNumberField.style.display = 'block';
                if (this.value === 'senior') {
                    discountIdInput.placeholder = 'e.g., SC-1234567 or 2022-0456789';
                } else {
                    discountIdInput.placeholder = 'e.g., PWD-4567891 or PWD2023-00921';
                }
                discountIdInput.classList.remove('is-invalid');
                const feedback = discountIdInput.nextElementSibling;
                if (feedback && feedback.className === 'invalid-feedback') {
                    feedback.remove();
                }
            }
            calculateTotals();
        });
    </script>
</body>
</html>