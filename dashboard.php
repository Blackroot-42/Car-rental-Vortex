<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Fetch payment statistics
$paymentStats = [
    'total_revenue' => 1500,
    'pending_payments' => 2,
    'today_revenue' => 350,
    'month_revenue' => 3500,
    'total_bookings' => 0,
    'active_bookings' => 0,
    'total_cars' => 0,
    'available_cars' => 0
];

// Get financial statistics
$sql = "SELECT 
    IFNULL(SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END),0) as total_revenue,
    COUNT(CASE WHEN b.payment_status = 'pending' THEN 1 END) as pending_payments,
    IFNULL(SUM(CASE WHEN p.status = 'completed' AND DATE(p.payment_date) = CURDATE() THEN p.amount ELSE 0 END),0) as today_revenue,
    IFNULL(SUM(CASE WHEN p.status = 'completed' AND MONTH(p.payment_date) = MONTH(CURDATE()) AND YEAR(p.payment_date) = YEAR(CURDATE()) THEN p.amount ELSE 0 END),0) as month_revenue,
    COUNT(*) as total_bookings,
    COUNT(CASE WHEN b.status = 'confirmed' AND b.end_date >= CURDATE() THEN 1 END) as active_bookings
    FROM bookings b
    LEFT JOIN payments p ON b.id = p.booking_id";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $paymentStats = array_merge($paymentStats, $row);
}

// Get car statistics
$sql = "SELECT 
    COUNT(*) as total_cars,
    COUNT(CASE WHEN c.id NOT IN (SELECT car_id FROM bookings WHERE status = 'confirmed' AND end_date >= CURDATE()) THEN 1 END) as available_cars
    FROM cars c";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $paymentStats = array_merge($paymentStats, $row);
}

// Fetch recent payments with more details
$sql = "SELECT b.*, p.payment_method, p.payment_date, c.year, m.make, m.model, u.username as user_name,
        DATEDIFF(b.end_date, b.start_date) as rental_days
        FROM bookings b
        LEFT JOIN payments p ON b.id = p.booking_id
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN car_models m ON c.car_model_id = m.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.payment_status = 'paid'
        ORDER BY p.payment_date DESC
        LIMIT 5";
$result = $conn->query($sql);
$recentPayments = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentPayments[] = $row;
    }
}

// Calculate average daily revenue
$avgDailyRevenue = $paymentStats['month_revenue'] / date('d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Vortex Rentals</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        
        .stat-card {
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px -5px rgba(255, 0, 0, 0.2);
        }

        .trend-up {
            color: #10B981;
        }

        .trend-down {
            color: #EF4444;
        }
    </style>
</head>
<body class="min-h-screen flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="flex-1 p-8">
        <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 border border-gray-800">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-800 pb-6">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">
                        <span class="text-red-600">ADMIN</span> DASHBOARD
                    </h1>
                    <p class="text-gray-400">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></p>
                </div>
                <div class="mt-4 md:mt-0 flex space-x-4">
                    <button onclick="window.location.href='car_add.php'" class="px-6 py-3 bg-gradient-to-r from-red-600 to-red-800 hover:from-red-700 hover:to-red-900 text-white rounded-lg font-bold transition-all transform hover:scale-105 flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> ADD VEHICLE
                    </button>
                    <button onclick="window.location.href='reports.php'" class="px-6 py-3 bg-gray-800 hover:bg-gray-700 text-white rounded-lg font-bold transition-all transform hover:scale-105 flex items-center">
                        <i class="fas fa-chart-line mr-2"></i> VIEW REPORTS
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Total Revenue</div>
                        <div class="w-12 h-12 bg-green-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-green-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo format_mad($paymentStats['total_revenue']); ?></div>
                    <div class="text-green-400 text-sm">
                        <i class="fas fa-arrow-up mr-1"></i> All time revenue
                    </div>
                </div>

                <!-- Today's Revenue -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Today's Revenue</div>
                        <div class="w-12 h-12 bg-blue-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-day text-blue-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo format_mad($paymentStats['today_revenue']); ?></div>
                    <div class="text-blue-400 text-sm">
                        <i class="fas fa-clock mr-1"></i> Today's earnings
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Monthly Revenue</div>
                        <div class="w-12 h-12 bg-purple-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-purple-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo format_mad($paymentStats['month_revenue']); ?></div>
                    <div class="text-purple-400 text-sm">
                        <i class="fas fa-chart-line mr-1"></i> This month
                    </div>
                </div>

                <!-- Pending Payments -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Pending Payments</div>
                        <div class="w-12 h-12 bg-yellow-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $paymentStats['pending_payments']; ?></div>
                    <div class="text-yellow-400 text-sm">
                        <i class="fas fa-exclamation-circle mr-1"></i> Awaiting payment
                    </div>
                </div>
            </div>

            <!-- Additional Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Bookings -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Total Bookings</div>
                        <div class="w-12 h-12 bg-indigo-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-indigo-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $paymentStats['total_bookings']; ?></div>
                    <div class="text-indigo-400 text-sm">
                        <i class="fas fa-chart-bar mr-1"></i> All bookings
                    </div>
                </div>

                <!-- Active Bookings -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Active Bookings</div>
                        <div class="w-12 h-12 bg-pink-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-car text-pink-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $paymentStats['active_bookings']; ?></div>
                    <div class="text-pink-400 text-sm">
                        <i class="fas fa-check-circle mr-1"></i> Currently active
                    </div>
                </div>

                <!-- Total Cars -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Total Cars</div>
                        <div class="w-12 h-12 bg-orange-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-car-side text-orange-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $paymentStats['total_cars']; ?></div>
                    <div class="text-orange-400 text-sm">
                        <i class="fas fa-warehouse mr-1"></i> In fleet
                    </div>
                </div>

                <!-- Available Cars -->
                <div class="stat-card rounded-xl p-6 border border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-400">Available Cars</div>
                        <div class="w-12 h-12 bg-teal-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-key text-teal-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $paymentStats['available_cars']; ?></div>
                    <div class="text-teal-400 text-sm">
                        <i class="fas fa-check mr-1"></i> Ready to rent
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="bg-gray-900/50 rounded-xl p-6 border border-gray-800">
                <h2 class="text-xl font-bold text-white mb-4">Recent Payments</h2>
                <div class="space-y-4">
                    <?php if (!empty($recentPayments)): ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-900/30 rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium"><?php echo htmlspecialchars($payment['make'] . ' ' . $payment['model'] . ' ' . $payment['year']); ?></div>
                                        <div class="text-gray-400 text-sm">
                                            <?php echo htmlspecialchars($payment['user_name']); ?> • 
                                            <?php echo ucfirst($payment['payment_method']); ?> •
                                            <?php echo $payment['rental_days']; ?> days
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-white font-bold"><?php echo format_mad($payment['total_amount']); ?></div>
                                    <div class="text-gray-400 text-sm"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-gray-400 py-8">
                            <i class="fas fa-receipt text-4xl mb-2"></i>
                            <p>No recent payments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
