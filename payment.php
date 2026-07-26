<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if booking_id is provided
if (!isset($_GET['booking_id'])) {
    header('Location: my_bookings.php');
    exit;
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];
$message = '';

// Fetch booking details
$stmt = $conn->prepare("
    SELECT b.*, c.year, c.color, c.image_url, cm.make, cm.model,
           DATEDIFF(b.end_date, b.start_date) + 1 as days,
           COALESCE(c.daily_rate, 100) as daily_rate,
           (DATEDIFF(b.end_date, b.start_date) + 1) * COALESCE(c.daily_rate, 100) as total_amount
    FROM bookings b 
    JOIN cars c ON b.car_id = c.id 
    JOIN car_models cm ON c.car_model_id = cm.id 
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status != 'paid'
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: my_bookings.php');
    exit;
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    $card_number = $_POST['card_number'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    
    // Simulate payment processing
    $payment_success = true; // In real application, integrate with payment gateway
    
    if ($payment_success) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert payment record
            $stmt = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, status) VALUES (?, ?, ?, 'completed')");
            $stmt->bind_param("ids", $booking_id, $booking['total_amount'], $payment_method);
            $stmt->execute();
            
            // Update booking payment status
            $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            
            $conn->commit();
            $message = "Payment successful! Your booking is now confirmed.";
            
            // Redirect after successful payment
            header("refresh:2;url=my_bookings.php");
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Payment failed. Please try again.";
        }
    } else {
        $message = "Payment failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        
        .payment-card {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border: 1px solid #2a2a2a;
        }
        
        .payment-card:hover {
            border-color: #ff2a2a;
        }
        
        .input-field {
            background-color: #1a1a1a;
            border: 1px solid #2a2a2a;
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            border-color: #ff2a2a;
            box-shadow: 0 0 0 2px rgba(255, 42, 42, 0.1);
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
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-12">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Complete Payment</h1>
            <a href="my_bookings.php" class="text-gray-400 hover:text-red-500 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Bookings
            </a>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo strpos($message, 'successful') !== false ? 'bg-green-600/20 border border-green-600 text-green-500' : 'bg-red-600/20 border border-red-600 text-red-500'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Booking Summary -->
            <div class="payment-card rounded-xl p-6">
                <h2 class="text-xl font-bold mb-4">Booking Summary</h2>
                <div class="space-y-4">
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
                            <h3 class="text-lg font-bold"><?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?></h3>
                            <p class="text-gray-400"><?php echo htmlspecialchars($booking['year'] . ' • ' . $booking['color']); ?></p>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-800 pt-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-400">Rental Period</span>
                            <span><?php echo date('M d', strtotime($booking['start_date'])); ?> - <?php echo date('M d, Y', strtotime($booking['end_date'])); ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-400">Number of Days</span>
                            <span><?php echo $booking['days']; ?> days</span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-400">Daily Rate</span>
                            <span><?php echo format_mad($booking['daily_rate']); ?></span>
                        </div>
                        <div class="border-t border-gray-800 pt-4 mt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold">Total Amount</span>
                                <span class="text-2xl font-bold text-green-500"><?php echo format_mad($booking['total_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="payment-card rounded-xl p-6">
                <h2 class="text-xl font-bold mb-4">Payment Details</h2>
                <form method="post" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Payment Method</label>
                        <select name="payment_method" class="w-full input-field rounded-lg px-4 py-2 text-white" required>
                            <option value="">Select payment method</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Card Number</label>
                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" 
                               class="w-full input-field rounded-lg px-4 py-2 text-white" 
                               pattern="[0-9\s]{13,19}" maxlength="19" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Expiry Date</label>
                            <input type="text" name="expiry_date" placeholder="MM/YY" 
                                   class="w-full input-field rounded-lg px-4 py-2 text-white" 
                                   pattern="(0[1-9]|1[0-2])\/([0-9]{2})" maxlength="5" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">CVV</label>
                            <input type="text" name="cvv" placeholder="123" 
                                   class="w-full input-field rounded-lg px-4 py-2 text-white" 
                                   pattern="[0-9]{3,4}" maxlength="4" required>
                        </div>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition-all transform hover:scale-105">
                            Pay <?php echo format_mad($booking['total_amount']); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Format card number with spaces
        document.querySelector('input[name="card_number"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = '';
            for(let i = 0; i < value.length; i++) {
                if(i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            e.target.value = formattedValue;
        });

        // Format expiry date
        document.querySelector('input[name="expiry_date"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0,2) + '/' + value.slice(2);
            }
            e.target.value = value;
        });
    </script>
</body>
</html> 