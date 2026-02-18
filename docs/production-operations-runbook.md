# Backend Production Operations Runbook

## 1) Incident entrypoint
- Validate API health first:
  - `curl -i -H "Accept: application/json" https://api.vertex.ferzendervarli.com/api/v1/health`
- Capture `request_id` from failing response and attach it to incident notes.

## 2) Service status check
- Check runtime services:
  - `systemctl status nginx php8.3-fpm vertex-queue --no-pager -l`
- Check scheduler:
  - `crontab -l | rg "schedule:run"`

## 3) Deploy verification
- In GitHub Actions, confirm:
  - `CI` is green
  - `Deploy` is green
  - deploy logs include `migrate --force`, cache rebuild, `queue:restart`

## 4) Environment protection
- GitHub `production` environment should require reviewers.
- Recommended: at least 1 required reviewer before manual `workflow_dispatch`.

## 5) SSH key rotation policy
- Rotate `PROD_SSH_KEY` every 90 days.
- Rotation flow:
  1. create new keypair
  2. add new public key to `/home/deploy/.ssh/authorized_keys`
  3. update GitHub secret
  4. test deploy workflow
  5. remove old key

## 6) Weekly security maintenance
- `Security Audit` workflow runs every Monday 07:00 UTC.
- If it fails:
  - review advisory output
  - patch dependencies
  - rerun manually (`workflow_dispatch`)

## 7) Hotfix deployment
- Commit hotfix directly to `main` only if urgent.
- Confirm post-deploy checks:
  - health endpoint `200`
  - login works
  - queue worker active

## 8) Rollback
- Backend rollback commands:
  - `cd /var/www/vertex-laravel-api`
  - `git reset --hard <previous_commit>`
  - `composer install --no-dev --optimize-autoloader`
  - `php artisan optimize:clear`
  - `php artisan config:cache && php artisan route:cache && php artisan view:cache`
  - `php artisan queue:restart`

## 9) Data safety
- Never run `migrate:fresh` in live production with non-disposable data.
- Use `migrate --force` only; rollback by controlled migration steps when safe.

## 10) Post-incident closeout
- Record:
  - root cause
  - affected window (UTC)
  - recovery steps
  - preventive action
- Update this runbook/checklists if a gap was discovered.

