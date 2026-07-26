<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status'])) {
    $booking_id = intval($_POST['booking_id']);
    $status = $_POST['status'];
    if (in_array($status, ['pending', 'confirmed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $booking_id);
        $stmt->execute();
        $stmt->close();
    }
    // Optional: redirect to avoid form resubmission
    header("Location: bookings.php");
    exit;
}

// Fetch bookings with car and model info
$sql = "SELECT b.*, c.year, c.image_url, m.make, m.model, p.payment_method, p.payment_date
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN car_models m ON c.car_model_id = m.id
        LEFT JOIN payments p ON b.id = p.booking_id
        ORDER BY b.start_date DESC";
$result = $conn->query($sql);

$bookings = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bookings | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 p-8">
        <div class="bg-white rounded-xl shadow-2xl p-8 border border-gray-200">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-200 pb-6">
                <div>
                    <h1 class="text-3xl font-bold text-blue-700 mb-2">
                        <span class="text-red-600">BOOKINGS</span> MANAGEMENT
                    </h1>
                    <p class="text-gray-500">View and manage all car bookings</p>
                </div>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full" id="bookings-table">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">ID</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">Car</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">User</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">Start Date</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">End Date</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">Status</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">Payment</th>
                            <th class="py-3 px-4 text-left text-gray-700 font-medium uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($b['id']); ?></td>
                                <td class="py-3 px-4 flex items-center gap-2">
                                    <?php if (!empty($b['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($b['image_url']); ?>" class="w-12 h-8 object-cover rounded shadow border border-gray-300" alt="Car">
                                    <?php endif; ?>
                                    <span>
                                        <?php echo htmlspecialchars($b['make'] . ' ' . $b['model'] . ' ' . $b['year']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($b['user_name']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($b['start_date']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($b['end_date']); ?></td>
                                <td class="py-3 px-4">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <select name="status" class="px-2 py-1 rounded text-xs font-semibold
                                            <?php
                                                $status = strtolower($b['status']);
                                                if ($status === 'confirmed') echo 'bg-green-100 text-green-700';
                                                elseif ($status === 'pending') echo 'bg-yellow-100 text-yellow-700';
                                                elseif ($status === 'cancelled') echo 'bg-red-100 text-red-700';
                                                else echo 'bg-gray-100 text-gray-700';
                                            ?>"
                                            onchange="this.form.submit()"
                                        >
                                            <option value="pending" <?php if ($status === 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="confirmed" <?php if ($status === 'confirmed') echo 'selected'; ?>>Confirmed</option>
                                            <option value="cancelled" <?php if ($status === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="py-3 px-4">
                                    <?php
                                    $paymentStatus = strtolower($b['payment_status']);
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    switch ($paymentStatus) {
                                        case 'paid':
                                            $statusClass = 'bg-green-100 text-green-700';
                                            $statusText = 'Paid';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-yellow-100 text-yellow-700';
                                            $statusText = 'Pending';
                                            break;
                                        case 'failed':
                                            $statusClass = 'bg-red-100 text-red-700';
                                            $statusText = 'Failed';
                                            break;
                                        default:
                                            $statusClass = 'bg-gray-100 text-gray-700';
                                            $statusText = 'Pending';
                                    }
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                    <?php if ($paymentStatus === 'paid' && !empty($b['payment_method'])): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php echo ucfirst($b['payment_method']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($b['total_amount']): ?>
                                        <div class="font-semibold"><?php echo format_mad($b['total_amount']); ?></div>
                                        <?php if ($paymentStatus === 'paid' && !empty($b['payment_date'])): ?>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('M d, Y', strtotime($b['payment_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400">
                                <i class="fas fa-calendar-times text-2xl mb-2"></i>
                                <div>No bookings found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
