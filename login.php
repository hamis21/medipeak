<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['industry'] === 'pharmacy') {
    header('Location: /pharmacy/dashboard.php');
    exit;
}

// Include database configuration
require_once __DIR__ . '/config/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $errors[] = 'Username and password are required.';
    } else {
        try {
            $db = (new \InventorySystem\Database())->getConnection();
            $stmt = $db->prepare("SELECT id, username, password, industry, permissions FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['industry'] = $user['industry'];
                $_SESSION['permissions'] = $user['permissions'] ? explode(',', $user['permissions']) : [];
                header('Location: /pharmacy/dashboard.php');
                exit;
            } else {
                $errors[] = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: Unable to connect or query.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pharmacy - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
            font-family: 'Arial', sans-serif;
            overflow: hidden;
        }
        .pill {
            position: absolute;
            border-radius: 50px;
            opacity: 0.7;
            animation: float 10s infinite ease-in-out;
        }
        .pill-1 {
            width: 60px;
            height: 30px;
            background: #ffffff;
            top: 20%;
            left: 70%;
            animation-delay: 0s;
        }
        .pill-2 {
            width: 50px;
            height: 25px;
            background: #ffd54f;
            top: 50%;
            left: 80%;
            animation-delay: 2s;
        }
        .pill-3 {
            width: 70px;
            height: 35px;
            background: #4fc3f7;
            top: 70%;
            left: 65%;
            animation-delay: 4s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-50px); }
        }
        .fade-in {
            animation: fadeIn 1s ease-in;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #4b5eAA;
        }
        .input-icon input {
            padding-left: 40px;
        }
        .btn-gradient {
            background: linear-gradient(to right, #4b5eAA, #3b82f6);
            transition: transform 0.2s, background 0.3s;
        }
        .btn-gradient:hover {
            transform: scale(1.05);
            background: linear-gradient(to right, #3b82f6, #4b5eAA);
        }
    </style>
</head>
<body class="relative">
    <!-- Decorative Pills -->
    <div class="pill pill-1"></div>
    <div class="pill pill-2"></div>
    <div class="pill pill-3"></div>

    <!-- Centered Container -->
    <div class="flex items-center justify-center min-h-screen p-6">
        <div class="text-center fade-in">
            <!-- Logo and Tagline -->
            <div class="flex justify-center items-center mb-8">
                <div class="text-4xl font-bold text-blue-900">MediPeak</div>
                
            </div>

            <!-- Login Form -->
            <div class="login-card w-full max-w-md p-8">
                <h1 class="text-3xl font-bold text-blue-900 mb-6 text-center">LOGIN</h1>
                <?php if ($errors): ?>
                    <div class="bg-red-100 text-red-600 p-4 rounded-md mb-6">
                        <?php echo htmlspecialchars(implode(', ', $errors)); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="space-y-6">
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" required
                               placeholder="Enter your username"
                               class="w-full p-3 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" required
                               placeholder="Enter your password"
                               class="w-full p-3 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="w-full btn-gradient text-white p-3 rounded-md font-semibold">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>