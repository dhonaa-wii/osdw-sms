# Deploying OSDW SMS

How to put the system on a live server. Any host that runs **PHP 8.2+** and
**MySQL 8** works — a VPS (Ubuntu + Nginx), or a PaaS such as Laravel Cloud,
Railway or Render.

## Server requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `bcmath`, `xml`, `curl`
- Composer 2, Node.js 20+ (only to build the CSS/JS)
- MySQL 8
- Web root pointed at **`public/`** — never the project folder
- Optional: Google Chrome or Microsoft Edge (Help Center "Download PDF"; without it the button reports that the PDF is unavailable), `mysqldump` (Database backup page)

## 1. Get the code

```bash
git clone https://github.com/dhonaa-wii/osdw-sms.git
cd osdw-sms
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

## 2. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Change at least these values:

| Key | Live value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — never `true` on a live server; it shows secrets on error pages |
| `APP_URL` | the public address, e.g. `https://osdw.example.edu.ph` (https makes every link and cookie secure) |
| `LOG_LEVEL` | `error` |
| `DB_*` | the live database and a dedicated user (not `root`) |
| `SESSION_SECURE_COOKIE` | `true` when the site is on https |
| `MAIL_*` | a real SMTP account — registration and password-reset codes are sent by email, so `MAIL_MAILER=log` means nobody can register |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | optional; add `APP_URL/auth/google/callback` as an authorized redirect URI in Google Cloud Console |
| `CHROME_PATH` | optional, path to Chrome/Edge if not in the usual place |

For Gmail SMTP use `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`,
`MAIL_SCHEME=null`, your address as `MAIL_USERNAME` and `MAIL_FROM_ADDRESS`, and a
Google **App Password** as `MAIL_PASSWORD`.

## 3. Database and first administrator

```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan osdw:create-admin
```

`ProductionSeeder` loads the branding images, campus and colleges only.
Do **not** run plain `db:seed` on a live server — it creates demo accounts with
the password `password`.

## 4. Files and caches

```bash
php artisan storage:link
php artisan optimize
```

`storage/` and `bootstrap/cache/` must be writable by the web server user
(`chown -R www-data:www-data storage bootstrap/cache` on Ubuntu).

## 5. Scheduler

Add one cron entry so registration notices and the 60-day purge run:

```
* * * * * cd /path/to/osdw-sms && php artisan schedule:run >> /dev/null 2>&1
```

The system still sends registration notices on page loads without it, but the
cron keeps them on time. No queue worker is needed — mail is sent immediately.

## Updating a live server

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

## Checking it works

- `https://your-domain/up` returns a green "Application up" page.
- Register a student account — the 6-digit code must arrive by email.
- Sign in as the administrator, add a staff account, and sign in as that staff member.
