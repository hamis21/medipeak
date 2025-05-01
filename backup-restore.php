<?php
session_start();
$page_title = "Backup & Restore";
require 'header.php';

if (!in_array('manage_backup', $_SESSION['permissions'] ?? [])) {
    echo '<div class="p-6"><div class="bg-red-100 text-red-600 p-4 rounded-md">Access Denied: You do not have permission to manage backups.</div></div>';
    require 'footer.php';
    exit;
}

$errors = [];
$success = '';
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        try {
            $backup_file = $backup_dir . 'pharmacy_backup_' . date('Ymd_His') . '.sql';
            $command = "mysqldump -h localhost -u root pharmacy > " . escapeshellarg($backup_file);
            exec($command, $output, $return_var);
            if ($return_var === 0) {
                $success = 'Backup created successfully: ' . basename($backup_file);
            } else {
                $errors[] = 'Backup failed. Ensure mysqldump is installed and accessible.';
            }
        } catch (Exception $e) {
            $errors[] = 'Backup error: ' . htmlspecialchars($e->getMessage());
        }
    } elseif ($action === 'restore' && !empty($_FILES['backup_file']['tmp_name'])) {
        try {
            $file = $_FILES['backup_file']['tmp_name'];
            $command = "mysql -h localhost -u root pharmacy < " . escapeshellarg($file);
            exec($command, $output, $return_var);
            if ($return_var === 0) {
                $success = 'Database restored successfully.';
            } else {
                $errors[] = 'Restore failed. Ensure the SQL file is valid.';
            }
        } catch (Exception $e) {
            $errors[] = 'Restore error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$backups = glob($backup_dir . '*.sql');
?>

<div class="p-6">
    <h2 class="text-lg font-semibold text-blue-900 mb-4">Backup & Restore</h2>
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
    <div class="grid md:grid-cols-2 gap-6">
        <div>
            <h3 class="text-md font-semibold text-blue-900 mb-2">Create Backup</h3>
            <form method="POST">
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600">Create Backup</button>
            </form>
        </div>
        <div>
            <h3 class="text-md font-semibold text-blue-900 mb-2">Restore Backup</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="restore">
                <input type="file" name="backup_file" accept=".sql" required class="mb-2">
                <button type="submit" class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600">Restore</button>
            </form>
        </div>
    </div>
    <h3 class="text-md font-semibold text-blue-900 mt-6 mb-2">Available Backups</h3>
    <table class="table-fixed w-full bg-white shadow-md rounded-md">
        <thead>
            <tr class="bg-blue-500 text-white">
                <th class="p-2">File</th>
                <th class="p-2">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($backups as $backup): ?>
                <tr>
                    <td class="p-2"><?php echo htmlspecialchars(basename($backup)); ?></td>
                    <td class="p-2"><?php echo date('Y-m-d H:i:s', filemtime($backup)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require 'footer.php'; ?>