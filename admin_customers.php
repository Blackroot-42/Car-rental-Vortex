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

// Handle customer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $user_id = intval($_POST['user_id']);
        $status = in_array($_POST['status'], ['active','suspended','inactive']) ? $_POST['status'] : 'active';
        $notes = trim($_POST['notes'] ?? '');

        // Ensure 'status' column exists
        $colStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'");
        $colStmt->execute();
        $colStmt->bind_result($col_count);
        $colStmt->fetch();
        $colStmt->close();

        if (intval($col_count) === 0) {
            if (!$conn->query("ALTER TABLE users ADD COLUMN status ENUM('active','suspended','inactive') DEFAULT 'active'")) {
                // Fallback: update admin_notes only
                $stmt = $conn->prepare("UPDATE users SET admin_notes = ? WHERE id = ?");
                $stmt->bind_param("si", $notes, $user_id);
                if ($stmt->execute()) {
                    $message = "Customer notes updated (status column missing).";
                } else {
                    $error = "Failed to update customer notes: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET status = ?, admin_notes = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $notes, $user_id);
                if ($stmt->execute()) {
                    $message = "Customer status added and updated successfully.";
                } else {
                    $error = "Failed to update customer status: " . $conn->error;
                }
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ?, admin_notes = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $notes, $user_id);
            if ($stmt->execute()) {
                $message = "Customer status updated successfully.";
            } else {
                $error = "Failed to update customer status: " . $conn->error;
            }
        }
    }
}

// Fetch all customers with their verification status
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT r.id) as total_rentals,
           COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.id END) as active_rentals,
           MAX(r.end_date) as last_rental_date
    FROM users u
    LEFT JOIN rentals r ON u.id = r.user_id
    WHERE u.role = 'user'
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
        /* Table scroll & sticky header */
        .table-scroll { max-height: 65vh; }
        .sticky-header th { position: sticky; top: 0; background: rgba(10,10,10,0.95); backdrop-filter: blur(4px); z-index: 20; }
        /* Modal helpers for smooth open/close */
        .modal-panel { transition: transform 0.2s ease, opacity 0.2s ease; }
    </style>
</head>
<body class="min-h-screen flex">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 ml-64">
        <div class="p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold">Customer Management</h1>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-600/20 border border-green-600 text-green-500 px-4 py-3 rounded-lg mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-600/20 border border-red-600 text-red-500 px-4 py-3 rounded-lg mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Customer List -->
            <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl border border-gray-800 p-6">
                <div class="table-scroll overflow-auto rounded">
                    <table class="w-full min-w-[720px]">
                        <thead class="sticky-header">
                            <tr class="text-left border-b border-gray-800">
                                <th class="py-3 px-4 font-semibold">Customer</th>
                                <th class="py-3 px-4 font-semibold">Contact</th>
                                <th class="py-3 px-4 font-semibold">Status</th>
                                <th class="py-3 px-4 font-semibold">Verification</th>
                                <th class="py-3 px-4 font-semibold">Rentals</th>
                                <th class="py-3 px-4 font-semibold">Last Rental</th>
                                <th class="py-3 px-4 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="border-b border-gray-800">
                                    <td class="py-4">
                                        <div>
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($customer['full_name']); ?></p>
                                            <p class="text-sm text-gray-400">Joined: <?php echo date('M d, Y', strtotime($customer['created_at'])); ?></p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <div>
                                            <p class="text-sm"><?php echo htmlspecialchars($customer['email']); ?></p>
                                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($customer['phone']); ?></p>
                                        </div>
                                    </td>
                                    <?php $status = $customer['status'] ?? 'active'; ?>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?php 
                                            echo $status === 'active' ? 'bg-green-600/20 text-green-500' : ($status === 'suspended' ? 'bg-red-600/20 text-red-500' : 'bg-gray-600/20 text-gray-500'); 
                                        ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="py-4">
                                        <span class="px-3 py-1 rounded-full text-sm <?php 
                                            echo $customer['is_verified'] ? 'bg-green-600/20 text-green-500' : 
                                                ($customer['verification_status'] === 'rejected' ? 'bg-red-600/20 text-red-500' : 'bg-yellow-600/20 text-yellow-500'); 
                                        ?>">
                                            <?php 
                                            echo $customer['is_verified'] ? 'Verified' : 
                                                ($customer['verification_status'] === 'rejected' ? 'Rejected' : 'Pending'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-4">
                                        <div>
                                            <p class="text-sm">Total: <?php echo $customer['total_rentals']; ?></p>
                                            <p class="text-sm text-gray-400">Active: <?php echo $customer['active_rentals']; ?></p>
                                        </div>
                                    </td>
                                    <td class="py-4 text-gray-400">
                                        <?php echo $customer['last_rental_date'] ? date('M d, Y', strtotime($customer['last_rental_date'])) : 'Never'; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex flex-wrap sm:flex-row flex-col sm:space-x-2 space-y-2 sm:space-y-0 justify-end items-center">
                                            <button onclick="openStatusModal(<?php echo htmlspecialchars(json_encode($customer)); ?>)"
                                                class="px-3 py-1 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition whitespace-nowrap">
                                                Update Status
                                            </button>
                                            <a href="admin_verification.php?user_id=<?php echo $customer['id']; ?>"
                                                class="px-3 py-1 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition whitespace-nowrap">
                                                View Documents
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
    <div id="statusModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center opacity-0 transition-opacity duration-200">
        <div class="modal-panel bg-gray-900 rounded-xl p-6 w-full max-w-md max-h-[80vh] overflow-auto transform scale-95 transition-all duration-200">
            <h2 class="text-xl font-bold mb-4">Update Customer Status</h2>
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
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" name="update_status"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg transition">
                        Update
                    </button>
                    <button type="button" onclick="closeStatusModal()"
                        class="flex-1 bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 rounded-lg transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(customer) {
            const modal = document.getElementById('statusModal');
            const panel = modal.querySelector('.modal-panel');
            document.getElementById('modal_user_id').value = customer.id;
            document.getElementById('modal_status').value = customer.status || 'active';
            document.getElementById('modal_notes').value = customer.admin_notes || '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // animate in
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                if (panel) {
                    panel.classList.remove('scale-95');
                    panel.classList.add('scale-100');
                }
            }, 20);
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusModal');
            const panel = modal.querySelector('.modal-panel');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            if (panel) {
                panel.classList.remove('scale-100');
                panel.classList.add('scale-95');
            }
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 200);
        }

        // Close when clicking outside modal panel
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });

        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('statusModal');
                if (!modal.classList.contains('hidden')) closeStatusModal();
            }
        });
    </script>
</body>
</html> 