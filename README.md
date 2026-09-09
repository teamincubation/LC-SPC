# Listening Community – Suicide Prevention Campaign (LC-SPC)

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Architecture](https://img.shields.io/badge/Architecture-Modular%20MVC-success.svg)]()
[![Production URL](https://img.shields.io/badge/Production-teami.in%2FLC-orange.svg)](https://teami.in/LC/)

The **LC-SPC** portal is a standalone web application designed for the **Listening Community – Suicide Prevention Campaign**. It is maintained by Team Incubation as a completely separate application from the main Team Incubation website.

---

## Architecture & Isolation

- **Standalone Codebase**: Isolated within `teamincubation/LC-SPC`. Zero code dependencies on `teami-2027`.
- **Dedicated Database**: Connects exclusively to dedicated MySQL database `u806388046_LC` with database user `u806388046_LC_SPC`. It does NOT share the main Team Incubation database.
- **Subdirectory-Aware Routing**: Runs under the `/LC/` subdirectory on Hostinger (`https://teami.in/LC/`) as well as local root (`http://localhost:8000/`) without hardcoding paths in controllers, views, or business logic.
- **Multi-Tier Apache Security**: Root `.htaccess` redirects to `public/index.php` while blocking direct HTTP access to `.env`, `app/`, `config/`, `database/`, `storage/`, and `composer.json`.
- **Hardened Sessions & CSRF**: HttpOnly, SameSite, and HTTPS Secure cookies, along with CSRF token generation and enforcement across mutating endpoints.

---

## Directory Structure

```text
LC-SPC/
├── .env.example              # Environment variable template with safe placeholders
├── .gitignore                # Strict rule set preventing secrets from entering Git
├── .htaccess                 # Root Apache protection & rewrite to public/
├── composer.json             # PHP 8.2+ definition, PSR-4 autoloading & scripts
├── README.md                 # Project documentation & deployment guide
├── server.php                # Local development server router (simulates Hostinger /LC)
├── index.php                 # Root forwarder fallback
├── bin/
│   └── migrate.php           # CLI migration management tool
├── config/
│   ├── app.php               # Application branding, environment, base path, timezone
│   ├── database.php          # Dedicated MySQL database connection parameters
│   └── security.php          # CSRF settings, session parameters, security headers
├── app/
│   ├── Core/
│   │   ├── App.php           # Application container, error & exception handlers
│   │   ├── Config.php        # Centralized dot-notation configuration store
│   │   ├── Controller.php    # Base controller (render, JSON, redirect, validation)
│   │   ├── Database.php      # PDO connection singleton with prepared statement helpers
│   │   ├── Env.php           # Lightweight safe .env parser/loader
│   │   ├── helpers.php       # Global view and URL helpers (e, url, asset, csrf_token)
│   │   ├── Logger.php        # Timestamped logger writing to storage/logs/
│   │   ├── Model.php         # Base model with CRUD query execution
│   │   ├── Request.php       # HTTP request abstraction (method, body, params, headers)
│   │   ├── Response.php      # HTTP response helper (HTML, JSON, status codes)
│   │   ├── Router.php        # Subdirectory-aware HTTP router
│   │   ├── Security.php      # Security utilities (CSRF, XSS escaping, password hashing)
│   │   ├── Session.php       # Session manager with HttpOnly & SameSite cookies
│   │   ├── View.php          # View engine with layout rendering
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php
│   │       ├── CsrfMiddleware.php
│   │       └── SecurityHeadersMiddleware.php
│   ├── Database/
│   │   └── MigrationRunner.php # Chronological migration execution engine
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   └── HealthController.php
│   ├── Models/
│   └── Views/
│       ├── layouts/
│       │   └── main.php      # Master layout with responsive navigation & alerts
│       ├── home/
│       │   └── index.php     # Foundation readiness & architecture overview
│       └── errors/
│           ├── 404.php       # 404 Not Found error page
│           └── 500.php       # 500 Internal Server Error (suppressed in production)
├── database/
│   ├── schema.sql            # Export/reference schema
│   ├── migrations/           # Chronological versioned migration scripts
│   └── seeders/              # Database seeders
├── public/
│   ├── .htaccess             # Front controller rewrite rules
│   ├── index.php             # Front controller entry point
│   ├── robots.txt            # Search engine indexing directives
│   └── assets/
│       ├── css/
│       │   └── app.css       # Clean, modern, responsive CSS design system
│       └── js/
│           └── app.js        # Core client utilities (CSRF fetch helper, dismissals)
├── storage/
│   ├── cache/
│   ├── logs/                 # Error and application logs
│   ├── private/
│   ├── sessions/
│   └── uploads/
└── tests/
    └── test_suite.php        # Automated verification test suite
```

---

## Local Development Setup

### 1. Requirements
- PHP 8.2 or higher (with `pdo`, `pdo_mysql`, `openssl`, `mbstring`, `json`)
- Composer 2.x

### 2. Installation
Clone the repository:
```bash
git clone https://github.com/teamincubation/LC-SPC.git
cd LC-SPC
```

Install/generate autoloaders:
```bash
composer dump-autoload
```

### 3. Configure Environment
Copy the environment template:
```bash
cp .env.example .env
```

Review `.env`:
```env
APP_NAME="Listening Community SPC"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_BASE_PATH=/
APP_TIMEZONE=Asia/Kolkata

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u806388046_LC
DB_USERNAME=u806388046_LC_SPC
DB_PASSWORD=your_local_password
```

### 4. Database Migrations
Run pending migrations:
```bash
php bin/migrate.php migrate
```
Check status:
```bash
php bin/migrate.php status
```
Rollback last batch:
```bash
php bin/migrate.php rollback
```

### 5. Start Development Server
Run via Composer or PHP CLI:
```bash
composer start
# or:
php -S localhost:8000 server.php
```
Visit in your browser:
- Root: `http://localhost:8000/`
- Simulated Subdirectory: `http://localhost:8000/LC/`
- Health Check: `http://localhost:8000/health` (or `http://localhost:8000/LC/health`)

---

## Testing & Verification

Execute the complete automated test suite (47 verification assertions covering routing, base path normalization, CSRF protection, password hashing, database transactions, and Apache protection rules):
```bash
php tests/test_suite.php
```

---

## Production Deployment on Hostinger
 
- **Hostinger Target Directory**: `/public_html/LC/` (under the `teami.in` website root)
- **Target URL**: `https://teami.in/LC/`
- **Dedicated Database**: `u806388046_LC` (User: `u806388046_LC_SPC`)
- **DNS / SSL Requirement**: `teami.in` must be pointed to the Hostinger hosting plan with active SSL/HTTPS.
 
### Deployment Steps:
1. Clone or pull the `main` branch into `/public_html/LC/`.
2. Generate or run `composer dump-autoload -o --no-dev`.
3. Create `/public_html/LC/.env` on the Hostinger server:
   ```env
   APP_NAME="Listening Community SPC"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://teami.in/LC
   APP_BASE_PATH=/LC
   APP_TIMEZONE=Asia/Kolkata

   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=u806388046_LC
   DB_USERNAME=u806388046_LC_SPC
   DB_PASSWORD=production_secret_password_here

   SESSION_NAME=LCSPC_SESSION
   ```
4. Run migrations on production:
   ```bash
   php bin/migrate.php migrate
   ```
5. Ensure `storage/` permissions allow writing by the web user (chmod 755).
6. Verify production health endpoint: `https://teami.in/LC/health` returns HTTP 200 `{ "status": "ok", "application": "LC-SPC" }`.

---

## Git Workflow & Branching Strategy

- **`main`**: Production-ready branch. Deployed to `https://teami.in/LC/`.
- **`develop`**: Integration branch for completed and tested modules.
- **`feature/*`**: Feature branches for individual development work (e.g. `feature/events`, `feature/registration`).

**Rules:**
- Never commit `.env`, credentials, or secrets to Git.
- Never merge incomplete or untested features directly into `main`.
- All code changes must pass `php -l` linting and `php tests/test_suite.php` before merging.
