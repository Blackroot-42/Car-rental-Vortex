<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);

    if ($username) {
        $stmt = $conn->prepare("UPDATE admin SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $username, $_SESSION['admin_id']);
        if ($stmt->execute()) {
            $_SESSION['admin_username'] = $username;
            $message = "Profile updated successfully.";
        } else {
            $error = "Failed to update profile.";
        }
    } else {
        $error = "Username cannot be empty.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $error = "New passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $stmt->bind_result($hashed);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($current, $hashed)) {
            $new_hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hashed, $_SESSION['admin_id']);
            if ($stmt->execute()) {
                $message = "Password changed successfully.";
            } else {
                $error = "Failed to change password.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// Fetch admin info
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$stmt->bind_result($admin_username);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <span class="text-red-600">ADMIN</span> SETTINGS
                    </h1>
                    <p class="text-gray-400">Manage your account and preferences</p>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Profile Settings -->
                <div class="stat-card rounded-xl p-8 border border-gray-800">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-user-cog text-red-500"></i> Profile
                    </h2>
                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-gray-400 mb-2 font-medium">Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($admin_username); ?>" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white" required>
                        </div>
                        <button type="submit" name="update_profile" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition flex items-center gap-2">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
                <!-- Password Settings -->
                <div class="stat-card rounded-xl p-8 border border-gray-800">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-key text-yellow-500"></i> Change Password
                    </h2>
                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-gray-400 mb-2 font-medium">Current Password</label>
                            <input type="password" name="current_password" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white" required>
                        </div>
                        <div>
                            <label class="block text-gray-400 mb-2 font-medium">New Password</label>
                            <input type="password" name="new_password" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white" required>
                        </div>
                        <div>
                            <label class="block text-gray-400 mb-2 font-medium">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white" required>
                        </div>
                        <button type="submit" name="change_password" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-6 rounded-lg transition flex items-center gap-2">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Preferences (Creative Section) -->
            <div class="mt-12">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-blue-500"></i> Preferences
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="stat-card rounded-xl p-6 border border-gray-800 flex flex-col items-center">
                        <div class="mb-3">
                            <i class="fas fa-moon text-2xl text-purple-400"></i>
                        </div>
                        <div class="text-white font-medium mb-2">Dark Mode</div>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked disabled class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:bg-red-600 transition"></div>
                            <span class="ml-3 text-gray-400 text-sm">Always On</span>
                        </label>
                    </div>
                    <div class="stat-card rounded-xl p-6 border border-gray-800 flex flex-col items-center">
                        <div class="mb-3">
                            <i class="fas fa-bell text-2xl text-yellow-400"></i>
                        </div>
                        <div class="text-white font-medium mb-2">Notifications</div>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:bg-green-600 transition"></div>
                            <span class="ml-3 text-gray-400 text-sm">Enable Email Alerts</span>
                        </label>
                    </div>
                    <div class="stat-card rounded-xl p-6 border border-gray-800 flex flex-col items-center">
                        <div class="mb-3">
                            <i class="fas fa-language text-2xl text-blue-400"></i>
                        </div>
                        <div class="text-white font-medium mb-2">Language</div>
                        <select class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                            <option>English</option>
                            <option>Español</option>
                            <option>Français</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>