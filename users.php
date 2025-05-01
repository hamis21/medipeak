<?php
session_start();
$page_title = "Users";
require 'header.php';

if (!in_array('manage_users', $_SESSION['permissions'] ?? [])) {
    echo '<div class="p-6"><div class="bg-red-100 text-red-600 p-4 rounded-md">Access Denied: You do not have permission to manage users.</div></div>';
    require 'footer.php';
    exit;
}

require_once 'config/db.php';
$db = (new \InventorySystem\Database())->getConnection();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $permissions = implode(',', $_POST['permissions'] ?? []);
    $user_id = $_POST['user_id'] ?? null;

    if ($action === 'add' || $action === 'edit') {
        if (!$username || !$role) {
            $errors[] = 'Username and role are required.';
        } elseif ($action === 'add' && !$password) {
            $errors[] = 'Password is required for new users.';
        } else {
            try {
                if ($action === 'add') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, role, permissions, industry) VALUES (?, ?, ?, ?, 'pharmacy')");
                    $stmt->execute([$username, $hashed_password, $role, $permissions]);
                    $success = 'User added successfully.';
                } elseif ($action === 'edit' && $user_id) {
                    $sql = "UPDATE users SET username = ?, role = ?, permissions = ?";
                    $params = [$username, $role, $permissions];
                    if ($password) {
                        $sql .= ", password = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $success = 'User updated successfully.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    } elseif ($action === 'delete' && $user_id) {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = 'User deleted successfully.';
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch users
$users = $db->query("SELECT id, username, role, permissions FROM users WHERE industry = 'pharmacy'")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-6">
    <h2 class="text-lg font-semibold text-blue-900 mb-4">Manage Users</h2>
    <?php if ($errors): ?>
        <div class="bg-red-100 text-red-600 p-4 rounded-md mb-4">
            <?php echo htmlspecialchars(implode(', ', $errors)); ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-600 p-4 rounded-md mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    <form method="POST" class="mb-6 grid md:grid-cols-3 gap-6">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="user_id" id="user_id">
        <div>
            <label for="username" class="block text-sm font-medium text-blue-900">Username</label>
            <input type="text" name="username" id="username" required class="w-full p-2 border border-blue-300 rounded-md">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-blue-900">Password</label>
            <input type="password" name="password" id="password" class="w-full p-2 border border-blue-300 rounded-md">
        </div>
        <div>
            <label for="role" class="block text-sm font-medium text-blue-900">Role</label>
            <select name="role" id="role" required class="w-full p-2 border border-blue-300 rounded-md">
                <option value="admin">Admin</option>
                <option value="cashier">Cashier</option>
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-blue-900">Permissions</label>
            <div class="flex flex-wrap gap-4">
                <label><input type="checkbox" name="permissions[]" value="manage_dashboard"> Dashboard</label>
                <label><input type="checkbox" name="permissions[]" value="use_pos"> POS</label>
                <label><input type="checkbox" name="permissions[]" value="manage_products"> Products</label>
                <label><input type="checkbox" name="permissions[]" value="view_reports"> Reports</label>
                <label><input type="checkbox" name="permissions[]" value="manage_users"> Users</label>
                <label><input type="checkbox" name="permissions[]" value="prescriber"> E-Prescription</label>
                <label><input type="checkbox" name="permissions[]" value="manage_backup"> Backup</label>
            </div>
        </div>
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600">Add User</button>
    </form>
    <table class="table-fixed w-full bg-white shadow-md rounded-md">
        <thead>
            <tr class="bg-blue-500 text-white">
                <th class="p-2">Username</th>
                <th class="p-2">Role</th>
                <th class="p-2">Permissions</th>
                <th class="p-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="p-2"><?php echo htmlspecialchars($user['username']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($user['role']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($user['permissions'] ?? 'None'); ?></td>
                    <td class="p-2">
                        <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['role']); ?>', '<?php echo htmlspecialchars($user['permissions']); ?>')"
                                class="bg-yellow-500 text-white p-1 rounded-md hover:bg-yellow-600">Edit</button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="bg-red-500 text-white p-1 rounded-md hover:bg-red-600">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function editUser(id, username, role, permissions) {
    document.querySelector('input[name="action"]').value = 'edit';
    document.getElementById('user_id').value = id;
    document.getElementById('username').value = username;
    document.getElementById('password').value = '';
    document.getElementById('role').value = role;
    const perms = permissions.split(',');
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = perms.includes(checkbox.value);
    });
}
</script>
<?php require 'footer.php'; ?>