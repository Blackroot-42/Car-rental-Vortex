<?php
require_once 'db.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? 'email';

$message = '';

if (!$booking_id || !$token) {
    $message = 'Invalid verification link.';
} else {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        $message = 'Booking not found.';
    } else {
        if ($type === 'email') {
            if (!empty($booking['email_token']) && hash_equals($booking['email_token'], $token)) {
                $upd = $conn->prepare("UPDATE bookings SET email_verified = 1, email_token = NULL WHERE id = ?");
                $upd->bind_param('i', $booking_id);
                if ($upd->execute()) {
                    $message = 'Email verified successfully. Thank you!';
                } else {
                    $message = 'Unable to verify email at this time.';
                }
                $upd->close();
            } else {
                $message = 'Invalid or expired email verification link.';
            }
        } elseif ($type === 'sms') {
            if (!empty($booking['sms_token']) && hash_equals($booking['sms_token'], $token)) {
                $upd = $conn->prepare("UPDATE bookings SET sms_verified = 1, sms_token = NULL WHERE id = ?");
                $upd->bind_param('i', $booking_id);
                if ($upd->execute()) {
                    $message = 'Phone number verified successfully. Thank you!';
                } else {
                    $message = 'Unable to verify phone at this time.';
                }
                $upd->close();
            } else {
                $message = 'Invalid or expired SMS verification link.';
            }
        } else {
            $message = 'Unknown verification type.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verification | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-black text-white flex items-center justify-center">
    <div class="bg-gray-900 rounded-lg p-8 max-w-xl w-full text-center">
        <h1 class="text-2xl font-bold mb-4">Verification</h1>
        <p class="text-gray-300"><?php echo htmlspecialchars($message); ?></p>
        <div class="mt-6">
            <a href="index.php" class="px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">Return to home</a>
        </div>
    </div>
</body>
</html>