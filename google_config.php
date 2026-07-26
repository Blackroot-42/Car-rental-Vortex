<?php
// Google OAuth configuration - set these to your app credentials
// Update these values before using the Google Sign-In feature.
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// Example: http://localhost/carso/google_callback.php
define('GOOGLE_REDIRECT_URI', 'http://localhost/carso/google_callback.php');

// Optionally set a default scope
define('GOOGLE_OAUTH_SCOPE', 'openid email profile');

// Helper: build the Google auth URL
function google_auth_url($state) {
    $params = [
        'response_type' => 'code',
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => GOOGLE_OAUTH_SCOPE,
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

?>