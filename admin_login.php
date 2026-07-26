<?php
require_once 'db.php';
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, password FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($admin_id, $hash);
            $stmt->fetch();
            if (password_verify($password, $hash)) {
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['admin_username'] = $username;
                header('Location: dashboard.php');
                exit;
            } else {
                $message = "Invalid username or password.";
            }
        } else {
            $message = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Vortex Rentals</title>
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
            background-image: linear-gradient(rgba(0, 0, 0, 0.85), url('https://images.unsplash.com/photo-1494976388531-d1058494cdd8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            background-blend-mode: multiply;
        }
        
        .login-box {
            box-shadow: 0 15px 30px rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .input-field {
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            border-color: #ff2a2a;
            box-shadow: 0 0 0 3px rgba(255, 42, 42, 0.2);
        }
        
        .glow-text {
            text-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="login-box bg-gradient-to-b from-gray-900 to-black rounded-xl p-8 w-full max-w-md border border-gray-800">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="flex justify-center items-center mb-4">
                <i class="fas fa-car text-red-600 text-4xl mr-2"></i>
                <span class="text-3xl font-bold">
                    <span class="text-white">VORTEX</span>
                    <span class="text-red-600 glow-text">RENTALS</span>
                </span>
            </div>
            <h1 class="text-2xl font-bold text-white">ADMIN PORTAL</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="mb-6 p-3 bg-red-900/50 border border-red-800 text-red-200 rounded-lg text-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="post" class="space-y-6">
            <div>
                <label for="username" class="block text-gray-300 text-sm font-medium mb-2">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-500"></i>
                    </div>
                    <input type="text" id="username" name="username" placeholder="Enter your username" 
                           class="input-field w-full pl-10 pr-3 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-0"
                           required>
                </div>
            </div>
            
            <div>
                <label for="password" class="block text-gray-300 text-sm font-medium mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-500"></i>
                    </div>
                    <input type="password" id="password" name="password" placeholder="Enter your password" 
                           class="input-field w-full pl-10 pr-3 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-0"
                           required>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" 
                           class="h-4 w-4 bg-gray-800 border-gray-700 rounded text-red-600 focus:ring-red-600">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-300">Remember me</label>
                </div>
                
                <div class="text-sm">
                    <a href="#" class="font-medium text-red-400 hover:text-red-300">Forgot password?</a>
                </div>
            </div>
            
            <div>
                <button type="submit" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-red-600 to-red-800 hover:from-red-700 hover:to-red-900 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-in-alt mr-2"></i> SIGN IN
                </button>
            </div>
        </form>
        
        <div class="mt-8 text-center text-gray-400 text-sm">
            <p>© <?php echo date('Y'); ?> VORTEX RENTALS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>