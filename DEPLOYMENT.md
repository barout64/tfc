# Deploying TFC on a new Ubuntu server

This is one self-contained Laravel app. No other server or service is required — Meeting and
Protocol are still separate for now (see `config/tfc.php`), but Kilimo and the TFC Portal itself
are fully native here. Clone it, configure it, run it.

## 1. Install prerequisites (Ubuntu 22.04/24.04)

```bash
sudo apt update
sudo apt install -y nginx php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl \
    git unzip composer nodejs npm certbot python3-certbot-nginx
```

If `php8.4` isn't in the default Ubuntu repos yet, add the Ondřej Surý PPA first:
```bash
sudo add-apt-repository ppa:ondrej/php && sudo apt update
```

## 2. Clone and install

```bash
sudo mkdir -p /var/www/tfc
sudo chown $USER:$USER /var/www/tfc
git clone https://github.com/barout64/tfc.git /var/www/tfc
cd /var/www/tfc
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

## 3. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
- `APP_NAME`, `APP_URL` — set to your real domain (or an internal address if there's no public domain yet).
- `APP_ENV=production`, `APP_DEBUG=false`.
- `DB_CONNECTION=sqlite` is fine for this scale; create the file: `touch database/database.sqlite`.
- The `TFC_*` / `SMS_*` / `BEEM_*` / `PAYMENT_*` block at the bottom is Kilimo's own settings
  (hotline, subsidy rules, SMS and payment gateway). Leave `SMS_DRIVER=log` and
  `PAYMENT_DRIVER=sandbox` until real gateway credentials are available — nothing breaks, it just
  logs instead of sending/charging for real.
- Leave `TFC_MEETING_KEY` / `TFC_PROTOCOL_KEY` / their `_URL` vars blank if Meeting and Protocol
  aren't deployed alongside this copy — their cards on the home page will just show "not
  connected" instead of erroring.

## 4. Set up the database

```bash
php artisan migrate --force
```

**Then create the season.** This is the one thing every fresh Kilimo database needs before
almost anything else works (sales, reports, dashboards and the public homepage all ask "what's
the current season?"). Create one for real, matching however your planting/selling year actually
runs — for example:

```bash
php artisan tinker --execute="App\Models\Season::create(['name' => '2027/28', 'starts_on' => '2027-10-01', 'ends_on' => '2028-09-30', 'is_active' => true]);"
```

## 5. Create the first administrator

```bash
php artisan tfc:admin admin@yourdomain.tz "Your Name"
```

This prints an email and a one-time password — that's the login. It has full access everywhere,
including Kilimo. Change the password after first sign-in (Password, in the top-right menu).

## 6. File permissions

```bash
sudo chown -R www-data:www-data /var/www/tfc
sudo chmod -R 775 storage bootstrap/cache database
```

## 7. nginx + PHP-FPM

```nginx
server {
    server_name your-domain-or-ip;
    root /var/www/tfc/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Save as `/etc/nginx/sites-available/tfc`, then:
```bash
sudo ln -s /etc/nginx/sites-available/tfc /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 8. SSL (only if there's a real public domain pointing here)

```bash
sudo certbot --nginx -d your-domain
```

Skip this entirely on an internal/offline government network with no public DNS — just serve
over plain HTTP on the LAN, or put it behind whatever internal reverse proxy they already use.

## 9. Confirm it's working

Visit the site. The public homepage (prices, advice, farmer registration) should load with no
sign-in needed. Sign in with the administrator account from step 5, and you should see both the
TFC hub (top nav "Systems") and the Kilimo module ("Kilimo" in the nav).

## Updating later

```bash
cd /var/www/tfc
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache
```

## Rebranding away from BossCreative

Nothing in the app logic is tied to BossCreative — only the domain in `.env` (`APP_URL`,
`TFC_MEETING_URL`, `TFC_PROTOCOL_URL` if those are in use) and the SSL certificate point at
`bosscreativegroup.com` today. Changing the domain and re-running certbot (or dropping SSL
entirely for an internal network) is the whole job.
