<?php
require_once 'config.php';

// Validate existing session: if user_id is set but login_time is missing or too old (> 8 hours), destroy session
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time']) > 28800) {
        session_destroy();
        session_start();
        $_SESSION = [];
    }
}

// Consider the user fully logged-in only if main auth exists
// (and NOT while an OTP is pending).
$hasMainAuth = isset($_SESSION['user_id']);
$hasPendingOtp = isset($_SESSION['temp_auth']);
$isLoggedIn = $hasMainAuth && !$hasPendingOtp;
$userRole = $_SESSION['role'] ?? null;
$username = $_SESSION['username'] ?? null;


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ADBU ERP Login</title>
    <link rel="stylesheet" href="style.css"/>
</head>
<body>
<header>
    <div class="topbar">
        <div class="brand">
            <img src="logo.webp" alt="ADBU Logo" width="80" height="80" decoding="async" fetchpriority="high">
            <div class="brand-text">
                <h1>Assam Don Bosco University</h1>
                <p>Enterprise Resource Portal</p>
            </div>
        </div>
        <div class="top-right">ERP Access Portal</div>
    </div>
</header>
<main>
    <div class="portal-panel">
        <div class="left-panel">
            <div class="session-bar" id="sessionBar">
                <div class="session-info">👋 Welcome, <strong id="sessionUser"><?php echo htmlspecialchars($username ?? ''); ?></strong><br><small id="sessionRole"><?php echo ucfirst($userRole ?? ''); ?> Dashboard</small></div>
                <button class="logout-btn" id="logoutBtn">Logout</button>
            </div>
            <div id="publicContent" <?php echo $isLoggedIn ? 'style="display:none;"' : ''; ?>>
                <div class="erp-badge">Unified ERP Access</div>
                <h1 class="main-title">ADBU ERP</h1>
                <p class="subtitle">Access university services, academic resources and institutional systems through the central ERP gateway.</p>
                <div class="login-grid">
                    <button class="portal-btn login-trigger" data-role="Student"><span class="btn-title">Student Login</span><span class="btn-desc">Academic & student services access</span></button>
                    <button class="portal-btn login-trigger" data-role="Employee"><span class="btn-title">Employee Login</span><span class="btn-desc">Faculty & staff access portal</span></button>
                    <button class="portal-btn login-trigger" data-role="Other User"><span class="btn-title">Other User Login</span><span class="btn-desc">External and guest account access</span></button>
                    <button class="portal-btn" id="admissionBtn"><span class="btn-title">Admission Portal</span><span class="btn-desc">Admissions & applications</span></button>
                    <button class="portal-btn" id="jobBtn" style="grid-column: span 2;"><span class="btn-title">Job Portal</span><span class="btn-desc">Recruitment & career opportunities</span></button>
                </div>
            </div>
<div id="dashboardContent" <?php echo $isLoggedIn ? '' : 'style="display:none;"'; ?>>
                <div class="erp-badge">Welcome Back</div>
                <div style="display:flex; gap:14px; align-items:center; justify-content:flex-end; margin-bottom:10px;">
                    <button class="logout-btn" id="dashboardLogoutBtn" type="button">Logout</button>
                </div>
                <h2 class="main-title" style="font-size:32px;">Dashboard</h2>
                <p class="subtitle">You are logged in as <strong><?php echo ucfirst($userRole ?? ''); ?></strong>.<br>Use the quick links below to access services.</p>
                <div class="login-grid">
                    <div class="portal-btn" style="cursor:default;">📊 My Profile</div>
                    <div class="portal-btn" style="cursor:default;">📚 Courses</div>
                    <div class="portal-btn" style="cursor:default;">💰 Fees</div>
                    <div class="portal-btn" style="cursor:default;">📢 Notifications</div>
                </div>
            </div>
        </div>
        <div class="right-panel">
            <h2>System Notice</h2>
            <p>Please use your official university credentials to sign in. Ensure that you are accessing the ERP through a secure and trusted network.</p>
            <div class="info-box"><h4>Maintenance Window</h4><p>ERP services may be temporarily unavailable during scheduled system maintenance activities.</p></div>
            <div class="info-box"><h4>Browser Recommendation</h4><p>Latest versions of Chrome, Edge and Firefox are recommended for best compatibility.</p></div>
        </div>
    </div>
</main>
<div class="modal" id="loginModal">
    <div class="login-box">
        <div class="close-btn" id="closeModal">&times;</div>
        <h3 id="loginTitle">Login</h3>
        <p class="login-sub">Enter your university credentials to continue.</p>
        <div class="msg" id="modalMsg"></div>
        <form id="loginFormModal">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group"><label>User ID</label><input type="text" id="modalUsername" placeholder="Enter user ID" required></div>
            <div class="form-group"><label>Password</label><input type="password" id="modalPassword" placeholder="Enter password" required></div>
            <button type="submit" class="login-submit" id="modalLoginBtn">Sign In</button>
            <p class="recaptcha-notice">This site is protected by reCAPTCHA and the Google
                <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy Policy</a> and
                <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms of Service</a> apply.
            </p>
            <div class="forgot"><a href="#" id="forgotLink">Forgot Password?</a></div>
            <div class="otp-panel" id="otpPanelModal">
                <div class="otp-meta"><span>⏱️ Expires in: <span id="otpTimer">02:00</span></span><span>🔄 Attempts left: <span id="otpAttempts">3</span></span></div>
                <div class="form-group"><label>Enter OTP Code</label><input type="text" id="otpCode" placeholder="6-digit OTP" maxlength="6"></div>
                <div style="display:flex; gap:10px;"><button type="button" class="login-submit" id="verifyOtpBtn" style="flex:1;">Verify OTP</button><button type="button" class="btn-secondary" id="resendOtpBtn" style="flex:1;" disabled>Resend (<span id="resendTimer">30</span>s)</button></div>
            </div>
        </form>
    </div>
</div>
<footer>Copyright @ADBU Since 2015. You are accessing ADBU ERP from <span id="serverIP"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'your network'); ?></span>. For any queries write to erp@dbuniversity.ac.in</footer>
<script>window.RECAPTCHA_SITE_KEY = <?php echo json_encode(recaptchaIsConfigured() ? RECAPTCHA_SITE_KEY : ''); ?>;</script>
<script src="app.js" defer></script>
</body>
</html>