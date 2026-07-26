<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Fetch user's verification status
$stmt = $conn->prepare("SELECT is_verified, verification_status, verification_notes FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch user's submitted documents
$stmt = $conn->prepare("SELECT * FROM verification_documents WHERE user_id = ? ORDER BY submission_date DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_type = $_POST['document_type'] ?? '';
    $document_number = trim($_POST['document_number'] ?? '');
    $expiry_date = $_POST['expiry_date'] ?? '';
    
    if ($document_type && $document_number && isset($_FILES['document_image'])) {
        $file = $_FILES['document_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = "Only JPG, JPEG & PNG files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $error = "File size must be less than 5MB.";
        } else {
            $upload_dir = 'uploads/verification/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'doc_' . time() . '_' . basename($file['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("INSERT INTO verification_documents (user_id, document_type, document_number, document_image, expiry_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $_SESSION['user_id'], $document_type, $document_number, $target_path, $expiry_date);
                
                if ($stmt->execute()) {
                    $message = "Document uploaded successfully. Please wait for verification.";
                    
                    // Update user's verification status
                    $stmt = $conn->prepare("UPDATE users SET verification_status = 'pending' WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT is_verified, verification_status, verification_notes FROM users WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    // Refresh documents list
                    $stmt = $conn->prepare("SELECT * FROM verification_documents WHERE user_id = ? ORDER BY submission_date DESC");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                } else {
                    $error = "Failed to save document information.";
                }
            } else {
                $error = "Failed to upload file.";
            }
        }
    } else {
        $error = "Please fill in all required fields and upload a document.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification | Vortex Rentals</title>
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

    <!-- Verification Section -->
    <div class="flex-1 py-16 md:py-20 bg-gradient-to-b from-black to-gray-900">
        <div class="max-w-4xl mx-auto px-4">
            <div class="text-center mb-16 fade-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="text-white">IDENTITY</span> <span class="text-red-600">VERIFICATION</span>
                </h2>
                <div class="w-24 h-1 bg-red-600 mx-auto mb-4"></div>
                <p class="text-gray-400">Upload your identification document for verification</p>
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

            <!-- Verification Status -->
            <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800 mb-8 slide-up">
                <h3 class="text-xl font-bold text-white mb-4">Verification Status</h3>
                <div class="flex items-center space-x-4">
                    <?php if ($user['is_verified']): ?>
                        <div class="w-12 h-12 rounded-full bg-green-600/20 flex items-center justify-center text-green-500">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-white">Verified</h4>
                            <p class="text-gray-400">Your account has been verified</p>
                        </div>
                    <?php elseif ($user['verification_status'] === 'rejected'): ?>
                        <div class="w-12 h-12 rounded-full bg-red-600/20 flex items-center justify-center text-red-500">
                            <i class="fas fa-times text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-white">Rejected</h4>
                            <p class="text-gray-400"><?php echo htmlspecialchars($user['verification_notes'] ?? 'Please submit a new document.'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-full bg-yellow-600/20 flex items-center justify-center text-yellow-500">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-white">Pending Verification</h4>
                            <p class="text-gray-400">Your document is being reviewed</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Upload Form -->
                <div class="bg-gradient-to-b from-gray-900 to-black p-8 rounded-xl border border-gray-800 slide-up">
                    <h3 class="text-xl font-bold text-white mb-6">Upload Document</h3>
                    <form method="post" enctype="multipart/form-data" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium mb-2">Document Type</label>
                            <select name="document_type" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                                <option value="">Select Document Type</option>
                                <option value="passport">Passport</option>
                                <option value="id_card">ID Card</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Document Number</label>
                            <input type="text" name="document_number" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Expiry Date</label>
                            <input type="date" name="expiry_date"
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Document Image</label>
                            <input type="file" name="document_image" required accept="image/*"
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                            <p class="text-sm text-gray-400 mt-2">Max file size: 5MB. Accepted formats: JPG, JPEG, PNG</p>
                        </div>
                        
                        <button type="submit"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition duration-300">
                            Upload Document
                        </button>
                    </form>
                </div>

                <!-- Submitted Documents -->
                <div class="bg-gradient-to-b from-gray-900 to-black p-8 rounded-xl border border-gray-800 slide-up animate-delay-100">
                    <h3 class="text-xl font-bold text-white mb-6">Submitted Documents</h3>
                    <?php if (empty($documents)): ?>
                        <p class="text-gray-400">No documents submitted yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($documents as $doc): ?>
                                <div class="bg-gray-800/50 p-4 rounded-lg">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas <?php echo $doc['document_type'] === 'passport' ? 'fa-passport' : 'fa-id-card'; ?> text-red-500"></i>
                                            <span class="font-semibold text-white">
                                                <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                            </span>
                                        </div>
                                        <span class="text-sm <?php 
                                            echo $doc['status'] === 'approved' ? 'text-green-500' : 
                                                ($doc['status'] === 'rejected' ? 'text-red-500' : 'text-yellow-500'); 
                                        ?>">
                                            <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <p>Number: <?php echo htmlspecialchars($doc['document_number']); ?></p>
                                        <p>Submitted: <?php echo date('M d, Y', strtotime($doc['submission_date'])); ?></p>
                                        <?php if ($doc['expiry_date']): ?>
                                            <p>Expires: <?php echo date('M d, Y', strtotime($doc['expiry_date'])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($doc['admin_notes']): ?>
                                            <p class="mt-2 text-red-400"><?php echo htmlspecialchars($doc['admin_notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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