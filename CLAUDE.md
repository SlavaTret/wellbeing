# CLAUDE.md — Wellbeing Company Platform

## LOCAL DEVELOPMENT

### Start backend (PHP Yii2)
```bash
cd backend/wellbeing-api
PHP_CLI_SERVER_WORKERS=4 php yii serve 0.0.0.0 --docroot=api/web --port=8000
```
**CRITICAL:** Must use `PHP_CLI_SERVER_WORKERS=4`. Without it the server is single-threaded — Angular fires multiple simultaneous requests on startup and they all timeout with 504.

### Start frontend (Angular)
```bash
cd frontend/wellbeing-app
npm start   # runs: ng serve --proxy-config proxy.conf.json
```
Frontend: http://localhost:4200 — Backend: http://localhost:8000

### Proxy config (`frontend/wellbeing-app/proxy.conf.json`)
Target MUST be `http://127.0.0.1:8000`, NOT `http://localhost:8000`.
On macOS, `localhost` resolves to `::1` (IPv6) but PHP listens only on IPv4 → 504 Gateway Timeout.

---

## MIGRATIONS — CRITICAL RULES

**Production DB is live with real user data. Never add INSERT, UPDATE, or DELETE to migrations.**

### Rules:
1. Migrations add/modify **schema only** — `createTable`, `addColumn`, `addForeignKey`, `createIndex`
2. **No seed data** — no `insert()`, `batchInsert()`, `execute("INSERT ...")` in `safeUp()`
3. **No data cleanup** — no `delete()`, `execute("DELETE ...")` in `safeUp()`
4. Every `addColumn` in `safeUp()` MUST be idempotent:
   ```php
   $table = $this->db->getTableSchema('{{%table_name}}');
   if ($table && !isset($table->columns['column_name'])) {
       $this->addColumn('{{%table_name}}', 'column_name', $this->string()->null());
   }
   ```
5. Use `safeUp()` / `safeDown()` (not `up()` / `down()`) for all new migrations
6. Migration class name must match filename exactly

### DB: PostgreSQL (Supabase)
- Production DSN in `backend/wellbeing-api/common/config/main-local.php` — never commit this file
- Run migrations: `php yii migrate --interactive=0`

---

## DEPLOY — CRITICAL RULES

Deploy happens via `.github/workflows/deploy.yml` on push to `main`.

### What deploy does:
1. Builds Angular (`npm ci && npm run build --configuration=production`)
2. Syncs built frontend to server via rsync
3. SSHs to server: `git pull` → `composer install --no-dev` → `php yii migrate --interactive=0` → restarts systemd service

### What deploy MUST NOT do:
- Never run `php init --overwrite=All` — it would overwrite `main-local.php` with example values, breaking DB connection
- Never commit or touch `backend/wellbeing-api/common/config/main-local.php`
- Never commit or touch `Caddyfile`

### Protected files (in .gitignore, never commit):
- `backend/wellbeing-api/common/config/main-local.php`
- `backend/wellbeing-api/common/config/params-local.php`
- `Caddyfile`

---

## BACKEND PHP SERVER (PRODUCTION)

Production runs via systemd, not `nohup`. Service file: `scripts/wellbeing-api.service`.

Install/update service:
```bash
sudo cp /var/www/wellbeing/scripts/wellbeing-api.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable wellbeing-api
sudo systemctl restart wellbeing-api
```

The deploy workflow automatically restarts the service after each deploy.
**Never use `nohup php yii serve ... &`** on production — it dies after 4-5h and can crash SSH.

---

## FREE SESSIONS LOGIC

- `company.free_sessions_per_user` — sessions allowed per user **per calendar month**
- Remaining = `company.free_sessions_per_user` - count of user's subscription appointments **this month**
- "Subscription appointment" = `payment_status='paid'` AND no row in `payment` table
- Count is scoped to current calendar month via `DATE_TRUNC('month', appointment_date::date) = DATE_TRUNC('month', CURRENT_DATE)`
- **No hardcoded defaults** — if `free_sessions_per_user = 0` or user has no company, subscription is unavailable
- Three places must stay in sync: `DashboardController::actionIndex()`, `DashboardController::actionFreeSessions()`, `AppointmentController::countSubscriptionSessions()`

---

## GOOGLE CALENDAR

- `GoogleController::actionCallback()` redirects to `Yii::$app->params['frontendUrl']`
- Set `frontendUrl` in `common/config/params-local.php` on server (e.g., `'frontendUrl' => 'https://yourdomain.com'`)
- Default fallback in `common/config/params.php` is `http://localhost:4200`
- `actionUpcomingEvents()`: if token exists but fetching events fails → return `connected: true` (token is valid, events just failed to load)
- Dashboard checks connection via `getGoogleUpcomingEvents()` (gets connected + events in one call)
- Settings page checks connection via `getGoogleStatus()` (gets connected + email)

---

## APPOINTMENT BOOKING FLOW (6 steps)

Step 1: Specialist catalog
Step 2: Specialist profile (info only, no form)
Step 3: Time slot selection
Step 4: Payment method + Communication method (Google Meet / Zoom / MS Teams)
Step 5: Confirm details
Step 6: Success

`communication_method` values: `'google_meet'`, `'zoom'`, `'teams'` — stored on `appointment.communication_method` column.
Sent to Creatio as `WelCommunicationMethodId` GUID + title suffix.
