<?php
ob_start();
session_start();
$page_title = "MediPeak Dashboard";

// Explicitly include the database configuration
$include_path = __DIR__ . '/config/db.php';
if (!file_exists($include_path)) {
    error_log("Dashboard.php - Database config file not found at: $include_path");
    die('Fatal Error: Database configuration file not found at ' . htmlspecialchars($include_path));
}
require_once $include_path;

// Include header.php
require 'header.php';

try {
    $db = (new \InventorySystem\Database())->getConnection();

    // Fetch business details
    $business_id = $_SESSION['business_id'] ?? null;
    $business_name = "Unknown Business";
    $business_logo = null;
    if ($business_id) {
        $stmt = $db->prepare("SELECT name, logo_path FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        $business = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($business) {
            $business_name = $business['name'];
            $business_logo = $business['logo_path'];
        }
    }

    // Create necessary tables if they don't exist
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS products (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            stock INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS orders (
            id INT PRIMARY KEY AUTO_INCREMENT,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS order_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS prescriptions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_name VARCHAR(100) NOT NULL,
            medication VARCHAR(100) NOT NULL,
            dosage VARCHAR(50) NOT NULL,
            instructions TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        error_log("Dashboard.php - Table creation error: " . $e->getMessage());
        throw new Exception("Failed to create database tables: " . $e->getMessage());
    }

    // Initialize variables with defaults
    $patient_count = $receipt_count = $total_sales = $avg_order_value = $fill_rate = $sales_trend = 0;
    $low_stock_products = $daily_sales = $top_products = $payment_methods = $daily_fills = $monthly_sales = $high_sales_days = $prescription_fills = [];
    $dates = $sales_data = $product_names = $quantities = $payment_labels = $payment_counts = $fill_data = $months = $monthly_sales_values = $calendar_events = [];
    $top_product_name = 'N/A';

    // Fetch number of patients (distinct patients by name from prescriptions)
    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_name) as patient_count FROM prescriptions");
        $stmt->execute();
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['patient_count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching patient count: " . $e->getMessage());
        $patient_count = 0;
    }

    // Fetch number of receipts (total orders)
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as receipt_count FROM orders");
        $stmt->execute();
        $receipt_count = $stmt->fetch(PDO::FETCH_ASSOC)['receipt_count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching receipt count: " . $e->getMessage());
        $receipt_count = 0;
    }

    // Fetch total sales money
    try {
        $stmt = $db->prepare("SELECT SUM(total_amount) as total_sales FROM orders");
        $stmt->execute();
        $total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching total sales: " . $e->getMessage());
        $total_sales = 0;
    }

    // Fetch total sales for the previous 30 days to calculate trend
    try {
        $stmt = $db->prepare("SELECT SUM(total_amount) as prev_sales 
                              FROM orders 
                              WHERE created_at BETWEEN DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 30 DAY), INTERVAL 30 DAY) 
                              AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $stmt->execute();
        $prev_sales = $stmt->fetch(PDO::FETCH_ASSOC)['prev_sales'] ?? 0;
        $sales_trend = $prev_sales > 0 ? (($total_sales - $prev_sales) / $prev_sales) * 100 : 0;
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching previous sales for trend: " . $e->getMessage());
        $sales_trend = 0;
    }

    // Fetch average order value
    try {
        $stmt = $db->prepare("SELECT AVG(total_amount) as avg_order_value FROM orders");
        $stmt->execute();
        $avg_order_value = $stmt->fetch(PDO::FETCH_ASSOC)['avg_order_value'] ?? 0;
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching average order value: " . $e->getMessage());
        $avg_order_value = 0;
    }

    // Fetch prescription fill rate
    try {
        $stmt = $db->prepare("SELECT 
                                (SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100 as fill_rate 
                              FROM prescriptions");
        $stmt->execute();
        $fill_rate = $stmt->fetch(PDO::FETCH_ASSOC)['fill_rate'] ?? 0;
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching prescription fill rate: " . $e->getMessage());
        $fill_rate = 0;
    }

    // Fetch low stock products (stock < 10)
    try {
        $stmt = $db->prepare("SELECT name, stock FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5");
        $stmt->execute();
        $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching low stock products: " . $e->getMessage());
        $low_stock_products = [];
    }

    // Fetch daily sales data for the past 30 days
    try {
        $stmt = $db->prepare("SELECT DATE(created_at) as sale_date, SUM(total_amount) as daily_total 
                              FROM orders 
                              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                              GROUP BY DATE(created_at) 
                              ORDER BY sale_date");
        $stmt->execute();
        $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching daily sales: " . $e->getMessage());
        $daily_sales = [];
    }

    $dates = [];
    $sales_data = [];
    $current_date = new DateTime();
    $start_date = (clone $current_date)->modify('-29 days');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start_date, $interval, $current_date);

    $sales_data = array_fill(0, 30, 0);
    foreach ($period as $date) {
        $dates[] = $date->format('Y-m-d');
    }
    foreach ($daily_sales as $sale) {
        $index = array_search($sale['sale_date'], $dates);
        if ($index !== false) {
            $sales_data[$index] = (float)$sale['daily_total'];
        }
    }

    // Fetch top 5 products by quantity sold
    try {
        $stmt = $db->prepare("SELECT p.name, SUM(oi.quantity) as total_quantity 
                              FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              GROUP BY p.id, p.name 
                              ORDER BY total_quantity DESC 
                              LIMIT 5");
        $stmt->execute();
        $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        $top_product_name = !empty($top_products) ? $top_products[0]['name'] : 'N/A';
        $product_names = array_column($top_products, 'name') ?: ['No Data'];
        $quantities = array_column($top_products, 'total_quantity') ?: [0];
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching top products: " . $e->getMessage());
        $top_products = [];
        $top_product_name = 'N/A';
        $product_names = ['No Data'];
        $quantities = [0];
    }

    // Fetch payment method distribution
    try {
        $stmt = $db->prepare("SELECT payment_method, COUNT(*) as count 
                              FROM orders 
                              GROUP BY payment_method");
        $stmt->execute();
        $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        $payment_labels = [];
        $payment_counts = [];
        foreach ($payment_methods as $method) {
            $payment_labels[] = ucfirst($method['payment_method'] ?? 'Unknown');
            $payment_counts[] = $method['count'] ?? 0;
        }
        if (empty($payment_labels)) {
            $payment_labels = ['No Data'];
            $payment_counts = [0];
        }
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching payment methods: " . $e->getMessage());
        $payment_labels = ['No Data'];
        $payment_counts = [0];
    }

    // Fetch daily prescription fills for the past 30 days
    try {
        $stmt = $db->prepare("SELECT DATE(created_at) as fill_date, COUNT(*) as fill_count 
                              FROM prescriptions 
                              WHERE status = 'filled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                              GROUP BY DATE(created_at) 
                              ORDER BY fill_date");
        $stmt->execute();
        $daily_fills = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        $fill_data = array_fill(0, 30, 0);
        foreach ($daily_fills as $fill) {
            $index = array_search($fill['fill_date'], $dates);
            if ($index !== false) {
                $fill_data[$index] = (int)$fill['fill_count'];
            }
        }
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching daily prescription fills: " . $e->getMessage());
        $fill_data = array_fill(0, 30, 0);
    }

    // Fetch monthly sales for the past 12 months
    try {
        $stmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as sale_month, SUM(total_amount) as monthly_total 
                              FROM orders 
                              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
                              GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                              ORDER BY sale_month");
        $stmt->execute();
        $monthly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        $months = [];
        $start_month = (clone $current_date)->modify('-11 months');
        $month_interval = new DateInterval('P1M');
        $month_period = new DatePeriod($start_month, $month_interval, $current_date);

        foreach ($month_period as $month) {
            $months[] = $month->format('Y-m');
        }
        $monthly_sales_values = array_fill(0, 12, 0);
        foreach ($monthly_sales as $sale) {
            $index = array_search($sale['sale_month'], $months);
            if ($index !== false) {
                $monthly_sales_values[$index] = (float)$sale['monthly_total'];
            }
        }
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching monthly sales: " . $e->getMessage());
        $months = array_fill(0, 12, 'N/A');
        $monthly_sales_values = array_fill(0, 12, 0);
    }

    // Fetch events for the calendar
    try {
        $stmt = $db->prepare("SELECT DATE(created_at) as event_date, SUM(total_amount) as total 
                              FROM orders 
                              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) 
                              GROUP BY DATE(created_at) 
                              HAVING total > 500");
        $stmt->execute();
        $high_sales_days = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching high sales days: " . $e->getMessage());
        $high_sales_days = [];
    }

    try {
        $stmt = $db->prepare("SELECT DATE(created_at) as event_date, COUNT(*) as fill_count 
                              FROM prescriptions 
                              WHERE status = 'filled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) 
                              GROUP BY DATE(created_at)");
        $stmt->execute();
        $prescription_fills = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    } catch (PDOException $e) {
        error_log("Dashboard.php - Error fetching prescription fills for calendar: " . $e->getMessage());
        $prescription_fills = [];
    }

    $calendar_events = [];
    foreach ($high_sales_days as $day) {
        $calendar_events[] = [
            'title' => 'High Sales: $' . number_format($day['total'] ?? 0, 2),
            'start' => $day['event_date'] ?? '',
            'color' => '#3B82F6'
        ];
    }
    foreach ($prescription_fills as $fill) {
        $calendar_events[] = [
            'title' => ($fill['fill_count'] ?? 0) . ' Prescriptions Filled',
            'start' => $fill['event_date'] ?? '',
            'color' => '#1D4ED8'
        ];
    }
} catch (Exception $e) {
    error_log("Dashboard.php - General error: " . $e->getMessage());
    $error_message = '<p class="error text-red-600 p-4 bg-white rounded-lg shadow-md">Error loading dashboard data: ' . htmlspecialchars($e->getMessage()) . '</p>';
    $patient_count = $receipt_count = $total_sales = $avg_order_value = $fill_rate = $sales_trend = 0;
    $low_stock_products = $daily_sales = $top_products = $payment_methods = $daily_fills = $monthly_sales = $high_sales_days = $prescription_fills = [];
    $dates = $sales_data = $product_names = $quantities = $payment_labels = $payment_counts = $fill_data = $months = $monthly_sales_values = $calendar_events = [];
    $top_product_name = 'N/A';
}
?>

        <div class="p-6">
            <?php if ($access_denied): ?>
                <div class="bg-red-100 text-red-600 p-4 rounded-md mb-6">
                    <?php echo htmlspecialchars($access_denied); ?>
                </div>
            <?php else: ?>
                <div class="flex items-center mb-6">
                    <?php if ($business_logo && file_exists($business_logo)): ?>
                        <img src="<?php echo htmlspecialchars($business_logo); ?>" alt="Business Logo" class="w-16 h-16 mr-4 rounded-full">
                    <?php endif; ?>
                    <h1 class="text-3xl font-bold text-blue-900">MediPeak - <?php echo htmlspecialchars($business_name); ?></h1>
                </div>

                <?php if (isset($error_message)): ?>
                    <?php echo $error_message; ?>
                <?php endif; ?>

                <!-- Metrics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-shadow fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-users fa-2x mr-3"></i>
                            <div>
                                <h2 class="text-lg font-semibold">Number of Patients</h2>
                                <p class="text-2xl font-bold"><?php echo htmlspecialchars($patient_count); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-shadow fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-receipt fa-2x mr-3"></i>
                            <div>
                                <h2 class="text-lg font-semibold">Number of Receipts</h2>
                                <p class="text-2xl font-bold"><?php echo htmlspecialchars($receipt_count); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-shadow fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-dollar-sign fa-2x mr-3"></i>
                            <div>
                                <h2 class="text-lg font-semibold">Total Sales</h2>
                                <p class="text-2xl font-bold">$<?php echo number_format($total_sales, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-shadow fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-shopping-cart fa-2x mr-3"></i>
                            <div>
                                <h2 class="text-lg font-semibold">Average Order Value</h2>
                                <p class="text-2xl font-bold">$<?php echo number_format($avg_order_value, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-shadow fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-percentage fa-2x mr-3"></i>
                            <div>
                                <h2 class="text-lg font-semibold">Prescription Fill Rate</h2>
                                <p class="text-2xl font-bold"><?php echo number_format($fill_rate, 1); ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Section -->
                <div class="bg-white p-4 rounded-lg shadow-md mb-6 fade-in">
                    <h2 class="text-lg font-semibold text-blue-900 mb-3">Analysis Summary</h2>
                    <ul class="list-disc list-inside text-blue-900 text-sm">
                        <li>Top-selling product this month: <strong><?php echo htmlspecialchars($top_product_name); ?></strong></li>
                        <li>Sales trend (last 30 days): <strong class="<?php echo $sales_trend >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <?php echo $sales_trend >= 0 ? '+' : ''; ?><?php echo number_format($sales_trend, 1); ?>%
                            </strong> (<?php echo $sales_trend >= 0 ? 'Growth' : 'Decline'; ?>)
                        </li>
                        <li>Average order value: <strong>$<?php echo number_format($avg_order_value, 2); ?></strong> (<?php echo $avg_order_value > 50 ? 'Healthy' : 'Consider upselling strategies'; ?>)</li>
                        <li>Prescription fill rate: <strong><?php echo number_format($fill_rate, 1); ?>%</strong> (<?php echo $fill_rate < 80 ? 'Needs improvement' : 'On track'; ?>)</li>
                        <?php if (!empty($low_stock_products)): ?>
                            <li class="text-red-600">Stock alert: Low stock on <?php echo htmlspecialchars(count($low_stock_products)); ?> products (e.g., <?php echo htmlspecialchars($low_stock_products[0]['name'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($low_stock_products[0]['stock'] ?? 0); ?> units)</li>
                        <?php else: ?>
                            <li class="text-green-600">Stock levels: All products sufficiently stocked</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Calendar -->
                <div class="bg-white p-4 rounded-lg shadow-md mb-6 fade-in">
                    <h2 class="text-lg font-semibold text-blue-900 mb-3">Event Calendar</h2>
                    <div id="calendar" class="border border-blue-300 rounded"></div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Daily Sales Chart -->
                    <div class="bg-white p-4 rounded-lg shadow-md fade-in">
                        <h2 class="text-lg font-semibold text-blue-900 mb-3">Daily Sales (Last 30 Days)</h2>
                        <div class="chart-container">
                            <div class="chart-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>
                            <canvas id="dailySalesChart" aria-label="Daily Sales Chart"></canvas>
                        </div>
                    </div>

                    <!-- Top Products Chart -->
                    <div class="bg-white p-4 rounded-lg shadow-md fade-in">
                        <h2 class="text-lg font-semibold text-blue-900 mb-3">Top 5 Products by Quantity Sold</h2>
                        <div class="chart-container">
                            <div class="chart-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>
                            <canvas id="topProductsChart" aria-label="Top Products Chart"></canvas>
                        </div>
                    </div>

                    <!-- Payment Method Distribution -->
                    <div class="bg-white p-4 rounded-lg shadow-md fade-in">
                        <h2 class="text-lg font-semibold text-blue-900 mb-3">Payment Method Distribution</h2>
                        <div class="chart-container">
                            <div class="chart-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>
                            <canvas id="paymentMethodChart" aria-label="Payment Method Distribution Chart"></canvas>
                        </div>
                    </div>

                    <!-- Daily Prescription Fills -->
                    <div class="bg-white p-4 rounded-lg shadow-md fade-in">
                        <h2 class="text-lg font-semibold text-blue-900 mb-3">Daily Prescription Fills (Last 30 Days)</h2>
                        <div class="chart-container">
                            <div class="chart-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>
                            <canvas id="prescriptionFillsChart" aria-label="Prescription Fills Chart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Sales Trends -->
                    <div class="bg-white p-4 rounded-lg shadow-md col-span-1 sm:col-span-2 fade-in">
                        <h2 class="text-lg font-semibold text-blue-900 mb-3">Monthly Sales Trends (Last 12 Months)</h2>
                        <div class="chart-container">
                            <div class="chart-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>
                            <canvas id="monthlySalesChart" aria-label="Monthly Sales Trends Chart"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<!-- Font Awesome for Icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<!-- FullCalendar Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// CDN fallback for Chart.js and FullCalendar
if (typeof Chart === 'undefined') {
    console.error('Chart.js failed to load. Please check your internet connection or CDN availability.');
}
if (typeof FullCalendar === 'undefined') {
    console.error('FullCalendar failed to load. Please check your internet connection or CDN availability.');
    document.getElementById('calendar').innerHTML = '<p class="text-red-600 text-center">Calendar failed to load. Please check your connection.</p>';
}

    // Hide loading spinners once charts load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.chart-loading').forEach(loader => {
            loader.style.display = 'none';
        });

     // Initialize FullCalendar
    if (typeof FullCalendar !== 'undefined') {
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: <?php echo json_encode($calendar_events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            eventClick: function(info) {
                alert(info.event.title);
            },
            height: 400,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            }
        });
        calendar.render();
    }


        // Daily Sales Chart
        if (typeof Chart !== 'undefined') {
            const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
            new Chart(dailySalesCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                    datasets: [{
                        label: 'Daily Sales ($)',
                        data: <?php echo json_encode($sales_data, JSON_NUMERIC_CHECK); ?>,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { title: { display: true, text: 'Date', font: { size: 10 } }, ticks: { font: { size: 8 } } },
                        y: { title: { display: true, text: 'Sales ($)', font: { size: 10 } }, ticks: { font: { size: 8 } }, beginAtZero: true }
                    },
                    plugins: {
                        legend: { labels: { font: { size: 10 } } }
                    }
                }
            });

            // Top Products Chart
            const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
            new Chart(topProductsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($product_names, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                    datasets: [{
                        label: 'Quantity Sold',
                        data: <?php echo json_encode($quantities, JSON_NUMERIC_CHECK); ?>,
                        backgroundColor: ['rgba(59, 130, 246, 0.6)', 'rgba(29, 78, 216, 0.6)', 'rgba(96, 165, 250, 0.6)', 'rgba(147, 197, 253, 0.6)', 'rgba(191, 219, 254, 0.6)'],
                        borderColor: ['#3B82F6', '#1D4ED8', '#60A5FA', '#93C5FD', '#BFDBFE'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { title: { display: true, text: 'Quantity Sold', font: { size: 10 } }, ticks: { font: { size: 8 } }, beginAtZero: true },
                        x: { title: { display: true, text: 'Product', font: { size: 10 } }, ticks: { font: { size: 8 } } }
                    },
                    plugins: { legend: { display: false } }
                }
            });

            // Payment Method Distribution Chart
            const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
            new Chart(paymentMethodCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($payment_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                    datasets: [{
                        label: 'Orders by Payment Method',
                        data: <?php echo json_encode($payment_counts, JSON_NUMERIC_CHECK); ?>,
                        backgroundColor: ['rgba(59, 130, 246, 0.6)', 'rgba(29, 78, 216, 0.6)', 'rgba(96, 165, 250, 0.6)']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { font: { size: 10 } } }
                    }
                }
            });

            // Daily Prescription Fills Chart
            const prescriptionFillsCtx = document.getElementById('prescriptionFillsChart').getContext('2d');
            new Chart(prescriptionFillsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                    datasets: [{
                        label: 'Prescriptions Filled',
                        data: <?php echo json_encode($fill_data, JSON_NUMERIC_CHECK); ?>,
                        borderColor: '#1D4ED8',
                        backgroundColor: 'rgba(29, 78, 216, 0.2)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { title: { display: true, text: 'Date', font: { size: 10 } }, ticks: { font: { size: 8 } } },
                        y: { title: { display: true, text: 'Prescriptions Filled', font: { size: 10 } }, ticks: { font: { size: 8 } }, beginAtZero: true }
                    },
                    plugins: {
                        legend: { labels: { font: { size: 10 } } }
                    }
                }
            });

            // Monthly Sales Trends Chart
            const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
            new Chart(monthlySalesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                    datasets: [{
                        label: 'Monthly Sales ($)',
                        data: <?php echo json_encode($monthly_sales_values, JSON_NUMERIC_CHECK); ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: '#3B82F6',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { title: { display: true, text: 'Sales ($)', font: { size: 10 } }, ticks: { font: { size: 8 } }, beginAtZero: true },
                        x: { title: { display: true, text: 'Month', font: { size: 10 } }, ticks: { font: { size: 8 } } }
                    },
                    plugins: {
                        legend: { labels: { font: { size: 10 } } }
                    }
                }
            });
        }
    });
    </script>

<?php
ob_end_flush();
?>
</body>
</html>