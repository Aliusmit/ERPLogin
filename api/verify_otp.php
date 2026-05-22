<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['temp_auth'])) {
    echo json_encode(['success' => false, 'message' => 'No pending authentication']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$enteredOtp = trim($data['otp'] ?? '');
$temp = $_SESSION['temp_auth'];

if (time() > $temp['otp_expiry']) {
    unset($_SESSION['temp_auth']);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Login again.']);
    exit;
}
if ($temp['otp_attempts'] >= OTP_MAX_RETRIES) {
    unset($_SESSION['temp_auth']);
    echo json_encode(['success' => false, 'message' => 'Maximum OTP attempts exceeded. Login again.']);
    exit;
}
if ($enteredOtp !== $temp['otp']) {
    $_SESSION['temp_auth']['otp_attempts']++;
    $remaining = OTP_MAX_RETRIES - $_SESSION['temp_auth']['otp_attempts'];
    echo json_encode(['success' => false, 'message' => "Invalid OTP. {$remaining} attempts left.", 'remaining_attempts' => $remaining]);
    exit;
}
$_SESSION['user_id'] = $temp['user_id'];
$_SESSION['username'] = $temp['username'];
$_SESSION['role'] = $temp['role'];
$_SESSION['email'] = $temp['email'];
$_SESSION['login_time'] = time();
unset($_SESSION['temp_auth']);
echo json_encode(['success' => true, 'message' => 'OTP verified']);
?>