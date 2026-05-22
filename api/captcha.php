<?php
session_start();
header('Content-Type: application/json');
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$captcha = '';
for ($i = 0; $i < 4; $i++) $captcha .= $chars[random_int(0, strlen($chars)-1)];
$_SESSION['captcha'] = $captcha;
echo json_encode(['captcha' => $captcha]);
?>