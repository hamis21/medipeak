<!-- php-app/public/pharmacy/pos_login.php -->
<?php
session_start();

// Destroy any existing session to prevent redirect loops
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
session_start();

require '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT u.id, u.password, u.industry, r.permissions 
                              FROM users u 
                              LEFT JOIN user_roles ur ON u.id = ur.user_id 
                              LEFT JOIN roles r ON ur.role_id = r.id 
                              WHERE u.username = ? AND u.industry = 'pharmacy'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $permissions = json_decode($user['permissions'], true);
            if (in_array('use_pos', $permissions)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['industry'] = $user['industry'];
                $_SESSION['permissions'] = $permissions;
                header('Location: pos.php');
                exit;
            } else {
                $error = "You do not have POS access.";
                session_destroy();
            }
        } else {
            $error = "Invalid username or password.";
        }
    } catch (Exception $e) {
        $error = "Database error: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>POS Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #FFF8E7;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #2A3F5F;
        }
        .login-container input {
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
        }
        .login-container button {
            background-color: #F5A623;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            width: 100%;
            cursor: pointer;
        }
        .login-container button:hover {
            background-color: #e59400;
        }
        .error {
            color: #E57373;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>POS Login</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>