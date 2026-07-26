<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $booking_id, $user_id);
    if ($stmt->execute()) {
        $message = "Booking cancelled successfully!";
    } else {
        $message = "Failed to cancel booking.";
    }
}

// Fetch user's bookings with car details and payment information
$stmt = $conn->prepare("
    SELECT b.*, c.year, c.color, c.image_url, cm.make, cm.model,
           p.payment_method, p.payment_date, p.status as payment_status
    FROM bookings b 
    JOIN cars c ON b.car_id = c.id 
    JOIN car_models cm ON c.car_model_id = cm.id 
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        
        .booking-card {
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border: 1px solid #2a2a2a;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px -10px rgba(255, 0, 0, 0.3);
            border-color: #ff2a2a;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #f59e0b;
            color: #000;
        }
        
        .status-confirmed {
            background-color: #10b981;
            color: #fff;
        }
        
        .status-cancelled {
            background-color: #ef4444;
            color: #fff;
        }

        .payment-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .payment-paid {
            background-color: #10b981;
            color: #fff;
        }
        
        .payment-pending {
            background-color: #f59e0b;
            color: #000;
        }
        
        .payment-failed {
            background-color: #ef4444;
            color: #fff;
        }
    </style>
</head>
<body class="min-h-screen">
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="nav-link hover:text-red-500 transition">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link hover:text-red-500 transition">Login</a>
                <?php endif; ?>
                <a href="#" class="ml-4 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-full font-medium transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-phone-alt mr-2"></i>Contact
                </a>
            </div>
            <button class="md:hidden text-2xl focus:outline-none">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">My Bookings</h1>
            <a href="index.php" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-full transition-all">
                <i class="fas fa-plus mr-2"></i>New Booking
            </a>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo strpos($message, 'successfully') !== false ? 'bg-green-600/20 border border-green-600 text-green-500' : 'bg-red-600/20 border border-red-600 text-red-500'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-12">
                <i class="fas fa-calendar-times text-5xl text-gray-600 mb-4"></i>
                <h3 class="text-xl text-gray-400 font-medium">No Bookings Found</h3>
                <p class="text-gray-500 mt-2">You haven't made any bookings yet</p>
                <a href="carlist.php" class="inline-block mt-4 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-full transition-all">
                    Browse Cars
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card rounded-xl overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div class="flex items-start space-x-4">
                                    <?php if (!empty($booking['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?>"
                                             class="w-24 h-24 object-cover rounded-lg">
                                    <?php else: ?>
                                        <div class="w-24 h-24 bg-gray-800 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-car text-3xl text-gray-600"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?></h3>
                                        <p class="text-gray-400"><?php echo htmlspecialchars($booking['year'] . ' • ' . $booking['color']); ?></p>
                                        <div class="mt-2 flex space-x-2">
                                            <div class="flex items-center">
                                                <span class="text-xs text-gray-400 mr-2">Rental:</span>
                                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                                    <?php echo strtoupper($booking['status']); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="text-xs text-gray-400 mr-2">Payment:</span>
                                                <span class="payment-badge payment-<?php echo strtolower($booking['payment_status'] ?? 'pending'); ?>">
                                                    <?php echo strtoupper($booking['payment_status'] ?? 'PENDING'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-end">
                                    <div class="text-right">
                                        <p class="text-sm text-gray-400">Booking Date</p>
                                        <p class="font-medium"><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></p>
                                    </div>
                                    <div class="mt-2 text-right">
                                        <p class="text-sm text-gray-400">Rental Period</p>
                                        <p class="font-medium">
                                            <?php echo date('M d', strtotime($booking['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($booking['total_amount']): ?>
                                        <div class="mt-2 text-right">
                                            <p class="text-sm text-gray-400">Total Amount</p>
                                            <p class="font-bold text-lg text-green-500"><?php echo format_mad($booking['total_amount']); ?></p>
                                            <?php if ($booking['payment_method']): ?>
                                                <p class="text-xs text-gray-400">Paid via <?php echo ucfirst($booking['payment_method']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end space-x-4">
                                <?php if ($booking['status'] !== 'cancelled' && $booking['payment_status'] !== 'paid' && $booking['payment_status'] !== 'completed'): ?>
                                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" 
                                       class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all">
                                        <i class="fas fa-credit-card mr-2"></i>Pay Now
                                    </a>
                                <?php endif; ?>
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="post" class="inline-block" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" name="cancel_booking" 
                                            class="px-4 py-2 bg-gray-800 hover:bg-red-600 text-white rounded-lg transition-all">
                                            <i class="fas fa-times mr-2"></i>Cancel Booking
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-black pt-16 pb-8 border-t border-gray-900 mt-12">
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
                        <li><a href="carlist.php" class="text-gray-400 hover:text-red-500 transition">Our Fleet</a></li>
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
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Terms of Service</a>
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Sitemap</a>
                    <a href="admin.php" class="text-gray-500 hover:text-red-500 text-sm transition">Admin</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html> 