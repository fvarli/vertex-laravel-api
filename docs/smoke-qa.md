# Backend Smoke QA

## Goal
Validate critical API flows in 10-15 minutes after deployment.

## 1) Auth + Workspace
1. Login with owner account.
2. Call `GET /api/v1/me/workspaces`.
3. Switch active workspace using `POST /api/v1/workspaces/{id}/switch`.
Expected:
- All calls return `200`.
- `request_id` present in response envelope.

## 2) Recurring Series Create
1. Call `POST /api/v1/appointments/series` with weekly payload.
Expected:
- `201`.
- Response includes `generated_count` and `skipped_conflicts_count`.

## 3) Reminder Queue Visibility
1. Call `GET /api/v1/reminders?status=all`.
Expected:
- Newly generated reminders are listed.
- Status values are valid (`pending/ready/sent/missed/cancelled/failed`).

## 4) Reminder Actions
1. Call `PATCH /api/v1/reminders/{id}/open`.
2. Call `PATCH /api/v1/reminders/{id}/mark-sent`.
Expected:
- Status transitions are persisted.
- Appointment WhatsApp fields reflect sent state.

## 5) WhatsApp Link Contract
1. Call `GET /api/v1/appointments/{appointment}/whatsapp-link`.
Expected:
- Response contains a valid `wa.me` URL.

## 6) Scheduler Behavior
1. Trigger `php artisan reminders:mark-missed`.
Expected:
- Past due `pending/ready` reminders transition to `missed`.

## Failure Handling
- If any step fails, capture `request_id`, endpoint, and payload.
- Check `storage/logs` and `apilog` channel entries.
- Stop release sign-off until root cause is documented.

