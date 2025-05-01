<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Skip all checks for login pages to prevent loops
if (in_array($current_page, ['login.php', 'pos_login.php'])) {
    error_log("Header.php - Skipping all checks for $current_page");
    return;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session at start
error_log("Header.php - Session at start: " . print_r($_SESSION, true));

// Prevent redirect loop by tracking redirect state
if (!isset($_SESSION['redirected'])) {
    $_SESSION['redirected'] = false;
}

// Check session validity
if (!isset($_SESSION['user_id']) || $_SESSION['industry'] !== 'pharmacy') {
    if (!$_SESSION['redirected']) {
        error_log("Header.php - User not logged in or wrong industry, redirecting to /pharmacy/login.php");
        $_SESSION['redirected'] = true;
        header('Location: /pharmacy/login.php');
        exit;
    } else {
        error_log("Header.php - Redirect loop detected, stopping redirect");
        return;
    }
}

// Reset redirect state after successful session check
$_SESSION['redirected'] = false;

// Permission checks: Show access denied instead of redirecting (except pos.php)
$access_denied = '';
$permissions = $_SESSION['permissions'] ?? [];

if ($current_page === 'dashboard.php' && !in_array('manage_dashboard', $permissions)) {
    error_log("Header.php - No manage_dashboard permission for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    $access_denied = 'Access Denied: You do not have permission to view the dashboard.';
}
if ($current_page === 'pos.php' && !in_array('use_pos', $permissions)) {
    error_log("Header.php - No use_pos permission, redirecting to /pharmacy/pos_login.php");
    if (!$_SESSION['redirected']) {
        $_SESSION['redirected'] = true;
        header('Location: /pharmacy/pos_login.php');
        exit;
    } else {
        error_log("Header.php - Redirect loop detected for pos_login.php, stopping redirect");
        return;
    }
}
if ($current_page === 'products.php' && !in_array('manage_products', $permissions)) {
    error_log("Header.php - No manage_products permission for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    $access_denied = 'Access Denied: You do not have permission to manage products.';
}
if ($current_page === 'reports.php' && !in_array('view_reports', $permissions)) {
    error_log("Header.php - No view_reports permission for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    $access_denied = 'Access Denied: You do not have permission to view reports.';
}
if ($current_page === 'users.php' && !in_array('manage_users', $permissions)) {
    error_log("Header.php - No manage_users permission for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    $access_denied = 'Access Denied: You do not have permission to manage users.';
}
if ($current_page === 'e-prescription.php' && !in_array('prescriber', $permissions)) {
    error_log("Header.php - No prescriber permission for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    $access_denied = 'Access Denied: You do not have permission to access e-prescriptions.';
}
if ($current_page === 'backup-restore.php' && !in_array('manage_backup', $permissions)) {
    error_log("Header.php - No manage_backup permission for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    $access_denied = 'Access Denied: You do not have permission to manage backups.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>MediPeak- <?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            background-color: #f3f4f6;
        }
        .sidebar {
            width: 80px;
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 15px;
        }
        .sidebar .user-profile {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        .sidebar .user-profile:hover {
            transform: scale(1.1);
        }
        .sidebar a {
            margin: 15px 0;
            font-size: 0.75rem;
            text-align: center;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: color 0.3s ease;
        }
        .sidebar a i {
            font-size: 1.25rem;
            margin-bottom: 4px;
        }
        .main-content {
            margin-left: 80px;
            width: calc(100% - 80px);
            min-height: 100vh;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 1.5rem;
        }
        .header .icons {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .header-border {
            border-bottom: 2px solid transparent;
            border-image: linear-gradient(to right, #F97316, #EA580C) 1;
        }
        .table-fixed th, .table-fixed td {
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <?php if ($access_denied): ?>
        <div class="p-6">
            <div class="bg-red-100 text-red-600 p-4 rounded-md">
                <?php echo htmlspecialchars($access_denied); ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="sidebar bg-gradient-to-b from-blue-500 to-blue-600 text-white shadow-lg fade-in">
        <div class="user-profile bg-blue-700 text-white hover:shadow-xl"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
        <a href="dashboard.php" class="text-white hover:text-blue-200">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a>
        <a href="pos.php" class="text-white hover:text-blue-200">
            <i class="fas fa-cash-register"></i>
            POS
        </a>
        <a href="products.php" class="text-white hover:text-blue-200">
            <i class="fas fa-boxes"></i>
            Products
        </a>
        <a href="reports.php" class="text-white hover:text-blue-200">
            <i class="fas fa-chart-bar"></i>
            Reports
        </a>
        <a href="users.php" class="text-white hover:text-blue-200">
            <i class="fas fa-users"></i>
            Users
        </a>
        <?php if (in_array('prescriber', $permissions)): ?>
            <a href="e-prescription.php" class="text-white hover:text-blue-200">
                <i class="fas fa-prescription-bottle-alt"></i>
                E-Prescription
            </a>
        <?php endif; ?>
        <?php if (in_array('manage_backup', $permissions)): ?>
            <a href="backup-restore.php" class="text-white hover:text-blue-200">
                <i class="fas fa-database"></i>
                Backup & Restore
            </a>
        <?php endif; ?>
        <a href="logout.php" class="text-white hover:text-blue-200">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
    <div class="main-content">
        <div class="header bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg header-border p-3 rounded-b-lg fade-in">
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h1>
            <div class="icons">
                <form method="GET" action="<?php echo htmlspecialchars($current_page); ?>" class="flex items-center">
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           class="p-2 text-sm border border-blue-700 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-gray-800">
                    <button type="submit" class="p-2 bg-blue-500 text-white rounded-r-md hover:bg-blue-400 transition text-sm">Search</button>
                </form>
               
            </div>
        </div>