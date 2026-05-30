# ELHOE Verification — Hostinger Deployment Guide

This guide walks through deploying the ELHOE Verification System to **Hostinger Business** hosting (hPanel) so it serves at `https://elhoe.com/checker`.

**Total time:** ~15–20 minutes
**Required:** hPanel access, PHP 8.1+, MySQL database, FTP or hPanel File Manager

---

## Step 1 — Download the Project Files

1. On GitHub, open the repo: `colorgravitybd-max/elhoeverification`
2. Click the green **Code** button → **Download ZIP**
3. Unzip on your computer. You'll get a folder called `elhoeverification-main` (or similar).

---

## Step 2 — Create the Database

1. Log in to **Hostinger hPanel**
2. Go to **Databases → MySQL Databases**
3. Click **Create New MySQL Database**
4. Fill in:
   - **Database name:** `elhoe_chk` (Hostinger will prefix it, e.g. `u123456789_elhoe_chk`)
   - **Username:** `elhoeadm` (also auto-prefixed)
   - **Password:** generate a strong password — **save this**
5. Click **Create**
6. **Write down the full values:**
   ```
   DB_HOST = localhost
   DB_NAME = u123456789_elhoe_chk     ← what hPanel shows
   DB_USER = u123456789_elhoeadm
   DB_PASS = YourStrongPasswordHere
   ```

---

## Step 3 — Upload the Files

### Option A — File Manager (easier, recommended)

1. hPanel → **Files → File Manager**
2. Navigate to `public_html/`
3. Click **New Folder** → name it `checker`
4. Open `public_html/checker/`
5. Click **Upload** → select **all files and folders** from the unzipped `elhoeverification` folder. Drag and drop the entire contents (not the parent folder itself).
6. Wait for upload to complete (≈2 MB total).

### Option B — FTP

1. Connect via FTP client (FileZilla, etc.) using credentials from hPanel → **Files → FTP Accounts**
2. Upload all files from the unzipped folder to `/public_html/checker/`

### Verify upload structure

`/public_html/checker/` should contain these top-level folders/files:
```
admin/   api/   config/   migrations/   public/   src/   storage/   tools/
.gitignore   .htaccess   DEPLOYMENT.md   README.md   SPEC.md
```

---

## Step 4 — Set File Permissions

Most files should be **644**, folders **755**. The `storage/` folder needs **775** (web server must be able to write logs, cache, uploads).

**In File Manager:**
1. Right-click `storage/` → **Permissions** → set to `775` → **Apply to subfolders + files**
2. If issues persist, set `storage/logs/`, `storage/cache/`, `storage/uploads/`, `storage/exports/` to `777` individually.

---

## Step 5 — Create the `.env` Configuration File

1. In File Manager, navigate to `/public_html/checker/config/`
2. Right-click `.env.example` → **Copy** → rename copy to `.env`
3. Right-click `.env` → **Edit**
4. Fill in your real values:

```ini
APP_NAME="ELHOE Product Verification"
APP_URL="https://elhoe.com/checker"
APP_ENV="production"
APP_DEBUG="false"
APP_TIMEZONE="Asia/Dhaka"

DB_HOST="localhost"
DB_PORT="3306"
DB_NAME="u123456789_elhoe_chk"           # ← from Step 2
DB_USER="u123456789_elhoeadm"            # ← from Step 2
DB_PASS="YourStrongPasswordHere"         # ← from Step 2
DB_CHARSET="utf8mb4"

SESSION_NAME="elhoe_admin_sess"
SESSION_LIFETIME_MINUTES="120"
COOKIE_SECURE="true"
COOKIE_SAMESITE="Lax"

# Generate a 64-char random string here. You can run:
#   openssl rand -hex 32
APP_KEY="paste-64-random-hex-chars-here"

ADMIN_LOGIN_ATTEMPTS="5"
ADMIN_LOGIN_LOCKOUT_MINUTES="15"

RATE_LIMIT_VERIFY_PER_MIN="10"
RATE_LIMIT_REGISTER_PER_HOUR="5"

GEOIP_PROVIDER="ipwho.is"
GEOIP_FALLBACK="ipapi.co"
GEOIP_CACHE_DAYS="30"

BRAND_NAME="ELHOE"
BRAND_TAGLINE="Redefine Your Skincare Journey"
BRAND_SUPPORT_EMAIL="support@elhoe.com"
BRAND_SUPPORT_WHATSAPP=""

META_PIXEL_ID=""
META_CAPI_TOKEN=""
META_CAPI_TEST_CODE=""
GTM_CONTAINER_ID=""

ADMIN_ALERT_EMAIL=""
ADMIN_ALERT_FROM="no-reply@elhoe.com"

LOG_LEVEL="info"
LOG_RETENTION_DAYS="30"
```

5. **Save**.

> 💡 **Generate APP_KEY easily:** open https://www.random.org/cgi-bin/randbyte?nbytes=32&format=h or run `openssl rand -hex 32` locally. Just paste the resulting 64-char hex string.

---

## Step 6 — Import the Database Schema

1. hPanel → **Databases → phpMyAdmin** → click **Enter phpMyAdmin** for your `elhoe_chk` database
2. Click the **Import** tab at the top
3. Click **Choose File** → select `/migrations/000_schema.sql` (from your local unzipped folder)
4. Click **Go** at the bottom

You should see *"Import has been successfully finished"*. Verify by clicking the database name on the left — you should see 9 tables: `admin_users`, `audit_log`, `codes`, `customers`, `ip_geo_cache`, `products`, `rate_limits`, `scan_logs`, `settings`.

### (Optional) Import legacy data

If you want to migrate the 42 codes + 80 scan logs from your old plugin:

5. Repeat: **Import** → choose `migrations/001_legacy_data.sql` → **Go**
6. After this finishes, go to your admin panel **Products** page and update the placeholder products (set names, image_url, product_url for each). Or use **CSV Import** with a CSV containing the matching `wp_product_id` values.

### Seed the initial admin user

7. Repeat: **Import** → choose `migrations/002_seed_admin.sql` → **Go**

This creates:
- **Username:** `admin`
- **Password:** `ChangeMe123!`
- **Email:** `admin@elhoe.com`
- **Role:** super_admin

⚠️ **Change this password immediately after first login.**

---

## Step 7 — Configure Routing for `/checker`

WordPress would normally try to handle `/checker` as a page. We need to bypass WordPress for the checker URL **AND** force a single canonical hostname (no-www) so the page never serves out of two cache buckets.

1. In File Manager, open `/public_html/.htaccess` (the **root** WordPress one, NOT the one inside `/checker/`)
2. Add these two blocks **at the very top** (before `# BEGIN WordPress`):

```apache
# === Force canonical hostname: www.elhoe.com -> elhoe.com ===
# (Eliminates split caching, CORS oddities, and "old page" issues.)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_HOST} ^www\.elhoe\.com$ [NC]
    RewriteRule ^(.*)$ https://elhoe.com/$1 [R=301,L]
</IfModule>
# === End Force canonical ===

# === ELHOE Verification Bypass (must come before WordPress) ===
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Don't let WordPress handle anything under /checker/
    RewriteRule ^checker/?$ /checker/public/index.php [L]
    RewriteRule ^checker/(public|admin|api|assets)(/.*)?$ - [L]
    RewriteRule ^checker/(.*)$ /checker/$1 [L]
</IfModule>
# === End ELHOE Verification Bypass ===
```

3. **Save**.

This tells Apache: "If anyone visits `www.elhoe.com/...`, send them to `elhoe.com/...` first. Then anything under `/checker/` is its own app — don't pass it to WordPress."

> ✅ **Test:** Visit `https://elhoe.com/checker` in your browser. You should see the verification page (eco-themed, with a code input field). Visit `https://www.elhoe.com/checker` — your browser URL should automatically flip to the non-www version.

---

## Step 8 — Verify Customer-Facing Page

1. Open https://elhoe.com/checker
2. You should see:
   - "Authenticate your ELHOE product" heading
   - Code input field (numeric keyboard on mobile)
   - "Verify Authenticity" button
   - Trust signals row + FAQ
3. Try entering a code from your imported legacy data — e.g. `729879234926`
4. You should see the success result with "Authentic Product" message and the placeholder product card.

---

## Step 9 — First Admin Login

1. Open https://elhoe.com/checker/admin
2. Sign in:
   - Username: `admin`
   - Password: `ChangeMe123!`
3. You'll land on the dashboard.

### Immediately do these things:

- **Settings → Admins** → Reset password to something strong, OR delete this admin and create your own
- **Settings → General** → Update brand name, support email, theme colors, upload logo
- **Settings → Admins** → Update email address (if you didn't already)

---

## Step 10 — Update Products

Two ways:

### Option A — Edit each placeholder product individually
Admin → **Products** → click **Edit** on each placeholder (Product #2387 etc.) and fill in:
- Name
- SKU
- Image URL
- Product URL (link to your WP product page)
- Category, Routine Group

### Option B — Bulk CSV import (recommended)
Admin → **Products** → **Import CSV**

Use a CSV with column `wp_product_id` matching the IDs from the placeholder data. The system will UPDATE existing products by `wp_product_id`. Download the template via the **Import** page.

---

## Step 11 — Configure Marketing Integrations (optional)

### Meta Pixel + Conversions API
Admin → **Integrations → Meta Pixel + CAPI**
- Pixel ID: get from Meta Events Manager → Data Sources
- CAPI Access Token: Events Manager → Settings → Conversions API → Generate
- Click **Fire Test Event** to verify

### Google Tag Manager
Admin → **Integrations → Google Tag Manager**
- GTM Container ID (format `GTM-XXXXXXX`)
- The system pushes `spv_verify_success`, `spv_verify_fail`, `spv_registration` events to dataLayer

---

## Step 12 — Set Up the Maintenance Cron (recommended)

Cleanup expired rate limits, old scan logs, etc. Run daily.

1. hPanel → **Advanced → Cron Jobs**
2. Add a new cron:
   - **Frequency:** Once a day, e.g. `0 3 * * *` (3 AM)
   - **Command:**
     ```
     /usr/bin/php /home/USERNAME/public_html/checker/tools/cleanup.php
     ```
     Replace `USERNAME` with your Hostinger username (find it in hPanel → Hosting Account).

---

## Step 13 — SSL Verification

Your site should already have SSL (Hostinger Business includes free Let's Encrypt SSL).

Verify: https://elhoe.com/checker should load with a padlock icon. If it doesn't:
- hPanel → **Security → SSL** → ensure SSL is active for `elhoe.com` (and the `www` alias)
- Force HTTPS is enabled in the included `.htaccess`

---

## ✅ Deployment Checklist

| Step | Done |
|------|------|
| Database created | ☐ |
| Files uploaded to `/public_html/checker/` | ☐ |
| `storage/` folder permissions = 775 | ☐ |
| `config/.env` filled with real values | ☐ |
| `APP_KEY` set to random 64-char hex | ☐ |
| `migrations/000_schema.sql` imported | ☐ |
| `migrations/002_seed_admin.sql` imported | ☐ |
| Root `.htaccess` updated to bypass WP for `/checker` | ☐ |
| https://elhoe.com/checker loads correctly | ☐ |
| Test verification with `729879234926` works | ☐ |
| Logged into admin and changed default password | ☐ |
| Settings → General configured (brand, support email) | ☐ |
| Cron job for cleanup added | ☐ |

---

## 🛠 Troubleshooting

### "Service temporarily unavailable" on `/checker`
- DB credentials in `config/.env` are wrong. Re-check `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

### 500 Internal Server Error
- Check `storage/logs/php_errors.log` for the exact error
- Set `APP_DEBUG="true"` in `.env` temporarily to see errors on screen — **set back to false** after fixing!
- Verify `storage/` permissions are 775

### `/checker` shows WordPress 404
- The root `.htaccess` rule isn't applied. Re-check it's at the **top** of `/public_html/.htaccess`, before WP rules.

### "Configuration error: config/.env not found"
- Make sure you copied `.env.example` to `.env` (not just edited the example), and it's inside the `config/` folder.

### Logo upload fails
- Set `storage/uploads/` to permission 777 temporarily. If it works, set back to 775.

### Admin login keeps failing
- Did you import `002_seed_admin.sql`?
- Try: phpMyAdmin → `admin_users` table → check the row exists
- Reset via SSH: `php tools/seed-admin.php newuser NewPass123 newemail@elhoe.com`

### Want to wipe and start over
- phpMyAdmin → `elhoe_chk` database → **Operations → Drop database**, recreate, re-import schema.

---

## 📞 Where to Get Help

- Hostinger live chat (24/7) for hosting / DNS / SSL issues
- Project source: `colorgravitybd-max/elhoeverification` on GitHub
- Logs: `storage/logs/app-YYYY-MM-DD.log` and `storage/logs/php_errors.log`
- Health check: https://elhoe.com/checker/api/health.json

---

## 🔁 Updating the Code Later

1. Pull new files (or download fresh ZIP)
2. Upload only changed files via File Manager (skip `config/.env` and `storage/` to keep your data)
3. If schema changed, run new migrations from `migrations/`
4. Done. No service restart needed (PHP picks up changes immediately)
