# ELHOE Verification System

Standalone product authenticity verification system for the **ELHOE** skincare brand. Replaces a WordPress plugin so the verification page keeps running even when WordPress is down.

**Live URL:** `https://elhoe.com/checker`
**Stack:** PHP 8.1+ · MySQL · Vanilla JS · HTML5 · CSS3 (eco/organic theme)
**Hosting:** Hostinger Business hPanel
**No Composer · No frameworks · Drop-folder + import-SQL deployment**

---

## Why this exists

The previous WordPress plugin worked, but every time WordPress crashed, plugin-conflicted, or hit a security issue, customers lost the ability to verify their products. This standalone system runs in its own folder under `/checker/` and is fully independent of WordPress — share the same domain, share nothing else.

---

## Highlights

- 🛡️ **Independent of WordPress** — keeps working when WP doesn't
- 📱 **Mobile-first PWA** — installable, numeric-only keyboard
- 🔢 **Smart input handling** — strips junk like `https://elhoe.com/checker` automatically, "Did you mean?" suggestions
- 🛒 **Complete Your Routine** — drives repurchases via product links back to your WP shop
- 📊 **Analytics dashboard** — KPIs, charts, geo, counterfeit watchlist
- 🚨 **Risk scoring engine** — auto-quarantines suspicious codes
- 📈 **Marketing native** — Meta Pixel + Conversions API + GTM, VIP audience exports for Meta/Google
- 🔐 **Admin panel** — products, codes (auto-gen + paste + CSV), customers, scan log, settings, audit log
- ♻️ **Eco-friendly UI** — forest green + sage + cream palette, Cormorant Garamond + Fraunces + Inter fonts

---

## Repo Layout

```
checker/
├── public/                  Customer-facing PWA (entry point)
│   ├── index.php            Verification page
│   ├── manifest.json        PWA manifest
│   ├── service-worker.js    Offline shell cache
│   └── assets/{css,js,images}
├── admin/                   Admin panel (login, dashboard, codes, products, etc.)
├── api/                     REST endpoints (verify, register, suggest, health, admin/*)
├── src/                     PHP service classes (PSR-4, manual autoloader)
│   ├── Database.php
│   ├── Auth.php
│   ├── CSRF.php
│   ├── RateLimiter.php
│   ├── CodeNormalizer.php
│   ├── VerifyService.php
│   ├── CodeService.php
│   ├── ProductService.php
│   ├── CustomerService.php
│   ├── GeoIP.php
│   ├── RiskScorer.php
│   ├── PixelDispatcher.php
│   ├── GTMHelper.php
│   ├── Settings.php
│   ├── AuditLog.php
│   ├── Cache.php
│   ├── CSV.php
│   └── Logger.php
├── config/
│   ├── config.php           Bootstrap (env loader + autoloader + helpers)
│   └── .env.example         Copy to .env on the server
├── storage/                 Logs, cache, uploads, exports (writable)
├── migrations/
│   ├── 000_schema.sql       Run on fresh DB
│   ├── 001_legacy_data.sql  Optional: import old WP plugin data
│   └── 002_seed_admin.sql   Default admin/ChangeMe123! credentials
├── tools/
│   ├── cleanup.php          Daily cron maintenance
│   └── seed-admin.php       CLI admin-seeder
├── .htaccess                Root rules, security headers, HTTPS forcing
├── README.md
├── SPEC.md
└── DEPLOYMENT.md            Hostinger step-by-step
```

---

## Quick Start (Production)

See [DEPLOYMENT.md](./DEPLOYMENT.md) for the complete step-by-step Hostinger walkthrough.

**TL;DR:**
1. Create MySQL DB in hPanel
2. Upload all files to `/public_html/checker/`
3. Copy `config/.env.example` → `config/.env`, fill DB creds and `APP_KEY`
4. phpMyAdmin → Import → `migrations/000_schema.sql` then `002_seed_admin.sql`
5. Add 5-line bypass rule to root `.htaccess` (above WordPress block)
6. Visit `https://elhoe.com/checker` ✓
7. Log in at `/checker/admin` with `admin / ChangeMe123!` and change the password

---

## Local Development

Requires PHP 8.1+ and MySQL 5.7+.

```bash
git clone https://github.com/colorgravitybd-max/elhoeverification.git
cd elhoeverification

# Configure
cp config/.env.example config/.env
# Edit config/.env with your local DB credentials

# Import schema (via your local phpMyAdmin or CLI)
mysql -u root -p elhoe_local < migrations/000_schema.sql
mysql -u root -p elhoe_local < migrations/002_seed_admin.sql

# Run dev server
php -S localhost:8000 -t public/
# Customer page: http://localhost:8000
# Admin (must adjust ROUTER): http://localhost:8000/../admin/index.php
```

For a smoother local experience, use Hostinger's exact production paths or set up a local Apache vhost so `/checker/` lives under `/public_html/`.

---

## Default Admin Credentials

After running `002_seed_admin.sql`:
- **Username:** `admin`
- **Password:** `ChangeMe123!`
- **Email:** `admin@elhoe.com`
- **Role:** `super_admin`

⚠️ **Change immediately after first login** (Settings → Admins).

---

## Default Theme

Eco / organic / premium — matches a skincare brand aesthetic.

| Role | Color | Hex |
|---|---|---|
| Primary | Forest Green | `#3E5641` |
| Accent | Sage | `#A4B494` |
| Background | Cream | `#F5F1E8` |
| Beige | Warm | `#D9C9A3` |

Fonts: **Cormorant Garamond** (display) · **Fraunces** (headings) · **Inter** (body) · **JetBrains Mono** (code).

All editable in **Admin → Settings → General**.

---

## License

Proprietary — © ELHOE Skincare. All rights reserved.

---

## Credits

Built with care for the ELHOE team. Replaces the previous WordPress plugin to give customers a more reliable verification experience.
