<?php
ob_start(); // Start output buffering to prevent stray HTML
session_start();
$page_title = "Point of Sale";

// Suppress error display for POST requests to avoid HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'config/stripe.php';
require_once 'vendor/autoload.php';

$db = (new \InventorySystem\Database())->getConnection();

// Initialize Stripe and validate API key
try {
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    \Stripe\Balance::retrieve(); // Test API key
    error_log("Stripe API key validated successfully");
} catch (\Stripe\Exception\AuthenticationException $e) {
    error_log("Stripe API key error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid Stripe API key']);
    exit();
} catch (Exception $e) {
    error_log("Stripe initialization error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Stripe initialization failed']);
    exit();
}

// Check user permissions
if (!isset($_SESSION['user_id']) || !in_array('use_pos', $_SESSION['permissions'] ?? [])) {
    header('Location: pos_login.php');
    exit();
}

// Fetch customers
$customers_query = "SELECT * FROM customers";
$customers = $db->query($customers_query);
if (!$customers) {
    error_log("Customer query error: " . $db->errorInfo()[2]);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to fetch customers']);
    exit();
}

// Fetch products with error handling
try {
    $products_query = "SELECT id AS product_id, name, unit_price AS price, stock AS stock_quantity FROM products";
    $stmt = $db->prepare($products_query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($products)) {
        error_log("No products found in database");
    }
} catch (PDOException $e) {
    error_log("Product query error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to fetch products']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Ensure JSON response
    ob_clean(); // Clear any stray output

    if ($_POST['action'] == 'complete_sale') {
        $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
        $cart_items = json_decode($_POST['cart_items'], true);
        $discount = (float)$_POST['discount'];
        $order_tax = (float)$_POST['order_tax'];
        $shipping = (float)$_POST['shipping'];
        $total = (float)$_POST['total'];
        $payment_method = $_POST['payment_method'] ?? 'Cash';

        if (empty($cart_items)) {
            echo json_encode(['success' => false, 'error' => 'Cart is empty']);
            exit();
        }

        try {
            $db->beginTransaction();

            // Insert sale
            $stmt = $db->prepare("INSERT INTO sales (customer_id, total_price, sale_date, payment_method) VALUES (?, ?, NOW(), ?)");
            $stmt->execute([$customer_id, $total, $payment_method]);
            $sale_id = $db->lastInsertId();

            // Insert sale items and update stock
            $stmt_item = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt_stock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt_check = $db->prepare("SELECT stock FROM products WHERE id = ?");

            foreach ($cart_items as $item) {
                $product_id = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $price = (float)$item['price'];

                // Check stock
                $stmt_check->execute([$product_id]);
                $row = $stmt_check->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['stock'] < $quantity) {
                    throw new Exception("Insufficient stock for product ID $product_id");
                }

                // Insert sale item
                $stmt_item->execute([$sale_id, $product_id, $quantity, $price]);

                // Update stock
                $stmt_stock->execute([$quantity, $product_id]);
            }

            $db->commit();
            echo json_encode(['success' => true, 'sale_id' => $sale_id]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Sale error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['action'] == 'process_card_payment') {
        $inTransaction = false; // Track transaction state
        try {
            // Validate POST data
            if (!isset($_POST['payment_method_id']) || empty($_POST['payment_method_id'])) {
                throw new Exception('Missing payment method ID');
            }
            if (!isset($_POST['total']) || !is_numeric($_POST['total'])) {
                throw new Exception('Invalid total amount');
            }
            if (!isset($_POST['cart_items']) || empty(json_decode($_POST['cart_items'], true))) {
                throw new Exception('Cart is empty');
            }
            if (!isset($_POST['card_type']) || empty($_POST['card_type'])) {
                throw new Exception('Missing card type');
            }

            $payment_method_id = $_POST['payment_method_id'];
            $amount = (int)((float)$_POST['total'] * 100); // Convert to cents
            $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
            $cart_items = json_decode($_POST['cart_items'], true);
            $discount = (float)$_POST['discount'];
            $order_tax = (float)$_POST['order_tax'];
            $shipping = (float)$_POST['shipping'];
            $total = (float)$_POST['total'];
            $card_type = $_POST['card_type'];

            // Create Payment Intent
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'usd',
                'payment_method' => $payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'description' => 'MediPeak Pharmacy Sale',
            ]);

            if ($payment_intent->status === 'succeeded') {
                $db->beginTransaction();
                $inTransaction = true;

                // Insert sale
                $stmt = $db->prepare("INSERT INTO sales (customer_id, total_price, sale_date, payment_method) VALUES (?, ?, NOW(), ?)");
                $stmt->execute([$customer_id, $total, $card_type]);
                $sale_id = $db->lastInsertId();

                // Insert sale items and update stock
                $stmt_item = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt_stock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt_check = $db->prepare("SELECT stock FROM products WHERE id = ?");

                foreach ($cart_items as $item) {
                    $product_id = (int)$item['product_id'];
                    $quantity = (int)$item['quantity'];
                    $price = (float)$item['price'];

                    // Check stock
                    $stmt_check->execute([$product_id]);
                    $row = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    if (!$row || $row['stock'] < $quantity) {
                        throw new Exception("Insufficient stock for product ID $product_id");
                    }

                    // Insert sale item
                    $stmt_item->execute([$sale_id, $product_id, $quantity, $price]);

                    // Update stock
                    $stmt_stock->execute([$quantity, $product_id]);
                }

                $db->commit();
                $inTransaction = false;
                echo json_encode(['success' => true, 'sale_id' => $sale_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Payment failed']);
            }
        } catch (Exception $e) {
            if ($inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Payment error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

require 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale</title>
    <!-- Note: Stripe.js is loaded over HTTPS. For production, ensure the entire site uses HTTPS. -->
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #e0f2fe;
            margin: 0;
            min-height: 100vh;
            display: flex;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 256px;
        }
        .top-bar {
            background: #ffffff;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .location-select select {
            padding: 8px;
            border: 2px solid #93c5fd;
            border-radius: 8px;
            background: #f8fafc;
            color: #1e40af;
            font-weight: 500;
        }
        .datetime {
            color: #1e40af;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .actions svg {
            fill: #3b82f6;
            width: 22px;
            height: 22px;
            cursor: pointer;
            margin-left: 12px;
            transition: fill 0.2s;
        }
        .actions svg:hover {
            fill: #1e3a8a;
        }
        .content {
            display: flex;
            gap: 20px;
        }
        .left-panel {
            flex: 1;
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .customer-section {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .customer-section select, .customer-section input {
            flex: 1;
            padding: 10px;
            border: 2px solid #93c5fd;
            border-radius: 8px;
            background: #f8fafc;
            color: #1e40af;
            font-size: 0.9rem;
        }
        .customer-section button {
            padding: 10px;
            background: #3b82f6;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .customer-section button svg {
            fill: #ffffff;
            width: 18px;
            height: 18px;
        }
        .customer-section button:hover {
            background: #1e3a8a;
        }
        .search-bar {
            width: 100%;
            padding: 10px;
            border: 2px solid #93c5fd;
            border-radius: 8px;
            background: #f8fafc;
            margin-bottom: 20px;
            color: #1e40af;
            font-size: 0.9rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            color: #1e40af;
        }
        th {
            background: #dbeafe;
            font-weight: 600;
        }
        .totals {
            background: #f1f5f9;
            padding: 16px;
            border-radius: 12px;
        }
        .totals div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            color: #1e40af;
            font-size: 0.9rem;
        }
        .totals input {
            width: 100px;
            padding: 8px;
            border: 2px solid #93c5fd;
            border-radius: 8px;
            background: #f8fafc;
            color: #1e40af;
        }
        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }
        .buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            color: #ffffff;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        .buttons button:hover {
            filter: brightness(90%);
        }
        .right-panel {
            width: 300px;
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .right-panel button {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            margin-bottom: 16px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .right-panel button:hover {
            background: #1e3a8a;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .product-item {
            background: #f1f5f9;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            color: #1e40af;
            transition: background 0.2s;
        }
        .product-item:hover {
            background: #dbeafe;
        }
        .product-item svg {
            fill: #3b82f6;
            width: 28px;
            height: 28px;
            margin-bottom: 8px;
        }
        .recent-transactions {
            position: fixed;
            bottom: 16px;
            right: 16px;
            padding: 12px 24px;
            background: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 24px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .recent-transactions:hover {
            background: #1e3a8a;
        }
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
        }
        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #ffffff;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            width: 380px;
            max-width: 90%;
            z-index: 1001;
        }
        .popup h2 {
            color: #1e40af;
            margin: 0 0 16px;
            font-size: 1.3rem;
            font-weight: 600;
        }
        .popup label {
            display: block;
            margin-bottom: 8px;
            color: #1e40af;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .popup #card-element {
            padding: 12px;
            border: 2px solid #93c5fd;
            border-radius: 8px;
            background: #f8fafc;
            margin-bottom: 16px;
        }
        .popup .error {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: -12px;
            margin-bottom: 12px;
            display: none;
        }
        .popup-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .popup-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        .popup-buttons .save {
            background: #3b82f6;
            color: #ffffff;
        }
        .popup-buttons .save:hover {
            background: #1e3a8a;
        }
        .popup-buttons .cancel {
            background: #d1d5db;
            color: #1e40af;
        }
        .popup-buttons .cancel:hover {
            background: #9ca3af;
        }
        .input-error {
            border-color: #ef4444 !important;
        }
        .no-products {
            text-align: center;
            color: #ef4444;
            font-size: 0.9rem;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="top-bar">
            <div class="location-select">
                <span class="font-semibold text-blue-900">Location: </span>
                <select>
                    <option>STORE 1</option>
                </select>
            </div>
            <div class="datetime"><?php echo date('m/d/Y H:i'); ?></div>
            <div class="actions">
                <svg title="Back" onclick="goBack()" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                <svg title="Close" onclick="closePOS()" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                <svg title="Settings" onclick="openSettings()" viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5-3.5-1.57-3.5-3.5-3.5z"/></svg>
                <svg title="Calculator" onclick="openCalculator()" viewBox="0 0 24 24"><path d="M7 2h10c1.1 0 2 .9 2 2v16c0 1.1-.9 2-2 2H7c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2zm0 2v3h10V4H7zm0 5v2h2V9H7zm4 0v2h2V9h-2zm4 0v2h2V9h-2zm-8 4v2h2v-2H7zm4 0v2h2v-2h-2zm4 0v2h2v-2h-2zm-8 4v2h2v-2H7zm4 0v2h2v-2h-2zm4 0v2h2v-2h-2z"/></svg>
                <svg title="Other" onclick="otherActions()" viewBox="0 0 24 24"><path d="M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
            </div>
        </div>
        <div class="content">
            <div class="left-panel">
                <div class="customer-section">
                    <select id="customer_id">
                        <option value="0">Walk-in Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['customer_id']; ?>">
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="alert('Add New Customer')">
                        <svg viewBox="0 0 24 24"><path d="M12 4c-4.41 0-8 3.59-8 8s3.59 8 8 8 8-3.59 8-8-3.59-8-8-8zm5 9h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                    </button>
                </div>
                <input type="text" id="search_bar" class="search-bar" placeholder="Search Product / SKU / Scan Barcode">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cart_items"></tbody>
                </table>
                <div class="totals">
                    <div>
                        <span>Items: <span id="total_items">0</span></span>
                        <span>Total: <span id="total_amount">0</span></span>
                    </div>
                    <div>
                        <span>Discount (+):</span>
                        <input type="number" id="discount" value="0" step="0.01">
                    </div>
                    <div>
                        <span>Order Tax (+):</span>
                        <input type="number" id="order_tax" value="0" step="0.01">
                    </div>
                    <div>
                        <span>Shipping (+):</span>
                        <input type="number" id="shipping" value="0" step="0.01">
                    </div>
                </div>
                <div class="buttons">
                    <button style="background: #6b7280;" onclick="alert('Draft')">Draft</button>
                    <button style="background: #22d3ee;" onclick="alert('Quotation')">Quotation</button>
                    <button style="background: #fb923c;" onclick="alert('Suspend')">Suspend</button>
                    <button style="background: #4ade80;" onclick="alert('Credit Sale')">Credit Sale</button>
                    <button style="background: #3b82f6;" onclick="openCardPopup()">Card</button>
                    <button style="background: #a78bfa;" onclick="alert('Multiple Pay')">Multiple Pay</button>
                    <button style="background: #4ade80;" onclick="completeSale('Cash')">Cash</button>
                    <button style="background: #ef4444;" onclick="cancelSale()">Cancel</button>
                    <div style="width: 100%; text-align: right; color: #1e40af; font-weight: 600;">
                        Payable: <span id="total_payable">0</span>
                    </div>
                </div>
            </div>
            <div class="right-panel">
                <button>Brands</button>
                <div class="product-grid" id="product_grid">
                    <?php if (empty($products)): ?>
                        <div class="no-products">No products available. Please add products to the database.</div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-item" 
                                 data-name="<?php echo htmlspecialchars($product['name']); ?>" 
                                 onclick="addToCart(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)">
                                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                <p style="font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($product['name']); ?><br>
                                    ($<?php echo number_format($product['price'], 2); ?>)
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="recent-transactions">Recent Transactions</div>
    </div>

    <!-- Card Payment Popup -->
    <div class="popup-overlay" id="cardPopupOverlay">
        <div class="popup">
            <h2>Card Payment</h2>
            <form id="cardForm">
                <label for="card-element">Card Information</label>
                <div id="card-element"></div>
                <div class="error" id="card_error">Please enter valid card details.</div>
                <div class="popup-buttons">
                    <button type="button" class="cancel" onclick="closeCardPopup()">Cancel</button>
                    <button type="submit" class="save">Pay</button>
                </div>
            </form>
        </div>
    </div>

    <iframe id="printFrame" name="printFrame" class="hidden" style="display: none;"></iframe>

    <script>
        // Initialize Stripe.js
        const stripe = Stripe('<?php echo $stripe_publishable_key; ?>');
        const elements = stripe.elements();
        const card = elements.create('card', {
            style: {
                base: {
                    color: '#1e40af',
                    fontFamily: '"Segoe UI", Arial, sans-serif',
                    fontSize: '16px',
                    '::placeholder': { color: '#6b7280' },
                },
                invalid: { color: '#ef4444' },
            }
        });
        card.mount('#card-element');

        let cart = [];
        const allProducts = Array.from(document.querySelectorAll('.product-item')) || [];

        function goBack() {
            window.history.back();
        }

        function closePOS() {
            if (confirm('Are you sure you want to close the POS?')) {
                window.location.href = 'pos_login.php';
            }
        }

        function openSettings() {
            alert('Settings page not implemented yet.');
        }

        function openCalculator() {
            alert('Please use your system\'s calculator.');
        }

        function otherActions() {
            alert('Other actions menu not implemented yet.');
        }

        function debounce(func, delay) {
            let timeoutId;
            return function (...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        function addToCart(productId, name, price) {
            console.log('addToCart called:', { productId, name, price });
            if (!productId || !name || isNaN(price) || price <= 0) {
                console.error('Invalid product data:', { productId, name, price });
                alert('Error: Invalid product data. Please check the product details.');
                return;
            }
            const existingItem = cart.find(item => item.product_id === productId);
            if (existingItem) {
                existingItem.quantity += 1;
                console.log('Incremented quantity for:', name, 'New quantity:', existingItem.quantity);
            } else {
                cart.push({ product_id: productId, name: name, price: price, quantity: 1 });
                console.log('Added new item to cart:', name);
            }
            updateCart();
        }

        function removeFromCart(productId) {
            console.log('removeFromCart called:', productId);
            cart = cart.filter(item => item.product_id !== productId);
            updateCart();
        }

        function cancelSale() {
            console.log('cancelSale called');
            cart = [];
            document.getElementById('discount').value = 0;
            document.getElementById('order_tax').value = 0;
            document.getElementById('shipping').value = 0;
            updateCart();
        }

        function validateInput(input) {
            const value = parseFloat(input.value);
            if (isNaN(value) || value < 0) {
                input.classList.add('input-error');
                return false;
            }
            input.classList.remove('input-error');
            return true;
        }

        function updateCart() {
            console.log('updateCart called, cart:', cart);
            const cartItems = document.getElementById('cart_items');
            let totalItems = 0;
            let totalAmount = 0;

            cartItems.innerHTML = '';
            cart.forEach(item => {
                const subtotal = item.price * item.quantity;
                totalItems += item.quantity;
                totalAmount += subtotal;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>$${subtotal.toFixed(2)}</td>
                    <td><span style="color: #ef4444; cursor: pointer;" onclick="removeFromCart(${item.product_id})">✖</span></td>
                `;
                cartItems.appendChild(row);
            });

            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const orderTax = parseFloat(document.getElementById('order_tax').value) || 0;
            const shipping = parseFloat(document.getElementById('shipping').value) || 0;

            const discountValid = validateInput(document.getElementById('discount'));
            const orderTaxValid = validateInput(document.getElementById('order_tax'));
            const shippingValid = validateInput(document.getElementById('shipping'));

            if (discountValid && orderTaxValid && shippingValid) {
                totalAmount = totalAmount - discount + orderTax + shipping;
            } else {
                totalAmount = 0;
            }

            document.getElementById('total_items').textContent = totalItems;
            document.getElementById('total_amount').textContent = totalAmount.toFixed(2);
            document.getElementById('total_payable').textContent = totalAmount.toFixed(2);
            console.log('Cart updated: Total items:', totalItems, 'Total amount:', totalAmount);
        }

        function completeSale(paymentMethod = 'Cash') {
            console.log('completeSale called:', paymentMethod);
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }

            const discountInput = document.getElementById('discount');
            const orderTaxInput = document.getElementById('order_tax');
            const shippingInput = document.getElementById('shipping');

            if (!validateInput(discountInput) || !validateInput(orderTaxInput) || !validateInput(shippingInput)) {
                alert('Please correct the invalid inputs.');
                return;
            }

            let customerId = document.getElementById('customer_id').value;
            if (customerId === '0') {
                customerId = '';
            }

            const discount = parseFloat(discountInput.value) || 0;
            const orderTax = parseFloat(orderTaxInput.value) || 0;
            const shipping = parseFloat(shippingInput.value) || 0;
            const total = parseFloat(document.getElementById('total_payable').textContent);

            const formData = new FormData();
            formData.append('action', 'complete_sale');
            formData.append('customer_id', customerId);
            formData.append('cart_items', JSON.stringify(cart));
            formData.append('discount', discount);
            formData.append('order_tax', orderTax);
            formData.append('shipping', shipping);
            formData.append('total', total);
            formData.append('payment_method', paymentMethod);

            fetch('pos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('completeSale response:', data);
                if (data.success) {
                    printReceipt(data.sale_id, paymentMethod);
                    cart = [];
                    updateCart();
                    alert('Sale completed successfully!');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('completeSale error:', error);
                alert('Error: ' + error.message);
            });
        }

        function openCardPopup() {
            console.log('openCardPopup called');
            document.getElementById('cardPopupOverlay').style.display = 'block';
            document.getElementById('card_error').style.display = 'none';
        }

        function closeCardPopup() {
            console.log('closeCardPopup called');
            document.getElementById('cardPopupOverlay').style.display = 'none';
        }

        document.getElementById('cardForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('cardForm submitted');

            const discountInput = document.getElementById('discount');
            const orderTaxInput = document.getElementById('order_tax');
            const shippingInput = document.getElementById('shipping');

            if (!validateInput(discountInput) || !validateInput(orderTaxInput) || !validateInput(shippingInput)) {
                alert('Please correct the invalid inputs.');
                closeCardPopup();
                return;
            }

            let customerId = document.getElementById('customer_id').value;
            if (customerId === '0') {
                customerId = '';
            }

            const discount = parseFloat(discountInput.value) || 0;
            const orderTax = parseFloat(orderTaxInput.value) || 0;
            const shipping = parseFloat(shippingInput.value) || 0;
            const total = parseFloat(document.getElementById('total_payable').textContent);

            const { paymentMethod, error } = await stripe.createPaymentMethod({
                type: 'card',
                card: card,
            });

            if (error) {
                console.error('Stripe createPaymentMethod error:', error);
                document.getElementById('card_error').textContent = error.message;
                document.getElementById('card_error').style.display = 'block';
                return;
            }

            const cardType = paymentMethod.card.brand.charAt(0).toUpperCase() + paymentMethod.card.brand.slice(1);
            console.log('Payment method created:', cardType);

            const formData = new FormData();
            formData.append('action', 'process_card_payment');
            formData.append('payment_method_id', paymentMethod.id);
            formData.append('customer_id', customerId);
            formData.append('cart_items', JSON.stringify(cart));
            formData.append('discount', discount);
            formData.append('order_tax', orderTax);
            formData.append('shipping', shipping);
            formData.append('total', total);
            formData.append('card_type', cardType);

            fetch('pos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Log raw response for debugging
                return response.text().then(text => ({ text, response }));
            })
            .then(({ text, response }) => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed response:', data);
                    if (data.success) {
                        printReceipt(data.sale_id, cardType);
                        cart = [];
                        updateCart();
                        closeCardPopup();
                        alert('Payment successful!');
                    } else {
                        document.getElementById('card_error').textContent = data.error;
                        document.getElementById('card_error').style.display = 'block';
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, 'Response:', text);
                    document.getElementById('card_error').textContent = 'Server error: ' + e.message;
                    document.getElementById('card_error').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('card_error').textContent = error.message;
                document.getElementById('card_error').style.display = 'block';
            });
        });

        document.getElementById('cardPopupOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCardPopup();
            }
        });

        function printReceipt(saleId, paymentMethod) {
            console.log('printReceipt called:', saleId, paymentMethod);
            const customerSelect = document.getElementById('customer_id');
            const printFrame = document.getElementById('printFrame');
            const printDoc = printFrame.contentDocument || printFrame.contentWindow.document;

            let receiptContent = `
                <html>
                <head>
                    <title>MediPeak Pharmacy Receipt</title>
                    <style>
                        body {
                            font-family: 'Segoe UI', Arial, sans-serif;
                            margin: 20px;
                            font-size: 12px;
                            color: #1e40af;
                            max-width: 300px;
                            line-height: 1.5;
                        }
                        .container {
                            border: 2px solid #3b82f6;
                            border-radius: 12px;
                            padding: 16px;
                            background: #f8fafc;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 16px;
                        }
                        .header h1 {
                            font-size: 20px;
                            color: #1e40af;
                            margin: 0;
                        }
                        .header .logo {
                            font-size: 14px;
                            color: #6b7280;
                            margin: 4px 0;
                        }
                        .header .contact {
                            font-size: 10px;
                            color: #6b7280;
                        }
                        .divider {
                            border-top: 2px dashed #3b82f6;
                            margin: 12px 0;
                        }
                        .sale-info p {
                            margin: 4px 0;
                            font-size: 11px;
                        }
                        .sale-info .label {
                            font-weight: 600;
                            color: #1e40af;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 12px 0;
                        }
                        th, td {
                            padding: 8px 4px;
                            text-align: left;
                            border-bottom: 1px solid #e5e7eb;
                        }
                        th {
                            background: #3b82f6;
                            color: #ffffff;
                            font-size: 11px;
                            font-weight: 600;
                        }
                        tr:nth-child(even) {
                            background: #f1f5f9;
                        }
                        .totals {
                            margin-top: 12px;
                            padding: 12px;
                            background: #dbeafe;
                            border-radius: 8px;
                        }
                        .totals p {
                            margin: 4px 0;
                            font-size: 11px;
                        }
                        .totals .label {
                            font-weight: 600;
                            color: #1e40af;
                        }
                        .totals .total {
                            font-size: 14px;
                            font-weight: 600;
                            color: #ef4444;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 16px;
                            font-size: 10px;
                            color: #6b7280;
                        }
                        .footer .thank-you {
                            font-style: italic;
                            color: #3b82f6;
                        }
                        @media print {
                            body { margin: 0; max-width: none; }
                            .container { border: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>MediPeak Pharmacy</h1>
                            <div class="logo">Quality Care, Trusted Service</div>
                            <div class="contact">
                                123 Health St, Wellness City<br>
                                Phone: (555) 123-4567<br>
                                Email: info@medipeakpharmacy.com
                            </div>
                        </div>
                        <div class="divider"></div>
                        <div class="sale-info">
                            <p><span class="label">Sale ID:</span> ${saleId}</p>
                            <p><span class="label">Date:</span> <?php echo date('m/d/Y H:i'); ?></p>
                            <p><span class="label">Location:</span> STORE 1</p>
                            <p><span class="label">Customer:</span> ${customerSelect.options[customerSelect.selectedIndex].text}</p>
                            <p><span class="label">Payment Method:</span> ${paymentMethod}</p>
                        </div>
                        <div class="divider"></div>
                        <table>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                            </tr>
            `;

            cart.forEach(item => {
                const subtotal = item.price * item.quantity;
                receiptContent += `
                    <tr>
                        <td>${item.name}</td>
                        <td>${item.quantity}</td>
                        <td>$${subtotal.toFixed(2)}</td>
                    </tr>
                `;
            });

            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const orderTax = parseFloat(document.getElementById('order_tax').value) || 0;
            const shipping = parseFloat(document.getElementById('shipping').value) || 0;
            const total = parseFloat(document.getElementById('total_payable').textContent);

            receiptContent += `
                        </table>
                        <div class="totals">
                            <p><span class="label">Discount:</span> $${discount.toFixed(2)}</p>
                            <p><span class="label">Order Tax:</span> $${orderTax.toFixed(2)}</p>
                            <p><span class="label">Shipping:</span> $${shipping.toFixed(2)}</p>
                            <p class="total"><span class="label">Total:</span> $${total.toFixed(2)}</p>
                        </div>
                        <div class="divider"></div>
                        <div class="footer">
                            <p class="thank-you">Thank You for Choosing MediPeak Pharmacy!</p>
                            <p>Return Policy: Returns accepted within 7 days with receipt.</p>
                            <p>Visit us at www.medipeakpharmacy.com</p>
                        </div>
                    </div>
                </body>
                </html>
            `;

            printDoc.open();
            printDoc.write(receiptContent);
            printDoc.close();
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        }

        const searchBar = document.getElementById('search_bar');
        searchBar.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            allProducts.forEach(product => {
                const productName = product.getAttribute('data-name')?.toLowerCase() || '';
                product.style.display = productName.includes(searchTerm) ? 'block' : 'none';
            });
        });

        const debouncedUpdateCart = debounce(updateCart, 300);
        document.getElementById('discount').addEventListener('input', debouncedUpdateCart);
        document.getElementById('order_tax').addEventListener('input', debouncedUpdateCart);
        document.getElementById('shipping').addEventListener('input', debouncedUpdateCart);

        // Debug: Log when product items are loaded
        console.log('Product items loaded:', allProducts.length);
        allProducts.forEach((item, index) => {
            console.log(`Product ${index}:`, item.getAttribute('data-name'));
        });

        updateCart();
    </script>
</body>
</html>

<?php
ob_end_flush(); // Flush output buffer
require 'footer.php';
?>