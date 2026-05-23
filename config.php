<?php
// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security constants
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 45);
define('OTP_EXPIRY_SECONDS', 120);
define('OTP_MAX_RETRIES', 3);
define('OTP_RESEND_COOLDOWN', 30);

define('USERS_FILE', __DIR__ . '/users.json');

// Google reCAPTCHA v3 — keys from env vars and/or secrets.local.php (never commit real secrets)
function loadAppSecrets() {
    $secrets = [
        'recaptcha_site_key'   => getenv('RECAPTCHA_SITE_KEY') ?: '',
        'recaptcha_secret_key' => getenv('RECAPTCHA_SECRET_KEY') ?: '',
    ];
    $localFile = __DIR__ . '/secrets.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            foreach (['recaptcha_site_key', 'recaptcha_secret_key'] as $key) {
                if (!empty($local[$key])) {
                    $secrets[$key] = $local[$key];
                }
            }
        }
    }
    return $secrets;
}

$appSecrets = loadAppSecrets();
define('RECAPTCHA_SITE_KEY', $appSecrets['recaptcha_site_key']);
define('RECAPTCHA_SECRET_KEY', $appSecrets['recaptcha_secret_key']);
define('RECAPTCHA_MIN_SCORE', 0.5); // 0.0 (bot) – 1.0 (human); 0.5 is Google's typical default

// Load users from JSON, create default if missing
function loadUsers() {
    if (!file_exists(USERS_FILE)) {
        $defaultUsers = [
            ['id' => 1, 'username' => '2023-ADBU-001', 'password_hash' => password_hash('student123', PASSWORD_DEFAULT), 'email' => 'student@adbu.edu.in', 'role' => 'student'],
            ['id' => 2, 'username' => 'EMP-2024-001', 'password_hash' => password_hash('employee123', PASSWORD_DEFAULT), 'email' => 'employee@adbu.edu.in', 'role' => 'employee'],
            ['id' => 3, 'username' => 'PAR-2024-001', 'password_hash' => password_hash('parent123', PASSWORD_DEFAULT), 'email' => 'parent@adbu.edu.in', 'role' => 'parent']
        ];
        file_put_contents(USERS_FILE, json_encode($defaultUsers, JSON_PRETTY_PRINT));
        return $defaultUsers;
    }
    $json = file_get_contents(USERS_FILE);
    return json_decode($json, true);
}

function findUser($username, $role) {
    foreach (loadUsers() as $user) {
        if ($user['username'] === $username && $user['role'] === $role) {
            return $user;
        }
    }
    return null;
}

function trackFailedAttempt($identifier) {
    if (!isset($_SESSION['failed_attempts'])) $_SESSION['failed_attempts'] = [];
    $now = time();
    if (!isset($_SESSION['failed_attempts'][$identifier])) {
        $_SESSION['failed_attempts'][$identifier] = ['count' => 1, 'last' => $now];
    } else {
        $_SESSION['failed_attempts'][$identifier]['count']++;
        $_SESSION['failed_attempts'][$identifier]['last'] = $now;
    }
    if ($_SESSION['failed_attempts'][$identifier]['count'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['locked_until'] = $now + LOGIN_LOCKOUT_TIME;
    }
}

function isLockedOut() {
    if (isset($_SESSION['locked_until']) && $_SESSION['locked_until'] > time()) {
        return $_SESSION['locked_until'] - time();
    }
    return false;
}

function resetFailedAttempts($identifier) {
    unset($_SESSION['failed_attempts'][$identifier]);
    unset($_SESSION['locked_until']);
}

function sendOTP($email, $otp, $username) {
    $logEntry = date('Y-m-d H:i:s') . " - OTP for $email: $otp (user: $username)\n";
    file_put_contents(__DIR__ . '/otp_log.txt', $logEntry, FILE_APPEND);
    return true;
}

function recaptchaIsConfigured() {
    return RECAPTCHA_SITE_KEY !== 'YOUR_SITE_KEY_HERE'
        && RECAPTCHA_SECRET_KEY !== 'YOUR_SECRET_KEY_HERE'
        && RECAPTCHA_SITE_KEY !== ''
        && RECAPTCHA_SECRET_KEY !== '';
}

function canReachHttps() {
    return extension_loaded('openssl') || extension_loaded('curl');
}

/**
 * Verify a reCAPTCHA v3 token with Google. Returns ['ok' => bool, 'message' => string].
 */
function verifyRecaptcha($token, $expectedAction = 'login') {
    if (!recaptchaIsConfigured()) {
        return ['ok' => false, 'message' => 'reCAPTCHA is not configured on the server'];
    }
    if (!canReachHttps()) {
        return ['ok' => false, 'message' => 'Server cannot verify reCAPTCHA: enable the PHP openssl extension (see README).'];
    }
    if (empty($token)) {
        return ['ok' => false, 'message' => 'Security verification failed. Please try again.'];
    }

    $post = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $response = false;
    $transportError = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($verifyUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $transportError = curl_error($ch) ?: 'curl request failed';
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $post,
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($verifyUrl, false, $ctx);
        if ($response === false) {
            $last = error_get_last();
            $transportError = $last['message'] ?? 'HTTPS request failed';
        }
    }

    if ($response === false) {
        error_log('reCAPTCHA siteverify failed: ' . $transportError);
        return ['ok' => false, 'message' => 'Could not reach reCAPTCHA service. Check PHP openssl/curl and internet access.'];
    }

    $result = json_decode($response, true);
    if (empty($result['success'])) {
        return ['ok' => false, 'message' => 'reCAPTCHA verification failed'];
    }
    if (isset($result['action']) && $result['action'] !== $expectedAction) {
        return ['ok' => false, 'message' => 'Invalid security action'];
    }
    $score = $result['score'] ?? 0;
    if ($score < RECAPTCHA_MIN_SCORE) {
        return ['ok' => false, 'message' => 'Security check failed. Please try again later.'];
    }

    return ['ok' => true, 'message' => ''];
}
?>