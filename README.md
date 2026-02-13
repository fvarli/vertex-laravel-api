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

## Authentication

Protected routes use Sanctum bearer tokens.

`Authorization: Bearer <token>`

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

## Running Tests

```bash
php artisan test
```

Current baseline: `94` tests passing.

## Notes

- App and database are configured for UTC usage.
- Responses follow a consistent envelope:
  - success: `success`, `message`, `data`
  - error: `success`, `message`, optional `errors`
- Recommended protected branch target: `main`
