# Wellbeing Platform — Server Setup Guide

Complete instructions for deploying the platform on a fresh Ubuntu server.

---

## Stack Overview

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.1+ | Built-in server via systemd |
| Composer | 2.x | Dependency manager |
| Node.js | 20.x | Angular build only (not needed on server runtime) |
| Angular | 15.x | Built in GitHub Actions, deployed as static files |
| PostgreSQL | via Supabase | External DB, no local Postgres needed |
| Web server | Caddy | Reverse proxy, HTTPS, static files |
| OS | Ubuntu 22.04+ | Systemd required |
| Git | any | For `git pull` during deploy |

---

## 1. System Packages

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.1 + extensions
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.1 php8.1-cli php8.1-pgsql php8.1-mbstring \
    php8.1-xml php8.1-curl php8.1-zip php8.1-intl php8.1-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Git
sudo apt install -y git

# Caddy
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudflare.com/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudflare.com/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install -y caddy
```

---

## 2. Create App Directory & Clone Repo

```bash
# Create directory and set permissions
sudo mkdir -p /var/www/wellbeing
sudo chown -R www-data:www-data /var/www/wellbeing

# Add your deploy user to www-data group (for git operations)
sudo usermod -aG www-data $USER

# Clone the repository (as your deploy user)
cd /var/www/wellbeing
git clone git@github.com:YOUR_ORG/wellbeing.git .
```

---

## 3. Backend Configuration Files

These files are **NOT in git** (protected). Create them manually on the server.

### 3a. `main-local.php` — Database connection

```bash
sudo nano /var/www/wellbeing/backend/wellbeing-api/common/config/main-local.php
```

```php
<?php
return [
    'components' => [
        'db' => [
            'class'    => 'yii\db\Connection',
            'dsn'      => 'pgsql:host=YOUR_SUPABASE_HOST;port=5432;dbname=postgres',
            'username' => 'postgres',
            'password' => 'YOUR_SUPABASE_PASSWORD',
            'charset'  => 'utf8',
        ],
        'cache' => [
            'class'     => 'yii\caching\FileCache',
            'cachePath' => '@runtime/cache',
        ],
        'mailer' => [
            'class'                => \yii\symfonymailer\Mailer::class,
            'viewPath'             => '@common/mail',
            'useFileTransport'     => false,
            'transport' => [
                'scheme'   => 'smtps',
                'host'     => 'smtp.gmail.com',
                'port'     => 465,
                'username' => 'YOUR_EMAIL@gmail.com',
                'password' => 'YOUR_GMAIL_APP_PASSWORD',
            ],
        ],
    ],
];
```

> **Supabase DSN:** Go to Supabase → Settings → Database → Connection string → URI.
> Use `?sslmode=require` if Supabase requires SSL:
> `'dsn' => 'pgsql:host=db.xxxx.supabase.co;port=5432;dbname=postgres;sslmode=require'`

### 3b. `params-local.php` — App parameters

```bash
sudo nano /var/www/wellbeing/backend/wellbeing-api/common/config/params-local.php
```

```php
<?php
return [
    'frontendUrl' => 'https://yourdomain.com',
    'adminEmail'  => 'admin@yourdomain.com',
];
```

> `frontendUrl` is used as the redirect base after Google OAuth callback.

### 3c. `api/config/main-local.php` — API-level config

```bash
sudo nano /var/www/wellbeing/backend/wellbeing-api/api/config/main-local.php
```

```php
<?php
return [
    'components' => [
        'request' => [
            'cookieValidationKey' => 'RANDOM_32_CHAR_STRING_HERE',
        ],
    ],
];
```

Generate a key: `openssl rand -base64 32`

---

## 4. Environment Variables for PHP Server

The `api/web/index.php` reads these env variables:

| Variable | Example | Purpose |
|----------|---------|---------|
| `CORS_ALLOWED_ORIGINS` | `https://yourdomain.com` | CORS whitelist for the API |
| `YII_DEBUG` | `false` | Must be `false` in production |
| `YII_ENV` | `prod` | Enables production mode |

Set them in the systemd service file (see step 6).

---

## 5. Install PHP Dependencies

```bash
cd /var/www/wellbeing/backend/wellbeing-api
composer install --no-dev --optimize-autoloader --no-interaction

# Set correct permissions
sudo chown -R www-data:www-data api/runtime api/web/assets
sudo chmod -R 775 api/runtime api/web/assets
```

---

## 6. Systemd Service for PHP Backend

The service file is already in the repo at `scripts/wellbeing-api.service`. Deploy it:

```bash
sudo cp /var/www/wellbeing/scripts/wellbeing-api.service /etc/systemd/system/wellbeing-api.service
```

Edit the service to add environment variables:

```bash
sudo nano /etc/systemd/system/wellbeing-api.service
```

Add under `[Service]`:

```ini
Environment="YII_DEBUG=false"
Environment="YII_ENV=prod"
Environment="CORS_ALLOWED_ORIGINS=https://yourdomain.com"
Environment="PHP_CLI_SERVER_WORKERS=4"
```

Full service file should look like:

```ini
[Unit]
Description=Wellbeing API (php yii serve)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/wellbeing/backend/wellbeing-api
ExecStart=/usr/bin/php yii serve 0.0.0.0 --docroot=api/web --port=8000
Restart=always
RestartSec=5
MemoryMax=256M
RuntimeMaxSec=10800
Environment="YII_DEBUG=false"
Environment="YII_ENV=prod"
Environment="CORS_ALLOWED_ORIGINS=https://yourdomain.com"
Environment="PHP_CLI_SERVER_WORKERS=4"
StandardOutput=journal
StandardError=journal
SyslogIdentifier=wellbeing-api

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable wellbeing-api
sudo systemctl start wellbeing-api
sudo systemctl status wellbeing-api
```

Logs: `sudo journalctl -u wellbeing-api -f`

---

## 7. Run Database Migrations

```bash
cd /var/www/wellbeing/backend/wellbeing-api
php yii migrate --interactive=0
```

---

## 8. Caddy Web Server (Reverse Proxy + HTTPS)

Caddy automatically handles SSL certificates via Let's Encrypt.

The `Caddyfile` is **NOT in git** (protected). Create it manually:

```bash
sudo nano /etc/caddy/Caddyfile
```

```caddyfile
yourdomain.com {
    # Serve Angular static files
    root * /var/www/wellbeing/frontend/
    
    # Proxy API requests to PHP backend
    reverse_proxy /api/* 127.0.0.1:8000
    reverse_proxy /uploads/* 127.0.0.1:8000
    
    # Angular HTML5 routing — fallback to index.html
    try_files {path} /index.html
    file_server
    
    # Logs
    log {
        output file /var/log/caddy/access.log
    }
}
```

```bash
sudo systemctl enable caddy
sudo systemctl restart caddy
sudo systemctl status caddy
```

---

## 9. Google OAuth Setup

Google Calendar integration requires credentials from Google Cloud Console.

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Create a project → Enable **Google Calendar API**
3. Go to **APIs & Services → Credentials → Create OAuth 2.0 Client ID**
   - Application type: **Web application**
   - Authorized redirect URIs: `https://yourdomain.com/api/v1/google/callback`
4. Copy **Client ID** and **Client Secret**
5. Enter them via the app's admin panel → Settings (stored in `app_settings` DB table, keys `google_client_id` and `google_client_secret`)

Also set `app_url` in `app_settings` to `https://yourdomain.com` — this is used to build the OAuth callback URL.

---

## 10. GitHub Actions Secrets

Go to: **GitHub repo → Settings → Secrets and variables → Actions → New repository secret**

| Secret name | Value | Description |
|-------------|-------|-------------|
| `SSH_PRIVATE_KEY` | Private key content | SSH key to connect to server |
| `REMOTE_HOST` | `123.45.67.89` | Server IP or hostname |
| `REMOTE_USER` | `root` or `ubuntu` | SSH user (must have sudo for systemctl) |

### Generate SSH key pair

```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-deploy" -f ~/.ssh/wellbeing_deploy

# Copy public key to server
ssh-copy-id -i ~/.ssh/wellbeing_deploy.pub USER@SERVER_IP

# Add private key content to GitHub secret SSH_PRIVATE_KEY
cat ~/.ssh/wellbeing_deploy
```

### Allow REMOTE_USER to run systemctl without password

If your deploy user is not `root`, allow passwordless systemctl for the service:

```bash
sudo visudo
# Add:
ubuntu ALL=(ALL) NOPASSWD: /bin/systemctl restart wellbeing-api, /bin/systemctl daemon-reload, /bin/systemctl enable wellbeing-api
```

---

## 11. First Deploy Checklist

After all of the above:

- [ ] `sudo systemctl status wellbeing-api` — PHP backend running on port 8000
- [ ] `sudo systemctl status caddy` — Caddy running, HTTPS working
- [ ] `curl https://yourdomain.com/api/v1/health` — API responds (or any valid endpoint)
- [ ] Open `https://yourdomain.com` — Angular app loads
- [ ] Login works
- [ ] Admin panel → Settings → set `google_client_id`, `google_client_secret`, `app_url`
- [ ] Test Google Calendar connection
- [ ] Trigger a push to `main` — deploy pipeline runs green

---

## 12. Maintenance Commands

```bash
# View backend logs
sudo journalctl -u wellbeing-api -f

# Restart backend manually
sudo systemctl restart wellbeing-api

# Run migrations manually
cd /var/www/wellbeing/backend/wellbeing-api
php yii migrate --interactive=0

# Clear cache
php yii cache/flush-all --interactive=0

# Check Caddy logs
sudo tail -f /var/log/caddy/access.log
```

---

## NEVER Do on Server

- **Never run** `php init --overwrite=All` — it overwrites `main-local.php` with placeholders, breaking DB connection
- **Never commit** `main-local.php`, `params-local.php`, or `Caddyfile` — they contain secrets and are in `.gitignore`
- **Never use** `nohup php yii serve &` — it dies after a few hours; always use the systemd service
