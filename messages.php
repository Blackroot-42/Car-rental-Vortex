<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

// Fetch all messages (assuming a 'messages' table with id, user_id, name, email, subject, message, created_at)
$messages = $conn->query("SELECT m.*, u.username FROM messages m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
                    <span class="text-red-600">ADMIN</span> MESSAGES
                </h1>
                <p class="text-gray-400">View and manage user messages</p>
            </div>
        </div>
        <div class="stat-card rounded-xl p-6 border border-gray-800 mb-10">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fas fa-envelope text-blue-400"></i> Inbox</h2>
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="py-2 px-4">User</th>
                        <th class="py-2 px-4">Name</th>
                        <th class="py-2 px-4">Email</th>
                        <th class="py-2 px-4">Subject</th>
                        <th class="py-2 px-4">Message</th>
                        <th class="py-2 px-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($messages && $messages->num_rows > 0): ?>
                    <?php while($msg = $messages->fetch_assoc()): ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800/40">
                        <td class="py-2 px-4"><?php echo htmlspecialchars($msg['username'] ?? '-'); ?></td>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($msg['name']); ?></td>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($msg['email']); ?></td>
                        <td class="py-2 px-4"><?php echo htmlspecialchars($msg['subject']); ?></td>
                        <td class="py-2 px-4 max-w-xs truncate" title="<?php echo htmlspecialchars($msg['message']); ?>"><?php echo htmlspecialchars(mb_strimwidth($msg['message'], 0, 50, '...')); ?></td>
                        <td class="py-2 px-4"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-gray-400 py-8">No messages found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>


