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
- Students: create, list, update, set status (`active` / `passive`)
- Programs: weekly program management with ordered program items
- Appointments: scheduling with overlap conflict protection
- Calendar availability endpoint for frontend schedule view
- WhatsApp deep-link helper endpoint for student messaging
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

Both users are seeded with:
- active workspace membership (`Vertex Demo Workspace`)
- `active_workspace_id` set
- sample students, programs (with items), and appointments

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
- `GET /api/v1/me/workspaces`
- `POST /api/v1/workspaces`
- `POST /api/v1/workspaces/{workspace}/switch`
- `POST /api/v1/students`
- `GET /api/v1/students`
- `GET /api/v1/students/{student}`
- `PUT /api/v1/students/{student}`
- `PATCH /api/v1/students/{student}/status`
- `POST /api/v1/students/{student}/programs`
- `GET /api/v1/students/{student}/programs`
- `GET /api/v1/programs/{program}`
- `PUT /api/v1/programs/{program}`
- `PATCH /api/v1/programs/{program}/status`
- `POST /api/v1/appointments`
- `GET /api/v1/appointments`
- `GET /api/v1/appointments/{appointment}`
- `PUT /api/v1/appointments/{appointment}`
- `PATCH /api/v1/appointments/{appointment}/status`
- `GET /api/v1/calendar`
- `GET /api/v1/calendar/availability`
- `GET /api/v1/students/{student}/whatsapp-link`

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
| POST | `/api/v1/workspaces` | Bearer token | `auth:sanctum,user.active` | `v1.workspace.store` |
| POST | `/api/v1/workspaces/{workspace}/switch` | Bearer token | `auth:sanctum,user.active` | `v1.workspace.switch` |
| POST | `/api/v1/students` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.store` |
| GET | `/api/v1/students` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.index` |
| GET | `/api/v1/students/{student}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.show` |
| PUT | `/api/v1/students/{student}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.update` |
| PATCH | `/api/v1/students/{student}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.students.status` |
| POST | `/api/v1/students/{student}/programs` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.store` |
| GET | `/api/v1/students/{student}/programs` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.index` |
| GET | `/api/v1/programs/{program}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.show` |
| PUT | `/api/v1/programs/{program}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.update` |
| PATCH | `/api/v1/programs/{program}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.programs.status` |
| POST | `/api/v1/appointments` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.store` |
| GET | `/api/v1/appointments` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.index` |
| GET | `/api/v1/appointments/{appointment}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.show` |
| PUT | `/api/v1/appointments/{appointment}` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.update` |
| PATCH | `/api/v1/appointments/{appointment}/status` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.appointments.status` |
| GET | `/api/v1/calendar` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.calendar.index` |
| GET | `/api/v1/calendar/availability` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.calendar.availability` |
| GET | `/api/v1/students/{student}/whatsapp-link` | Bearer token | `auth:sanctum,user.active,workspace.context` | `v1.whatsapp.student-link` |

## Domain Rules

- Student status lifecycle: `active` or `passive`.
- Program guard: one `active` program per student per `week_start_date`.
- Appointment guard: trainer and student overlap is blocked (`422 Unprocessable Entity`, `errors.code[0] = time_slot_conflict`).
- WhatsApp link endpoint returns ready-to-open `wa.me` URL.

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
  "data": {}
}
```

Validation error envelope:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Validation message"]
  }
}
```

Conflict error envelope (appointment overlap):

```json
{
  "success": false,
  "message": "Appointment conflict detected for trainer or student.",
  "errors": {
    "code": ["time_slot_conflict"]
  }
}
```

Paginated data envelope example:

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "data": [],
    "current_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

Calendar availability envelope example:

```json
{
  "success": true,
  "message": "Success",
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

## Frontend Integration Quickstart

React client flow after login:
1. Store token and set `Authorization: Bearer <token>`.
2. Fetch workspaces via `GET /api/v1/me/workspaces`.
3. Select active workspace via `POST /api/v1/workspaces/{workspace}/switch`.
4. Consume domain endpoints (`students`, `programs`, `appointments`, `calendar`).
5. Use `GET /api/v1/students/{student}/whatsapp-link` for one-click WhatsApp action in table rows.

Recommended headers:
- `Accept: application/json`
- `Authorization: Bearer <token>`
- optional `X-Request-Id`

## API Docs (Scramble)

If Scramble routes are enabled:
- UI: `/docs/api`
- OpenAPI JSON: `/api.json`

If UI opens but `/api.json` returns `404`, check:
- `APP_URL` matches the served host (example: `https://vertex.local`).
- Local host mapping and TLS setup are correct.
- Docs route access middleware is not blocking JSON export in your environment.
- Route/config cache is refreshed after env changes:
  - `php artisan optimize:clear`

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

Current baseline: `111` tests passing (`430` assertions).

## CI Pipeline

GitHub Actions workflow (`.github/workflows/ci.yml`) runs on push/PR to `main`:
- `php-tests`: install, audit, test
- `code-style`: `./vendor/bin/pint --test --dirty`

## Keeping README Current

Treat README as code. Update it in the same change set when endpoints, middleware, contracts, limits, CI, or env-based behavior changes.

PR checklist:
- [ ] Code changes reviewed for README impact
- [ ] README updated or explicitly confirmed as accurate
