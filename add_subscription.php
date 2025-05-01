<?php
session_start();
$page_title = "Create Account";

// Explicitly include the database configuration
$include_path = __DIR__ . '/config/db.php';
if (!file_exists($include_path)) {
    die('Fatal Error: Database configuration file not found at ' . htmlspecialchars($include_path));
}
require_once $include_path;

$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $db = (new \InventorySystem\Database())->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errors[] = 'Invalid CSRF token';
        } else {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
            $industry = 'pharmacy';

            // Validation
            if (!$username || !$password || !$confirm_password || !$role) {
                $errors[] = 'All fields are required.';
            }
            if ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }
            if (!in_array($role, ['admin', 'cashier'])) {
                $errors[] = 'Invalid role selected.';
            }

            // Check if username exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors[] = 'Username already exists.';
            }

            if (empty($errors)) {
                $db->beginTransaction();
                try {
                    // Insert user
                    $permissions = $role === 'admin' ? 'manage_dashboard,manage_products,manage_users' : 'use_pos';
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, role, permissions, industry) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $role, $permissions, $industry]);
                    $user_id = $db->lastInsertId();

                    // Insert subscription
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+1 year'));
                    $stmt = $db->prepare("INSERT INTO subscriptions (user_id, plan, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, 'basic', $start_date, $end_date, 'active']);

                    $db->commit();
                    $success = 'Account created successfully. You can now log in.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = 'Error creating account: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
} catch (Exception $e) {
    $errors[] = 'Error: ' . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #3B82F6, #1D4ED8);
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .form-container {
            max-width: 500px;
            width: 100%;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .error { color: #B91C1C; }
        .success { color: #15803D; }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="form-container fade-in">
        <h2 class="text-2xl font-bold text-blue-900 mb-4 text-center">Create Account</h2>
        <?php if ($errors): ?>
            <p class="error text-center"><?php echo htmlspecialchars(implode(', ', $errors)); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success text-center"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div>
                <label for="username" class="block text-sm font-medium text-blue-900">
                    <i class="fas fa-user mr-2 text-blue-500"></i>Username
                </label>
                <input type="text" name="username" id="username" required
                       class="mt-1 p-2 w-full border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-blue-900">
                    <i class="fas fa-lock mr-2 text-blue-500"></i>Password
                </label>
                <input type="password" name="password" id="password" required
                       class="mt-1 p-2 w-full border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-blue-900">
                    <i class="fas fa-lock mr-2 text-blue-500"></i>Confirm Password
                </label>
                <input type="password" name="confirm_password" id="confirm_password" required
                       class="mt-1 p-2 w-full border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-blue-900">
                    <i class="fas fa-user-tag mr-2 text-blue-500"></i>Role
                </label>
                <select name="role" id="role" required class="mt-1 p-2 w-full border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select role</option>
                    <option value="admin">Admin</option>
                    <option value="cashier">Cashier</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-blue-700 text-white p-2 rounded-md hover:bg-blue-600 transition">
                <i class="fas fa-user-plus mr-2"></i>Create Account
            </button>
        </form>
        <div class="text-center mt-4">
            <a href="login.php" class="text-blue-500 hover:underline">Back to Login</a>
        </div>
    </div>
</body>
</html>