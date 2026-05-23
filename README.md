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

Google reCAPTCHA v3 Setup
-------------------------
1. Go to https://www.google.com/recaptcha/admin/create
2. Choose **reCAPTCHA v3**
3. Add your domains (for local dev include `localhost`)
4. Copy `secrets.local.php.example` to `secrets.local.php` and paste your keys there.
   (That file is gitignored — do not commit it.)
   Alternatively set environment variables `RECAPTCHA_SITE_KEY` and `RECAPTCHA_SECRET_KEY`.
5. v3 runs invisibly on login (no checkbox). The server checks Google's score (default min 0.5 in `RECAPTCHA_MIN_SCORE`).

PHP must reach Google over HTTPS (server-side verify)
-----------------------------------------------------
If login shows **"Could not reach reCAPTCHA service"**, your PHP install likely has no **openssl** (or **curl**) extension.

**Windows quick fix:**
1. Find PHP folder: run `where php` in a terminal.
2. Copy `php.ini-development` to `php.ini` in that folder (if `php.ini` does not exist).
3. Edit `php.ini`:
   - Set `extension_dir` to your `ext` folder (e.g. `extension_dir = "C:\php\ext"`).
   - Uncomment: `extension=openssl` (and optionally `extension=curl`).
4. Restart the PHP server (`php -S localhost:5000`).
5. Confirm: `php -m` should list **openssl**.

Without openssl, PHP cannot call `https://www.google.com/recaptcha/api/siteverify`.

Features:
- Role‑based login (Student, Employee, Parent)
- Google reCAPTCHA v3 (invisible, score-based)
- Employee OTP (expiry 120s, max 3 retries, resend cooldown 30s)
- Login throttling (5 failures → 45s lockout)
- CSRF protection
- Session management & logout
- Fully responsive UI (merged from both designs)

No database required – user data stored in users.json (auto‑created).

Performance / smaller page weight
---------------------------------
**Typical load:** ~80–100 KB your files + **~1–2 MB from Google reCAPTCHA** if loaded on every visit.

Already optimized in this project:
- reCAPTCHA loads only when you open the login modal (not on first paint)
- Logo loads once (header only; page background is CSS gradient)
- Client IP comes from PHP (no third‑party ipify call)

Further steps when hosting:
1. **Compress `logo.webp`** (e.g. Squoosh) — aim for &lt;20 KB at 80×80 display size
2. **Enable gzip/Brotli** on the web server (see `.htaccess` for Apache)
3. **Minify** `style.css` / `app.js` for production (optional build step)
4. In Chrome DevTools → Network, filter **your domain** vs **google.com** to see what is yours vs third‑party
