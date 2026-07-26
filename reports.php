<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

// Total Users
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
// Total Cars
$totalCars = $conn->query("SELECT COUNT(*) FROM cars")->fetch_row()[0];
// Total Bookings
$totalBookings = $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0];
// Total Revenue
$totalRevenue = $conn->query("SELECT IFNULL(SUM(amount),0) FROM payments WHERE status='completed'")->fetch_row()[0];
// Bookings by Status
$statusCounts = $conn->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
$statusData = [];
while ($row = $statusCounts->fetch_assoc()) {
    $statusData[$row['status']] = $row['count'];
}
// Top 5 Cars by Bookings
$topCars = $conn->query("SELECT c.id, m.make, m.model, COUNT(b.id) as bookings FROM cars c JOIN car_models m ON c.car_model_id = m.id LEFT JOIN bookings b ON c.id = b.car_id GROUP BY c.id ORDER BY bookings DESC LIMIT 5");
// Recent Bookings
$recentBookings = $conn->query("SELECT b.*, u.username FROM bookings b LEFT JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #0a0a0a; color: #e5e5e5; }
        .stat-card { transition: all 0.3s ease; background: linear-gradient(145deg, #1a1a1a, #0f0f0f); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px -5px rgba(255, 0, 0, 0.2); }
    </style>
</head>
<body class="min-h-screen flex">
<?php include 'sidebar.php'; ?>
<main class="flex-1 p-8">
    <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 border border-gray-800">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-800 pb-6">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">
                    <span class="text-red-600">ADMIN</span> REPORTS
                </h1>
                <p class="text-gray-400">Overview, statistics, and insights</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="stat-card rounded-xl p-6 border border-gray-800 text-center">
                <div class="text-2xl font-bold text-white mb-2"><?php echo $totalUsers; ?></div>
                <div class="text-gray-400">Total Users</div>
            </div>
            <div class="stat-card rounded-xl p-6 border border-gray-800 text-center">
                <div class="text-2xl font-bold text-white mb-2"><?php echo $totalCars; ?></div>
                <div class="text-gray-400">Total Cars</div>
            </div>
            <div class="stat-card rounded-xl p-6 border border-gray-800 text-center">
                <div class="text-2xl font-bold text-white mb-2"><?php echo $totalBookings; ?></div>
                <div class="text-gray-400">Total Bookings</div>
            </div>
            <div class="stat-card rounded-xl p-6 border border-gray-800 text-center">
                <div class="text-2xl font-bold text-white mb-2"><?php echo format_mad($totalRevenue); ?></div>
                <div class="text-gray-400">Total Revenue</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
            <div class="stat-card rounded-xl p-6 border border-gray-800">
                <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fas fa-chart-pie text-blue-400"></i> Booking Status</h2>
                <canvas id="bookingStatusChart" height="120"></canvas>
                <ul class="mt-4">
                    <li class="mb-2"><span class="font-semibold text-green-400">Confirmed:</span> <?php echo $statusData['confirmed'] ?? 0; ?></li>
                    <li class="mb-2"><span class="font-semibold text-yellow-400">Pending:</span> <?php echo $statusData['pending'] ?? 0; ?></li>
                    <li class="mb-2"><span class="font-semibold text-red-400">Cancelled:</span> <?php echo $statusData['cancelled'] ?? 0; ?></li>
                </ul>
            </div>
            <div class="stat-card rounded-xl p-6 border border-gray-800">
                <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fas fa-trophy text-yellow-400"></i> Top 5 Cars by Bookings</h2>
                <canvas id="topCarsChart" height="120"></canvas>
                <ol class="list-decimal ml-6 mt-4">
                <?php 
                $topCars->data_seek(0); // reset pointer
                while($car = $topCars->fetch_assoc()): ?>
                    <li class="mb-2">
                        <span class="font-semibold text-white"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?></span>
                        <span class="text-gray-400">(<?php echo $car['bookings']; ?> bookings)</span>
                    </li>
                <?php endwhile; ?>
                </ol>
            </div>
        </div>
        <div class="stat-card rounded-xl p-6 border border-gray-800 mb-10">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fas fa-clock text-purple-400"></i> Recent Bookings</h2>
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="py-2 px-4">User</th>
                        <th class="py-2 px-4">Car ID</th>
                        <th class="py-2 px-4">Start</th>
                        <th class="py-2 px-4">End</th>
                        <th class="py-2 px-4">Status</th>
                        <th class="py-2 px-4">Payment</th>
                        <th class="py-2 px-4">Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($b = $recentBookings->fetch_assoc()): ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800/40">
                        <td class="py-2 px-4"><?php echo htmlspecialchars($b['username'] ?? $b['user_name']); ?></td>
                        <td class="py-2 px-4"><?php echo $b['car_id']; ?></td>
                        <td class="py-2 px-4"><?php echo $b['start_date']; ?></td>
                        <td class="py-2 px-4"><?php echo $b['end_date']; ?></td>
                        <td class="py-2 px-4"><?php echo ucfirst($b['status']); ?></td>
                        <td class="py-2 px-4"><?php echo ucfirst($b['payment_status']); ?></td>
                        <td class="py-2 px-4"><?php echo $b['created_at']; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</main>
<script>
// Booking Status Chart
const bookingStatusCtx = document.getElementById('bookingStatusChart').getContext('2d');
new Chart(bookingStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Confirmed', 'Pending', 'Cancelled'],
        datasets: [{
            data: [
                <?php echo $statusData['confirmed'] ?? 0; ?>,
                <?php echo $statusData['pending'] ?? 0; ?>,
                <?php echo $statusData['cancelled'] ?? 0; ?>
            ],
            backgroundColor: ['#22c55e', '#eab308', '#ef4444'],
            borderWidth: 1
        }]
    },
    options: {
        plugins: { legend: { labels: { color: '#e5e5e5' } } },
        cutout: '70%'
    }
});
// Top Cars Chart
const topCarsCtx = document.getElementById('topCarsChart').getContext('2d');
new Chart(topCarsCtx, {
    type: 'bar',
    data: {
        labels: [
            <?php 
            $topCars->data_seek(0); 
            $labels = [];
            $counts = [];
            while($car = $topCars->fetch_assoc()) {
                $labels[] = "'" . addslashes($car['make'] . ' ' . $car['model']) . "'";
                $counts[] = $car['bookings'];
            }
            echo implode(',', $labels);
            ?>
        ],
        datasets: [{
            label: 'Bookings',
            data: [<?php echo implode(',', $counts); ?>],
            backgroundColor: '#f87171',
            borderRadius: 8
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#e5e5e5' } },
            y: { ticks: { color: '#e5e5e5' }, beginAtZero: true }
        }
    }
});
</script>
</body>
</html>
