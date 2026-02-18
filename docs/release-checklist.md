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
- [ ] GitHub Actions production secrets are configured (`PROD_HOST`, `PROD_USER`, `PROD_SSH_KEY`, optional `PROD_SSH_PORT`).
- [ ] GitHub `production` environment has required reviewers enabled.
- [ ] `PROD_SSH_KEY` rotation date is within policy window (<= 90 days).

## Deploy
- [ ] Merge PR to `main` and confirm `CI` workflow is green.
- [ ] Confirm `Deploy` workflow started automatically (or run `workflow_dispatch`).
- [ ] Confirm deploy job completed without SSH/migration/cache errors.

## Post-deploy verification
- [ ] `php artisan route:list --path=api/v1` contains templates/timeline/copy-week/series/reminders endpoints.
- [ ] Health check endpoint responds `200` with `Accept: application/json` header.
- [ ] Login + workspace switch works.
- [ ] Program template create + from-template + copy-week endpoints work.
- [ ] Student timeline endpoint returns events.
- [ ] Recurring series create works.
- [ ] Reminder list/open/mark-sent/requeue/cancel works.
- [ ] Reminder bulk action works.
- [ ] Reminder CSV export works.
- [ ] Reminder report endpoint works.
- [ ] Attendance transition guards return expected `422` on invalid transitions.
- [ ] API logs show no unexpected 5xx spike.
- [ ] Queue worker was restarted and is healthy after deploy (`php artisan queue:restart` + service status).
- [ ] Scheduler is still running (`php artisan schedule:run` via cron/system timer).

## Rollback
- [ ] If migration rollback needed: `php artisan migrate:rollback --step=1` (only if safe).
- [ ] Re-deploy previous image/revision.
- [ ] Re-run smoke checks.
- [ ] Update `docs/production-operations-runbook.md` if new operational lessons were found.
