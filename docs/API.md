# Wellbeing API Reference

**Base URL:** `/api/v1/`  
**Auth:** `Authorization: Bearer {access_token}` on all protected endpoints  
**Format:** JSON (`Content-Type: application/json`)

---

## Authentication

### POST /v1/user/register
Register a new user. Creates a Creatio Contact on success.

**Public** | Rate-limited: 5 requests / 10 min per IP | reCAPTCHA required

Request:
```json
{
  "first_name": "Іван",
  "last_name": "Петренко",
  "email": "ivan@example.com",
  "password": "secret123",
  "company_id": 5,
  "accepted_terms": true,
  "recaptcha_token": "..."
}
```

Response `201`:
```json
{
  "success": true,
  "access_token": "...",
  "user": { "id": 42, "email": "ivan@example.com", "first_name": "Іван", ... }
}
```

Errors: `400` reCAPTCHA failed | `422` validation errors | `429` rate limit

---

### POST /v1/user/login
**Public** | Rate-limited: 10 requests / 5 min per IP | reCAPTCHA required

Request:
```json
{ "email": "ivan@example.com", "password": "secret123", "recaptcha_token": "..." }
```

Response `200`:
```json
{ "success": true, "access_token": "...", "user": { ... } }
```

Errors: `400` missing fields | `401` wrong credentials | `429` rate limit

---

### POST /v1/user/logout
**Auth required**

Invalidates the current access token.

Response `200`: `{ "success": true, "message": "Logged out successfully" }`

---

## User Profile

### GET /v1/user/profile
**Auth required**

Returns full user object including company branding.

Response:
```json
{
  "id": 42,
  "email": "ivan@example.com",
  "first_name": "Іван",
  "last_name": "Петренко",
  "patronymic": null,
  "phone": "+380991234567",
  "company": "EPAM",
  "company_id": 5,
  "company_name": "EPAM Ukraine",
  "company_branding": {
    "name": "EPAM Ukraine",
    "primary_color": "#2DB928",
    "logo_url": "/api/uploads/logos/epam.png"
  },
  "avatar_url": "/api/uploads/avatars/user_42_1234.jpg",
  "accepted_terms": true,
  "is_admin": false,
  "role": "user",
  "created_at": 1715500000
}
```

---

### POST /v1/user/update-profile
**Auth required**

Allowed fields: `first_name`, `last_name`, `patronymic`, `phone`, `company`, `company_id`, `avatar_url`, `accepted_terms`

Response `200`: `{ "success": true, "message": "Профіль оновлено успішно", "user": { ... } }`

---

### POST /v1/user/upload-avatar
**Auth required** | Multipart form-data, field `avatar`

Allowed: jpg, jpeg, png, gif, webp | Max 5 MB

Response `200`:
```json
{ "success": true, "avatar_url": "/api/uploads/avatars/user_42_1715500000.jpg", "user": { ... } }
```

---

### POST /v1/user/change-password
**Auth required**

Request: `{ "old_password": "...", "new_password": "..." }`

Response `200`: `{ "success": true }`

Errors: `422` wrong current password or new password < 6 chars

---

## Companies

### GET /v1/company
**Public**

Returns list of active companies for registration dropdown.

Response:
```json
[
  { "id": 5, "name": "EPAM Ukraine", "primary_color": "#2DB928", "logo_url": "..." }
]
```

---

## Specialists

### GET /v1/specialist
**Public**

Query params: `type` (filter by specialization key), `category_id`

Response:
```json
{
  "specialists": [
    {
      "id": 1,
      "name": "Ірина Коваль",
      "type": "psychologist",
      "type_label": "Психолог",
      "bio": "...",
      "experience": 8,
      "price": 600,
      "avatar_url": "...",
      "categories": ["Тривога та стрес", "Депресія"],
      "rating": 4.8,
      "reviews_count": 24,
      "is_active": true
    }
  ]
}
```

Cached for 60 seconds.

---

### GET /v1/categories
**Public**

Returns active specialist categories.

Response: `[{ "id": 1, "name": "Тривога та стрес" }, ...]`

---

### POST /v1/specialist/{id}/review
**Auth required**

Submit a review for a completed appointment with this specialist.

Request: `{ "rating": 5, "comment": "Дуже допомогло!", "appointment_id": 101 }`

Response `200`: `{ "success": true }`

Errors: `404` specialist not found | `409` review already submitted | `422` no completed appointment

---

## Dashboard

### GET /v1/dashboard
**Auth required**

Returns combined dashboard data: stats, recent appointments, mood tracker summary, free sessions info.

Response:
```json
{
  "stats": {
    "upcoming": 2,
    "completed": 15,
    "cancelled": 1,
    "free_sessions_remaining": 3,
    "free_sessions_limit": 5
  },
  "recent_appointments": [ ... ],
  "mood_today": { "score": 4, "recorded_today": true },
  "active_contract": {
    "name": "Корпоративний договір EPAM 2025",
    "free_sessions_per_employee": 5,
    "session_price": 0,
    "is_active": true,
    "end_date": "2025-12-31"
  }
}
```

---

### GET /v1/dashboard/free-sessions
**Auth required**

Returns remaining free sessions count for the current user's company.

Response: `{ "remaining": 3, "limit": 5, "used": 2 }`

---

## Appointments

### GET /v1/appointment
**Auth required**

Returns all appointments for the current user, newest first.

Response:
```json
{
  "appointments": [
    {
      "id": 101,
      "specialist_id": 1,
      "specialist_name": "Ірина Коваль",
      "specialist_type": "psychologist",
      "specialist_avatar": "...",
      "appointment_date": "2025-06-15",
      "appointment_time": "10:00",
      "status": "confirmed",
      "payment_status": "paid",
      "payment_via": "card",
      "price": 600,
      "communication_method": "google_meet",
      "google_meet_link": "https://meet.google.com/...",
      "cancel_reason": null
    }
  ]
}
```

---

### POST /v1/appointment
**Auth required**

Create a new appointment. Triggers Creatio Activity sync and Google Calendar event.

Request:
```json
{
  "specialist_id": 1,
  "appointment_date": "2025-06-15",
  "appointment_time": "10:00",
  "payment_via": "subscription",
  "communication_method": "google_meet"
}
```

`payment_via`: `"card"` | `"subscription"`  
`communication_method`: `"google_meet"` | `"zoom"` | `"teams"`

Response `201`:
```json
{
  "success": true,
  "appointment": { "id": 101, "status": "confirmed", "payment_status": "paid", ... }
}
```

Errors: `422` slot unavailable | `422` subscription limit exceeded | `409` slot conflict

---

### GET /v1/appointment/{id}
**Auth required**

Returns single appointment detail for the current user.

---

### POST /v1/appointment/{id}/cancel
**Auth required**

Request: `{ "reason": "Не можу прийти" }` (reason required if < 2 hours before appointment)

Response `200`: `{ "success": true, "refunded": true }`

---

### POST /v1/appointment/{id}/review
**Auth required**

Request: `{ "rating": 5, "comment": "Відмінно!" }`

Response `200`: `{ "success": true }`

---

## Documents

### GET /v1/document
**Auth required**

Returns list of uploaded documents for the current user.

Response:
```json
{
  "items": [
    {
      "id": 7,
      "name": "passport.pdf",
      "url": "/api/uploads/documents/doc_42_1715500000_ab12.pdf",
      "type": "pdf",
      "size": 204800,
      "size_label": "200 KB",
      "date": "12 трав. 2026"
    }
  ]
}
```

---

### POST /v1/document/upload
**Auth required** | Multipart form-data, field `file`

Allowed: pdf, jpg, jpeg, png, doc, docx | Max 10 MB

On success: file is saved locally AND synced to Creatio ContactFile (if user has `creatio_contact_id`).

Response `201`:
```json
{
  "id": 7,
  "name": "passport.pdf",
  "url": "/api/uploads/documents/doc_42_1715500000_ab12.pdf",
  "type": "pdf",
  "size": 204800,
  "size_label": "200 KB",
  "date": "12 трав. 2026"
}
```

Errors: `400` no file | `422` wrong format or size

---

### DELETE /v1/document/{id}
**Auth required**

Deletes document from portal and removes from Creatio ContactFile (best-effort).

Response `204` (no body)

---

## Payments

### GET /v1/payment
**Auth required**

Returns all payment records for the current user.

Response:
```json
{
  "payments": [
    {
      "id": 55,
      "appointment_id": 101,
      "specialist_name": "Ірина Коваль",
      "amount": 600,
      "currency": "UAH",
      "status": "completed",
      "gateway": "liqpay",
      "created_at": 1715500000
    }
  ]
}
```

---

### POST /v1/payment/{id}/initiate
**Auth required**

Initiates payment for appointment. Creates order in payment gateway.

Response `200`: `{ "checkout_url": "https://liqpay.ua/..." }`

---

### POST /v1/payment/{id}/sync
**Auth required**

Polls payment status from gateway and updates local record.

Response `200`: `{ "status": "completed", "appointment_status": "confirmed" }`

---

### POST /v1/payment/sync-by-order
**Auth required**

Syncs payment by gateway order ID (used on return from checkout page).

Request: `{ "order_id": "liqpay_order_123" }`

---

### POST /v1/payment/callback/{gateway}
**Public** (verified by gateway signature)

Webhook endpoint for LiqPay / UaPay payment callbacks.

`gateway`: `liqpay` | `uapay`

---

## Google Calendar

### GET /v1/google/auth-url
**Auth required**

Returns OAuth authorization URL to redirect user to Google consent screen.

Response: `{ "url": "https://accounts.google.com/o/oauth2/auth?..." }`

---

### GET /v1/google/callback
**Public** (called by Google OAuth redirect)

Exchanges code for tokens, saves to `user_google_token`.

Redirects to `{frontendUrl}/settings?google=connected`

---

### POST /v1/google/disconnect
**Auth required**

Revokes Google token and removes from DB.

Response `200`: `{ "success": true }`

---

### GET /v1/google/status
**Auth required**

Response: `{ "connected": true, "email": "ivan@gmail.com" }`

---

### GET /v1/google/upcoming-events
**Auth required**

Returns upcoming Google Calendar events filtered by "Wellbeing" in description.

Response:
```json
{
  "connected": true,
  "events": [
    {
      "id": "google_event_id",
      "title": "Консультація з Іриною",
      "start": "2025-06-15T10:00:00+03:00",
      "end": "2025-06-15T11:00:00+03:00",
      "meet_link": "https://meet.google.com/...",
      "html_link": "https://calendar.google.com/..."
    }
  ]
}
```

---

## Mood Tracker

### GET /v1/mood/today
**Auth required**

Response: `{ "recorded": true, "score": 4, "date": "2026-05-12" }`

---

### POST /v1/mood
**Auth required**

Request: `{ "score": 4 }` (1–5)

Response `200`: `{ "success": true }`

Errors: `409` already recorded today

---

### GET /v1/mood/history
**Auth required**

Returns last 7 days of mood scores.

Response: `{ "history": [{ "date": "2026-05-12", "score": 4 }, ...] }`

---

## Questionnaire / Surveys

### GET /v1/survey/active
**Auth required**

Returns the currently active survey (if any).

---

### GET /v1/survey/my-status
**Auth required**

Returns whether the user has already completed the active survey.

---

### POST /v1/survey/respond
**Auth required**

Submit survey answers.

Request: `{ "survey_id": 3, "answers": [{ "question_id": 1, "answer": "text or option_id" }, ...] }`

---

## Notifications

### GET /v1/notification
**Auth required**

Returns all notifications for the current user.

---

### GET /v1/notification/unread-count
**Auth required**

Response: `{ "count": 3 }`

---

### GET /v1/notification/settings
**Auth required**

Returns notification preferences.

---

### POST /v1/notification/save-settings
**Auth required**

Request: `{ "email_enabled": true, "sms_enabled": false, "reminder_12h": true }`

---

### POST /v1/notification/read-all
**Auth required**

Marks all notifications as read.

---

### POST /v1/notification/{id}/read
**Auth required**

Marks one notification as read.

---

## Support Tickets

### GET /v1/support-ticket
**Auth required** | Returns user's support tickets.

### POST /v1/support-ticket
**Auth required** | Creates a new support ticket.

Request: `{ "subject": "...", "message": "...", "contact_method": "email" }`

### POST /v1/support-ticket/{id}/reply
**Auth required** | Adds a reply to a ticket.

### POST /v1/support-ticket/{id}/close
**Auth required** | Closes a ticket.

---

## Portal Settings

### GET /v1/portal-settings
**Public**

Returns public platform settings (app name, favicon, reCAPTCHA site key, etc.)

---

## Admin Endpoints

All admin endpoints require `Authorization: Bearer {token}` where the user has `is_admin = true`.

### Dashboard

**GET /v1/admin/dashboard** — Platform-wide statistics.

---

### Companies

**GET /v1/admin/companies** — List all companies with stats.

Response includes: `id`, `name`, `primary_color`, `logo_url`, `email_domain`, `is_active`, `creatio_account_id`, `user_count`

**POST /v1/admin/companies** — Create company.

Fields: `name`, `primary_color`, `email_domain`, `is_active`, `creatio_account_id`

**POST /v1/admin/companies/{id}** — Update company (same fields).

**DELETE /v1/admin/companies/{id}** — Delete company.

**POST /v1/admin/upload-logo** — Upload company logo (multipart, field `logo`). Returns `{ "logo_url": "..." }`.

**GET /v1/admin/companies/{id}/contracts** — List contracts synced from Creatio for this company.

**POST /v1/admin/companies/{id}/contracts/sync** — Trigger immediate Creatio contract sync for this company.

---

### Users

**GET /v1/admin/users** — List all users. Query: `company_id`, `search`.

**POST /v1/admin/users** — Create user.

**POST /v1/admin/users/{id}** — Update user.

**DELETE /v1/admin/users/{id}** — Delete user.

---

### Specialists

**GET /v1/admin/specialists** — List all specialists.

**POST /v1/admin/specialists** — Create specialist. Triggers Creatio Contact sync.

Fields: `name`, `type`, `bio`, `experience`, `price`, `categories`, `email`, `is_active`, `user_id` (portal user link), `creatio_contact_id`

**POST /v1/admin/specialists/{id}** — Update specialist.

**DELETE /v1/admin/specialists/{id}** — Delete specialist.

**POST /v1/admin/specialists/{id}/upload-avatar** — Upload avatar (multipart, field `avatar`).

**GET /v1/admin/specialists/{id}/slots** — Get weekly schedule.

**POST /v1/admin/specialists/{id}/slots** — Save weekly schedule.

**GET /v1/admin/specialists/{id}/available-slots** — Get available booking slots (next 14 days).

**GET /v1/admin/specialists/{id}/week-schedule** — Get current week's schedule.

**POST /v1/admin/specialists/{id}/block-date** — Block a date for specialist.

**DELETE /v1/admin/specialists/{id}/block-date** — Unblock a date.

**POST /v1/admin/specialists/{id}/link-user** — Link specialist to a portal user account.

**DELETE /v1/admin/specialists/{id}/link-user** — Unlink specialist from portal user account.

---

### Specializations

**GET /v1/admin/specializations** — List all specialization types.

**POST /v1/admin/specializations** — Create specialization (fields: `key`, `name`).

**POST /v1/admin/specializations/{id}** — Update.

**DELETE /v1/admin/specializations/{id}** — Delete.

---

### Categories

**GET /v1/admin/categories** — List all categories.

**POST /v1/admin/categories** — Create (`name`, `is_active`).

**POST /v1/admin/categories/{id}** — Update.

**DELETE /v1/admin/categories/{id}** — Delete.

---

### Appointments

**GET /v1/admin/appointments** — All appointments. Query: `status`, `specialist_id`, `company_id`, `date_from`, `date_to`.

**POST /v1/admin/appointments** — Create appointment on behalf of user.

**POST /v1/admin/appointments/{id}** — Update appointment (status, etc.).

**DELETE /v1/admin/appointments/{id}** — Delete appointment.

---

### Payments

**GET /v1/admin/payments** — All payment records.

**POST /v1/admin/payments/{id}** — Update payment (manual status override).

**POST /v1/admin/payments/{id}/check-status** — Re-check status from gateway.

---

### Surveys

**GET /v1/admin/survey** — List surveys.

**POST /v1/admin/survey** — Create survey.

**POST /v1/admin/survey/{id}** — Update survey.

**DELETE /v1/admin/survey/{id}** — Delete survey.

**POST /v1/admin/survey/{id}/activate** — Activate survey (deactivates others).

**GET /v1/admin/survey/{id}/questions** — List questions.

**POST /v1/admin/survey/{id}/questions** — Add question.

**POST /v1/admin/survey/{id}/questions/{qid}** — Update question.

**DELETE /v1/admin/survey/{id}/questions/{qid}** — Delete question.

**GET /v1/admin/survey/{id}/results** — Aggregated results.

---

### Settings

**GET /v1/admin/settings** — Platform settings (name, URL, favicon, Google OAuth, reCAPTCHA, Creatio).

**POST /v1/admin/settings** — Save settings.

**POST /v1/admin/settings/upload-favicon** — Upload favicon (multipart, field `favicon`).

**GET /v1/admin/payment-settings** — Payment gateway settings.

**POST /v1/admin/payment-settings** — Save payment gateway settings.

---

## Specialist Panel

Accessible by users with `role = 'specialist'`.

**GET /v1/specialist-panel/dashboard** — Own stats.

**GET /v1/specialist-panel/appointments** — Own appointments.

**POST /v1/specialist-panel/appointments/{id}** — Update appointment status (confirm / complete / no-show).

**GET /v1/specialist-panel/my-slots** — Own weekly schedule.

**POST /v1/specialist-panel/my-slots** — Save own schedule.

**GET /v1/specialist-panel/my-week-schedule** — Current week schedule.

**POST /v1/specialist-panel/block-date** — Block own date.

**DELETE /v1/specialist-panel/block-date** — Unblock own date.

---

## Creatio Webhooks

### POST /v1/creatio/webhook/contract
**Public** (authenticated by `X-Creatio-Secret` header matching `creatio_webhook_secret` in AppSettings)

Receives real-time contract updates from Creatio and upserts into the portal `contract` table.

Request body: Creatio `WelContract` OData object.

Response `200`: `{ "ok": true }`

---

## Error Format

All errors follow this structure:

```json
{ "error": "Human-readable message" }
```

or for validation errors:

```json
{
  "errors": {
    "email": ["Email is invalid"],
    "password": ["Password is too short"]
  }
}
```

Standard HTTP status codes:
- `200` OK
- `201` Created
- `204` No Content
- `400` Bad Request
- `401` Unauthorized
- `403` Forbidden
- `404` Not Found
- `409` Conflict
- `422` Unprocessable Entity
- `429` Too Many Requests
- `500` Server Error
