# Vertex Laravel API

Versioned REST API skeleton built with Laravel 12 and Sanctum.

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
