# LC-SPC Production Deployment Guide (Hostinger)

> **Application**: Listening Community – Suicide Prevention Campaign (LC-SPC)  
> **Repository**: `teamincubation/LC-SPC`  
> **Production URL**: `https://teami.in/LC/`  
> **Target Subdirectory**: `/public_html/LC/`  
> **Dedicated Database**: `u806388046_LC`  
> **Database User**: `u806388046_LC_SPC`  

---

> [!IMPORTANT]  
> **Verification Disclaimer**: DNS configuration, SSL provisioning, Hostinger Git deployment, and live database connectivity must be validated on the live server during deployment. Never commit live passwords or sensitive secrets to version control.

---

## 1. Connecting GitHub Repository to Hostinger

Hostinger provides an integrated Git deployment feature inside **hPanel**:

1. Log in to **Hostinger hPanel** (https://hpanel.hostinger.com).
2. Select your hosting account for **`teami.in`**.
3. In the left navigation sidebar, navigate to **Advanced** &rarr; **Git**.
4. In the **Create a New Repository** section:
   - **Repository**: `https://github.com/teamincubation/LC-SPC.git` (or SSH URL `git@github.com:teamincubation/LC-SPC.git` if using a Deploy Key).
   - **Branch**: Specify the production branch (see Section 2).
   - **Install Directory**: Enter `public_html/LC`
5. Click **Create**. Hostinger will clone the specified repository into `/home/<user>/public_html/LC`.
6. *(Optional - Auto-Deployment)*:
   - After repository creation, Hostinger generates a **Webhook URL** and **Secret**.
   - In GitHub: Navigate to `teamincubation/LC-SPC` &rarr; **Settings** &rarr; **Webhooks** &rarr; **Add webhook**.
   - Paste the Webhook URL, select `application/json`, paste the Secret, and choose **Just the push event**.

---

## 2. Production Branch Strategy

- **Current Approved Foundation Branch**: `feature/phase-0-foundation` (latest approved commit: `b675a87`).
- **Production Target Branch**: `main` (when pull request is merged) or `feature/phase-0-foundation` during Phase 0 staging deployment.
- Confirm the branch selected in Hostinger hPanel matches the exact approved commit before deploying.

---

## 3. Expected Deployment Directory & Document Root

- **Server Document Root**: `/home/<user>/public_html` (serves `https://teami.in/`)
- **Application Directory**: `/home/<user>/public_html/LC/`
- **Application Web Entry Point**: `/home/<user>/public_html/LC/public/index.php`

### Request Flow Architecture
1. Incoming requests to `https://teami.in/LC/` hit the root `.htaccess` inside `/public_html/LC/`.
2. The root `.htaccess`:
   - Enforces security rules (blocks access to `.env`, `app/`, `config/`, `database/`, `storage/`, `vendor/`, `bin/`, `tests/`, and file extensions `.json`, `.sql`, `.log`, `.lock`, `.md`).
   - Disables directory indexing (`Options -Indexes -MultiViews`).
   - Rewrites web traffic to `public/index.php`.
   - Passes static asset requests directly to `public/assets/`.
3. Root `index.php` acts as a direct forwarder to `public/index.php` if invoked directly.

### Filesystem Permissions
Set standard permissions via Hostinger File Manager or SSH:
```bash
# Directories: 755
find /home/<user>/public_html/LC -type d -exec chmod 755 {} \;

# Files: 644
find /home/<user>/public_html/LC -type f -exec chmod 644 {} \;

# Writable Storage directories:
chmod -R 775 /home/<user>/public_html/LC/storage
```

---

## 4. Production Environment Configuration (`.env`)

The `.env` file is gitignored and must be manually created on the Hostinger server.

1. In Hostinger **File Manager** (or via SSH), create a new file at `/public_html/LC/.env`.
2. Populate the file with the following production values:

```env
# ==============================================================================
# Listening Community SPC (LC-SPC) - Production Environment
# ==============================================================================
APP_NAME="Listening Community SPC"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://teami.in/LC
APP_BASE_PATH=/LC
APP_TIMEZONE=Asia/Kolkata

# Dedicated MySQL Database Credentials
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u806388046_LC
DB_USERNAME=u806388046_LC_SPC
DB_PASSWORD=<ENTER_YOUR_SECURE_HOSTINGER_DB_PASSWORD_HERE>

# Session Configuration
SESSION_NAME=LCSPC_SESSION
SESSION_LIFETIME=7200
```

> [!CAUTION]
> - Ensure `APP_DEBUG=false` in production. This suppresses sensitive diagnostic information and prevents stack traces from leaking.
> - Ensure `APP_BASE_PATH=/LC`.
> - Never commit `.env` into version control.

---

## 5. Required Database Credentials

In Hostinger **hPanel** &rarr; **Databases** &rarr; **Management**:

1. Ensure the MySQL database is created:
   - **Database Name**: `u806388046_LC`
   - **Database User**: `u806388046_LC_SPC`
   - **Host**: `localhost` (or `127.0.0.1`)
   - **Port**: `3306`
2. Set a strong, randomly generated password.
3. Grant **All Privileges** for user `u806388046_LC_SPC` on database `u806388046_LC`.
4. Enter this password into `/public_html/LC/.env` under `DB_PASSWORD`.

---

## 6. Running Database Migrations

### Option A: Via Hostinger SSH Access (Recommended)
1. Enable SSH in hPanel: **Advanced** &rarr; **SSH Access**.
2. Connect to the server:
   ```bash
   ssh -p <PORT> <USER>@<HOST_IP>
   ```
3. Navigate to the project directory:
   ```bash
   cd ~/public_html/LC
   ```
4. Verify migration status:
   ```bash
   php bin/migrate.php status
   ```
5. Execute pending migrations:
   ```bash
   php bin/migrate.php migrate
   ```

### Option B: Via phpMyAdmin (Fallback if SSH is disabled)
1. In hPanel &rarr; **Databases** &rarr; open **phpMyAdmin** for `u806388046_LC`.
2. Go to **Import** &rarr; choose `database/schema.sql` from your local copy.
3. Click **Go** to ensure the `migrations` tracking table exists.

---

## 7. Verifying Health Endpoint

After deployment, test the health check endpoint:

**URL**: `https://teami.in/LC/health`

### Expected Successful Response (HTTP 200 OK)
```json
{
  "status": "ok",
  "application": "LC-SPC"
}
```

### Failure Response (HTTP 503 Service Unavailable)
If the database credentials are incorrect or the storage directory is non-writable:
```json
{
  "status": "unhealthy",
  "application": "LC-SPC"
}
```
*(Notice: The response deliberately does NOT leak database host, username, passwords, or filesystem paths).*

---

## 8. Verifying HTTPS & SSL

1. **Verify SSL Active**: Visit `https://teami.in/LC/` in a browser and check the security lock icon (issued to `teami.in`).
2. **Verify HTTP to HTTPS Redirection**: In hPanel &rarr; **Websites** &rarr; enable **Force HTTPS**.
3. **Verify Security Headers via cURL**:
   ```bash
   curl -I https://teami.in/LC/
   ```
   Confirm presence of:
   - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Content-Security-Policy: ...`
   - `Set-Cookie: LCSPC_SESSION=...; path=/; secure; HttpOnly; SameSite=Lax`

---

## 9. Verifying Inaccessibility of `.env` and Private Directories

Run the following cURL requests to ensure private files cannot be accessed over the web:

```bash
# 1. Environment file (Must return 403 Forbidden)
curl -I https://teami.in/LC/.env

# 2. Internal application code (Must return 403 Forbidden)
curl -I https://teami.in/LC/app/Core/App.php

# 3. Configuration file (Must return 403 Forbidden)
curl -I https://teami.in/LC/config/database.php

# 4. Storage directory (Must return 403 Forbidden)
curl -I https://teami.in/LC/storage/logs/

# 5. Database schema files (Must return 403 Forbidden)
curl -I https://teami.in/LC/database/schema.sql

# 6. Composer manifest (Must return 403 Forbidden)
curl -I https://teami.in/LC/composer.json

# 7. Documentation files (Must return 403 Forbidden)
curl -I https://teami.in/LC/DEPLOYMENT.md
```

All commands above must return **HTTP 403 Forbidden** (or 404). If any return HTTP 200 with file content, the `.htaccess` rules are not being processed by Apache.

---

## 10. Rollback to Previous Git Commit

If a deployment produces unexpected runtime issues, rollback immediately:

### Option A: Via Hostinger hPanel Git
1. In hPanel &rarr; **Advanced** &rarr; **Git**.
2. Locate the deployment history table.
3. Click **Deploy** next to the previous stable commit (e.g., `b675a87`).

### Option B: Via SSH Command Line
```bash
cd ~/public_html/LC

# 1. View recent commits
git log -n 5 --oneline

# 2. Reset hard to the known stable commit
git reset --hard b675a87

# 3. If database migrations need to be rolled back
php bin/migrate.php rollback
```

---

## Production Verification Checklist

| Step | Item | Target URL / Command | Expected Result |
|---|---|---|---|
| 1 | Application Root | `https://teami.in/LC/` | HTTP 200 &mdash; LC-SPC homepage renders |
| 2 | Health Endpoint | `https://teami.in/LC/health` | HTTP 200 &mdash; `{"status":"ok","application":"LC-SPC"}` |
| 3 | Stylesheet Asset | `https://teami.in/LC/assets/css/app.css` | HTTP 200 &mdash; Content-Type: `text/css` |
| 4 | JavaScript Asset | `https://teami.in/LC/assets/js/app.js` | HTTP 200 &mdash; Content-Type: `application/javascript` |
| 5 | Brand Logo | `https://teami.in/LC/assets/images/listening-community-logo.png` | HTTP 200 &mdash; Content-Type: `image/png` |
| 6 | `.env` Protection | `https://teami.in/LC/.env` | HTTP 403 Forbidden |
| 7 | Source Protection | `https://teami.in/LC/app/Core/App.php` | HTTP 403 Forbidden |
| 8 | Config Protection | `https://teami.in/LC/config/database.php` | HTTP 403 Forbidden |
| 9 | Storage Protection | `https://teami.in/LC/storage/logs/` | HTTP 403 Forbidden |
| 10 | Security Headers | `curl -I https://teami.in/LC/` | HSTS, CSP, X-Frame-Options present |
