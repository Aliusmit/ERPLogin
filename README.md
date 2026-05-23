ADBU ERP Login System – PHP Built‑in Server Edition
====================================================

1. Extract all files into a folder (e.g., adbu-erp).

2. Open a terminal / command prompt inside that folder.

3. Run:
   php -S localhost:5000

4. Open your browser and go to:
   http://localhost:5000

Demo Credentials (case‑sensitive)
----------------------------------
Student   : 2023-ADBU-001 / student123
Employee  : EMP-2024-001  / employee123
Parent    : PAR-2024-001  / parent123

OTP for Employees:
- After correct password, an OTP is generated and logged to otp_log.txt (in the same folder).
- Check that file for the 6‑digit code.
- In production, replace sendOTP() in config.php with real email/SMS.

Features:
- Role‑based login (Student, Employee, Parent)
- CAPTCHA with refresh
- Employee OTP (expiry 120s, max 3 retries, resend cooldown 30s)
- Login throttling (5 failures → 45s lockout)
- CSRF protection
- Session management & logout
- Fully responsive UI (merged from both designs)

No database required – user data stored in users.json (auto‑created).
