<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['temp_auth'])) {
    echo json_encode(['success' => false, 'message' => 'No pending authentication']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['csrf_token']) || !isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token'])
     {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

$enteredOtp = preg_replace('/\D/', '', trim($data['otp'] ?? ''));
if (strlen($enteredOtp) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 6-digit OTP']);
    exit;
}
$temp = $_SESSION['temp_auth'];
$storedOtp = (string) ($temp['otp'] ?? '');

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
if (!hash_equals($storedOtp, $enteredOtp)) {
    $temp['otp_attempts'] = ($temp['otp_attempts'] ?? 0) + 1;
    $_SESSION['temp_auth'] = $temp;
    $remaining = OTP_MAX_RETRIES - $temp['otp_attempts'];
    echo json_encode(['success' => false, 'message' => "Invalid OTP. {$remaining} attempts left.", 'remaining_attempts' => $remaining]);
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = $temp['user_id'];
$_SESSION['username'] = $temp['username'];
$_SESSION['role'] = $temp['role'];
$_SESSION['email'] = $temp['email'];
$_SESSION['login_time'] = time();
unset($_SESSION['temp_auth']);
echo json_encode(['success' => true, 'message' => 'OTP verified']);
?>