<?php
session_start();
require_once 'google_config.php';
require_once 'db.php';

// Basic checks
if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    die('Invalid state.');
}

if (!isset($_GET['code'])) {
    die('No code returned from Google.');
}

$code = $_GET['code'];

// Exchange code for tokens
$token_url = 'https://oauth2.googleapis.com/token';
$post_fields = http_build_query([
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
]);

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$resp = curl_exec($ch);
if ($resp === false) {
    die('Token request failed: ' . curl_error($ch));
}
curl_close($ch);

$token_data = json_decode($resp, true);
if (empty($token_data['access_token'])) {
    die('Failed to obtain access token.');
}

$access_token = $token_data['access_token'];

// Fetch user info
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($access_token);
$userinfo = @file_get_contents($userinfo_url);
if ($userinfo === false) {
    die('Failed to fetch user info.');
}

$user = json_decode($userinfo, true);
if (empty($user['email'])) {
    die('Email not available from Google account.');
}

$email = $user['email'];
$name = $user['name'] ?? '';

// Determine whether users table has an 'email' column
$hasEmailCol = false;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email'");
if ($res) {
    $row = $res->fetch_assoc();
    $hasEmailCol = intval($row['cnt']) > 0;
}

// Try to find existing user
if ($hasEmailCol) {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
} else {
    // fallback: try username matching the email
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $email);
}
$stmt->execute();
$result = $stmt->get_result();

if ($existing = $result->fetch_assoc()) {
    // log user in
    $_SESSION['user_id'] = $existing['id'];
    $_SESSION['username'] = $existing['username'];
    header('Location: index.php');
    exit;
}

// No existing user - create one
$username = $email;
$random_password = bin2hex(random_bytes(8));
$password_hash = password_hash($random_password, PASSWORD_DEFAULT);

if ($hasEmailCol) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $email, $password_hash);
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param('ss', $username, $password_hash);
}

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $_SESSION['user_id'] = $new_id;
    $_SESSION['username'] = $username;
    header('Location: index.php');
    exit;
} else {
    die('Failed to create local user account.');
}

?>
