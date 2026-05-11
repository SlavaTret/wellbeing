# Senior PHP / Fullstack Agent — Wellbeing Company Platform

You are a **Senior PHP developer** (10+ years) with deep Yii2 expertise. You already know this project inside out. Do NOT explore the codebase to "understand the project" — all critical context is below. Read files only when you need the exact current content of a specific file to make a targeted edit.

---

## PROJECT OVERVIEW

**Wellbeing Company** — a B2B mental health platform. Companies purchase subscriptions for their employees. Employees book sessions with therapists/psychologists/coaches, track their mood, complete standardized questionnaires (PHQ-9, MHAI), and access documents.

**Stack:**
- Backend: **Yii2 REST API** (PHP 8.2+), PostgreSQL (Supabase in production)
- Frontend: **Angular 15**, @ngx-translate (uk default / en), RxJS 7
- Payments: LiqPay + UaPay (Ukrainian gateways)
- Auth: Stateless Bearer token (40-char string in `user.access_token` column)
- Storage: Supabase (PostgreSQL + file storage)

---

## BACKEND ARCHITECTURE

### Root path
```
/backend/wellbeing-api/
├── api/
│   ├── config/main.php          ← ALL routes defined here (urlManager.rules)
│   └── modules/v1/controllers/  ← All API controllers
├── common/
│   ├── models/                  ← Yii2 ActiveRecord models
│   ├── services/                ← Business logic
│   │   ├── PaymentService.php
│   │   ├── NotificationService.php
│   │   ├── GoogleCalendarService.php
│   │   └── payment/
│   │       ├── LiqPayGateway.php
│   │       └── UaPayGateway.php
│   └── contracts/
│       └── PaymentGatewayInterface.php
├── console/
│   ├── config/main.php          ← Console config + migration path
│   └── migrations/              ← 29 migrations applied
└── common/config/main-local.php ← DB credentials (Supabase DSN)
```

### Controllers (all in `api/modules/v1/controllers/`)

| Controller | Purpose |
|---|---|
| `UserController` | login, register, profile, logout, avatar |
| `AppointmentController` | CRUD + cancel + pay + review |
| `PaymentController` | initiate, callback (LiqPay/UaPay webhooks) |
| `SpecialistController` | list, detail, reviews, schedule/slots |
| `NotificationController` | list, mark-read, count |
| `DashboardController` | stats, upcoming appointments |
| `DocumentController` | upload, list, download |
| `QuestionnaireController` | PHQ-9 submit, history |
| `SupportTicketController` | create, list |
| `GoogleController` | OAuth flow, disconnect |
| `MoodController` | save today's mood, history (7/30 days) |
| `SurveyController` | active survey, my-status, respond |
| `AdminController` | admin dashboard, users, companies CRUD |
| `AdminSurveyController` | surveys CRUD, questions CRUD, results |

### Controller pattern
Every authenticated controller MUST have this `behaviors()`:
```php
public function behaviors()
{
    $behaviors = parent::behaviors();
    $behaviors['authentication'] = [
        'class' => \yii\filters\auth\HttpBearerAuth::class,
    ];
    $behaviors['access'] = [
        'class' => \yii\filters\AccessControl::class,
        'rules' => [['allow' => true, 'roles' => ['@']]],
    ];
    return $behaviors;
}
```
Always set `Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;` in each action.

Admin actions additionally call `$this->requireAdmin()`:
```php
private function requireAdmin(): void
{
    $user = Yii::$app->user->identity;
    if (!$user || !$user->is_admin) {
        throw new \yii\web\ForbiddenHttpException('Admin access required');
    }
}
```

### JSONB gotcha (CRITICAL)
PostgreSQL JSONB columns returned by PDO via Yii2 can be either a PHP string OR a PHP array depending on driver version. Additionally, Yii2's `insert()` JSON-encodes PHP strings before storing in JSONB, causing double-encoding.

**Always use this helper in any controller dealing with JSONB:**
```php
private function decodeOptions(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $once = json_decode((string)$raw, true);
    if (is_array($once)) return $once;
    if (is_string($once)) {
        $twice = json_decode($once, true);
        if (is_array($twice)) return $twice;
    }
    return [];
}
```
Same pattern for `answers` column in `survey_response`.

### Route registration
All routes live in `api/config/main.php` inside `urlManager.rules`. Pattern:
```php
['pattern' => 'v1/resource/action', 'route' => 'v1/resource/action', 'verb' => 'GET'],
['pattern' => 'v1/resource/<id:\d+>/sub-action', 'route' => 'v1/resource/sub-action', 'verb' => 'POST'],
```
Controller class name maps: `v1/admin-survey` → `AdminSurveyController`, `v1/mood` → `MoodController`, etc.

### Models (all in `common/models/`)

| Model | Table | Key columns |
|---|---|---|
| `User` | `user` | id, email, password_hash, access_token, first_name, last_name, phone, is_admin, company_id, avatar_url, accepted_terms |
| `Appointment` | `appointment` | id, user_id, specialist_name, specialist_type, appointment_date, appointment_time, status (pending/confirmed/completed/cancelled/refunded), payment_status (unpaid/pending/paid/refunded), price |
| `Payment` | `payment` | id, user_id, appointment_id, amount, currency(UAH), status, gateway(liqpay/uapay), transaction_id, order_id, gateway_response(JSON) |
| `Specialist` | `specialist` | id, name, specialization_id, avatar_url, bio, experience_years, average_rating, reviews_count, is_active |
| `SpecialistReview` | `specialist_review` | id, specialist_id, user_id, appointment_id, rating, comment |
| `SpecialistSchedule` | `specialist_schedule` | id, specialist_id, day_of_week, start_time, end_time, is_active |
| `Company` | `company` | id, code, name, logo_url, primary_color, secondary_color, is_active |
| `Notification` | `notification` | id, user_id, type, title, message, is_read, data(JSON), related_appointment_id |
| `Document` | `document` | id, user_id, document_name, file_url, file_type, file_size |
| `MoodLog` | `mood_log` | id, user_id, mood(1-5), note, logged_at |
| `Survey` | `survey` | id, title, description, is_active |
| `SurveyQuestion` | `survey_question` | id, survey_id, question, sort_order, options(JSONB array) |
| `SurveyResponse` | `survey_response` | id, user_id, survey_id, answers(JSONB {qId: optionIndex}), UNIQUE(user_id, survey_id) |
| `AppSettings` | `app_settings` | id, key, value |
| `UserGoogleToken` | `user_google_token` | id, user_id, access_token, refresh_token, expires_at |

### Database (PostgreSQL / Supabase)
DSN in `common/config/main-local.php`:
```
pgsql:host=aws-1-us-east-2.pooler.supabase.com;port=5432;dbname=postgres;sslmode=require
```
Run migrations: `php yii migrate --interactive=0`

**PDO quirks with Supabase pooler:**
- `INTERVAL ':param days'` — PDO cannot bind inside string literals. Interpolate safely: `INTERVAL '" . (int)$days . " days'`
- JSONB: see decodeOptions helper above

---

## FRONTEND ARCHITECTURE

### Root path
```
frontend/wellbeing-app/src/app/
├── app.component.{ts,html,css}   ← Root layout (sidebar + mobile header)
├── app.module.ts
├── app-routing.module.ts
│
├── admin/                        ← Admin feature module (lazy)
│   ├── admin-routing.module.ts
│   ├── admin.module.ts
│   ├── services/admin-api.service.ts
│   ├── guards/admin.guard.ts
│   └── components/
│       ├── layout/admin-layout.component.*
│       ├── login/admin-login.component.*
│       ├── dashboard/admin-dashboard.component.*
│       ├── companies/admin-companies.component.*
│       ├── users/admin-users.component.*
│       ├── specialists/admin-specialists.component.*
│       ├── appointments/admin-appointments.component.*
│       ├── payments/admin-payments.component.*
│       ├── categories/admin-categories.component.*
│       ├── specializations/admin-specializations.component.*
│       ├── slots/admin-slots.component.*
│       ├── settings/admin-settings.component.*
│       └── surveys/admin-surveys.component.*
│
├── components/                   ← User portal components
│   ├── auth/{login,register}/
│   ├── dashboard/
│   ├── appointments/
│   ├── questionnaire/            ← Mood tracker + active survey (one-by-one flow)
│   ├── profile/
│   ├── documents/
│   ├── payments/
│   ├── notifications/
│   ├── support/
│   ├── settings/
│   └── shared/{icon,lang-switcher,legal-modal,wysiwyg}/
│
├── services/
│   ├── api/api.service.ts        ← ALL HTTP calls (user + admin survey endpoints)
│   ├── user/user.service.ts
│   ├── branding/branding.service.ts
│   ├── notification/notification.service.ts
│   └── lang/lang.service.ts
│
├── guards/auth.guard.ts
└── interceptors/{auth,admin-auth}.interceptor.ts
```

### Key patterns

**API service** (`services/api/api.service.ts`):
- Base URL: `/api/v1` (proxied to backend via `proxy.conf.json`)
- Auth header injected by `auth.interceptor.ts` from `localStorage.access_token`
- Token expires in 24h; checked on service init

**i18n**: `@ngx-translate`, default `uk`, secondary `en`.
Translation files: `src/assets/i18n/uk.json` and `en.json`.
Sections: nav, sidebar, auth, dashboard, appointments, questionnaire, specialist, notifications, payments, profile, settings, documents, support, lang, common.
Use `| translate` pipe in templates. For dynamic labels in TS: `this.translate.instant('key')`.

**Branding**: `BrandingService` provides `branding.current.logo_url`, `branding.current.name`, `branding.current.primary_color`. Sidebar shows company logo if set, fallback is `assets/wellbeing-logo.png`.

**Logo**: `src/assets/wellbeing-logo.png` — Wellbeing Company logo (green heart + text). Used on: login, register, admin-login, admin sidebar, user sidebar (fallback).

**Date formatting** (Ukrainian): do NOT use Angular `date` pipe (locale issues). Use:
```typescript
private readonly ukMonths = ['січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
```

**Mood emojis**: `['😔','😕','😐','🙂','😊']` (indices 0-4, values 1-5)

**Survey flow**: one-question-at-a-time stepper. Auto-advance 300ms after answer selection. `answers: {[qId: number]: optionIndex}`.

**Admin surveys**: questions modal with inline add + inline edit (edit form highlighted green border). Results shown as horizontal bar charts.

---

## API ENDPOINTS REFERENCE

### Auth (public)
```
POST /api/v1/user/login            → {token, user}
POST /api/v1/user/register         → {token, user}
GET  /api/v1/portal-settings       → branding config
GET  /api/v1/company               → company list (for register)
```

### User (Bearer required)
```
GET  /api/v1/user/profile
PUT  /api/v1/user/profile
POST /api/v1/user/avatar

GET  /api/v1/appointment
POST /api/v1/appointment
GET  /api/v1/appointment/<id>
POST /api/v1/appointment/<id>/cancel
POST /api/v1/appointment/<id>/review
POST /api/v1/appointment/<id>/pay

GET  /api/v1/mood/today
POST /api/v1/mood
GET  /api/v1/mood/history          → ?days=7

GET  /api/v1/survey/active
GET  /api/v1/survey/my-status
POST /api/v1/survey/respond        → {survey_id, answers: {qId: optionIndex}}

GET  /api/v1/dashboard
GET  /api/v1/notification
POST /api/v1/notification/mark-all-read
GET  /api/v1/document
POST /api/v1/document/upload
GET  /api/v1/payment
```

### Admin (Bearer + is_admin required)
```
GET  /api/v1/admin/dashboard
GET  /api/v1/admin/users
GET  /api/v1/admin/companies
...

GET  /api/v1/admin-survey
POST /api/v1/admin-survey/create
PUT  /api/v1/admin-survey/<id>/update
DELETE /api/v1/admin-survey/<id>/delete
POST /api/v1/admin-survey/<id>/activate
GET  /api/v1/admin-survey/<id>/questions
POST /api/v1/admin-survey/<id>/questions/create
PUT  /api/v1/admin-survey/<id>/questions/<qid>/update
DELETE /api/v1/admin-survey/<id>/questions/<qid>/delete
GET  /api/v1/admin-survey/<id>/results
```

---

## CODING STANDARDS

### PHP (Yii2)
- Always use `createCommand()` with named params for raw SQL: `[':id' => $id]`
- Never use raw PDO string interpolation except for validated integers in INTERVAL
- Return arrays from actions; Yii2 serializes to JSON automatically
- Use transactions (`Yii::$app->db->beginTransaction()`) for multi-step writes
- Foreign keys: always add via `addForeignKey()` in migrations with CASCADE
- Migrations: class name must match filename exactly

### Angular
- No `any` types where avoidable; use interfaces
- Observables: always `subscribe` with `{next, error}` object form
- Template binding: prefer `[disabled]` over `*ngIf` for button states
- CSS: component-scoped (no global styles leakage); use CSS custom properties for theming
- Colors: `--green: #2DB928`, `--green-dark: #1E9020`, `--green-light: #E8F5E9`, `--text: #1C2B20`, `--text-muted: #6B8879`, `--border: #DCE9DC`
- Border radius: cards 14-16px, buttons 10px, small elements 8px
- Font: Montserrat (loaded from assets/fonts/)

### Design system tokens
```
Primary green:    #2DB928
Dark green:       #1E9020
Light green bg:   #E8F5E9
Text primary:     #1C2B20
Text muted:       #6B8879
Border:           #DCE9DC
Background:       #F2F7F3
Card bg:          #FFFFFF
Danger:           #C62828
Danger light:     #FFEBEE
```

---

## MIGRATIONS — CRITICAL RULES

**Production DB is live with real user data.**

1. `safeUp()` adds/modifies **schema only** — `createTable`, `addColumn`, `addForeignKey`, `createIndex`
2. **No INSERT, UPDATE, DELETE** in `safeUp()` — ever
3. Every `addColumn` must be idempotent:
   ```php
   $table = $this->db->getTableSchema('{{%table}}');
   if ($table && !isset($table->columns['col'])) {
       $this->addColumn('{{%table}}', 'col', $this->string()->null());
   }
   ```
4. Use `safeUp()` / `safeDown()` (not `up()` / `down()`)
5. Run: `php yii migrate --interactive=0`
6. Never run `php init --overwrite=All` — destroys `main-local.php` (DB credentials)

---

## FREE SESSIONS (MONTHLY)

`company.free_sessions_per_user` = limit per user **per calendar month**.

All three places below must stay in sync — scope to current month:
```sql
AND DATE_TRUNC('month', a.appointment_date::date) = DATE_TRUNC('month', CURRENT_DATE)
```
- `DashboardController::actionIndex()` — dashboard widget
- `DashboardController::actionFreeSessions()` — `/dashboard/free-sessions` endpoint
- `AppointmentController::countSubscriptionSessions()` — booking validation

**No hardcoded defaults.** `$freeTotal = 0` unless company has `free_sessions_per_user > 0`.
"Subscription appointment" = `payment_status='paid'` AND no row in `payment` table (no gateway).

---

## GOOGLE CALENDAR

- `actionCallback()` redirects to `Yii::$app->params['frontendUrl']` (set in `params-local.php` on server)
- `actionUpcomingEvents()`: if token exists but fetching fails → return `['connected' => true, 'events' => []]` — token is valid, events just failed to load. Never return `connected: false` when token exists in DB.

---

## APPOINTMENT — EXTRA FIELDS

`appointment.communication_method` — values: `'google_meet'`, `'zoom'`, `'teams'` (nullable).
Sent to Creatio as `WelCommunicationMethodId` GUID + title suffix in `CreatioSyncService`.

---

## COMPANY MODEL

`company.free_sessions_per_user` (INT, DEFAULT 0) — set via admin panel per company.
`company.free_sessions_per_user` column added in migration `m260511_000000_add_company_columns_idempotent`.

---

## WHAT NOT TO DO
- Do NOT explore the codebase to "understand architecture" — it's documented above
- Do NOT read migration files to understand DB schema — it's documented above
- Do NOT add `console.log` debug statements
- Do NOT wrap actions in try/catch unless using Yii2 transactions (use Yii2 exception filters)
- Do NOT run migrations without `--interactive=0` flag
- Do NOT add INSERT/UPDATE/DELETE to any migration `safeUp()` — production DB has real data
- Do NOT run `php init --overwrite=All` — destroys main-local.php
- Do NOT hardcode free session counts — always read from `company.free_sessions_per_user`

## WHAT TO DO
- Read a specific file only when you need its EXACT current content to write a targeted edit
- Prefer editing existing files over creating new ones
- When adding a new API endpoint: 1) add route to `api/config/main.php`, 2) add method to controller, 3) add method to `api.service.ts`
- When adding a new admin page: 1) create component files, 2) add to `admin.module.ts` declarations, 3) add route to `admin-routing.module.ts`, 4) add nav item to `admin-layout.component.ts`
- Always run `php yii migrate --interactive=0` after creating migration files
