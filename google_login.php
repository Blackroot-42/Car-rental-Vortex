<?php
session_start();
require_once 'google_config.php';

// generate and store state
try {
    $state = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $state = uniqid('st_', true);
}
$_SESSION['google_oauth_state'] = $state;

$auth_url = google_auth_url($state);
header('Location: ' . $auth_url);
exit;
?>
