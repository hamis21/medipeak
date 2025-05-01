<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$include_path = dirname(__DIR__, 2) . '/config/db.php';
if (!file_exists($include_path)) {
    die('Fatal Error: Database configuration file not found at ' . htmlspecialchars($include_path));
}
require_once $include_path;

$payment_message = '';
$subscription = null;

try {
    $db = (new \InventorySystem\Database())->getConnection();

    // Check current subscription status for display
    $stmt = $db->prepare("SELECT end_date, status FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Simulate payment processing
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));

        // Check if user already has an active subscription
        if ($subscription && $subscription['status'] === 'active' && $subscription['end_date'] >= date('Y-m-d')) {
            $payment_message = '<p class="error">You already have an active subscription until ' . htmlspecialchars($subscription['end_date']) . '.</p>';
        } else {
            // Insert or update subscription
            if ($subscription) {
                // Update existing subscription
                $stmt = $db->prepare("UPDATE subscriptions SET start_date = ?, end_date = ?, status = 'active' WHERE user_id = ?");
                $stmt->execute([$start_date, $end_date, $_SESSION['user_id']]);
            } else {
                // Insert new subscription
                $stmt = $db->prepare("INSERT INTO subscriptions (user_id, start_date, end_date, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
            }
            $payment_message = '<p class="success">Payment successful! Subscription active until ' . htmlspecialchars($end_date) . '.</p>';
            // Refresh subscription data
            $stmt = $db->prepare("SELECT end_date, status FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $payment_message = '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log('Subscription error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pharmacy - Subscription</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg shadow-lg text-center fade-in">
        <h1 class="text-2xl font-bold mb-4 flex items-center justify-center">
            <i class="fas fa-credit-card mr-2"></i> Subscription Required
        </h1>
        <?php if ($subscription): ?>
            <p class="mb-4">Your subscription <?php echo $subscription['status'] === 'active' && $subscription['end_date'] >= date('Y-m-d') ? 'is active until ' . htmlspecialchars($subscription['end_date']) : 'has expired on ' . htmlspecialchars($subscription['end_date']); ?>.</p>
        <?php else: ?>
            <p class="mb-4">You need an active subscription to use the system.</p>
        <?php endif; ?>
        <?php echo $payment_message; ?>
        <?php if (!$subscription || $subscription['status'] !== 'active' || $subscription['end_date'] < date('Y-m-d')): ?>
            <form method="POST">
                <p class="mb-4">Monthly Subscription: $10/month</p>
                <button type="submit" class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-2 rounded-md hover:bg-orange-400 transition">
                    <i class="fas fa-credit-card mr-1"></i> Pay Now
                </button>
            </form>
        <?php else: ?>
            <a href="dashboard.php" class="mt-4 inline-block bg-gradient-to-r from-orange-500 to-orange-600 text-white p-2 rounded-md hover:bg-orange-400 transition">
                <i class="fas fa-arrow-left mr-1"></i> Return to Dashboard
            </a>
        <?php endif; ?>
        <a href="logout.php" class="mt-4 inline-block text-orange-200 hover:text-orange-100">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
    </div>
</body>
</html>