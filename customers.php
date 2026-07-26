<?php
session_start();
require_once 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

// Handle status update
if (isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id']);
    $status = in_array($_POST['status'], ['active','suspended','inactive']) ? $_POST['status'] : 'active';
    $notes = trim($_POST['notes'] ?? '');

    // Check if 'status' column exists
    $colStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'");
    $colStmt->execute();
    $colStmt->bind_result($col_count);
    $colStmt->fetch();
    $colStmt->close();

    if (intval($col_count) === 0) {
        // Try to add the column if missing
        if (!$conn->query("ALTER TABLE users ADD COLUMN status ENUM('active','suspended','inactive') DEFAULT 'active'")) {
            // Fallback: update only admin_notes if we couldn't add the column
            $stmt = $conn->prepare("UPDATE users SET admin_notes = ? WHERE id = ?");
            $stmt->bind_param("si", $notes, $user_id);
            if ($stmt->execute()) {
                $message = "Customer notes updated (status column missing).";
            } else {
                $error = "Error updating customer notes: " . $conn->error;
            }
        } else {
            // Column added successfully, proceed to update
            $stmt = $conn->prepare("UPDATE users SET status = ?, admin_notes = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $notes, $user_id);
            if ($stmt->execute()) {
                $message = "Customer status added and updated successfully.";
            } else {
                $error = "Error updating customer status: " . $conn->error;
            }
        }
    } else {
        // Column exists, proceed normally
        $stmt = $conn->prepare("UPDATE users SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $notes, $user_id);
        if ($stmt->execute()) {
            $message = "Customer status updated successfully";
        } else {
            $error = "Error updating customer status: " . $conn->error;
        }
    }
}

// Fetch all customers with their booking information
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT b.id) as total_bookings,
           COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN b.id END) as active_bookings,
           MAX(b.end_date) as last_booking_date
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            /* Removed negative margin for mobile */
        }
    </style>
</head>
<body class="min-h-screen flex">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 ml-64">
        <div class="p-4 md:p-8">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold">Customer Management</h1>
                    <p class="text-gray-400 mt-1">Manage and monitor customer accounts</p>
                </div>
                <div class="flex gap-4">
                    <div class="relative">
                        <input type="text" placeholder="Search customers..." 
                            class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-red-600 w-full md:w-64 text-gray-300">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-2">
                        <i class="fas fa-filter"></i>
                        <span class="hidden md:inline">Filter</span>
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-600/20 border border-green-600 text-green-500 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-600/20 border border-red-600 text-red-500 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-gradient-to-br from-gray-900 to-black rounded-xl border border-gray-800 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Customers</p>
                            <p class="text-2xl font-bold mt-1"><?php echo count($customers); ?></p>
                        </div>
                        <div class="bg-blue-600/20 p-3 rounded-lg">
                            <i class="fas fa-users text-blue-500"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-gray-900 to-black rounded-xl border border-gray-800 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Active Customers</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count(array_filter($customers, function($c) { return ($c['status'] ?? 'active') === 'active'; })); ?>
                            </p>
                        </div>
                        <div class="bg-green-600/20 p-3 rounded-lg">
                            <i class="fas fa-user-check text-green-500"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-gray-900 to-black rounded-xl border border-gray-800 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Pending Verification</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count(array_filter($customers, function($c) { return !($c['is_verified'] ?? false) && ($c['verification_status'] ?? 'pending') === 'pending'; })); ?>
                            </p>
                        </div>
                        <div class="bg-yellow-600/20 p-3 rounded-lg">
                            <i class="fas fa-clock text-yellow-500"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-gray-900 to-black rounded-xl border border-gray-800 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Suspended Accounts</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count(array_filter($customers, function($c) { return ($c['status'] ?? 'active') === 'suspended'; })); ?>
                            </p>
                        </div>
                        <div class="bg-red-600/20 p-3 rounded-lg">
                            <i class="fas fa-user-slash text-red-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer List -->
            <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl border border-gray-800 p-4 md:p-6">
                <div class="table-container">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="text-left border-b border-gray-800">
                                <th class="pb-4 font-semibold">Customer</th>
                                <th class="pb-4 font-semibold">Contact</th>
                                <th class="pb-4 font-semibold">Status</th>
                                <th class="pb-4 font-semibold">Verification</th>
                                <th class="pb-4 font-semibold">Bookings</th>
                                <th class="pb-4 font-semibold">Last Booking</th>
                                <th class="pb-4 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-900/50 transition">
                                    <td class="py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-400"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-white"><?php echo htmlspecialchars($customer['full_name'] ?? 'N/A'); ?></p>
                                                <p class="text-sm text-gray-400">Joined: <?php echo date('M d, Y', strtotime($customer['created_at'] ?? 'now')); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <div>
                                            <p class="text-sm flex items-center gap-2">
                                                <i class="fas fa-envelope text-gray-400"></i>
                                                <?php echo htmlspecialchars($customer['email']); ?>
                                            </p>
                                            <p class="text-sm text-gray-400 flex items-center gap-2 mt-1">
                                                <i class="fas fa-phone text-gray-400"></i>
                                                <?php echo htmlspecialchars($customer['phone'] ?? 'Not provided'); ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <span class="px-3 py-1 rounded-full text-sm <?php 
                                            $status = $customer['status'] ?? 'active';
                                            echo $status === 'active' ? 'bg-green-600/20 text-green-500' : 
                                                ($status === 'suspended' ? 'bg-red-600/20 text-red-500' : 'bg-gray-600/20 text-gray-500'); 
                                        ?>">
                                            <i class="fas <?php 
                                                echo $status === 'active' ? 'fa-check-circle' : 
                                                    ($status === 'suspended' ? 'fa-ban' : 'fa-clock'); 
                                            ?> mr-1"></i>
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="py-4">
                                        <span class="px-3 py-1 rounded-full text-sm <?php 
                                            $isVerified = $customer['is_verified'] ?? false;
                                            $verificationStatus = $customer['verification_status'] ?? 'pending';
                                            echo $isVerified ? 'bg-green-600/20 text-green-500' : 
                                                ($verificationStatus === 'rejected' ? 'bg-red-600/20 text-red-500' : 'bg-yellow-600/20 text-yellow-500'); 
                                        ?>">
                                            <i class="fas <?php 
                                                echo $isVerified ? 'fa-check-circle' : 
                                                    ($verificationStatus === 'rejected' ? 'fa-times-circle' : 'fa-clock'); 
                                            ?> mr-1"></i>
                                            <?php 
                                            echo $isVerified ? 'Verified' : 
                                                ($verificationStatus === 'rejected' ? 'Rejected' : 'Pending'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="text-center">
                                                <p class="text-lg font-semibold"><?php echo $customer['total_bookings']; ?></p>
                                                <p class="text-xs text-gray-400">Total</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-lg font-semibold"><?php echo $customer['active_bookings']; ?></p>
                                                <p class="text-xs text-gray-400">Active</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 text-gray-400">
                                        <?php if ($customer['last_booking_date']): ?>
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php echo date('M d, Y', strtotime($customer['last_booking_date'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openStatusModal(<?php echo htmlspecialchars(json_encode($customer)); ?>)"
                                                class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition flex items-center gap-1">
                                                <i class="fas fa-edit"></i>
                                                <span class="hidden md:inline">Update</span>
                                            </button>
                                            <a href="admin_verification.php?user_id=<?php echo $customer['id']; ?>"
                                                class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition flex items-center gap-1">
                                                <i class="fas fa-file-alt"></i>
                                                <span class="hidden md:inline">Documents</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-900 rounded-xl p-6 w-full max-w-md mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Update Customer Status</h2>
                <button onclick="closeStatusModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post" class="space-y-4">
                <input type="hidden" name="user_id" id="modal_user_id">
                <div>
                    <label class="block text-sm font-medium mb-2">Status</label>
                    <select name="status" id="modal_status"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <textarea name="notes" id="modal_notes" rows="3"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"></textarea>
                </div>
                <div class="flex space-x-4">
                    <button type="submit" name="update_status"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg transition flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i>
                        Update
                    </button>
                    <button type="button" onclick="closeStatusModal()"
                        class="flex-1 bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 rounded-lg transition flex items-center justify-center gap-2">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(customer) {
            document.getElementById('modal_user_id').value = customer.id;
            document.getElementById('modal_status').value = customer.status || 'active';
            document.getElementById('modal_notes').value = customer.admin_notes || '';
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusModal').classList.remove('flex');
        }

        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>