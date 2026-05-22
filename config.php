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
?>