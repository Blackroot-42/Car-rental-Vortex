<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (password_verify($current_password, $result['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
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

    // Handle document upload
    if (isset($_POST['upload_document'])) {
        $document_type = $_POST['document_type'];
        $document_number = trim($_POST['document_number']);
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        // Handle file upload
        if (isset($_FILES['document_image']) && $_FILES['document_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['document_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['document_image']['tmp_name'], $target_path)) {
                    $stmt = $conn->prepare("INSERT INTO verification_documents (user_id, document_type, document_number, document_image, expiry_date, status, submission_date) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                    $stmt->bind_param("issss", $user_id, $document_type, $document_number, $target_path, $expiry_date);
                    
                    if ($stmt->execute()) {
                        $message = "Document uploaded successfully. Waiting for verification.";
                    } else {
                        $error = "Failed to save document information.";
                        unlink($target_path); // Delete uploaded file if database insert fails
                    }
                } else {
                    $error = "Failed to upload file.";
                }
            } else {
                $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, PDF";
            }
        } else {
            $error = "Please select a file to upload.";
        }
    }
}

// Fetch user's verification documents
$stmt = $conn->prepare("SELECT * FROM verification_documents WHERE user_id = ? ORDER BY submission_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management | Car Rental</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        
        .fade-in {
            animation: fadeIn 1s ease-in forwards;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        .slide-up {
            animation: slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            opacity: 0;
            transform: translateY(30px);
        }
        
        @keyframes slideUp {
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Navigation Bar -->
    <nav class="w-full bg-black text-white shadow-lg sticky top-0 z-50 border-b border-gray-900">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center space-x-2">
                <i class="fas fa-car text-2xl text-red-600"></i>
                <a href="index.php" class="text-2xl font-bold">VORTEX<span class="text-red-600">RENTALS</span></a>
            </div>
            <div class="hidden md:flex space-x-8 text-lg items-center">
                <a href="index.php" class="nav-link hover:text-red-500 transition">Home</a>
                <a href="carlist.php" class="nav-link hover:text-red-500 transition">Cars List</a>
                <a href="my_bookings.php" class="nav-link hover:text-red-500 transition">My Bookings</a>
                <a href="profile.php" class="nav-link hover:text-red-500 transition">Profile</a>
                <a href="logout.php" class="nav-link hover:text-red-500 transition">Logout</a>
                <a href="contact.php" class="ml-4 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-full font-medium transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-phone-alt mr-2"></i>Contact
                </a>
            </div>
            <button class="md:hidden text-2xl focus:outline-none">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Profile Section -->
    <div class="flex-1 py-16 md:py-20 bg-gradient-to-b from-black to-gray-900">
        <div class="max-w-4xl mx-auto px-4">
            <div class="text-center mb-16 fade-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="text-white">MY</span> <span class="text-red-600">PROFILE</span>
                </h2>
                <div class="w-24 h-1 bg-red-600 mx-auto mb-4"></div>
                <p class="text-gray-400">Manage your account information and preferences</p>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-600/20 border border-green-600 text-green-500 px-4 py-3 rounded-lg mb-6 fade-in">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-600/20 border border-red-600 text-red-500 px-4 py-3 rounded-lg mb-6 fade-in">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Profile Information -->
                <div class="bg-gradient-to-b from-gray-900 to-black p-8 rounded-xl border border-gray-800 slide-up">
                    <h3 class="text-xl font-bold text-white mb-6">Profile Information</h3>
                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium mb-2">Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-gray-400">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Address</label>
                            <textarea name="address" rows="3"
                                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition duration-300">
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-gradient-to-b from-gray-900 to-black p-8 rounded-xl border border-gray-800 slide-up animate-delay-100">
                    <h3 class="text-xl font-bold text-white mb-6">Change Password</h3>
                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium mb-2">Current Password</label>
                            <input type="password" name="current_password" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">New Password</label>
                            <input type="password" name="new_password" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <button type="submit" name="change_password"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition duration-300">
                            Change Password
                        </button>
                    </form>

                    <!-- Account Statistics -->
                    <div class="mt-8 pt-8 border-t border-gray-800">
                        <h3 class="text-xl font-bold text-white mb-6">Account Statistics</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <?php
                            // Get booking statistics
                            $stmt = $conn->prepare("SELECT 
                                COUNT(*) as total_bookings,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings
                                FROM bookings WHERE user_id = ?");
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $stats = $stmt->get_result()->fetch_assoc();
                            ?>
                            <div class="bg-gray-800/50 p-4 rounded-lg">
                                <div class="text-gray-400 text-sm">Total Bookings</div>
                                <div class="text-white font-bold text-xl"><?php echo $stats['total_bookings']; ?></div>
                            </div>
                            <div class="bg-gray-800/50 p-4 rounded-lg">
                                <div class="text-gray-400 text-sm">Completed Bookings</div>
                                <div class="text-white font-bold text-xl"><?php echo $stats['completed_bookings']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verification Status -->
            <div class="mt-8 bg-gradient-to-b from-gray-900 to-black rounded-xl border border-gray-800 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Verification Status</h2>
                    <span class="px-4 py-2 rounded-full text-sm <?php 
                        echo $user['is_verified'] ? 'bg-green-600/20 text-green-500' : 
                            ($user['verification_status'] === 'rejected' ? 'bg-red-600/20 text-red-500' : 'bg-yellow-600/20 text-yellow-500'); 
                    ?>">
                        <?php 
                        echo $user['is_verified'] ? 'Verified' : 
                            ($user['verification_status'] === 'rejected' ? 'Rejected' : 'Pending Verification'); 
                        ?>
                    </span>
                </div>

                <?php if ($user['verification_status'] === 'rejected' && $user['verification_notes']): ?>
                    <div class="bg-red-600/20 border border-red-600 text-red-500 px-4 py-3 rounded-lg mb-6">
                        <p class="font-semibold">Verification Rejected</p>
                        <p class="mt-1"><?php echo htmlspecialchars($user['verification_notes']); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Document Upload Form -->
                <form method="post" enctype="multipart/form-data" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Document Type</label>
                            <select name="document_type" required
                                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                                <option value="passport">Passport</option>
                                <option value="drivers_license">Driver's License</option>
                                <option value="national_id">National ID</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Document Number</label>
                            <input type="text" name="document_number" required
                                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Expiry Date (if applicable)</label>
                        <input type="date" name="expiry_date"
                            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Document Image</label>
                        <input type="file" name="document_image" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        <p class="mt-1 text-sm text-gray-400">Accepted formats: JPG, JPEG, PNG, PDF</p>
                    </div>
                    <button type="submit" name="upload_document"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg transition">
                        Upload Document
                    </button>
                </form>

                <!-- Document History -->
                <?php if (!empty($documents)): ?>
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold mb-4">Document History</h3>
                        <div class="space-y-4">
                            <?php foreach ($documents as $doc): ?>
                                <div class="bg-gray-800/50 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-medium"><?php echo ucfirst($doc['document_type']); ?></p>
                                            <p class="text-sm text-gray-400">Submitted: <?php echo date('M d, Y H:i', strtotime($doc['submission_date'])); ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-sm <?php 
                                            echo $doc['status'] === 'approved' ? 'bg-green-600/20 text-green-500' : 
                                                ($doc['status'] === 'rejected' ? 'bg-red-600/20 text-red-500' : 'bg-yellow-600/20 text-yellow-500'); 
                                        ?>">
                                            <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($doc['admin_notes']): ?>
                                        <p class="text-sm text-gray-400 mt-2"><?php echo htmlspecialchars($doc['admin_notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-black pt-16 pb-8 border-t border-gray-900">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
                <div>
                    <h3 class="text-xl font-bold mb-6 flex items-center">
                        <i class="fas fa-car mr-3 text-red-600"></i> 
                        <span class="text-white">VORTEX</span><span class="text-red-600">RENTALS</span>
                    </h3>
                    <p class="text-gray-400 mb-6">Premium performance and luxury vehicle rentals for the discerning driver.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-6 text-white">QUICK LINKS</h4>
                    <ul class="space-y-3">
                        <li><a href="index.php" class="text-gray-400 hover:text-red-500 transition">Home</a></li>
                        <li><a href="allcars.php" class="text-gray-400 hover:text-red-500 transition">Our Fleet</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500 transition">Special Offers</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500 transition">Locations</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500 transition">FAQ</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-6 text-white">CONTACT US</h4>
                    <ul class="space-y-3">
                        <li class="flex items-start text-gray-400">
                            <i class="fas fa-map-marker-alt mt-1 mr-3 text-red-600"></i>
                            <span>123 Performance Ave, Motor City</span>
                        </li>
                        <li class="flex items-center text-gray-400">
                            <i class="fas fa-phone-alt mr-3 text-red-600"></i>
                            <span>(555) 123-4567</span>
                        </li>
                        <li class="flex items-center text-gray-400">
                            <i class="fas fa-envelope mr-3 text-red-600"></i>
                            <span>contact@vortexrentals.com</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-6 text-white">NEWSLETTER</h4>
                    <p class="text-gray-400 mb-4">Subscribe for exclusive offers and updates</p>
                    <form class="flex">
                        <input type="email" placeholder="Your email" class="px-4 py-3 bg-gray-800 text-white rounded-l-lg focus:outline-none focus:ring-2 focus:ring-red-600 w-full border border-gray-700">
                        <button type="submit" class="px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-r-lg transition">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm mb-4 md:mb-0">© 2023 VORTEX RENTALS. ALL RIGHTS RESERVED.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Privacy Policy</a>
                    <a href="admin_login.php" class="text-gray-500 hover:text-red-500 text-sm transition">Admin</a>
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Terms of Service</a>
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Initialize animations when elements come into view
        const animateOnScroll = () => {
            const elements = document.querySelectorAll('.fade-in, .slide-up');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (elementPosition < screenPosition) {
                    element.style.animationPlayState = 'running';
                }
            });
        };
        
        // Set initial state
        document.querySelectorAll('.fade-in, .slide-up').forEach(el => {
            el.style.animationPlayState = 'paused';
        });
        
        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);
    </script>
</body>
</html> 