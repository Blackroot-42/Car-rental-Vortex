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

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = TRUE, verification_status = 'verified', verification_notes = ? WHERE id = ?");
        $stmt->bind_param("si", $notes, $user_id);
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = FALSE, verification_status = 'unverified', verification_notes = ? WHERE id = ?");
        $stmt->bind_param("si", $notes, $user_id);
    }

    if (isset($stmt) && $stmt->execute()) {
        $message = "Verification status updated successfully";
    } else {
        $error = "Error updating verification status: " . $conn->error;
    }
}

// Get user ID from query parameter or default to all pending verifications
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// First, get the user information
$user_query = "
    SELECT * FROM users 
    WHERE id = ?
";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Then, get the user's documents
$documents_query = "
    SELECT * FROM verification_documents 
    WHERE user_id = ?
    ORDER BY submission_date DESC
";
$stmt = $conn->prepare($documents_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare the data for display
$verification_data = [
    'user' => [
        'id' => $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'is_verified' => $user['is_verified'],
        'verification_status' => $user['verification_status'],
        'verification_notes' => $user['verification_notes'],
        'created_at' => $user['created_at']
    ],
    'documents' => array_map(function($doc) {
        return [
            'id' => $doc['id'],
            'type' => $doc['document_type'],
            'image' => $doc['document_image'],
            'submitted_at' => $doc['submission_date'],
            'status' => $doc['status'],
            'notes' => $doc['admin_notes']
        ];
    }, $documents)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Requests | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
    </style>
</head>
<body class="min-h-screen flex">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 ml-64">
        <div class="p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold">Verification Requests</h1>
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

            <!-- Verification Request -->
            <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl border border-gray-800 p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h2 class="text-xl font-bold"><?php echo htmlspecialchars($verification_data['user']['full_name']); ?></h2>
                        <p class="text-gray-400"><?php echo htmlspecialchars($verification_data['user']['email']); ?></p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="openVerificationModal('approve', <?php echo $verification_data['user']['id']; ?>)"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                            Approve
                        </button>
                        <button onclick="openVerificationModal('reject', <?php echo $verification_data['user']['id']; ?>)"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                            Reject
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-400">Phone</p>
                        <p><?php echo htmlspecialchars($verification_data['user']['phone'] ?? 'Not provided'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Joined</p>
                        <p><?php echo date('M d, Y', strtotime($verification_data['user']['created_at'])); ?></p>
                    </div>
                </div>

                <?php if (!empty($verification_data['documents'])): ?>
                    <div class="mt-4">
                        <h3 class="text-lg font-semibold mb-2">Submitted Documents</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($verification_data['documents'] as $document): ?>
                                <div class="bg-gray-800/50 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-medium"><?php echo ucfirst($document['type']); ?></p>
                                            <p class="text-sm text-gray-400">
                                                Submitted: <?php echo date('M d, Y', strtotime($document['submitted_at'])); ?>
                                            </p>
                                        </div>
                                        <?php if ($document['status']): ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php 
                                                echo $document['status'] === 'verified' ? 'bg-green-600/20 text-green-500' : 
                                                    ($document['status'] === 'unverified' ? 'bg-red-600/20 text-red-500' : 'bg-yellow-600/20 text-yellow-500'); 
                                            ?>">
                                                <?php echo ucfirst($document['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($document['image']): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($document['image']); ?>" 
                                                 alt="Document" 
                                                 class="w-full h-48 object-cover rounded-lg">
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($document['notes']): ?>
                                        <p class="text-sm text-gray-400 mt-2">
                                            <?php echo htmlspecialchars($document['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-4 text-yellow-500">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        No documents submitted
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div id="verificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-900 rounded-xl p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Update Verification Status</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="user_id" id="modal_user_id">
                <input type="hidden" name="action" id="modal_action">
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <textarea name="notes" id="modal_notes" rows="3"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"></textarea>
                </div>
                <div class="flex space-x-4">
                    <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg transition">
                        Confirm
                    </button>
                    <button type="button" onclick="closeVerificationModal()"
                        class="flex-1 bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 rounded-lg transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openVerificationModal(action, userId) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_action').value = action;
            document.getElementById('verificationModal').classList.remove('hidden');
            document.getElementById('verificationModal').classList.add('flex');
        }

        function closeVerificationModal() {
            document.getElementById('verificationModal').classList.add('hidden');
            document.getElementById('verificationModal').classList.remove('flex');
        }
    </script>
</body>
</html> 