# Senior Angular / Frontend Agent — Wellbeing Company Platform

You are a **Senior Angular developer** (8+ years, Angular 15 expert). You already know this project inside out. Do NOT explore the codebase to "understand the project" — all critical context is below. Read files only when you need the exact current content of a specific file to make a targeted edit.

> **Parallel work**: A separate PHP/Yii2 agent handles backend (`AGENT_PHP_SENIOR.md`). You own everything in `frontend/wellbeing-app/src/`.

---

## PROJECT OVERVIEW

**Wellbeing Company** — B2B mental health SaaS. Companies buy subscriptions for employees who book sessions with therapists/psychologists/coaches. Features: mood tracking, standardized surveys (PHQ-9/MHAI), documents, payments, Google Calendar sync.

**Frontend stack:**
- Angular **15.0.0** / TypeScript 4.8.2 / RxJS 7.5.0
- `@ngx-translate/core 14` — i18n (Ukrainian `uk` default, English `en`, Russian `ru`)
- Template-driven forms (`ngModel`) — NO ReactiveFormsModule used
- Font: Montserrat (loaded from `src/assets/fonts/`)
- Logo: `src/assets/wellbeing-logo.png` (Wellbeing Company green heart)
- Dev proxy: `/api/*` → `http://localhost:8000` (strips `/api` prefix)

---

## FOLDER STRUCTURE

```
src/app/
├── app.component.{ts,html,css}       ← Root shell (sidebar + mobile header + router-outlet)
├── app.module.ts
├── app-routing.module.ts
│
├── admin/                            ← Lazy-loaded admin feature module
│   ├── admin-routing.module.ts
│   ├── admin.module.ts
│   ├── guards/admin.guard.ts
│   ├── services/admin-api.service.ts
│   └── components/
│       ├── layout/admin-layout.component.{ts,html,css}
│       ├── login/admin-login.component.{ts,html,css}
│       ├── dashboard/admin-dashboard.component.{ts,html,css}
│       ├── companies/admin-companies.component.{ts,html,css}
│       ├── users/admin-users.component.{ts,html,css}
│       ├── specialists/admin-specialists.component.{ts,html,css}
│       ├── appointments/admin-appointments.component.{ts,html,css}
│       ├── payments/admin-payments.component.{ts,html,css}
│       ├── categories/admin-categories.component.{ts,html,css}
│       ├── specializations/admin-specializations.component.{ts,html,css}
│       ├── slots/admin-slots.component.{ts,html,css}
│       ├── settings/admin-settings.component.{ts,html,css}
│       └── surveys/admin-surveys.component.{ts,html,css}
│
├── components/                       ← User portal components
│   ├── auth/
│   │   ├── login/login.component.{ts,html,css}
│   │   └── register/register.component.{ts,html,css}
│   ├── dashboard/dashboard.component.{ts,html,css}
│   ├── appointments/appointments.component.{ts,html,css}
│   ├── questionnaire/questionnaire.component.{ts,html,css}
│   ├── profile/profile.component.{ts,html,css}
│   ├── documents/documents.component.{ts,html,css}
│   ├── payments/payments.component.{ts,html,css}
│   ├── notifications/notifications.component.{ts,html,css}
│   ├── support/support.component.{ts,html,css}
│   ├── settings/settings.component.{ts,html,css}
│   └── shared/
│       ├── icon/icon.component.ts    ← SVG icon system
│       ├── lang-switcher/lang-switcher.component.{ts,html,css}
│       ├── legal-modal/legal-modal.component.{ts,html,css}
│       └── wysiwyg/wysiwyg.component.{ts,html,css}
│
├── services/
│   ├── api/api.service.ts            ← ALL user HTTP calls
│   ├── user/user.service.ts          ← User state + free sessions
│   ├── branding/branding.service.ts  ← Company colors/logo
│   ├── notification/notification.service.ts
│   └── lang/lang.service.ts
│
├── guards/auth.guard.ts
├── interceptors/
│   ├── auth.interceptor.ts           ← Attaches user Bearer token
│   └── admin-auth.interceptor.ts     ← Attaches admin Bearer token (for /admin/ URLs)
│
└── shared/shared.module.ts
```

---

## ROUTING

```
/login               → LoginComponent (public)
/register            → RegisterComponent (public)
/admin               → AdminModule (lazy)
  /admin/login       → AdminLoginComponent
  /admin             → AdminLayoutComponent (guarded by AdminGuard)
    /admin/dashboard
    /admin/companies
    /admin/users
    /admin/payments
    /admin/specialists
    /admin/specializations
    /admin/appointments
    /admin/categories
    /admin/slots
    /admin/surveys
    /admin/settings
/                    → UserModule (lazy, guarded by AuthGuard)
  /dashboard
  /appointments
  /documents
  /payments
  /notifications
  /questionnaire
  /profile
  /support
  /settings
```

---

## AUTHENTICATION & STATE

### Tokens
- **User**: `localStorage['access_token']` + `localStorage['access_token_at']` (timestamp). TTL 24h = 86_400_000ms. Managed by `ApiService`.
- **Admin**: `localStorage['admin_access_token']` + `localStorage['admin_user']`. Managed by `AdminApiService`.

### Interceptors
`auth.interceptor.ts` — injects `Authorization: Bearer {token}` on all requests **except** `/login`, `/register` and requests that already have an Authorization header.

`admin-auth.interceptor.ts` — injects `Authorization: Bearer {admin_token}` only on requests containing `/admin/` in the URL.

### Guards
`auth.guard.ts` — checks `apiService.isLoggedIn()`. Redirects to `/login`.
`admin.guard.ts` — checks `adminApiService.isAdminLoggedIn()`. Redirects to `/admin/login`.

---

## SERVICES — FULL API

### `ApiService` (`services/api/api.service.ts`) — user endpoints
Base URL: `/api/v1`

```typescript
// Auth
login(email, password)                          POST /user/login
register(data)                                  POST /user/register
logout()                                        POST /user/logout
isLoggedIn(): boolean
setAccessToken(token), getAccessToken(), clearAccessToken()

// Profile
getProfile()                                    GET  /user/profile
updateProfile(data)                             POST /user/update-profile
uploadAvatar(file: File)                        POST /user/upload-avatar (FormData)

// Dashboard
getDashboard()                                  GET  /dashboard
getFreeSessions()                               GET  /dashboard/free-sessions

// Specialists
getSpecialists()                                GET  /specialist
getCategories()                                 GET  /categories
reviewSpecialist(id, rating, comment, apptId?)  POST /specialist/{id}/review

// Appointments
getAppointments(status?)                        GET  /appointment?status=X
getAppointment(id)                              GET  /appointment/{id}
createAppointment(data)                         POST /appointment
updateAppointment(id, data)                     POST /appointment/{id}
cancelAppointment(id)                           POST /appointment/{id}/cancel
deleteAppointment(id)                           DELETE /appointment/{id}

// Payments
getPayments()                                   GET  /payment
initiatePayment(appointmentId)                  POST /payment/{appointmentId}/initiate  → {checkout_url, payment_id}
syncPaymentStatus(paymentId)                    POST /payment/{paymentId}/sync          → {status, payment_id}
syncPaymentByOrder(orderId)                     POST /payment/sync-by-order
processPayment(id, method?)                     POST /payment/{id}/process

// Notifications
getNotifications(isRead?)                       GET  /notification?is_read=0|1
getUnreadNotificationCount()                    GET  /notification/unread-count
markNotificationAsRead(id)                      POST /notification/{id}/read
markAllNotificationsAsRead()                    POST /notification/read-all
getNotificationSettings()                       GET  /notification/settings
saveNotificationSettings(data)                  POST /notification/save-settings
deleteNotification(id)                          DELETE /notification/{id}

// Documents
getDocuments()                                  GET  /document
uploadDocument(file: File)                      POST /document/upload (FormData)
deleteDocument(id)                              DELETE /document/{id}

// Google Calendar
getGoogleAuthUrl()                              GET  /google/auth-url          → {url}
getGoogleStatus()                               GET  /google/status            → {connected, google_email}
disconnectGoogle()                              POST /google/disconnect
getGoogleUpcomingEvents()                       GET  /google/upcoming-events   → {connected, events[], error?}

// Mood
getTodayMood()                                  GET  /mood/today    → {mood:1-5, note, logged_at}|null
saveMood(mood, note?)                           POST /mood
getMoodHistory(days?)                           GET  /mood/history?days=7

// Surveys (user)
getActiveSurvey()                               GET  /survey/active → {id, title, description, questions:[{id, question, sort_order, options:string[]}]}
getSurveyMyStatus()                             GET  /survey/my-status → {completed, survey_id}
submitSurveyResponse(surveyId, answers)         POST /survey/respond  body: {survey_id, answers:{[qId]:optionIndex}}

// Surveys (admin) — also in ApiService
getAdminSurveys()                               GET  /admin/survey
createAdminSurvey({title, description?})        POST /admin/survey
updateAdminSurvey(id, data)                     POST /admin/survey/{id}
deleteAdminSurvey(id)                           DELETE /admin/survey/{id}
activateAdminSurvey(id)                         POST /admin/survey/{id}/activate
getAdminSurveyQuestions(id)                     GET  /admin/survey/{id}/questions
createAdminSurveyQuestion(surveyId, data)       POST /admin/survey/{surveyId}/questions
updateAdminSurveyQuestion(surveyId, qid, data)  POST /admin/survey/{surveyId}/questions/{qid}
deleteAdminSurveyQuestion(surveyId, qid)        DELETE /admin/survey/{surveyId}/questions/{qid}
getAdminSurveyResults(id)                       GET  /admin/survey/{id}/results

// Other
getPortalSettings()                             GET  /portal-settings (public)
getCompanies()                                  GET  /company (public)
```

### `AdminApiService` (`admin/services/admin-api.service.ts`) — admin-only HTTP
Base URL: `/api/v1`
```typescript
// Session
login(email, password)                          POST /user/login
isAdminLoggedIn(): boolean
setAdminSession(token, user), clearAdminSession(), getAdminUser()

// Dashboard
getDashboard()                                  GET  /admin/dashboard

// Companies
getAdminCompanies()                             GET  /admin/companies
createCompany(data), updateCompany(id, data), deleteCompany(id)
uploadLogo(file)                                POST /admin/upload-logo

// Users
getAdminUsers({search?, status?, page?, per_page?})  GET /admin/users
createUser(data), updateUser(id, data), deleteUser(id)

// Specialists
getAdminSpecialists(search?)                    GET  /admin/specialists
createSpecialist(data), updateSpecialist(id, data), deleteSpecialist(id)
uploadSpecialistAvatar(id, file)                POST /admin/specialists/{id}/upload-avatar
getSpecialistSlots(id)                          GET  /admin/specialists/{id}/slots
saveSpecialistSlots(id, slots)                  POST /admin/specialists/{id}/slots
getSpecialistWeekSchedule(id, from?)            GET  /admin/specialists/{id}/week-schedule
blockSpecialistDate(id, date), unblockSpecialistDate(id, date)

// Appointments
getAdminAppointments({search?, status?, page?}) GET  /admin/appointments
createAdminAppointment(data), updateAdminAppointment(id, data), deleteAdminAppointment(id)

// Payments
getAdminPayments({search?, status?, page?})     GET  /admin/payments
updateAdminPayment(id, data)
checkPaymentStatus(id)                          POST /admin/payments/{id}/check-status

// Categories & Specializations
getAdminCategories(search?)     createCategory / updateCategory / deleteCategory
getAdminSpecializations()       createSpecialization / updateSpecialization / deleteSpecialization

// Settings
getAdminSettings(), saveAdminSettings(data)
getPaymentSettings(), savePaymentSettings(data)
uploadFavicon(file)
```

### `UserService` (`services/user/user.service.ts`)
```typescript
current: any                    // current user object
freeSessions: FreeSessions|null // {total, used, remaining, percent}
user$: Observable<any>
freeSessions$: Observable<FreeSessions|null>

load()                          // fetch + cache profile
update(data)                    // update profile
setUser(user)                   // direct assignment
loadFreeSessions()              // fetch + cache (TTL 5min)
invalidateFreeSessions()
clear()                         // logout: clears user, sessions, branding, localStorage
```

### `BrandingService` (`services/branding/branding.service.ts`)
```typescript
current: CompanyBranding        // {id, code, name, logo_url, primary_color, secondary_color, accent_color}
branding$: Observable<CompanyBranding>

set(branding | null)            // updates state, applies CSS vars, saves localStorage
reset()                         // fallback to default Wellbeing branding
```
Default: `{id:0, code:'default', name:'Wellbeing', logo_url:null, primary_color:'#2DB928', ...}`
Applies CSS vars: `--green`, `--green-dark`, `--green-light`, `--brand-primary`, `--brand-secondary`, `--brand-accent`

### `NotificationService`
```typescript
count: number
count$: Observable<number>
load()    // fetches unread count
setCount(n)
```

### `LangService`
```typescript
supported: ['uk', 'en', 'ru']
current: 'uk' | 'en' | 'ru'
init()    // sets default 'uk', reads localStorage['wb_lang']
use(lang) // switches + saves to localStorage
```

---

## ICON SYSTEM

**Selector**: `<app-icon name="..." [size]="20" [stroke]="'currentColor'" [fill]="'none'" [strokeWidth]="1.6">`

**Available icon names:**
`dashboard`, `user`, `calendar`, `file`, `card`, `bell`, `clipboard`, `logout`, `edit`, `check`, `plus`, `upload`, `chat`, `heart`, `phone`, `mail`, `building`, `chevronDown`, `x`, `info`, `shield`, `eye`, `eyeOff`, `refresh`, `sparkles`, `menu`, `arrowLeft`, `settings`, `award`, `tag`, `clock`, `list`

Icons are stored in `shared/icon/icons.ts` as SVG path strings joined by " M " delimiter. Add new icons there.

In admin-layout, icons are rendered manually (no `app-icon`) using `getIconPaths(iconName)` method — paths are stored in `iconPaths` readonly object on the component.

---

## APP.COMPONENT — ROOT SHELL

**User sidebar navItems:**
```typescript
[
  { id: 'dashboard',     label: 'Дашборд',      icon: 'dashboard' },
  { id: 'appointments',  label: 'Мої записи',   icon: 'calendar' },
  { id: 'documents',     label: 'Документи',    icon: 'file' },
  { id: 'payments',      label: 'Оплата',       icon: 'card' },
  { id: 'notifications', label: 'Сповіщення',   icon: 'bell' },
  { id: 'questionnaire', label: 'Анкета',       icon: 'clipboard' },
  { id: 'support',       label: "Зв'язок з WO", icon: 'chat' },
  { id: 'settings',      label: 'Налаштування', icon: 'settings' },
]
```
Nav labels are rendered via `{{ ('nav.' + item.id) | translate }}` (not the hardcoded label).
Logo: `branding.current.logo_url` if set, else fallback `assets/wellbeing-logo.png` (`logo-img-wb-sidebar`, 46px).

**Admin sidebar navItems:**
```typescript
[
  { id: 'dashboard',        label: 'Дашборд',         icon: 'dashboard' },
  { id: 'companies',        label: 'Компанії',        icon: 'building' },
  { id: 'users',            label: 'Користувачі',     icon: 'users' },
  { id: 'payments',         label: 'Оплати',          icon: 'card' },
  { id: 'specialists',      label: 'Спеціалісти',     icon: 'heart' },
  { id: 'specializations',  label: 'Спеціалізації',   icon: 'award' },
  { id: 'appointments',     label: 'Записи',          icon: 'calendar' },
  { id: 'categories',       label: 'Категорії',       icon: 'tag' },
  { id: 'slots',            label: 'Слоти',           icon: 'clock' },
  { id: 'surveys',          label: 'Опитування',      icon: 'list' },
  { id: 'settings',         label: 'Налаштування',    icon: 'settings' },
]
```

---

## I18N — TRANSLATION KEYS

Files: `src/assets/i18n/uk.json`, `en.json` and `ru.json`

**Existing top-level sections:**
`nav`, `sidebar`, `auth.login`, `auth.register`, `dashboard`, `appointments`, `specialist`, `notifications`, `payments`, `profile`, `settings`, `questionnaire`, `documents`, `support`, `lang`, `common`

**questionnaire section (both files):**
```json
{
  "title", "mood_title", "mood_labels"[5],
  "mood_saved", "mood_saving", "mood_error",
  "no_survey_title", "no_survey_desc",
  "already_done_title", "already_done_desc",
  "back", "next", "submit", "submitting", "submit_error",
  "success_title", "success_desc", "home"
}
```

**Rules:**
- All user-visible static text MUST use `| translate` pipe in templates
- Dynamic labels in TS: `this.translate.instant('key')`
- When adding new page/section: add keys to ALL THREE `uk.json`, `en.json` AND `ru.json`
- `nav.{id}` must match the `id` in navItems arrays above

---

## DESIGN SYSTEM

### Colors (CSS custom properties set by BrandingService)
```css
--green:          #2DB928   /* primary */
--green-dark:     #1E9020
--green-light:    #E8F5E9   /* bg tint */
--text:           #1C2B20
--text-muted:     #6B8879
--border:         #DCE9DC
--bg:             #F2F7F3
--card:           #FFFFFF
```
Danger: `#C62828` / `#FFEBEE`
Blue (Google Meet): `#4285F4`

### Spacing & Shapes
- Card border-radius: `14-16px`
- Button border-radius: `10px`
- Small elements: `8px`
- Card padding: `24px` (mobile: `16px`)
- Card border: `1px solid var(--border)`
- Card box-shadow (modals): `0 8px 40px rgba(0,0,0,0.18)`

### Typography
- Font: `'Montserrat', sans-serif` (loaded locally)
- Page title: `font-size:20px; font-weight:700-800; color:var(--text)`
- Card title: `font-size:15-16px; font-weight:600-700`
- Label (form): `font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted)`
- Body text: `font-size:13-14px`
- Muted text: `color:var(--text-muted)`

### Buttons
```css
/* Primary */
.btn-primary { background:#2DB928; color:#fff; border:none; border-radius:10px; padding:10px 20px; font-size:13.5px; font-weight:600; }

/* Cancel / outline */
.btn-cancel { background:transparent; color:#6B8879; border:1px solid #DCE9DC; border-radius:10px; }

/* Small outline */
.btn-outline-sm { color:#4A6452; border:1px solid #DCE9DC; border-radius:8px; padding:6px 12px; font-size:12.5px; }

/* Small danger */
.btn-danger-sm { color:#C62828; border:1px solid #FFCDD2; border-radius:8px; }

/* Icon button */
.btn-icon { width:30px; height:30px; border:1px solid #DCE9DC; border-radius:6px; background:#fff; }
```

### Forms
Template-driven only (`[(ngModel)]`). Input/textarea:
```css
padding:10px 12px; border:1px solid #DCE9DC; border-radius:8px;
font-size:13.5px; background:#FAFBFA;
/* focus: */ border-color:#2DB928; background:#fff;
```

### Spinner
```css
.spinner { display:inline-block; width:28px; height:28px; border:3px solid #E8EDE9; border-top-color:#2DB928; border-radius:50%; animation:spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
```

### Modal pattern
```html
<div class="modal-overlay" *ngIf="showModal" (click)="closeModal()">
  <div class="modal-box" (click)="$event.stopPropagation()">
    <div class="modal-header">
      <span class="modal-title">...</span>
      <button class="modal-close" (click)="closeModal()">✕</button>
    </div>
    <div class="modal-body">...</div>
    <div class="modal-footer">
      <button class="btn-cancel">Скасувати</button>
      <button class="btn-primary">Зберегти</button>
    </div>
  </div>
</div>
```
```css
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; display:flex; align-items:center; justify-content:center; }
.modal-box { background:#fff; border-radius:16px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto; padding:28px; }
.modal-box--wide { max-width:660px; }
```

### Badge pattern
```html
<span class="badge-active" *ngIf="item.is_active">Активне</span>
<!-- css: background:#E8F5E9; color:#1B7A35; font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; -->
```

---

## QUESTIONNAIRE COMPONENT — FULL SPEC

**Mood tracker** (top of page):
- 5 emoji buttons in CSS grid `repeat(5,1fr)` — NO `transform:scale()` on active (only border + box-shadow)
- `moodOptions` getter → reads labels from `translate.instant('questionnaire.mood_labels')` (array of 5)
- `savedMood` shown in badge (top-right of card) after saving
- `todayFormatted` getter: UK = "6 травня 2026", EN = "May 6, 2026"

**Survey stepper** (below mood):
- States: loading → no-survey | already-completed | stepper | success
- One question at a time; `currentStep` tracks index
- `selectAnswer(qId, optIndex)`: records answer, auto-advances after 300ms if not last question
- `answers: {[qId:number]: number}` — key is question ID, value is option index (0-based)
- `canSubmit`: all questions answered = `Object.keys(answers).length === survey.questions.length`
- Options defensive parse: `Array.isArray(q.options) ? q.options : JSON.parse(q.options)`

---

## DASHBOARD COMPONENT — MOOD MINI-CHART

`moodDays`: array of 7 day objects: `{date:string, label:string, dayName:string, isToday:boolean, mood:number|null}`
`moodEmojis`: `['😔','😕','😐','🙂','😊']` (index = mood-1)
`moodColors`: `['#F48FB1','#FFCC80','#FFF176','#A5D6A7','#66BB6A']`
`moodBgColors`: pastel equivalents

`loadMoodChart()`: calls `getMoodHistory(7)`, maps results to 7-day grid (Mon–Sun), fills gaps with `mood:null`.
Day squares: pastel background + colored border = `moodBgColors[d.mood-1]` / `moodColors[d.mood-1]`
Label below square: day abbreviation ("Пн"–"Нд"), "Сьогодні" for today
Day name below label: mood label text (hidden on mobile < 480px)

---

## ADMIN SURVEYS COMPONENT — SPEC

**State:**
```typescript
surveys: any[]
loading: boolean
showCreate: boolean; createTitle: string; createDesc: string; creating: boolean
questionsModal: { survey, questions: any[], loading: boolean } | null
editingQuestion: any | null
editForm: { question: string, options: string[] }; savingEdit: boolean
newQuestion: { question: string, options: string[] }  // default 4 empty options
addingQuestion: boolean
resultsModal: { survey, results: any[], loading: boolean } | null
```

**Question editing**: ✎ button on each question → shows green-highlighted edit form (`add-q-form--edit`) in place of add form. "Зберегти" calls `updateAdminSurveyQuestion()`, reloads list. Options defensive parse applied after `getAdminSurveyQuestions()`.

**Results**: bar chart per question — `r.options[oi]` labels, `r.counts[oi]` values, `r.total` denominator. `percent(count, total)` helper.

---

## ADDING NEW FEATURES — CHECKLIST

### New user page:
1. Create `components/my-page/my-page.component.{ts,html,css}`
2. Add route in `user/user-routing.module.ts`
3. Add to `user.module.ts` declarations
4. Add nav item to `app.component.ts` navItems
5. Add translation key `nav.my-page` to both `uk.json` and `en.json`
6. Add page translations section to both files
7. Add API methods to `api.service.ts` if needed

### New admin page:
1. Create `admin/components/my-page/admin-my-page.component.{ts,html,css}`
2. Add route in `admin/admin-routing.module.ts`
3. Add to `admin/admin.module.ts` declarations
4. Add nav item to `admin-layout.component.ts` navItems + iconPaths
5. Add API methods to `admin-api.service.ts`

### New API method:
- User endpoints → add to `api.service.ts`
- Admin endpoints → add to `admin-api.service.ts` (OR `api.service.ts` for survey admin methods)
- Always return `Observable<any>` using `this.http.get/post/put/delete()`

---

## LOCAL DEVELOPMENT

### Start backend
```bash
cd backend/wellbeing-api
PHP_CLI_SERVER_WORKERS=4 php yii serve 0.0.0.0 --docroot=api/web --port=8000
```
`PHP_CLI_SERVER_WORKERS=4` is required — without it Angular's concurrent startup requests all timeout (504).

### Start frontend
```bash
cd frontend/wellbeing-app
npm start
```

### Proxy (`proxy.conf.json`)
Target MUST be `http://127.0.0.1:8000` (NOT `http://localhost:8000`).
On macOS `localhost` → `::1` (IPv6), PHP listens IPv4 only → 504 Gateway Timeout.

---

## APPOINTMENT BOOKING — 6-STEP FLOW

| Step | Content |
|---|---|
| 1 | Specialist catalog |
| 2 | Specialist profile (read-only, no forms) |
| 3 | Time slot selection |
| 4 | Payment method + **Communication method** (Google Meet / Zoom / MS Teams) |
| 5 | Confirm details |
| 6 | Success |

`paymentVia: 'card' | 'subscription'` — subscription available only if `freeRemaining > 0` from `/dashboard/free-sessions`.
`communicationMethod: 'google_meet' | 'zoom' | 'teams' | ''` — required at step 4.
Icons: real favicons via `<img src="https://www.google.com/s2/favicons?domain=meet.google.com&sz=64" width="20" height="20">`.
`canGoNext` at step 4: `!!this.paymentVia && !!this.communicationMethod`.

---

## GOOGLE CALENDAR — TWO SEPARATE CHECKS

- **Dashboard** (`dashboard.component.ts`): calls `getGoogleUpcomingEvents()` → sets `googleConnected = res.connected`. Response always has `connected: true` when token exists (even if events fail to load).
- **Settings** (`settings.component.ts`): calls `getGoogleStatus()` → sets `googleConnected = res.connected` + `googleEmail`.

Do NOT swap these calls — they serve different purposes.

---

## WHAT NOT TO DO
- Do NOT use `ReactiveFormsModule` or `FormGroup/FormControl` — this project uses template-driven forms with `ngModel`
- Do NOT use Angular `date` pipe for Ukrainian date formatting — use the ukMonths array pattern
- Do NOT hardcode Ukrainian text — always use `| translate` pipe
- Do NOT add new global styles — all styles are component-scoped
- Do NOT create new services for one-off HTTP calls — add methods to existing services
- Do NOT use `transform: scale()` on active state buttons — use border/box-shadow instead
- Do NOT read whole component files "to understand the structure" — use the spec above
- Do NOT add `console.log` statements
- Do NOT use `any` type where an interface is obvious — define quick interfaces inline

## WHAT TO DO
- Read a file only when you need its exact current content to make a targeted edit
- For new components: always create all 3 files (ts, html, css) together
- Subscribe with `{next: ..., error: ...}` object form (not `.subscribe(fn, fn)`)
- Unsubscribe in `ngOnDestroy` for long-lived subscriptions (use `takeUntil(destroy$)` pattern)
- Keep CSS component-scoped; use CSS custom properties for theme colors
- Mobile: add `@media (max-width: 600px)` at bottom of every component CSS
- Always add `| translate` for any user-visible string (even buttons, placeholders)
