<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if ($lockout = isLockedOut()) {
    echo json_encode(['success' => false, 'message' => "Too many attempts. Try again in {$lockout} seconds."]);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}
if (!isset($_SESSION['captcha']) || strtoupper($data['captcha'] ?? '') !== $_SESSION['captcha']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CAPTCHA code']);
    exit;
}
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? '';
if (!in_array($role, ['student', 'employee', 'parent'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}
$user = findUser($username, $role);
if (!$user || !password_verify($password, $user['password_hash'])) {
    trackFailedAttempt($username);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}
resetFailedAttempts($username);
if ($role === 'employee') {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $expiry = time() + OTP_EXPIRY_SECONDS;
    $_SESSION['temp_auth'] = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'otp' => $otp,
        'otp_expiry' => $expiry,
        'otp_attempts' => 0,
        'otp_resend_time' => time() + OTP_RESEND_COOLDOWN
    ];
    sendOTP($user['email'], $otp, $user['username']);
    echo json_encode(['success' => true, 'requires_otp' => true, 'message' => 'OTP sent']);
} else {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['login_time'] = time();
    echo json_encode(['success' => true, 'requires_otp' => false]);
}
?>