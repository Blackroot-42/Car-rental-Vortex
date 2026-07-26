<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if ($username && $email && $password && $confirm_password && $full_name) {
        if ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Username or email already exists';
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $phone);
                
                if ($stmt->execute()) {
                    $success = 'Registration successful! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Vortex Rentals</title>
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
<body class="min-h-screen flex items-center justify-center py-12">
    <div class="bg-gradient-to-b from-gray-900 to-black p-8 rounded-xl shadow-2xl w-full max-w-md border border-gray-800">
        <div class="text-center mb-8">
            <a href="index.php" class="text-2xl font-bold">
                <i class="fas fa-car text-red-600"></i>
                VORTEX<span class="text-red-600">RENTALS</span>
            </a>
            <h2 class="text-2xl font-bold mt-6 mb-2">Create Account</h2>
            <p class="text-gray-400">Join our premium car rental service</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-600/20 border border-red-600 text-red-500 px-4 py-3 rounded-lg mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-600/20 border border-green-600 text-green-500 px-4 py-3 rounded-lg mb-6">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Full Name *</label>
                <input type="text" name="full_name" required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Username *</label>
                <input type="text" name="username" required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-2">Email *</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Phone Number</label>
                <input type="tel" name="phone"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-2">Password *</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Confirm Password *</label>
                <input type="password" name="confirm_password" required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
            </div>

            <button type="submit" 
                class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition duration-300 mt-6">
                Create Account
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-400">
                Already have an account? 
                <a href="login.php" class="text-red-500 hover:text-red-400 font-medium">Sign in here</a>
            </p>
        </div>
    </div>
</body>
</html> 