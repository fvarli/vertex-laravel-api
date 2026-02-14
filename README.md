# Vertex Laravel API

Versioned REST API skeleton built with Laravel 12 and Sanctum.

Repository status: active skeleton baseline for upcoming domain implementation.

## Features

- Authentication: register, login, logout, logout-all, refresh-token
- Password flow: forgot-password, reset-password
- Email verification: verify (signed URL), resend
- Profile: me, update profile, change password, avatar upload/delete, account delete (soft delete)
- User listing: verified users only
- Security middleware: request-id, forced JSON, security headers, locale
- API logging: dedicated `apilog` channel with sensitive-data masking
- Health endpoint with database/cache/queue checks
- Localized responses: English and Turkish
- Endpoint-specific rate limits for sensitive routes
- CI quality gate: tests, code style, and dependency audit

## Requirements

- PHP 8.2+
- Composer
- SQLite/MySQL/PostgreSQL (project defaults support all standard Laravel drivers)

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API base path: `/api/v1`

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
- In production (`APP_DEBUG=false`), server errors return generic messages (no internal exception leakage).

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

Authenticated (`auth:sanctum`, `user.active`):
- `POST /api/v1/logout`
- `POST /api/v1/logout-all`
- `POST /api/v1/refresh-token`
- `POST /api/v1/email/verify/{id}/{hash}` (`signed` + throttled)
- `POST /api/v1/email/resend` (throttled)
- `GET /api/v1/users` (verified + paginated)
- `GET /api/v1/me`
- `PUT /api/v1/me`
- `PUT /api/v1/me/password`
- `POST /api/v1/me/avatar` (throttled)
- `DELETE /api/v1/me/avatar`
- `DELETE /api/v1/me` (throttled)

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
    "email": ["The email field is required."]
  }
}
```

Authentication error envelope:

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

Rate-limit error envelope:

```json
{
  "success": false,
  "message": "Too many requests. Please try again later."
}
```

## API Docs (Scramble)

If Scramble routes are enabled in your environment:
- UI: `/docs/api`
- OpenAPI JSON: `/api.json`

If UI opens but `/api.json` returns `404`, check:
- `APP_URL` matches the served host (example: `https://vertex.local`).
- Local host mapping and TLS setup for that host are correct.
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

- Time handling is UTC-first:
  - app timezone default is `UTC`
  - database connection timezone is set to `UTC` where supported
- Localization is header-driven:
  - `Accept-Language` supports `en` and `tr`
  - unsupported locales fall back to `en`
- API-only access policy:
  - web root and non-API web routes return `403` JSON
  - optional strict JSON gate with `API_STRICT_JSON_ONLY=true`
- Avatar upload policy:
  - allowed types: `jpeg`, `jpg`, `png`, `webp`
  - max file size: `2MB`
  - max dimensions: `4096x4096`
  - replacing avatar removes previous file from storage

## Running Tests

```bash
php artisan test
```

Current baseline: `103` tests passing (`401` assertions).

## Developer Confidence

- Feature coverage includes:
  - authentication and token flows
  - profile, avatar, and account deletion flows
  - email verification and resend flows
  - middleware behavior (request-id, locale, security headers, forced-json)
  - rate-limit enforcement
  - API exception handling responses
- Unit coverage includes API log masking logic.

## CI Pipeline

GitHub Actions workflow (`.github/workflows/ci.yml`) runs on push/PR to `main`:
- `php-tests` job:
  - `composer install`
  - `composer audit --locked --no-interaction`
  - `php artisan test`
- `code-style` job:
  - `./vendor/bin/pint --test --dirty`

## Keeping README Current

Treat README as code. Update it in the same change set when any of these change:
- API endpoints, route names, middleware, or auth rules
- Response envelopes or error semantics
- Rate limits or security headers
- CI workflow behavior
- Environment variables that affect API behavior (example: `API_STRICT_JSON_ONLY`)

PR checklist (required):
- [ ] Code changes reviewed for README impact
- [ ] README updated or explicitly confirmed as still accurate

## Notes

- App and database are configured for UTC usage.
- Responses follow a consistent envelope:
  - success: `success`, `message`, `data`
  - error: `success`, `message`, optional `errors`
- Recommended protected branch target: `main`
