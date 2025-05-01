<?php
session_start();
$page_title = "Manage Products";

// Explicitly include the database configuration
$include_path = __DIR__ . '/config/db.php';
if (!file_exists($include_path)) {
    die('Fatal Error: Database configuration file not found at ' . htmlspecialchars($include_path));
}
require_once $include_path;

// Check if user is logged in and has manage_products permission
if (!isset($_SESSION['user_id']) || !in_array('manage_products', $_SESSION['permissions'] ?? [])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';

try {
    $db = (new \InventorySystem\Database())->getConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_product'])) {
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $unit_price = filter_input(INPUT_POST, 'unit_price', FILTER_VALIDATE_FLOAT);
            $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

            if (!$name || $unit_price === false || $unit_price < 0 || $stock === false || $stock < 0) {
                $errors[] = 'Please provide valid product name, price, and stock.';
            } else {
                $stmt = $db->prepare("INSERT INTO products (name, unit_price, stock) VALUES (?, ?, ?)");
                if ($stmt->execute([$name, $unit_price, $stock])) {
                    $success = 'Product added successfully.';
                } else {
                    $errors[] = 'Error adding product.';
                }
            }
        } elseif (isset($_POST['edit_product'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $unit_price = filter_input(INPUT_POST, 'unit_price', FILTER_VALIDATE_FLOAT);
            $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

            if (!$id || !$name || $unit_price === false || $unit_price < 0 || $stock === false || $stock < 0) {
                $errors[] = 'Please provide valid product details.';
            } else {
                $stmt = $db->prepare("UPDATE products SET name = ?, unit_price = ?, stock = ? WHERE id = ?");
                if ($stmt->execute([$name, $unit_price, $stock, $id])) {
                    $success = 'Product updated successfully.';
                } else {
                    $errors[] = 'Error updating product.';
                }
            }
        } elseif (isset($_POST['delete_product'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = 'Product deleted successfully.';
                } else {
                    $errors[] = 'Error deleting product.';
                }
            } else {
                $errors[] = 'Invalid product ID.';
            }
        }
    }

    // Fetch products
    $query = "SELECT id, name, unit_price, stock FROM products";
    if ($search) {
        $query .= " WHERE name LIKE ?";
        $stmt = $db->prepare($query);
        $stmt->execute(['%' . $search . '%']);
    } else {
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) {
    $errors[] = 'Error: ' . htmlspecialchars($e->getMessage());
}

require 'header.php';
?>

<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold text-blue-900 mb-6">Manage Products</h1>

    <?php if ($errors): ?>
        <div class="bg-red-100 text-red-600 p-4 rounded-md mb-6">
            <?php echo htmlspecialchars(implode(', ', $errors)); ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-600 p-4 rounded-md mb-6">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Add Product Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-lg font-semibold text-blue-900 mb-4">Add New Product</h2>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-blue-900 mb-1">
                        <i class="fas fa-box mr-2 text-blue-500"></i>Product Name
                    </label>
                    <input type="text" name="name" id="name" required
                           class="w-full p-2 border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="unit_price" class="block text-sm font-medium text-blue-900 mb-1">
                        <i class="fas fa-dollar-sign mr-2 text-blue-500"></i>Unit Price
                    </label>
                    <input type="number" name="unit_price" id="unit_price" step="0.01" min="0" required
                           class="w-full p-2 border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="stock" class="block text-sm font-medium text-blue-900 mb-1">
                        <i class="fas fa-cubes mr-2 text-blue-500"></i>Stock
                    </label>
                    <input type="number" name="stock" id="stock" min="0" required
                           class="w-full p-2 border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" name="add_product"
                        class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition">
                    <i class="fas fa-plus mr-2"></i>Add Product
                </button>
            </div>
        </form>
    </div>

    <!-- Search Products -->
    <div class="mb-6">
        <form method="GET" class="flex items-center space-x-2 max-w-md">
            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search products by name"
                   class="flex-1 p-2 border border-blue-300 rounded-md text-blue-900 focus:ring-blue-500 focus:border-blue-500">
            <button type="submit"
                    class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>

    <!-- Products Table -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-lg font-semibold text-blue-900 mb-4">Product List</h2>
        <?php if (empty($products)): ?>
            <p class="text-blue-900">No products found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-blue-900 table-auto">
                    <thead>
                        <tr class="bg-blue-100 border-b border-blue-300">
                            <th class="p-3 text-left">Name</th>
                            <th class="p-3 text-right">Unit Price</th>
                            <th class="p-3 text-right">Stock</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="border-b border-blue-300">
                                <td class="p-3"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="p-3 text-right">$<?php echo number_format($product['unit_price'], 2); ?></td>
                                <td class="p-3 text-right"><?php echo htmlspecialchars($product['stock']); ?></td>
                                <td class="p-3 text-center">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required
                                               class="p-1 border border-blue-300 rounded-md text-sm hidden edit-field-<?php echo $product['id']; ?>">
                                        <input type="number" name="unit_price" step="0.01" min="0" value="<?php echo $product['unit_price']; ?>" required
                                               class="p-1 border border-blue-300 rounded-md text-sm hidden edit-field-<?php echo $product['id']; ?>">
                                        <input type="number" name="stock" min="0" value="<?php echo $product['stock']; ?>" required
                                               class="p-1 border border-blue-300 rounded-md text-sm hidden edit-field-<?php echo $product['id']; ?>">
                                        <button type="submit" name="edit_product"
                                                class="bg-blue-500 text-white px-2 py-1 rounded-md hover:bg-blue-600 transition hidden edit-btn-<?php echo $product['id']; ?>">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                    <button onclick="toggleEdit(<?php echo $product['id']; ?>)"
                                            class="bg-blue-500 text-white px-2 py-1 rounded-md hover:bg-blue-600 transition edit-toggle-<?php echo $product['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="delete_product"
                                                class="bg-red-500 text-white px-2 py-1 rounded-md hover:bg-red-600 transition"
                                                onclick="return confirm('Are you sure you want to delete this product?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEdit(id) {
    const editFields = document.querySelectorAll(`.edit-field-${id}`);
    const editBtn = document.querySelector(`.edit-btn-${id}`);
    const toggleBtn = document.querySelector(`.edit-toggle-${id}`);
    editFields.forEach(field => field.classList.toggle('hidden'));
    editBtn.classList.toggle('hidden');
    toggleBtn.classList.toggle('hidden');
}
</script>

<?php require 'footer.php'; ?>