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

Free sessions are now driven by **Creatio contracts** (`contract` table), NOT by `company.free_sessions_per_user`.

- Active contract = `contract.is_active = true` for the user's company
- `contract.free_sessions_per_employee` — sessions allowed per user (synced from `WelNumberOfFreeConsultation` in Creatio)
- `contract.session_price` — price per session (synced from `WelPriceConsultation` in Creatio)
- Remaining = `contract.free_sessions_per_employee` - count of user's subscription appointments **this month**
- "Subscription appointment" = `payment_status='paid'` AND no row in `payment` table
- Count is scoped to current calendar month via `DATE_TRUNC('month', appointment_date::date) = DATE_TRUNC('month', CURRENT_DATE)`
- **No hardcoded defaults** — if no active contract or `free_sessions_per_employee = 0`, subscription is unavailable
- Three places must stay in sync: `DashboardController::actionIndex()`, `DashboardController::actionFreeSessions()`, `AppointmentController::countSubscriptionSessions()`

`company.free_sessions_per_user` column still exists but is **no longer the source of truth** — contracts are.
`syncCompany()` no longer sends `WelNumberOfFreeConsultation` to Creatio (contracts manage this now).

---

## GOOGLE CALENDAR

- `GoogleController::actionCallback()` redirects to `Yii::$app->params['frontendUrl']`
- Set `frontendUrl` in `common/config/params-local.php` on server (e.g., `'frontendUrl' => 'https://yourdomain.com'`)
- Default fallback in `common/config/params.php` is `http://localhost:4200`
- `actionUpcomingEvents()`: if token exists but fetching events fails → return `connected: true` (token is valid, events just failed to load)
- Dashboard checks connection via `getGoogleUpcomingEvents()` (gets connected + events in one call)
- Settings page checks connection via `getGoogleStatus()` (gets connected + email)

---

## CREATIO CRM INTEGRATION

Settings stored in `app_settings` table (managed via Admin → Settings):
- `creatio_enabled` — `'1'` to enable, `'0'` to disable
- `creatio_base_url` — e.g. `https://dev-wellbeing-company.creatio.com`
- `creatio_identity_url` — e.g. `https://dev-wellbeing-company-is.creatio.com` (derived automatically if blank)
- `creatio_client_id` / `creatio_client_secret` — OAuth2 client credentials

Service: `common/services/CreatioSyncService.php`

| Method | When called | What it does |
|---|---|---|
| `syncUser($user)` | Registration only | Creates/updates Creatio Contact (type=Клієнт), saves GUID → `user.creatio_contact_id` |
| `syncCompany($company)` | Company create/update | Creates/updates Creatio Account, saves GUID → `company.creatio_account_id`, uploads logo (on create) |
| `syncSpecialist($specialist)` | Specialist create/update | Creates/updates Creatio Contact (type=Експерт), saves GUID → `specialist.creatio_contact_id` |
| `syncAppointment($appt, $user)` | Appointment created | Creates Creatio Activity with all GUIDs, saves GUID → `appointment.creatio_activity_id` |
| `markAppointmentPaid($appt)` | Payment confirmed | PATCHes `WelIsPaid=true` on the linked Activity |
| `syncContracts($accountId?)` | Cron nightly / admin manual | Fetches all `WelContract` from Creatio, upserts into `contract` table |
| `syncDocument($doc, $user)` | Document upload | POST ContactFile metadata + PUT binary to Creatio; saves GUID → `document.creatio_file_id` |
| `deleteDocument($doc)` | Document delete | DELETEs ContactFile from Creatio (best-effort) |
| `upsertContractPayload($payload)` | Creatio webhook | Upserts a single contract from webhook payload |

### Key Creatio GUIDs (hardcoded)
- Contact type Клієнт: `00783ef6-f36b-1410-a883-16d83cab0980`
- Contact type Експерт: `479beccb-5438-4730-ae62-f44dd65b68b9`
- Activity type Консультація: `fbe0acdc-cfc0-df11-b00f-001d60e938c6`
- ContactFile TypeId: `529bc2f8-0ee0-df11-971b-001d60e938c6`
- WelCommunicationMethod — Google Meet: `efe5d7a2-5f38-e111-851e-00155d04c01d`, Zoom: `6e3d1f7d-f2aa-4d10-a44f-ebd63b2b2dfc`, Teams: `0294562f-80f1-4ae6-b88c-70991d620514`

### Contract sync details
- `WelNumberOfFreeConsultation` → `contract.free_sessions_per_employee` (NOT `WelLimitConsultationsEmployee`)
- `WelPriceConsultation` → `contract.session_price`
- Dual lookup: contracts matched by `WelAccountId == company.creatio_account_id` (new) AND by existing `contract.creatio_contract_id` (stale data updates)
- Webhook endpoint: `POST /v1/creatio/webhook/contract` — authenticated by `X-Creatio-Secret` header

### Company Creatio ID linkage
- `company.creatio_account_id` — stored Creatio Account GUID
- Admin can set this manually in Admin → Companies (Creatio Account ID field) to link an existing Creatio account
- On portal company create → auto-creates Creatio Account, saves GUID back
- On portal company update with existing `creatio_account_id` → PATCHes existing Creatio Account

---

## NIGHTLY CREATIO SYNC (systemd timer)

Console command: `php yii creatio/sync-contracts`

Systemd files in `scripts/`:
- `creatio-sync.service` — one-shot service that runs the command
- `creatio-sync.timer` — timer that fires daily at 03:00

### Install on server:
```bash
sudo cp /var/www/wellbeing/scripts/creatio-sync.service /etc/systemd/system/
sudo cp /var/www/wellbeing/scripts/creatio-sync.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable creatio-sync.timer
sudo systemctl start creatio-sync.timer
```

### Check timer status:
```bash
systemctl status creatio-sync.timer
systemctl list-timers creatio-sync.timer
```

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
