# Backend Smoke QA

## Goal
Validate critical API flows in 10-15 minutes after deployment.

## 0) Demo Seed Sanity (Local/Staging)
1. Run `php artisan migrate:fresh --seed`.
2. Verify demo workspace contains 14-day appointment coverage with series + one-off mix.
Expected:
- Reminder queue is populated immediately after seed.
- Re-running `php artisan db:seed` does not duplicate domain data.

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

## 3.1) Program Template + Copy Week
1. Call `POST /api/v1/program-templates` with at least one item.
2. Call `POST /api/v1/students/{student}/programs/from-template`.
3. Call `POST /api/v1/students/{student}/programs/copy-week`.
Expected:
- Template is created and listed.
- Program generated from template includes copied items.
- Copy-week creates target week program with source week items.

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

## 7) RBAC + Workspace Isolation
1. Login as trainer user in workspace A.
2. Attempt to read/update resources that belong to another trainer.
3. Attempt to read a resource from workspace B while active workspace is A.
Expected:
- API returns `403`.
- No cross-trainer or cross-workspace data leak.
- `request_id` is returned for traceability.

## 8) Student Timeline + Attendance Guard
1. Call `GET /api/v1/students/{student}/timeline`.
2. Try invalid status transition (`done -> cancelled`) on an appointment.
3. Try setting future appointment directly to `done`.
Expected:
- Timeline returns merged `program` and `appointment` event items.
- Invalid transitions return `422`.
- Future completion guard returns `422`.

## Failure Handling
- If any step fails, capture `request_id`, endpoint, and payload.
- Check `storage/logs` and `apilog` channel entries.
- Stop release sign-off until root cause is documented.
