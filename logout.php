<?php
session_start();

// Clear session data
$_SESSION = [];

// Remove session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

// Destroy session
session_destroy();

// Redirect to homepage
header('Location: index.php', true, 303);
exit;