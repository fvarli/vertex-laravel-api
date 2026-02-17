# Vertex Laravel API

Versioned REST API backend for a trainer/coach workflow, built with Laravel 12 and Sanctum.

Repository status: API skeleton + Domain MVP (workspace, students, programs, appointments) is ready.

## Features

- Authentication: register, login, logout, logout-all, refresh-token
- Password flow: forgot-password, reset-password
- Email verification: verify (signed URL), resend
- Profile: me, update profile, change password, avatar upload/delete, account delete (soft delete)
- Multi-workspace tenancy: active workspace context per user
- Role-based scope: `owner_admin` and `trainer`
- Identity model: single `users` table with `system_role` (`workspace_user` / `platform_admin`)
- RBAC foundation: `roles`, `permissions`, and scoped pivots (`model_role`, `model_permission`)
- Students: create, list, update, set status (`active` / `passive`)
- Programs: weekly program management with ordered program items
- Program templates: reusable workout templates with item rows
- Program acceleration: create from template + copy source week to target week
- Appointments: scheduling with overlap conflict protection
- Attendance hardening: status transition guard (planned/done/cancelled/no_show)
- Recurring appointments: weekly/monthly appointment series generation
- Reminder queue: appointment-scoped WhatsApp reminders (`pending/ready/sent/missed/cancelled`)
- Reminder automation: retry scheduling, escalation flow, quiet-hours policy support
- Dashboard summary endpoint for KPI cards and today overview
- Student timeline endpoint: merged program + appointment activity feed
- Calendar availability endpoint for frontend schedule view
- WhatsApp deep-link helper endpoint for appointment reminder messaging
- Domain audit trail for student/program/appointment mutations
- Security middleware: request-id, forced JSON, security headers, locale
- API logging: dedicated `apilog` channel with sensitive-data masking
- Health endpoint with database/cache/queue checks
- Localized responses: English and Turkish
- Endpoint-specific rate limits for sensitive routes
- CI quality gate: tests, code style, and dependency audit

## Requirements

- PHP 8.2+
- Composer
- SQLite/MySQL/PostgreSQL

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

API base path: `/api/v1`

## Demo Seed Data

Run seeders for a ready-to-use owner/trainer demo graph:

```bash
php artisan migrate:fresh --seed
```

Demo login credentials:

- Owner/Admin:
  - email: `owner@vertex.local`
  - password: `password123`
- Trainer:
  - email: `trainer@vertex.local`
  - password: `password123`
- Platform Admin:
  - email: `admin@vertex.local`
  - password: `password123`

Both users are seeded with:
- active workspace membership (`Vertex Demo Workspace`)
- `active_workspace_id` set
- sample students and weekly programs (with items)
- dynamic 14-day calendar data anchored to current day (`UTC`)
- mixed schedule model: recurring appointment series + one-off daily appointments
- appointment reminder queue generated from workspace reminder policy
- rebuild-safe behavior: rerunning seeders replaces demo domain data instead of growing it

## Local Development Checklist

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
./vendor/bin/pint --test
```

## API-only Mode

- Web routes are intentionally locked down.
- `GET /` returns `403` JSON.
- Non-API web paths also return `403` JSON.
- Optional strict mode:
  - Set `API_STRICT_JSON_ONLY=true` to reject API requests that do not expect JSON (`Accept: application/json` required).

## Authentication

Protected routes use Sanctum bearer tokens.

`Authorization: Bearer <token>`

## Workspace Context

- Domain endpoints require an active workspace (`workspace.context` middleware).
- Use these first after login:
  1. `GET /api/v1/me/workspaces`
  2. `POST /api/v1/workspaces/{workspace}/switch`
- If no active workspace is set, domain endpoints return `403`.

## Role Scope

- `owner_admin`: workspace-wide access to trainer-assigned records.
- `trainer`: access limited to own assigned students/programs/appointments.
- Resource policies require `user.active_workspace_id` to match resource workspace.

## Security Model

### Token Lifecycle

- Register/login create a new Sanctum token.
- `POST /api/v1/refresh-token` revokes current token and issues a new one.
- `PUT /api/v1/me/password` keeps current token active and revokes other tokens.
- `POST /api/v1/reset-password` revokes all user tokens after successful reset.
- `DELETE /api/v1/me` revokes all tokens and soft-deletes the account.

### Request ID Contract

- Client can send `X-Request-Id`.
- Valid IDs are echoed back in response header `X-Request-Id`.
- If missing or invalid, server generates a UUID and returns it in `X-Request-Id`.
- Validation rules:
  - max length: `128`
  - allowed chars: `A-Z a-z 0-9 . _ : -`

### Exception Handling

- API errors are rendered as JSON with consistent envelope shape.
- Common HTTP statuses are explicitly handled: `401`, `403`, `404`, `405`, `422`, `429`, `500`.
- In production (`APP_DEBUG=false`), server errors return generic messages.

### API Log Masking

- API traffic is logged via dedicated `apilog` channel.
- Sensitive keys such as password/token/authorization secrets are masked.
- Email and phone values are partially masked before log write.

## Main Endpoints

Public:
- `GET /api/v1/health`
- `POST /api/v1/register`
- `POST /api/v1/login`
- `POST /api/v1/forgot-password`
- `POST /api/v1/reset-password`

Authenticated (core):
- `POST /api/v1/logout`
- `POST /api/v1/logout-all`
- `POST /api/v1/refresh-token`
- `POST /api/v1/email/verify/{id}/{hash}`
- `POST /api/v1/email/resend`
- `GET /api/v1/users`
- `GET /api/v1/me`
- `PUT /api/v1/me`
- `PUT /api/v1/me/password`
- `POST /api/v1/me/avatar`
- `DELETE /api/v1/me/avatar`
- `DELETE /api/v1/me`

Authenticated (workspace/domain):
- `GET /api/v1/dashboard/summary`
- `GET /api/v1/reports/appointments`
- `GET /api/v1/reports/students`
- `GET /api/v1/reports/programs`
- `GET /api/v1/me/workspaces`
- `POST /api/v1/workspaces`
- `POST /api/v1/workspaces/{workspace}/switch`
- `POST /api/v1/students`
- `GET /api/v1/students`
- `GET /api/v1/students/{student}`
- `PUT /api/v1/students/{student}`
- `PATCH /api/v1/students/{student}/status`
- `GET /api/v1/students/{student}/timeline`
- `POST /api/v1/students/{student}/programs`
- `GET /api/v1/students/{student}/programs`
- `POST /api/v1/students/{student}/programs/from-template`
- `POST /api/v1/students/{student}/programs/copy-week`
- `GET /api/v1/programs/{program}`
- `PUT /api/v1/programs/{program}`
- `PATCH /api/v1/programs/{program}/status`
- `GET /api/v1/program-templates`
- `POST /api/v1/program-templates`
- `GET /api/v1/program-templates/{template}`
- `PUT /api/v1/program-templates/{template}`
- `DELETE /api/v1/program-templates/{template}`
- `POST /api/v1/appointments`
- `GET /api/v1/appointments`
- `GET /api/v1/appointments/{appointment}`
- `PUT /api/v1/appointments/{appointment}`
- `PATCH /api/v1/appointments/{appointment}/status`
- `PATCH /api/v1/appointments/{appointment}/whatsapp-status`
- `GET /api/v1/appointments/{appointment}/whatsapp-link`
- `POST /api/v1/appointments/series`
- `GET /api/v1/appointments/series`
- `GET /api/v1/appointments/series/{series}`
- `PUT /api/v1/appointments/series/{series}`
- `PATCH /api/v1/appointments/series/{series}/status`
- `GET /api/v1/reminders`
- `PATCH /api/v1/reminders/{reminder}/open`
- `PATCH /api/v1/reminders/{reminder}/mark-sent`
- `PATCH /api/v1/reminders/{reminder}/requeue`
- `PATCH /api/v1/reminders/{reminder}/cancel`
- `POST /api/v1/reminders/bulk`
- `GET /api/v1/reminders/export.csv`
- `GET /api/v1/reports/reminders`
- `GET /api/v1/calendar`
- `GET /api/v1/calendar/availability`

## Operations Accelerator (Sprint)

- `POST /students/{student}/programs/from-template`
  - Uses `template_id` + `week_start_date` to generate a full weekly program.
- `POST /students/{student}/programs/copy-week`
  - Copies a student's source week program to a target week.
- `GET /students/{student}/timeline`
  - Returns recent merged events (`program` + `appointment`) for fast coach context.
- Appointment status transitions are now guarded:
  - Allowed: `planned -> done/cancelled/no_show`
  - Allowed: `done -> planned` (correction)
  - Allowed: `cancelled -> planned`
  - Allowed: `no_show -> planned/done/cancelled`
  - Disallowed transitions return `422`.

## Reminder Automation (Hybrid MVP)

- Send model: hybrid manual confirmation (provider integration intentionally out of scope).
- Retry defaults: 2 attempts with backoff `[15, 30]` minutes.
- Escalation: exhausted retries move reminder to `escalated`.
- Quiet-hours: policy-driven scheduling guardrails (workspace-level).

## Route Matrix

| Method | Path | Auth | Middleware | Route Name |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/health` | Public | `api.log` + global API middlewares | `v1.health` |
| POST | `/api/v1/register` | Public | `throttle:register` | `v1.auth.register` |
| POST | `/api/v1/login` | Public | `throttle:login` | `v1.auth.login` |
| POST | `/api/v1/forgot-password` | Public | `throttle:forgot-password` | `v1.auth.forgot-password` |
| POST | `/api/v1/reset-password` | Public | `throttle:reset-password` | `v1.auth.reset-password` |
| POST | `/api/v1/logout` | Bearer token | `auth:sanctum,user.active` | `v1.auth.logout` |
| POST | `/api/v1/logout-all` | Bearer token | `auth:sanctum,user.active` | `v1.auth.logout-all` |
| POST | `/api/v1/refresh-token` | Bearer token | `auth:sanctum,user.active` | `v1.auth.refresh-token` |
| POST | `/api/v1/email/verify/{id}/{hash}` | Bearer token | `auth:sanctum,user.active,signed,throttle:verify-email` | `v1.verification.verify` |
| POST | `/api/v1/email/resend` | Bearer token | `auth:sanctum,user.active,throttle:resend-verification` | `v1.verification.resend` |
| GET | `/api/v1/users` | Bearer token | `auth:sanctum,user.active,verified` | `v1.users.index` |
| GET | `/api/v1/me` | Bearer token | `auth:sanctum,user.active` | `v1.profile.show` |
| PUT | `/api/v1/me` | Bearer token | `auth:sanctum,user.active` | `v1.profile.update` |
| PUT | `/api/v1/me/password` | Bearer token | `auth:sanctum,user.active` | `v1.profile.password` |
| POST | `/api/v1/me/avatar` | Bearer token | `auth:sanctum,user.active,throttle:avatar-upload` | `v1.profile.avatar.update` |
| DELETE | `/api/v1/me/avatar` | Bearer token | `auth:sanctum,user.active` | `v1.profile.avatar.delete` |
| DELETE | `/api/v1/me` | Bearer token | `auth:sanctum,user.active,throttle:delete-account` | `v1.profile.destroy` |
| GET | `/api/v1/me/workspaces` | Bearer token | `auth:sanctum,user.active` | `v1.workspace.index` |
| GET | `/api/v1/dashboard/summary` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.dashboard.summary` |
| GET | `/api/v1/reports/appointments` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reports.appointments` |
| GET | `/api/v1/reports/students` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reports.students` |
| GET | `/api/v1/reports/programs` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reports.programs` |
| POST | `/api/v1/workspaces` | Bearer token | `auth:sanctum,user.active` | `v1.workspace.store` |
| POST | `/api/v1/workspaces/{workspace}/switch` | Bearer token | `auth:sanctum,user.active` | `v1.workspace.switch` |
| POST | `/api/v1/students` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.store` |
| GET | `/api/v1/students` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.index` |
| GET | `/api/v1/students/{student}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.show` |
| PUT | `/api/v1/students/{student}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.update` |
| PATCH | `/api/v1/students/{student}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.status` |
| GET | `/api/v1/students/{student}/timeline` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.timeline` |
| POST | `/api/v1/students/{student}/programs` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.store` |
| GET | `/api/v1/students/{student}/programs` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.index` |
| POST | `/api/v1/students/{student}/programs/from-template` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.store-from-template` |
| POST | `/api/v1/students/{student}/programs/copy-week` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.copy-week` |
| GET | `/api/v1/programs/{program}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.show` |
| PUT | `/api/v1/programs/{program}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.update` |
| PATCH | `/api/v1/programs/{program}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.status` |
| GET | `/api/v1/program-templates` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.program-templates.index` |
| POST | `/api/v1/program-templates` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.program-templates.store` |
| GET | `/api/v1/program-templates/{template}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.program-templates.show` |
| PUT | `/api/v1/program-templates/{template}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.program-templates.update` |
| DELETE | `/api/v1/program-templates/{template}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.program-templates.destroy` |
| POST | `/api/v1/appointments` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.store` |
| GET | `/api/v1/appointments` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.index` |
| POST | `/api/v1/appointments/series` | Bearer token | `auth:sanctum,user.active,workspace.context,idempotent.appointments` | `v1.appointments.series.store` |
| GET | `/api/v1/appointments/series` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.series.index` |
| GET | `/api/v1/appointments/series/{series}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.series.show` |
| PUT | `/api/v1/appointments/series/{series}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.series.update` |
| PATCH | `/api/v1/appointments/series/{series}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.series.status` |
| GET | `/api/v1/appointments/{appointment}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.show` |
| PUT | `/api/v1/appointments/{appointment}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.update` |
| PATCH | `/api/v1/appointments/{appointment}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.status` |
| PATCH | `/api/v1/appointments/{appointment}/whatsapp-status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.whatsapp-status` |
| GET | `/api/v1/reminders` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reminders.index` |
| PATCH | `/api/v1/reminders/{reminder}/open` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reminders.open` |
| PATCH | `/api/v1/reminders/{reminder}/mark-sent` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reminders.mark-sent` |
| PATCH | `/api/v1/reminders/{reminder}/cancel` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.reminders.cancel` |
| GET | `/api/v1/appointments/{appointment}/whatsapp-link` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.whatsapp.appointment-link` |
| GET | `/api/v1/calendar` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.calendar.index` |
| GET | `/api/v1/calendar/availability` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.calendar.availability` |

## Domain Rules

- Student status lifecycle: `active` or `passive`.
- `trainer_user_id` assignment/reassignment is owner-admin only.
- Program guard: one `active` program per student per `week_start_date`.
- Program item guard: `day_of_week + order_no` must be unique inside a program payload.
- Appointment guard: trainer and student overlap is blocked (`422 Unprocessable Entity`, `errors.code[0] = time_slot_conflict`).
- Appointment idempotency guard: optional `Idempotency-Key` prevents duplicate create requests for the same actor/workspace.
- WhatsApp reminder flow is appointment-scoped: link generation and manual `sent/not_sent` tracking live under appointment endpoints.
- Reminder offsets default to `24h` and `2h` before `starts_at`, configurable by `workspaces.reminder_policy`.
- Student-level WhatsApp link endpoint is removed; use appointment-scoped links only.

## Scheduler

- Reminder queue housekeeping is executed with:
  - `php artisan reminders:mark-missed`
- `routes/console.php` schedules it every 5 minutes.

## Rate Limits

| Limiter | Value | Scope Key |
| --- | --- | --- |
| `api` | `60/minute` | `user_id` or IP |
| `login` | `5/minute` | IP |
| `register` | `3/minute` | IP |
| `forgot-password` | `3/minute` | IP |
| `reset-password` | `5/minute` | `email+IP` |
| `verify-email` | `6/minute` | `user_id` or IP |
| `resend-verification` | `3/minute` | `user_id` or IP |
| `avatar-upload` | `10/minute` | `user_id` or IP |
| `delete-account` | `3/minute` | `user_id` or IP |

## Response Contract

Success envelope:

```json
{
  "success": true,
  "message": "Operation successful.",
  "data": {},
  "request_id": "d92861c5-5f30-4f3e-bf3a-3a4f053f8c5a"
}
```

Validation error envelope:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Validation message"]
  },
  "request_id": "d92861c5-5f30-4f3e-bf3a-3a4f053f8c5a"
}
```

Conflict error envelope (appointment overlap):

```json
{
  "success": false,
  "message": "Appointment conflict detected for trainer or student.",
  "errors": {
    "code": ["time_slot_conflict"]
  },
  "request_id": "d92861c5-5f30-4f3e-bf3a-3a4f053f8c5a"
}
```

Paginated data envelope example:

```json
{
  "success": true,
  "message": "Success",
  "request_id": "d92861c5-5f30-4f3e-bf3a-3a4f053f8c5a",
  "data": {
    "data": []
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0
  },
  "links": {
    "first": "https://vertex.local/api/v1/students?page=1",
    "last": "https://vertex.local/api/v1/students?page=1",
    "prev": null,
    "next": null
  }
}
```

Calendar availability envelope example:

```json
{
  "success": true,
  "message": "Success",
  "request_id": "d92861c5-5f30-4f3e-bf3a-3a4f053f8c5a",
  "data": {
    "from": "2026-02-20 00:00:00",
    "to": "2026-02-27 23:59:59",
    "appointments": [],
    "days": [
      {
        "date": "2026-02-21",
        "items": []
      }
    ]
  }
}
```

List endpoint query contract:
- shared: `page`, `per_page`, `search`, `sort`, `direction`
- students: `status` (`active`, `passive`, `all`) - optional, defaults to `all` when omitted
- programs: `status` (`draft`, `active`, `archived`, `all`)
- appointments: `status`, `whatsapp_status` (`sent`, `not_sent`, `all`), `trainer_id`, `student_id`, `from|to`, `date_from|date_to`
- appointment series: `status` (`active`, `paused`, `ended`, `all`), `trainer_id`, `student_id`, `from`, `to`
- reminders: `status` (`pending`, `ready`, `sent`, `missed`, `cancelled`, `failed`, `all`), `trainer_id`, `student_id`, `from`, `to`
- users: `search`, `sort` (`id`, `name`, `email`, `created_at`)
- reporting: `date_from`, `date_to`, `group_by` (`day`, `week`, `month`), `trainer_id` (owner_admin only)

Dashboard summary envelope example:

```json
{
  "success": true,
  "message": "Success",
  "request_id": "d92861c5-5f30-4f3e-bf3a-3a4f053f8c5a",
  "data": {
    "date": "2026-02-15",
    "students": { "active": 42, "passive": 8, "total": 50 },
    "appointments": {
      "today_total": 6,
      "today_done": 2,
      "today_planned": 3,
      "today_cancelled": 1,
      "upcoming_7d": 18
    },
    "programs": { "active_this_week": 21, "draft_this_week": 7 }
  }
}
```

## Frontend Integration Quickstart

React client flow after login:
1. Store token and set `Authorization: Bearer <token>`.
2. Fetch workspaces via `GET /api/v1/me/workspaces`.
3. Select active workspace via `POST /api/v1/workspaces/{workspace}/switch`.
4. Consume domain endpoints (`students`, `programs`, `appointments`, `reminders`, `calendar`).
5. For reminder messaging from scheduling screens:
   - fetch deep link with `GET /api/v1/appointments/{appointment}/whatsapp-link`
   - persist delivery state with `PATCH /api/v1/appointments/{appointment}/whatsapp-status`
6. For reminder queue workflows:
   - list queue with `GET /api/v1/reminders`
   - mark reminder opened/sent/cancelled with reminder action endpoints

Recommended headers:
- `Accept: application/json`
- `Authorization: Bearer <token>`
- optional `X-Request-Id`

## API Docs (Scramble)

If Scramble routes are enabled:
- UI: `/docs/api`
- OpenAPI JSON: `/api.json`
- Local HTTPS server target can be customized with:
  - `SCRAMBLE_LOCAL_HTTPS_URL=https://vertex.local`

If UI opens but `/api.json` returns `404`, check:
- `APP_URL` matches the served host (example: `https://vertex.local`).
- Local host mapping and TLS setup are correct.
- Docs route access middleware is not blocking JSON export in your environment.
- Route/config cache is refreshed after env changes:
  - `php artisan optimize:clear`

Example docs URLs:
- `https://vertex.local/docs/api`
- `https://vertex.local/api.json`

### Quick API Examples (TR/EN friendly)

Login:

```bash
curl -k -X POST 'https://vertex.local/api/v1/login' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"owner@vertex.local","password":"password123"}'
```

Switch workspace:

```bash
curl -k -X POST 'https://vertex.local/api/v1/workspaces/1/switch' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>'
```

List students (query contract):

```bash
curl -k 'https://vertex.local/api/v1/students?status=active&search=ali&sort=full_name&direction=asc&page=1&per_page=15' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>'
```

List programs for one student:

```bash
curl -k 'https://vertex.local/api/v1/students/10/programs?status=all&search=week&sort=week_start_date&direction=desc&page=1&per_page=25' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>'
```

Appointments report example:

```bash
curl -k 'https://vertex.local/api/v1/reports/appointments?date_from=2026-06-01&date_to=2026-06-30&group_by=day' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>'
```

Create appointment:

```bash
curl -k -X POST 'https://vertex.local/api/v1/appointments' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <token>' \
  -d '{"student_id":10,"starts_at":"2026-04-11 10:00:00","ends_at":"2026-04-11 11:00:00"}'
```

Create appointment with idempotency:

```bash
curl -k -X POST 'https://vertex.local/api/v1/appointments' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <token>' \
  -H 'Idempotency-Key: appt-2026-04-11-1000-student10' \
  -d '{"student_id":10,"starts_at":"2026-04-11 10:00:00","ends_at":"2026-04-11 11:00:00"}'
```

## Security Headers

API responses include:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- `Cross-Origin-Opener-Policy: same-origin`
- `Cross-Origin-Resource-Policy: same-site`
- `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none';`
- `Strict-Transport-Security` in non-debug mode only

## Operational Defaults

- Time handling is UTC-first.
- Localization is header-driven (`Accept-Language`: `en` or `tr`, fallback `en`).
- API-only access policy is enabled.
- Avatar upload policy:
  - allowed types: `jpeg`, `jpg`, `png`, `webp`
  - max file size: `2MB`
  - max dimensions: `4096x4096`

## Running Tests

```bash
php artisan test
```

Current baseline: `136` tests passing (`553` assertions).

## Release Ops

- Release docs:
  - `CHANGELOG.md`
  - `docs/release-checklist.md`
  - `docs/smoke-qa.md`
- Tagging convention:
  - semantic tags (example: `v0.3.0`)
  - tag after backend+frontend `main` branches are both green
- Reminder scheduler requirement:
  - keep `php artisan schedule:run` active via cron/system timer
  - reminder missed-state upkeep is handled by `reminders:mark-missed`

## Known Limitations

- WhatsApp provider integration is not automatic in this release.
- Reminder queue uses hybrid flow (manual open + manual sent confirmation).
- Provider-side delivery receipts are not available in this release.

## CI Pipeline

GitHub Actions workflow (`.github/workflows/ci.yml`) runs on push/PR to `main`:
- `php-tests`: install, audit, test
- `code-style`: `./vendor/bin/pint --test --dirty`

Recommended branch protection on `main`:
- require `php-tests` and `code-style` to pass before merge

## CD Pipeline

GitHub Actions workflow (`.github/workflows/deploy.yml`) deploys production when:
- backend `CI` workflow succeeds on `main` (`workflow_run`)
- or manually via `workflow_dispatch`

Production deploy steps:
- SSH into server (`deploy` user)
- pull `origin/main`
- `composer install --no-dev --optimize-autoloader`
- `php artisan migrate --force`
- cache rebuild (`optimize:clear`, `config:cache`, `route:cache`, `view:cache`)
- `php artisan queue:restart`
- health gate: `GET /api/v1/health` with `Accept: application/json`

Required repository secrets:
- `PROD_HOST`
- `PROD_USER`
- `PROD_SSH_KEY`
- `PROD_SSH_PORT` (optional, defaults to `22`)

## Keeping README Current

Treat README as code. Update it in the same change set when endpoints, middleware, contracts, limits, CI, or env-based behavior changes.

PR checklist:
- [ ] Code changes reviewed for README impact
- [ ] README updated or explicitly confirmed as accurate
