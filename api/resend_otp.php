<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['temp_auth'])) {
    echo json_encode(['success' => false, 'message' => 'No pending authentication']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true) ?: [];
if (!isset($data['csrf_token']) || !isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}
$temp = $_SESSION['temp_auth'];
if (time() < $temp['otp_resend_time']) {
    $wait = $temp['otp_resend_time'] - time();
    echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before resending"]);
    exit;
}
$newOtp = sprintf('%06d', random_int(0, 999999));
$temp['otp'] = $newOtp;
$temp['otp_expiry'] = time() + OTP_EXPIRY_SECONDS;
$temp['otp_attempts'] = 0;
$temp['otp_resend_time'] = time() + OTP_RESEND_COOLDOWN;
$_SESSION['temp_auth'] = $temp;
sendOTP($temp['email'], $newOtp, $temp['username']);
echo json_encode(['success' => true, 'message' => 'New OTP sent']);
?>