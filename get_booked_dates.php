<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['car_id'])) {
    echo json_encode([]);
    exit;
}

$car_id = intval($_GET['car_id']);

// Get all booked dates for this car
$stmt = $conn->prepare("
    SELECT start_date, end_date 
    FROM bookings 
    WHERE car_id = ? 
    AND status != 'cancelled'
    AND end_date >= CURDATE()
");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

$bookedDates = [];
while ($row = $result->fetch_assoc()) {
    $start = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

    foreach ($dateRange as $date) {
        $bookedDates[] = $date->format('Y-m-d');
    }
}

echo json_encode($bookedDates); 