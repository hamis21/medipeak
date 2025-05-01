<?php
ob_start();
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        // Sanitize and validate inputs
        $business_name = trim($_POST['business_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? '';

        if (empty($business_name)) {
            $errors[] = 'Business name is required.';
        }
        if (empty($username)) {
            $errors[] = 'Username is required.';
        }
        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        if (!in_array($role, ['admin', 'pharmacist', 'cashier'])) {
            $errors[] = 'Invalid role selected.';
        }

        // Validate logo upload
        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = mime_content_type($_FILES['logo']['tmp_name']);
            $file_size = $_FILES['logo']['size'];
            $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

            if (!in_array($file_type, $allowed_types)) {
                $errors[] = 'Logo must be a JPEG or PNG image.';
            }
            if ($file_size > $max_size) {
                $errors[] = 'Logo file size must not exceed 2MB.';
            }
            if (!in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                $errors[] = 'Invalid logo file extension.';
            }
        } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Error uploading logo: ' . $_FILES['logo']['error'];
        }

        // Check for duplicate username
        try {
            $db = (new \InventorySystem\Database())->getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Username already exists.';
            }
        } catch (PDOException $e) {
            error_log("Register.php - Database error checking username: " . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }

        // Map role to permissions
        $permissions = [];
        switch ($role) {
            case 'admin':
                $permissions = ['manage_dashboard', 'use_pos', 'manage_products', 'view_reports', 'manage_users', 'prescriber', 'manage_backup'];
                break;
            case 'pharmacist':
                $permissions = ['manage_dashboard', 'manage_products', 'view_reports', 'prescriber'];
                break;
            case 'cashier':
                $permissions = ['use_pos'];
                break;
            default:
                $permissions = [];
        }

        // Proceed if no errors
        if (empty($errors)) {
            try {
                // Create uploads/logos directory if it doesn't exist
                $upload_dir = __DIR__ . '/uploads/logos/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        error_log("Register.php - Failed to create directory: $upload_dir");
                        $errors[] = 'Failed to create upload directory.';
                    }
                }

                // Set directory permissions
                if (!is_writable($upload_dir)) {
                    if (!chmod($upload_dir, 0777)) {
                        error_log("Register.php - Failed to set permissions on: $upload_dir");
                        $errors[] = 'Upload directory is not writable.';
                    }
                }

                // Handle logo upload
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK && empty($errors)) {
                    $logo_name = uniqid() . '_logo.' . $file_ext;
                    $logo_path = 'Uploads/logos/' . $logo_name;
                    $full_path = $upload_dir . $logo_name;

                    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $full_path)) {
                        error_log("Register.php - Failed to move uploaded file to: $full_path");
                        $errors[] = 'Failed to upload logo.';
                    }
                }

                // Insert data if no upload errors
                if (empty($errors)) {
                    // Insert business
                    $stmt = $db->prepare("INSERT INTO businesses (name, logo_path) VALUES (?, ?)");
                    $stmt->execute([$business_name, $logo_path ?? '']);
                    $business_id = $db->lastInsertId();

                    // Insert user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (business_id, username, password, industry, permissions) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$business_id, $username, $hashed_password, 'pharmacy', json_encode($permissions)]);

                    // Set session variables
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['business_id'] = $business_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['industry'] = 'pharmacy';
                    $_SESSION['permissions'] = $permissions;

                    $success = 'Registration successful! Redirecting to dashboard...';
                    header('Refresh: 2; URL=dashboard.php');
                }
            } catch (PDOException $e) {
                error_log("Register.php - Database error: " . $e->getMessage());
                $errors[] = 'Database error: Unable to register. Please try again.';
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediPeak</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        .pill {
            position: absolute;
            border-radius: 50px;
            opacity: 0.7;
            animation: float 15s infinite ease-in-out;
        }
        .pill-1 { width: 40px; height: 20px; background: #ffffff; top: 10%; left: 15%; }
        .pill-2 { width: 60px; height: 30px; background: #ffeb3b; top: 60%; left: 25%; animation-delay: 2s; }
        .pill-3 { width: 50px; height: 25px; background: #42a5f5; top: 30%; left: 70%; animation-delay: 4s; }
        .pill-4 { width: 30px; height: 15px; background: #ffffff; top: 80%; left: 80%; animation-delay: 6s; }
        .pill-5 { width: 45px; height: 22px; background: #34d399; top: 20%; left: 90%; animation-delay: 3s; }
        .pill-6 { width: 35px; height: 17px; background: #f87171; top: 70%; left: 10%; animation-delay: 5s; }
        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0); }
        }
        .form-container {
            max-width: 28rem;
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="font-sans">
    <!-- Floating Pills -->
    <div class="pill pill-1"></div>
    <div class="pill pill-2"></div>
    <div class="pill pill-3"></div>
    <div class="pill pill-4"></div>
    <div class="pill pill-5"></div>
    <div class="pill pill-6"></div>

    <div class="container mx-auto p-6">
        <div class="form-container mx-auto bg-white p-6 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold text-blue-900 mb-6 text-center">Register Your Pharmacy</h1>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 text-red-600 p-4 rounded-md mb-4">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 text-green-600 p-4 rounded-md mb-4 text-center">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-700">Business Name</label>
                        <input type="text" name="business_name" id="business_name" value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" 
                               class="mt-1 p-2 w-full border rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               class="mt-1 p-2 w-full border rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="password" 
                               class="mt-1 p-2 w-full border rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               class="mt-1 p-2 w-full border rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role" class="mt-1 p-2 w-full border rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="" disabled selected>Select a role</option>
                            <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="pharmacist" <?php echo ($_POST['role'] ?? '') === 'pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                            <option value="cashier" <?php echo ($_POST['role'] ?? '') === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                        </select>
                    </div>
                    <div>
                        <label for="logo" class="block text-sm font-medium text-gray-700">Business Logo (JPEG/PNG, max 2MB)</label>
                        <input type="file" name="logo" id="logo" accept="image/jpeg,image/png" 
                               class="mt-1 p-2 w-full border rounded-md">
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition">
                        Register
                    </button>
                </form>
                <p class="mt-4 text-center text-sm text-gray-600">
                    Already have an account? <a href="login.php" class="text-blue-500 hover:underline">Log in</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>