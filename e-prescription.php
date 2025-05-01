<?php
session_start();
$page_title = "E-Prescription";
require 'header.php';

if (!in_array('prescriber', $_SESSION['permissions'] ?? [])) {
    echo '<div class="p-6"><div class="bg-red-100 text-red-600 p-4 rounded-md">Access Denied: You do not have permission to access e-prescriptions.</div></div>';
    require 'footer.php';
    exit;
}

require_once 'config/db.php';
$db = (new \InventorySystem\Database())->getConnection();

// Create prescriptions table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_name VARCHAR(100) NOT NULL,
    medication VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    instructions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_name = trim($_POST['patient_name'] ?? '');
    $medication = trim($_POST['medication'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');

    if (!$patient_name || !$medication || !$dosage) {
        $errors[] = 'Patient name, medication, and dosage are required.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO prescriptions (patient_name, medication, dosage, instructions) VALUES (?, ?, ?, ?)");
            $stmt->execute([$patient_name, $medication, $dosage, $instructions]);
            $success = 'Prescription added successfully.';
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch prescriptions
$prescriptions = $db->query("SELECT id, patient_name, medication, dosage, instructions, created_at FROM prescriptions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-6">
    <h2 class="text-lg font-semibold text-blue-900 mb-4">E-Prescriptions</h2>
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
    <form method="POST" class="mb-6 grid md:grid-cols-2 gap-6">
        <div>
            <label for="patient_name" class="block text-sm font-medium text-blue-900">Patient Name</label>
            <input type="text" name="patient_name" id="patient_name" required class="w-full p-2 border border-blue-300 rounded-md">
        </div>
        <div>
            <label for="medication" class="block text-sm font-medium text-blue-900">Medication</label>
            <input type="text" name="medication" id="medication" required class="w-full p-2 border border-blue-300 rounded-md">
        </div>
        <div>
            <label for="dosage" class="block text-sm font-medium text-blue-900">Dosage</label>
            <input type="text" name="dosage" id="dosage" required class="w-full p-2 border border-blue-300 rounded-md">
        </div>
        <div>
            <label for="instructions" class="block text-sm font-medium text-blue-900">Instructions</label>
            <textarea name="instructions" id="instructions" class="w-full p-2 border border-blue-300 rounded-md"></textarea>
        </div>
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 md:col-start-2">Add Prescription</button>
    </form>
    <table class="table-fixed w-full bg-white shadow-md rounded-md">
        <thead>
            <tr class="bg-blue-500 text-white">
                <th class="p-2">Patient</th>
                <th class="p-2">Medication</th>
                <th class="p-2">Dosage</th>
                <th class="p-2">Instructions</th>
                <th class="p-2">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prescriptions as $prescription): ?>
                <tr>
                    <td class="p-2"><?php echo htmlspecialchars($prescription['patient_name']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($prescription['medication']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($prescription['instructions'] ?? ''); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($prescription['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require 'footer.php'; ?>