<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['temp_auth'])) {
    echo json_encode(['success' => false, 'message' => 'No pending authentication']);
    exit;
}
$temp = $_SESSION['temp_auth'];
if (time() < $temp['otp_resend_time']) {
    $wait = $temp['otp_resend_time'] - time();
    echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before resending"]);
    exit;
}
$newOtp = sprintf("%06d", mt_rand(0, 999999));
$newExpiry = time() + OTP_EXPIRY_SECONDS;
$_SESSION['temp_auth']['otp'] = $newOtp;
$_SESSION['temp_auth']['otp_expiry'] = $newExpiry;
$_SESSION['temp_auth']['otp_attempts'] = 0;
$_SESSION['temp_auth']['otp_resend_time'] = time() + OTP_RESEND_COOLDOWN;
sendOTP($temp['email'], $newOtp, $temp['username']);
echo json_encode(['success' => true, 'message' => 'New OTP sent']);
?>