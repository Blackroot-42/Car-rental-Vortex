<?php
require_once 'db.php';

// Fetch available cars with model info
$sql = "SELECT cars.*, car_models.make, car_models.model FROM cars
        LEFT JOIN car_models ON cars.car_model_id = car_models.id
        WHERE cars.available = 1";
$result = $conn->query($sql);

$cars = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cars[] = $row;
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_id = intval($_POST['car_id'] ?? 0);
    // capture contact fields
    $user_name = trim($_POST['user_name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
    $phone = trim($_POST['phone'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';

    if (!$email) {
        $message = "Please provide a valid email address.";
    } elseif ($car_id && $user_name && $start_date && $end_date && $payment_method) {
        // Validate dates
        $today = date('Y-m-d');
        if ($start_date < $today) {
            $message = "Cannot book dates in the past.";
        } elseif ($end_date < $start_date) {
            $message = "End date must be after start date.";
        } else {
            // Calculate number of days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $days = $end->diff($start)->days + 1;

            // Get car price (you can add a price field to your cars table)
            $stmt = $conn->prepare("SELECT c.*, m.make, m.model FROM cars c 
                                  LEFT JOIN car_models m ON c.car_model_id = m.id 
                                  WHERE c.id = ?");
            $stmt->bind_param("i", $car_id);
            $stmt->execute();
            $car = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Calculate total amount (example: $100 per day)
            $daily_rate = 100; // You can make this dynamic based on car type
            $total_amount = $days * $daily_rate;

            // Check for existing bookings
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE car_id = ? AND status != 'cancelled' AND 
                ((start_date <= ? AND end_date >= ?) OR 
                 (start_date <= ? AND end_date >= ?) OR 
                 (start_date >= ? AND end_date <= ?))");
            // types: i(car_id) + s*6 (date placeholders)
            $stmt->bind_param("issssss", $car_id, $end_date, $start_date, $end_date, $start_date, $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = "This car is already booked for the selected dates.";
            } else {
                // ensure bookings table has contact & verification columns
                $requiredCols = [
                    'contact_email' => "ALTER TABLE bookings ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL",
                    'contact_phone' => "ALTER TABLE bookings ADD COLUMN contact_phone VARCHAR(30) DEFAULT NULL",
                    'email_token' => "ALTER TABLE bookings ADD COLUMN email_token VARCHAR(64) DEFAULT NULL",
                    'sms_token' => "ALTER TABLE bookings ADD COLUMN sms_token VARCHAR(64) DEFAULT NULL",
                    'email_verified' => "ALTER TABLE bookings ADD COLUMN email_verified TINYINT(1) DEFAULT 0",
                    'sms_verified' => "ALTER TABLE bookings ADD COLUMN sms_verified TINYINT(1) DEFAULT 0",
                ];
                foreach ($requiredCols as $col => $alterSql) {
                    $colStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = ?");
                    $colStmt->bind_param('s', $col);
                    $colStmt->execute();
                    $colStmt->bind_result($col_count);
                    $colStmt->fetch();
                    $colStmt->close();
                    if (intval($col_count) === 0) {
                        @$conn->query($alterSql);
                    }
                }

                // Start transaction
                $conn->begin_transaction();

                try {
                    // generate tokens
                    $email_token = bin2hex(random_bytes(16));
                    $sms_token = bin2hex(random_bytes(3)); // short token for sms

                    // Insert booking with contact info & tokens
                    $stmt = $conn->prepare("INSERT INTO bookings (car_id, user_name, start_date, end_date, status, total_amount, contact_email, contact_phone, email_token, sms_token) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssdssss", $car_id, $user_name, $start_date, $end_date, $total_amount, $email, $phone, $email_token, $sms_token);
                    $stmt->execute();
                    $booking_id = $conn->insert_id;

                    // Simulate payment processing
                    $payment_success = true; // In real application, integrate with payment gateway
                    
                    if ($payment_success) {
                        // Insert payment record
                        $stmt = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, status) VALUES (?, ?, ?, 'completed')");
                        $stmt->bind_param("ids", $booking_id, $total_amount, $payment_method);
                        $stmt->execute();

                        // Update booking: mark as paid and confirmed
                        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
                        $stmt->bind_param("i", $booking_id);
                        $stmt->execute();

                        // If user is logged in, attach user to booking
                        if (isset($_SESSION['user_id'])) {
                            $uid = intval($_SESSION['user_id']);
                            $uStmt = $conn->prepare("UPDATE bookings SET user_id = ? WHERE id = ?");
                            $uStmt->bind_param('ii', $uid, $booking_id);
                            $uStmt->execute();
                        }

                        // Mark car as unavailable
                        $updateCar = $conn->prepare("UPDATE cars SET available = 0 WHERE id = ?");
                        $updateCar->bind_param("i", $car_id);
                        $updateCar->execute();

                        // Send verification email
                        $host = $_SERVER['HTTP_HOST'];
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $verifyUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']) . "/verify_contact.php?booking_id={$booking_id}&token={$email_token}&type=email";

                        $subject = "Verify your booking contact - Vortex Rentals";
                        $emailMessage = "Hello {$user_name},\n\nThank you for your booking. Your booking is confirmed and the car has been reserved for you. Please verify your email address by clicking the link below:\n\n{$verifyUrl}\n\nIf you didn't make this booking, ignore this email.";
                        $headers = "From: no-reply@{$host}\r\n" . "Content-Type: text/plain; charset=utf-8\r\n";
                        @mail($email, $subject, $emailMessage, $headers);
                        // Log email content for debugging (local dev)
                        file_put_contents(__DIR__ . '/email_logs.txt', date('[Y-m-d H:i] ') . $email . ' ' . str_replace("\n", " ", $emailMessage) . PHP_EOL, FILE_APPEND);

                        // Send SMS only for +212 numbers (Morocco)
                        if (strpos($phone, '+212') === 0) {
                            // In production: integrate with SMS provider (Twilio, Nexmo, etc.)
                            // Here we log the SMS to a file as a placeholder
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'];
                            $smsVerifyUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']) . "/verify_contact.php?booking_id={$booking_id}&token={$sms_token}&type=sms";
                            $smsText = "Verify your Vortex Rentals booking: {$smsVerifyUrl} (or use code {$sms_token})";
                            file_put_contents(__DIR__ . '/sms_logs.txt', date('[Y-m-d H:i] ') . $phone . ' ' . $smsText . PHP_EOL, FILE_APPEND);
                            $message .= " SMS sent to your Moroccan number.";
                        }
                    } else {
                        // Update booking payment status to failed
                        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE id = ?");
                        $stmt->bind_param("i", $booking_id);
                        $stmt->execute();

                        $message = "Payment failed. Please try again.";
                    }

                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "An error occurred. Please try again.";
                }
            }
            $stmt->close();
        }
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reserve a Car | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        .car-card {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            transition: all 0.3s ease;
        }
        .car-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 8px 24px -8px #ff2a2a33;
            border: 1.5px solid #ff2a2a;
        }
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.85);
        }
    </style>
</head>
<body class="min-h-screen flex">
    <?php if (file_exists('sidebar.php')) include 'sidebar.php'; ?>
    <main class="flex-1 p-8">
        <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 border border-gray-800">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-800 pb-6">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">
                        <span class="text-blue-600">RESERVE</span> YOUR CAR
                    </h1>
                    <p class="text-gray-400">Choose a car and book your ride</p>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="mb-6 text-center text-lg <?php echo strpos($message, 'submitted') !== false ? 'text-green-500' : 'text-red-500'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($cars as $car): ?>
                    <div class="car-card rounded-xl shadow-lg border border-gray-800 flex flex-col cursor-pointer hover:shadow-2xl" onclick="showReserveModal(<?php echo htmlspecialchars(json_encode($car), ENT_QUOTES, 'UTF-8'); ?>)">
                        <?php if (!empty($car['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($car['image_url']); ?>" alt="Car" class="w-full h-48 object-cover rounded-t-xl border-b border-gray-800">
                        <?php else: ?>
                            <div class="w-full h-48 flex items-center justify-center bg-gray-900 rounded-t-xl text-gray-600 border-b border-gray-800">
                                <i class="fas fa-car text-5xl"></i>
                            </div>
                        <?php endif; ?>
                        <div class="p-6 flex-1 flex flex-col">
                            <div class="text-xl font-bold text-blue-400 mb-1">
                                <?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>
                            </div>
                            <div class="text-gray-400 mb-2"><?php echo htmlspecialchars($car['year']); ?> • <?php echo htmlspecialchars($car['color'] ?: 'N/A'); ?></div>
                            <div class="mb-4 grid grid-cols-2 gap-2 text-sm">
                                <div><span class="font-semibold">Mileage:</span> <?php echo htmlspecialchars($car['mileage'] ?: 'N/A'); ?> km</div>
                                <div><span class="font-semibold">Transmission:</span> <?php echo htmlspecialchars($car['transmission']); ?></div>
                                <div><span class="font-semibold">Fuel:</span> <?php echo htmlspecialchars($car['fuel_type']); ?></div>
                                <div><span class="font-semibold">Status:</span> <span class="text-green-500 font-semibold">Available</span></div>
                            </div>
                            <button class="mt-auto w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition" onclick="event.stopPropagation(); showReserveModal(<?php echo htmlspecialchars(json_encode($car), ENT_QUOTES, 'UTF-8'); ?>)">
                                Reserve
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Reserve Modal -->
        <div id="reserveModal" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
            <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 max-w-lg w-full mx-4 border border-gray-800 relative">
                <button onclick="closeReserveModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl transition">
                    <i class="fas fa-times"></i>
                </button>
                <div id="reserveModalContent"></div>
            </div>
        </div>
    </main>
    <script>
        function showReserveModal(car) {
            let html = `
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="flex-1">
                        ${car.image_url ? `
                        <div class="mb-6 rounded-lg overflow-hidden border border-gray-800">
                            <img src="${car.image_url}" alt="${car.make} ${car.model}" class="w-full h-40 md:h-56 object-cover">
                        </div>
                        ` : `
                        <div class="mb-6 rounded-lg border border-gray-800 bg-gray-900 h-40 md:h-56 flex items-center justify-center text-gray-600">
                            <i class="fas fa-car text-5xl"></i>
                        </div>
                        `}
                    </div>
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-blue-400 mb-2">${car.make} ${car.model}</h2>
                        <div class="text-gray-400 font-medium mb-4">${car.year} • ${car.color || 'N/A'}</div>
                        <form method="post" class="space-y-4">
                            <input type="hidden" name="car_id" value="${car.id}">
                            <div>
                                <label class="block mb-1 font-semibold">Your Name</label>
                                <input type="text" name="user_name" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" required>
                            </div>
                            <div>
                                <label class="block mb-1 font-semibold">Email</label>
                                <input type="email" name="email" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" required placeholder="you@example.com">
                            </div>
                            <div>
                                <label class="block mb-1 font-semibold">Phone (include country code)</label>
                                <input type="tel" name="phone" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" placeholder="+2126XXXXXXXX" required>
                                <p class="text-xs text-gray-400 mt-1">SMS verification will be sent only for Moroccan numbers starting with <code>+212</code>.</p>
                            </div>
                            <div>
                                <label class="block mb-1 font-semibold">Start Date</label>
                                <input type="date" name="start_date" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" required>
                            </div>
                            <div>
                                <label class="block mb-1 font-semibold">End Date</label>
                                <input type="date" name="end_date" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" required>
                            </div>
                            <div>
                                <label class="block mb-1 font-semibold">Payment Method</label>
                                <select name="payment_method" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" required>
                                    <option value="">Select payment method</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div id="payment-summary" class="bg-gray-800/50 p-4 rounded-lg mb-4 hidden">
                                <h3 class="font-bold text-lg mb-2">Payment Summary</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Daily Rate:</span>
                                        <span>${car.price_mad ? 'MAD ' + Number(car.price_mad).toFixed(2) : 'MAD 100.00'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Number of Days:</span>
                                        <span id="days-count">0</span>
                                    </div>
                                    <div class="border-t border-gray-700 my-2 pt-2">
                                        <div class="flex justify-between font-bold">
                                            <span>Total Amount:</span>
                                            <span id="total-amount">MAD 0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition font-bold">Submit Booking</button>
                        </form>
                    </div>
                </div>
            `;
            document.getElementById('reserveModalContent').innerHTML = html;
            document.getElementById('reserveModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Add event listeners for date changes
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            const paymentSummary = document.getElementById('payment-summary');
            const daysCount = document.getElementById('days-count');
            const totalAmount = document.getElementById('total-amount');

            function updatePaymentSummary() {
                const start = new Date(startDateInput.value);
                const end = new Date(endDateInput.value);
                
                if (start && end && !isNaN(start) && !isNaN(end) && end >= start) {
                    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                    const dailyRate = 100;
                    const total = days * dailyRate;
                    
                    daysCount.textContent = days;
                    totalAmount.textContent = 'MAD ' + total.toFixed(2);
                    paymentSummary.classList.remove('hidden');
                } else {
                    paymentSummary.classList.add('hidden');
                }
            }

            startDateInput.addEventListener('change', updatePaymentSummary);
            endDateInput.addEventListener('change', updatePaymentSummary);
        }
        function closeReserveModal() {
            document.getElementById('reserveModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        // Close modal when clicking outside content
        document.getElementById('reserveModal').addEventListener('click', function(e) {
            if (e.target === this) closeReserveModal();
        });
        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeReserveModal();
        });
    </script>
</body>
</html>
