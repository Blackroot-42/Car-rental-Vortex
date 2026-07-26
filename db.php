<?php
$host = 'localhost';
$user = 'root';
$password = ''; // Change if your MySQL password is different
$database = 'cars';
$port = 3306; // Default MySQL port
$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Global currency formatting helpers
 */
function format_mad($amount) {
    // Ensure numeric and format with thousand separators and 2 decimals
    $val = is_numeric($amount) ? floatval($amount) : 0.0;
    return 'MAD ' . number_format($val, 2, '.', ',');
}

function format_currency($amount, $currency = 'MAD') {
    // For now we only format MAD; expand as needed
    if (strtoupper($currency) === 'MAD') return format_mad($amount);
    return $currency . ' ' . number_format(floatval($amount), 2, '.', ',');
}
?>
