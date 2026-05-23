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
            let res = await fetch(API_BASE + 'login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username, password, role: apiRole, captcha, csrf_token: document.querySelector('input[name = "csrf_token"]').value }) });
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
