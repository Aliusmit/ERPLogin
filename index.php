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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #173a6b; --secondary: #0f2747; --gold: #d1a04d; --light: #f4f7fb; --text: #1b1b1b; --border: #d8dde6; }
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(rgba(8,20,38,0.82), rgba(8,20,38,0.9)), url("https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1920&auto=format&fit=crop"); background-size: cover; background-position: center; min-height: 100vh; color: white; display: flex; flex-direction: column; }
        header { width: 100%; padding: 18px 60px; border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(8,18,35,0.65); backdrop-filter: blur(8px); }
        .topbar { display: flex; align-items: center; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 18px; }
        .brand img { width: 82px; height: auto; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.45)); }
        .brand-text h1 { font-size: 28px; font-weight: 600; letter-spacing: 0.5px; }
        .brand-text p { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 3px; letter-spacing: 1px; text-transform: uppercase; }
        .top-right { font-size: 14px; color: rgba(255,255,255,0.75); letter-spacing: 0.4px; }
        main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 50px 20px; }
        .portal-panel { width: 100%; max-width: 1100px; display: grid; grid-template-columns: 1.1fr 0.9fr; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(14px); overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.04); }
        .left-panel { padding: 70px 65px; position: relative; background: linear-gradient(135deg, rgba(17,41,74,0.96), rgba(13,29,53,0.94)); }
        .left-panel::after { content: ""; position: absolute; top: 0; right: 0; width: 1px; height: 100%; background: rgba(255,255,255,0.08); }
        .erp-badge { display: inline-block; padding: 7px 14px; border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.05); font-size: 12px; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 24px; color: rgba(255,255,255,0.78); }
        .main-title { font-size: 46px; line-height: 1.1; font-weight: 700; margin-bottom: 18px; letter-spacing: -1px; }
        .subtitle { font-size: 16px; line-height: 1.7; color: rgba(255,255,255,0.78); max-width: 540px; margin-bottom: 45px; }
        .login-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .portal-btn { position: relative; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: white; padding: 22px; text-align: left; cursor: pointer; transition: 0.25s ease; overflow: hidden; }
        .portal-btn:hover { transform: translateY(-3px); background: rgba(255,255,255,0.09); border-color: rgba(255,255,255,0.18); }
        .portal-btn span { display: block; }
        .portal-btn .btn-title { font-size: 17px; font-weight: 600; margin-bottom: 5px; }
        .portal-btn .btn-desc { font-size: 13px; color: rgba(255,255,255,0.65); }
        .portal-btn::before { content: ""; position: absolute; left: 0; bottom: 0; width: 100%; height: 3px; background: var(--gold); transform: scaleX(0); transform-origin: left; transition: 0.25s ease; }
        .portal-btn:hover::before { transform: scaleX(1); }
        .right-panel { background: rgba(255,255,255,0.97); color: var(--text); padding: 60px 55px; display: flex; flex-direction: column; justify-content: center; }
        .right-panel h2 { font-size: 28px; color: var(--secondary); margin-bottom: 14px; }
        .right-panel p { color: #5d6672; line-height: 1.7; margin-bottom: 28px; font-size: 15px; }
        .info-box { border-left: 4px solid var(--gold); background: #f5f7fa; padding: 18px; margin-bottom: 18px; }
        .info-box h4 { margin-bottom: 6px; color: var(--secondary); font-size: 15px; }
        .info-box p { margin: 0; font-size: 14px; line-height: 1.6; }
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000; }
        .modal.active { display: flex; }
        .login-box { width: 100%; max-width: 460px; background: white; color: var(--text); padding: 42px; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.4); animation: fadeIn 0.22s ease; max-height: 90vh; overflow-y: auto; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .close-btn { position: absolute; top: 15px; right: 18px; font-size: 24px; cursor: pointer; color: #777; }
        .login-box h3 { font-size: 28px; margin-bottom: 10px; color: var(--secondary); }
        .login-box .login-sub { color: #5d6672; margin-bottom: 28px; font-size: 14px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #334; }
        .form-group input { width: 100%; padding: 14px 14px; border: 1px solid #ccd3dc; outline: none; font-size: 15px; transition: 0.2s ease; border-radius: 8px; }
        .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(23,58,107,0.1); }
        .captcha-row { display: flex; gap: 12px; align-items: center; margin-top: 5px; }
        .captcha-code { background: #f0f2f5; font-family: monospace; font-size: 26px; font-weight: bold; letter-spacing: 6px; padding: 10px 15px; border-radius: 8px; text-align: center; border: 1px solid #ccd3dc; }
        .btn-secondary { background: #e9ecef; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-sm { padding: 8px 12px; font-size: 13px; }
        .otp-panel { display: none; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 20px; }
        .otp-panel.show { display: block; }
        .otp-meta { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 15px; color: #5d6672; }
        .login-submit { width: 100%; padding: 15px; border: none; background: linear-gradient(135deg, var(--primary), #0f2747); color: white; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 6px; transition: 0.2s ease; border-radius: 8px; }
        .login-submit:hover { filter: brightness(1.05); }
        .forgot { text-align: right; margin-top: 14px; }
        .forgot a { text-decoration: none; color: var(--primary); font-size: 14px; }
        .msg { padding: 12px; border-radius: 8px; margin-bottom: 20px; display: none; font-size: 14px; }
        .msg.show { display: block; }
        .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .msg.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; margin-right: 8px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .session-bar { display: none; background: rgba(255,255,255,0.1); border-radius: 12px; padding: 15px 20px; margin-bottom: 30px; align-items: center; justify-content: space-between; backdrop-filter: blur(4px); }
        .session-bar.show { display: flex; }
        .session-info { color: white; }
        .session-info strong { font-size: 18px; }
        .logout-btn { background: rgba(255,255,255,0.2); border: none; padding: 8px 20px; border-radius: 30px; color: white; cursor: pointer; font-weight: 600; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        footer { background: rgba(4,10,20,0.92); border-top: 1px solid rgba(255,255,255,0.06); padding: 18px 40px; font-size: 13px; color: rgba(255,255,255,0.72); text-align: center; line-height: 1.8; }
        @media(max-width: 960px) { .portal-panel { grid-template-columns: 1fr; } .left-panel::after { display: none; } .main-title { font-size: 38px; } }
        @media(max-width: 640px) { header { padding: 18px 22px; } .topbar { flex-direction: column; align-items: flex-start; gap: 14px; } .left-panel, .right-panel { padding: 40px 28px; } .login-grid { grid-template-columns: 1fr; } .main-title { font-size: 32px; } .brand img { width: 64px; } .brand-text h1 { font-size: 22px; } }
    </style>
</head>
<body>
<header>
    <div class="topbar">
        <div class="brand">
            <img src="logo.png" alt="ADBU Logo">
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
            <div class="form-group">
                <label>CAPTCHA</label>
                <div class="captcha-row">
                    <div class="captcha-code" id="captchaCode">ABCD</div>
                    <input type="text" id="captchaInput" placeholder="Enter code" style="flex:1;" required>
                    <button type="button" class="btn-secondary btn-sm" id="refreshCaptchaBtn">⟳</button>
                </div>
            </div>
            <button type="submit" class="login-submit" id="modalLoginBtn">Sign In</button>
            <div class="forgot"><a href="#" id="forgotLink">Forgot Password?</a></div>
            <div class="otp-panel" id="otpPanelModal">
                <div class="otp-meta"><span>⏱️ Expires in: <span id="otpTimer">02:00</span></span><span>🔄 Attempts left: <span id="otpAttempts">3</span></span></div>
                <div class="form-group"><label>Enter OTP Code</label><input type="text" id="otpCode" placeholder="6-digit OTP" maxlength="6"></div>
                <div style="display:flex; gap:10px;"><button type="button" class="login-submit" id="verifyOtpBtn" style="flex:1;">Verify OTP</button><button type="button" class="btn-secondary" id="resendOtpBtn" style="flex:1;" disabled>Resend (<span id="resendTimer">30</span>s)</button></div>
            </div>
        </form>
    </div>
</div>
<footer>Copyright @ADBU Since 2015. You are accessing ADBU ERP from <span id="serverIP">detecting...</span>. For any queries write to erp@dbuniversity.ac.in</footer>
<script>
    const API_BASE = './api/';
    let currentRole = 'Student';
    let otpTimerInterval = null, resendTimerInterval = null, otpExpiryTime = 0;
    let tempAuthActive = false;
    const modal = document.getElementById('loginModal');
    const closeModal = document.getElementById('closeModal');
    const loginTitle = document.getElementById('loginTitle');
    const modalMsg = document.getElementById('modalMsg');
    const loginForm = document.getElementById('loginFormModal');
    const otpPanel = document.getElementById('otpPanelModal');
    const modalLoginBtn = document.getElementById('modalLoginBtn');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    function showModalMessage(msg, type = 'error') {
        modalMsg.textContent = msg;
        modalMsg.className = `msg show ${type}`;
        setTimeout(() => { if (modalMsg.classList.contains('show')) modalMsg.classList.remove('show'); }, 5000);
    }
    document.querySelectorAll('.login-trigger').forEach(btn => {
        btn.addEventListener('click', () => {
            currentRole = btn.dataset.role;
            loginTitle.textContent = currentRole + " Login";
            document.getElementById('modalUsername').value = '';
            document.getElementById('modalPassword').value = '';
            document.getElementById('captchaInput').value = '';
            otpPanel.classList.remove('show');
            tempAuthActive = false;
            clearOtpTimers();
            refreshCaptcha();
            modal.classList.add('active');
        });
    });
    document.getElementById('admissionBtn')?.addEventListener('click', () => alert('Admission Portal - Coming Soon'));
    document.getElementById('jobBtn')?.addEventListener('click', () => alert('Job Portal - Coming Soon'));
    closeModal.addEventListener('click', () => modal.classList.remove('active'));
    window.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
    function refreshCaptcha() {
        fetch(API_BASE + 'captcha.php').then(res => res.json()).then(data => { if (data.captcha) document.getElementById('captchaCode').textContent = data.captcha; }).catch(() => showModalMessage('CAPTCHA error', 'error'));
    }
    document.getElementById('refreshCaptchaBtn').addEventListener('click', refreshCaptcha);
    refreshCaptcha();
    document.getElementById('forgotLink').addEventListener('click', (e) => { e.preventDefault(); showModalMessage('Password reset link would be sent to your registered email.', 'info'); });
    function clearOtpTimers() { if (otpTimerInterval) clearInterval(otpTimerInterval); if (resendTimerInterval) clearInterval(resendTimerInterval); otpTimerInterval = null; resendTimerInterval = null; }
    function startOtpTimers(expiresInSeconds = 120) {
        otpExpiryTime = Date.now() + (expiresInSeconds * 1000);
        if (otpTimerInterval) clearInterval(otpTimerInterval);
        otpTimerInterval = setInterval(() => { let remaining = Math.max(0, Math.floor((otpExpiryTime - Date.now()) / 1000)); let m = Math.floor(remaining / 60), s = remaining % 60; document.getElementById('otpTimer').textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`; if (remaining <= 0) { clearInterval(otpTimerInterval); showModalMessage('OTP expired. Please request a new one.', 'error'); verifyOtpBtn.disabled = true; } }, 1000);
    }
    function startResendCooldown(seconds = 30) {
        let remaining = seconds;
        resendOtpBtn.disabled = true;
        if (resendTimerInterval) clearInterval(resendTimerInterval);
        resendTimerInterval = setInterval(() => { remaining--; document.getElementById('resendTimer').textContent = remaining; if (remaining <= 0) { clearInterval(resendTimerInterval); resendOtpBtn.disabled = false; document.getElementById('resendTimer').textContent = '0'; } }, 1000);
    }
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (tempAuthActive) { showModalMessage('Please complete OTP verification first.', 'error'); return; }
        const username = document.getElementById('modalUsername').value.trim();
        const password = document.getElementById('modalPassword').value;
        const captcha = document.getElementById('captchaInput').value.trim();
        if (!username || !password) return showModalMessage('Please enter username and password', 'error');
        if (!captcha) return showModalMessage('Please enter CAPTCHA code', 'error');
        let roleMap = { 'Student': 'student', 'Employee': 'employee', 'Other User': 'parent' };
        let apiRole = roleMap[currentRole] || 'student';
        modalLoginBtn.disabled = true;
        modalLoginBtn.innerHTML = '<span class="spinner"></span> Authenticating...';
        try {
            let res = await fetch(API_BASE + 'login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username, password, role: apiRole, captcha, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' }) });
            let data = await res.json();
            if (data.success) {
                if (data.requires_otp) {
                    tempAuthActive = true;
                    showModalMessage('✅ Password verified! OTP sent to your registered email (check otp_log.txt).', 'success');
                    otpPanel.classList.add('show');
                    startOtpTimers(120);
                    startResendCooldown(30);
                    document.getElementById('otpAttempts').textContent = '3';
                    document.getElementById('otpCode').value = '';
                    verifyOtpBtn.disabled = false;
                } else {
                    showModalMessage('✅ Login successful! Redirecting...', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            } else { showModalMessage(data.message || 'Login failed', 'error'); refreshCaptcha(); document.getElementById('captchaInput').value = ''; }
        } catch (err) { showModalMessage('Network error. Please try again.', 'error'); }
        finally { modalLoginBtn.disabled = false; modalLoginBtn.innerHTML = 'Sign In'; }
    });
    verifyOtpBtn.addEventListener('click', async () => {
        const otp = document.getElementById('otpCode').value.trim();
        if (!otp || otp.length !== 6) return showModalMessage('Enter 6-digit OTP', 'error');
        verifyOtpBtn.disabled = true;
        verifyOtpBtn.innerHTML = '<span class="spinner"></span>';
        try {
            let res = await fetch(API_BASE + 'verify_otp.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ otp }) });
            let data = await res.json();
            if (data.success) { showModalMessage('✅ OTP verified! Redirecting...', 'success'); setTimeout(() => location.reload(), 1000); }
            else { showModalMessage(data.message || 'Invalid OTP', 'error'); if (data.remaining_attempts !== undefined) document.getElementById('otpAttempts').textContent = data.remaining_attempts; if (data.remaining_attempts === 0) verifyOtpBtn.disabled = true; }
        } catch (err) { showModalMessage('Verification failed', 'error'); }
        finally { verifyOtpBtn.disabled = false; verifyOtpBtn.innerHTML = 'Verify OTP'; }
    });
    resendOtpBtn.addEventListener('click', async () => {
        resendOtpBtn.disabled = true;
        resendOtpBtn.innerHTML = 'Sending...';
        try {
            let res = await fetch(API_BASE + 'resend_otp.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } });
            let data = await res.json();
            if (data.success) { showModalMessage('✅ New OTP sent', 'success'); startOtpTimers(120); startResendCooldown(30); document.getElementById('otpAttempts').textContent = '3'; document.getElementById('otpCode').value = ''; verifyOtpBtn.disabled = false; }
            else { showModalMessage(data.message || 'Cannot resend OTP', 'error'); resendOtpBtn.disabled = false; resendOtpBtn.innerHTML = 'Resend'; }
        } catch (err) { showModalMessage('Failed to resend OTP', 'error'); resendOtpBtn.disabled = false; resendOtpBtn.innerHTML = 'Resend'; }
    });
document.getElementById('logoutBtn')?.addEventListener('click', async () => { await fetch(API_BASE + 'logout.php', { method: 'POST' }); location.reload(); });
    document.getElementById('dashboardLogoutBtn')?.addEventListener('click', async () => { await fetch(API_BASE + 'logout.php', { method: 'POST' }); location.reload(); });
    fetch('https://api.ipify.org?format=json').then(res => res.json()).then(data => document.getElementById('serverIP').textContent = data.ip).catch(() => document.getElementById('serverIP').textContent = 'Unknown Network');
</script>
</body>
</html>