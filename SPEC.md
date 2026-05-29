# ELHOE Verification System — Final Specification

**Status:** Implemented (v1.0)
**Owner:** ELHOE Skincare
**Repository:** colorgravitybd-max/elhoeverification
**Public URL:** https://www.elhoe.com/checker
**Hosting:** Hostinger Business hPanel
**Stack:** PHP 8.1+ · MySQL · Vanilla JS · HTML5 · CSS3
**No Composer · No frameworks · Pure PHP**

---

## 1. Goals

1. **Reliability** — independent of WordPress. If WP is down, `/checker` keeps working.
2. **Same URL** — customers continue using `www.elhoe.com/checker` (no link / QR-code changes).
3. **Feature parity** — replicate every feature of the existing WP plugin.
4. **Improvements** — smart input handling, "did you mean?" suggestions, risk scoring, Meta CAPI, eco theme.
5. **Drop-folder deployment** — upload + import SQL = live. Zero CLI required.

## 2. Out of Scope (deferred to v2+)

- QR code generation & label printing _(planned for a later phase per user's request)_
- Multi-language (English only)
- 2FA admin login
- External webhooks (Zapier/Make/n8n)
- Mobile native apps

## 3. Routing on Hostinger

Add **above** the WordPress block in `/public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^checker/?$ /checker/public/index.php [L]
    RewriteRule ^checker/(public|admin|api|assets)(/.*)?$ - [L]
    RewriteRule ^checker/(.*)$ /checker/$1 [L]
</IfModule>
```

The `/checker/` folder is fully separate from WordPress.

## 4. Folder Structure (Production)

```
/public_html/checker/
├── public/                  Customer pages + assets (web root for /checker)
├── admin/                   Auth-gated admin panel
├── api/                     REST endpoints
├── src/                     PHP classes (PSR-4)
├── config/                  .env + bootstrap
├── storage/                 Runtime data (logs, cache, uploads) - 775 perms
├── migrations/              SQL schema + seed + legacy data
└── tools/                   CLI scripts (cleanup, seed-admin)
```

## 5. Database Schema (9 Tables)

| Table | Purpose |
|---|---|
| `products` | Product catalog (links to WP product_id, image_url, product_url) |
| `codes` | The core verification table (unique on `code_normalized`) |
| `scan_logs` | Every verification attempt (with geo + UA) |
| `customers` | VIP list (registered owners) with hashed PII for Meta CAPI |
| `admin_users` | Bcrypt-hashed admin accounts with role + lockout |
| `audit_log` | Admin action history (super_admin only) |
| `settings` | Key-value runtime config (brand, integrations) |
| `rate_limits` | Per-IP per-bucket rate limiting |
| `ip_geo_cache` | 30-day cached GeoIP lookups |

All tables `utf8mb4_unicode_ci`. Codes stored as `VARCHAR(32)` to preserve leading zeros.

## 6. API Endpoints

### Public (no auth, CSRF-protected)
| Method | Path | Purpose |
|---|---|---|
| GET | `/checker` | Customer verification page |
| POST | `/checker/api/verify.php` | Verify a code |
| POST | `/checker/api/register.php` | Register owner on first scan |
| POST | `/checker/api/suggest.php` | Did-you-mean suggestions |
| GET | `/checker/api/health.json` | Uptime / DB health check |

### Admin (session auth)
| Method | Path | Purpose |
|---|---|---|
| GET | `/checker/api/admin/products-export.php` | CSV of all products |
| GET | `/checker/api/admin/products-template.php` | Sample import template |
| GET | `/checker/api/admin/codes-export.php?…filters` | Filtered CSV of codes |
| GET | `/checker/api/admin/codes-template.php` | Sample import template |
| GET | `/checker/api/admin/customers-export.php?format=meta\|google\|full` | VIP audience exports |
| GET | `/checker/api/admin/backup-download.php` | Full SQL dump (super_admin) |

## 7. Verification Logic

### Input Normalization (codes are NUMERIC ONLY)
1. Trim whitespace
2. Strip URLs (`http://`, `https://`, `www.`, common domains)
3. Strip ALL non-digit characters
4. Reject if empty / length < 6 / length > 20
5. Reject obvious junk (all-zeros, sequential `1234567890`)

### Decision Tree
```
malformed input            → 'malformed'   (input too short/junk)
not in DB                  → 'invalid' + did-you-mean
status='quarantined'       → 'quarantined'
status='inactive'          → 'invalid'
mode='universal'           → 'valid_universal'
mode='unique', first scan  → 'valid_unique_first'  → prompt registration
mode='unique', returning   → 'valid_unique_returning' OR 'already_registered'
> rate limit               → 'rate_limited'
```

Every result is logged to `scan_logs` with IP + GeoIP + user-agent + result.

## 8. Risk Scoring (Counterfeit Detection)

Recomputed on every successful scan:
- **+30** if unique-mode code scanned > 1 time
- **+20** if scanned from > 5 unique IPs in 24h
- **+15** if scanned from > 3 different countries
- **+15** if 5+ scans within 1 minute (bot pattern)
- **+10** per occurrence: universal mode > 1000 scans (abuse)

Score ≥ 70 → auto-quarantines the code. Score ≥ 50 → admin watchlist alert.

## 9. Marketing Integrations

### Meta Pixel (browser-side)
Events: `PageView`, `ViewContent` (genuine), `Lead` (registration), `Search` (invalid).
Configurable via Settings → Integrations → Meta.

### Meta Conversions API (server-side)
Mirrors events with hashed PII (em, ph, fn, ln, ct, country) for higher match rate. Bypasses ad-blockers and iOS 14.5+ restrictions.

### Google Tag Manager
dataLayer events: `spv_verify_success`, `spv_verify_fail`, `spv_registration`.
Configurable GTM container ID.

## 10. Admin Panel Modules

1. **Login** (with lockout: 5 attempts → 15 min)
2. **Dashboard** — KPIs, 14-day trend, hourly today, top products/cities/counterfeits
3. **Codes** — list (filters + bulk), generate (auto-gen), paste import, CSV import, edit single, export
4. **Products** — list, edit, CSV import/export, image preview, Buy-Again link
5. **Customers** — VIP list with Meta/Google/Full audience CSV exports
6. **Analytics** — Overview / Scan Log / Counterfeit Watchlist (3 tabs)
7. **Integrations** — Meta Pixel + CAPI / GTM
8. **Settings** — General (brand) / Admins (super_admin) / Backup (super_admin)
9. **Audit Log** — super_admin only

## 11. Customer-Facing UX

### `/checker` (entry)
- Big hero with brand logo / text
- Single numeric input: `<input type="text" inputmode="numeric" pattern="[0-9]*">`
- Mobile auto-opens number pad. Paste-stripped to digits only.
- Trust signals + FAQ + footer
- PWA installable

### Result Screens
- ✅ Genuine + product card + "Complete Your Routine" recommendations
- ✅ Genuine + register form (unique first-scan)
- 👋 Welcome back (already registered)
- ⚠️ Suspicious code → contact support
- ❌ Code not found + "Did you mean?" suggestions
- 🚦 Rate limited
- ⚠️ Malformed input

Result page also fires Pixel event + GTM dataLayer push for analytics.

## 12. Security

| Layer | Measure |
|---|---|
| HTTPS | Forced via root `.htaccess` |
| Headers | HSTS, X-Frame-Options, X-Content-Type, Referrer-Policy, Permissions-Policy |
| Sessions | HttpOnly + Secure + SameSite=Lax cookies, regenerate ID on login |
| Passwords | Bcrypt cost 12 |
| CSRF | Per-session tokens on all forms + AJAX calls |
| SQL | PDO prepared statements only |
| Input | All user input sanitized + escaped on output |
| Rate limiting | Verify 10/min, register 5/hour, suggest 30/min per IP |
| Login lockout | 5 fails → 15 min lockout |
| Audit log | Every admin action recorded |
| File access | `storage/`, `config/`, `src/`, `migrations/`, `tools/` denied via `.htaccess` |
| File uploads | Whitelist extensions, size limit 2 MB, randomized filenames |

## 13. Eco-Friendly Theme

| Token | Value |
|---|---|
| `--color-primary` | `#3E5641` (forest green) |
| `--color-accent`  | `#A4B494` (sage) |
| `--color-bg`      | `#F5F1E8` (cream) |
| `--color-beige`   | `#D9C9A3` |
| Display font | Cormorant Garamond |
| Headings | Fraunces |
| Body | Inter |
| Mono | JetBrains Mono |

All overridable per-deployment via Admin → Settings → General → Theme Colors.

## 14. Build Phases (delivered)

- ✅ **Phase 1 — Foundation:** project skeleton, autoload, schema, customer page, admin login
- ✅ **Phase 2 — Analytics:** dashboard, scan logs, geo, risk scoring, VIP exports
- ✅ **Phase 3 — Marketing:** Meta Pixel + CAPI, GTM, audience exports
- ✅ **Phase 4 — Polish:** PWA, audit log, admin user mgmt, brand customization, SQL backup

**Deferred:** QR code generation + label printing module (will be planned separately).

## 15. Maintenance

### Daily Cron (recommended)
```
0 3 * * * /usr/bin/php /home/USER/public_html/checker/tools/cleanup.php
```
Cleans rate limits, expired scan logs, audit log entries > 2 years, geo cache > 90 days.

### Updates
1. Pull new repo files
2. Upload via File Manager (skip `config/.env` and `storage/`)
3. Run any new SQL in `migrations/`
4. Done

### Backups
- Hostinger auto-backups daily (built-in to Business plan)
- Admin → Settings → Backup → Download SQL Dump for off-site copies

## 16. File Summary (≈70 files, ≈2 MB)

- 18 PHP service classes in `src/`
- 17 admin panel pages in `admin/`
- 5 public API endpoints in `api/`
- 6 admin API endpoints in `api/admin/`
- 1 customer-facing page (PWA) in `public/`
- 4 CSS/JS asset files
- 3 SQL migration files
- 2 CLI maintenance scripts
- 7 `.htaccess` security files
- Documentation: README, SPEC, DEPLOYMENT
