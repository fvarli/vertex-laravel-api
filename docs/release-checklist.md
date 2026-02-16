# Backend Release Checklist

## Pre-merge
- [ ] `git status` clean (expected files only).
- [ ] `php artisan test` passes.
- [ ] `./vendor/bin/pint --test --dirty` passes for touched files.
- [ ] README/API contract updates are in the same PR.

## Pre-deploy
- [ ] `.env` values verified (DB, cache, queue, app URL, CORS).
- [ ] Backup/restore readiness confirmed.
- [ ] Migration plan reviewed.
- [ ] Rollback owner assigned.

## Deploy
- [ ] `php artisan down` (if required by release strategy).
- [ ] `php artisan migrate --force`.
- [ ] `php artisan optimize:clear`.
- [ ] Ensure scheduler is running (`php artisan schedule:run` via cron).
- [ ] Ensure worker process is healthy if queue driver is async.
- [ ] `php artisan up` (if used).

## Post-deploy verification
- [ ] `php artisan route:list --path=api/v1` contains series/reminders endpoints.
- [ ] Health check endpoint responds `200`.
- [ ] Login + workspace switch works.
- [ ] Recurring series create works.
- [ ] Reminder list/open/mark-sent/cancel works.
- [ ] API logs show no unexpected 5xx spike.

## Rollback
- [ ] If migration rollback needed: `php artisan migrate:rollback --step=1` (only if safe).
- [ ] Re-deploy previous image/revision.
- [ ] Re-run smoke checks.

