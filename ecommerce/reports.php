<?php
session_start();
include 'config.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    // For development purposes, we'll continue without redirecting
    // In production, uncomment the following:
    // header("Location: login.php");
    // exit();
}

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Process date filter if submitted
if(isset($_POST['filter_date'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
}

// Function to safely execute queries and handle errors
function safe_query($conn, $query) {
    try {
        $result = mysqli_query($conn, $query);
        if(!$result) {
            return ['error' => mysqli_error($conn)];
        }
        return ['result' => $result];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// Determine table structures dynamically
$tables_info = [];
$check_tables = ["orders", "products", "users", "categories"];

foreach($check_tables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = safe_query($conn, $query);
    
    if(isset($result['result']) && mysqli_num_rows($result['result']) > 0) {
        $columns_query = "SHOW COLUMNS FROM $table";
        $columns_result = safe_query($conn, $columns_query);
        
        if(isset($columns_result['result'])) {
            $tables_info[$table] = [];
            while($column = mysqli_fetch_assoc($columns_result['result'])) {
                $tables_info[$table][] = $column['Field'];
            }
        }
    }
}

// Find order date and price columns
$date_column = in_array('order_date', $tables_info['orders'] ?? []) ? 'order_date' : 
              (in_array('date', $tables_info['orders'] ?? []) ? 'date' : 
              (in_array('created_at', $tables_info['orders'] ?? []) ? 'created_at' : 'id'));

$price_column = in_array('price', $tables_info['orders'] ?? []) ? 'price' : 
               (in_array('amount', $tables_info['orders'] ?? []) ? 'amount' : 
               (in_array('total', $tables_info['orders'] ?? []) ? 'total' : 'id'));

// Find user and product ID columns in orders
$user_id_column = in_array('user_id', $tables_info['orders'] ?? []) ? 'user_id' : 
                 (in_array('customer_id', $tables_info['orders'] ?? []) ? 'customer_id' : 'id');

$product_id_column = in_array('product_id', $tables_info['orders'] ?? []) ? 'product_id' : 
                    (in_array('item_id', $tables_info['orders'] ?? []) ? 'item_id' : 'id');

// REPORTS DATA

// 1. Sales by date (for chart)
$sales_query = "SELECT DATE($date_column) as date, SUM($price_column) as total 
                FROM orders 
                WHERE $date_column BETWEEN '$start_date' AND '$end_date' 
                GROUP BY DATE($date_column) 
                ORDER BY date";
$sales_result = safe_query($conn, $sales_query);

$sales_data = [];
$total_sales = 0;
$total_orders = 0;

if(isset($sales_result['result'])) {
    while($row = mysqli_fetch_assoc($sales_result['result'])) {
        $sales_data[] = [
            'date' => $row['date'],
            'total' => floatval($row['total'])
        ];
        $total_sales += floatval($row['total']);
    }
}

// 2. Count orders
$orders_count_query = "SELECT COUNT(*) as count FROM orders WHERE $date_column BETWEEN '$start_date' AND '$end_date'";
$orders_count_result = safe_query($conn, $orders_count_query);

if(isset($orders_count_result['result'])) {
    $row = mysqli_fetch_assoc($orders_count_result['result']);
    $total_orders = $row['count'];
}

// 3. Top selling products
$top_products_query = "SELECT p.id, 
                      " . (in_array('name', $tables_info['products'] ?? []) ? "p.name" : "p.id") . " as product_name, 
                      COUNT(o.id) as orders_count, 
                      SUM(o.$price_column) as total_sales 
                      FROM orders o 
                      JOIN products p ON o.$product_id_column = p.id 
                      WHERE o.$date_column BETWEEN '$start_date' AND '$end_date' 
                      GROUP BY p.id 
                      ORDER BY total_sales DESC 
                      LIMIT 5";
$top_products_result = safe_query($conn, $top_products_query);

$top_products = [];
if(isset($top_products_result['result'])) {
    while($row = mysqli_fetch_assoc($top_products_result['result'])) {
        $top_products[] = $row;
    }
}

// 4. Top customers
$top_customers_query = "SELECT u.id, 
                       " . (in_array('username', $tables_info['users'] ?? []) ? "u.username" : 
                          (in_array('name', $tables_info['users'] ?? []) ? "u.name" : "u.id")) . " as customer_name, 
                       COUNT(o.id) as orders_count, 
                       SUM(o.$price_column) as total_spent 
                       FROM orders o 
                       JOIN users u ON o.$user_id_column = u.id 
                       WHERE o.$date_column BETWEEN '$start_date' AND '$end_date' 
                       GROUP BY u.id 
                       ORDER BY total_spent DESC 
                       LIMIT 5";
$top_customers_result = safe_query($conn, $top_customers_query);

$top_customers = [];
if(isset($top_customers_result['result'])) {
    while($row = mysqli_fetch_assoc($top_customers_result['result'])) {
        $top_customers[] = $row;
    }
}

// 5. Sales by status
$status_query = "SELECT status, COUNT(*) as count, SUM($price_column) as total 
                FROM orders 
                WHERE $date_column BETWEEN '$start_date' AND '$end_date' 
                GROUP BY status";
$status_result = safe_query($conn, $status_query);

$status_data = [];
if(isset($status_result['result'])) {
    while($row = mysqli_fetch_assoc($status_result['result'])) {
        $status_data[] = $row;
    }
}

// 6. Recent activity
$recent_query = "SELECT o.id, 
                " . (in_array('username', $tables_info['users'] ?? []) ? "u.username" : 
                   (in_array('name', $tables_info['users'] ?? []) ? "u.name" : "u.id")) . " as customer_name,
                " . (in_array('name', $tables_info['products'] ?? []) ? "p.name" : "p.id") . " as product_name,
                o.$date_column as date,
                o.$price_column as amount,
                o.status
                FROM orders o
                LEFT JOIN users u ON o.$user_id_column = u.id
                LEFT JOIN products p ON o.$product_id_column = p.id
                ORDER BY o.$date_column DESC
                LIMIT 10";
$recent_result = safe_query($conn, $recent_query);

$recent_activities = [];
if(isset($recent_result['result'])) {
    while($row = mysqli_fetch_assoc($recent_result['result'])) {
        $recent_activities[] = $row;
    }
}

// Convert sales data to JSON for chart.js
$chart_labels = [];
$chart_data = [];

foreach($sales_data as $day) {
    $chart_labels[] = date('M d', strtotime($day['date']));
    $chart_data[] = $day['total'];
}

$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);

// Calculate average order value
$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo.jpg" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - MetaDrop Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(45deg, #f5f7fa, #e4e8f0);
            min-height: 100vh;
            padding: 20px;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #4361ee, #3a0ca3);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .content {
            padding: 30px;
        }
        
        .filter-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9fafc;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
        }
        
        .form-group button {
            background-color: #4361ee;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .form-group button:hover {
            background-color: #3a0ca3;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 1px solid #eaeaea;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #4361ee;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
        
        .chart-section {
            margin-top: 20px;
            margin-bottom: 40px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #eaeaea;
        }
        
        .chart-title {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        canvas {
            width: 100%;
            max-height: 350px;
        }
        
        .table-section {
            margin-top: 30px;
            margin-bottom: 20px;
        }
        
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
        }
        
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid #eaeaea;
        }
        
        .table-card-header {
            background-color: #f5f7fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .table-card-header h3 {
            font-size: 1.1rem;
            color: #333;
            margin: 0;
        }
        
        .table-card-body {
            padding: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background-color: #f9fafc;
            color: #333;
            padding: 12px 15px;
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #eaeaea;
            font-size: 0.9rem;
        }
        
        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eaeaea;
            color: #555;
            font-size: 0.95rem;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover {
            background-color: #f9fafc;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .status-completed {
            color: #4caf50; 
            background-color: rgba(76, 175, 80, 0.1);
        }
        
        .status-processing {
            color: #ff9800; 
            background-color: rgba(255, 152, 0, 0.1);
        }
        
        .status-cancelled {
            color: #f44336; 
            background-color: rgba(244, 67, 54, 0.1);
        }
        
        .status-pending {
            color: #2196f3; 
            background-color: rgba(33, 150, 243, 0.1);
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .no-data-message {
            text-align: center;
            padding: 30px;
            color: #777;
            font-style: italic;
        }
        
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .table-card {
                overflow-x: auto;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>Sales Reports</h1>
            <a href="admin.php" class="back-link">← Back to Dashboard</a>
        </div>
        
        <div class="content">
            <div class="filter-section">
                <form class="filter-form" method="post">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="filter_date">Apply Filter</button>
                    </div>
                </form>
            </div>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value">$<?php echo number_format($total_sales, 2); ?></div>
                    <div class="stat-label">During selected period</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Orders</div>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">During selected period</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Avg. Order Value</div>
                    <div class="stat-value">$<?php echo number_format($avg_order_value, 2); ?></div>
                    <div class="stat-label">During selected period</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Period</div>
                    <div class="stat-value"><?php echo count($sales_data); ?></div>
                    <div class="stat-label">Days</div>
                </div>
            </div>
            
            <div class="charts-grid">
                <div class="chart-section">
                    <div class="chart-title">Sales Trend</div>
                    <canvas id="salesChart"></canvas>
                </div>
                
                <div class="chart-section">
                    <div class="chart-title">Order Status Distribution</div>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div class="tables-grid">
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>Top Selling Products</h3>
                    </div>
                    <div class="table-card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($top_products)): ?>
                                <tr>
                                    <td colspan="3" class="no-data-message">No data available for the selected period</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo $product['orders_count']; ?></td>
                                        <td>$<?php echo number_format($product['total_sales'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>Top Customers</h3>
                    </div>
                    <div class="table-card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($top_customers)): ?>
                                <tr>
                                    <td colspan="3" class="no-data-message">No data available for the selected period</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($top_customers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                        <td><?php echo $customer['orders_count']; ?></td>
                                        <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="table-section">
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="table-card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_activities)): ?>
                                <tr>
                                    <td colspan="6" class="no-data-message">No recent activity available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($recent_activities as $activity): ?>
                                    <tr>
                                        <td>#ORD-<?php echo str_pad($activity['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($activity['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['product_name']); ?></td>
                                        <td>
                                            <?php 
                                            if(!empty($activity['date'])) {
                                                try {
                                                    echo date('M d, Y', strtotime($activity['date'])); 
                                                } catch (Exception $e) {
                                                    echo $activity['date'];
                                                }
                                            } else {
                                                echo 'Unknown';
                                            }
                                            ?>
                                        </td>
                                        <td>$<?php echo number_format(floatval($activity['amount']), 2); ?></td>
                                        <td>
                                            <?php 
                                            $status = isset($activity['status']) ? $activity['status'] : 'Unknown';
                                            $status_class = '';
                                            
                                            switch(strtolower($status)) {
                                                case 'completed':
                                                    $status_class = 'status-completed';
                                                    break;
                                                case 'processing':
                                                    $status_class = 'status-processing';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'status-cancelled';
                                                    break;
                                                case 'pending':
                                                    $status_class = 'status-pending';
                                                    break;
                                                default:
                                                    $status_class = 'status-pending';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Sales Chart
        var salesCtx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo $chart_labels_json; ?>,
                datasets: [{
                    label: 'Sales',
                    data: <?php echo $chart_data_json; ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(67, 97, 238, 1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        
        // Status Chart
        var statusData = <?php 
            $status_labels = [];
            $status_counts = [];
            $status_colors = [];
            
            foreach($status_data as $status) {
                $status_labels[] = $status['status'];
                $status_counts[] = $status['count'];
                
                // Assign colors based on status
                switch(strtolower($status['status'])) {
                    case 'completed':
                        $status_colors[] = 'rgba(76, 175, 80, 0.8)';
                        break;
                    case 'processing':
                        $status_colors[] = 'rgba(255, 152, 0, 0.8)';
                        break;
                    case 'cancelled':
                        $status_colors[] = 'rgba(244, 67, 54, 0.8)';
                        break;
                    case 'pending':
                        $status_colors[] = 'rgba(33, 150, 243, 0.8)';
                        break;
                    default:
                        $status_colors[] = 'rgba(158, 158, 158, 0.8)';
                }
            }
            
            echo json_encode([
                'labels' => $status_labels,
                'counts' => $status_counts,
                'colors' => $status_colors
            ]);
        ?>;
        
        var statusCtx = document.getElementById('statusChart').getContext('2d');
        var statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.counts,
                    backgroundColor: statusData.colors,
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw || 0;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = Math.round((value / total) * 100);
                                return label + ': ' + value + ' orders (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>