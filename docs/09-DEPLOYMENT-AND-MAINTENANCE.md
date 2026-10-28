# 09 - Deployment and Maintenance Guide

## Deployment boundary

No deployment, production migration, worker installation, database backup or cleanup was executed for this documentation. Hosting topology, service manager, DNS/TLS, deployed revision, actual database engine/version and rollback capability are **Not Verified**. Existing [deployment readiness](DEPLOYMENT_READINESS.md) and [policy queue](POLICY_INGESTION_QUEUE.md) documents are operational supplements; their historical results are not current certification.

The following procedures require an approved deployment/maintenance window and environment-specific review. Do not apply them blindly to the running database.

## Prerequisites and configuration

1. Record current commit, uncommitted changes, deployed dependencies, PHP web/CLI versions and configured database.
2. Verify backups and restoration for database, private files, policy PDFs, public avatars and protected environment/application key.
3. Prepare code/dependencies using the lockfiles on compatible PHP. Web root must be public; restrict write permissions to runtime directories and private storage.
4. Configure APP_ENV=production, APP_DEBUG=false, correct APP_URL, HTTPS and SESSION_SECURE_COOKIE=true. Confirm cookie domain/session integration with the external HR login.
5. Verify database TLS/certificate policy, PHP/ODBC drivers, least-privilege service identities and storage permissions. Source currently trusts SQL Server certificates and contains a separate hardcoded connection; resolve deployment assumptions deliberately.
6. Install OCR tools/languages and test under the worker service identity. Provision the selected provider key/model privately; outbound provider access is not established by key presence.

Source configuration: [database](../config/database.php), [session](../config/session.php), [services](../config/services.php), [filesystem](../config/filesystems.php), [OCR](../config/ocr.php).

## Release and migration procedure

- Rehearse on an isolated database of the deployed engine with synthetic/anonymized approved fixtures; SQLite results are insufficient.
- Review the exact pending migrations and data-changing migration bodies. Attachment widening/Unicode changes, bounded chat questions, historical conversation backfill, survey UUID indexes, permission grants and policy queue markers need specific review.
- Do not edit earlier executed migration files. Validate any previously deployed variants using metadata without displaying confidential data.
- Quiesce writers and stop/drain workers when schema/code compatibility requires it. Keep code, database and files mutually consistent.

Typical commands, **only after target approval**:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

A selectively authorized migration must use its approved --path and must not be expanded to the entire batch. Do not run db:seed as a routine release step: role synchronization and reference/demo behavior can change data. Never use migrate:fresh/db:wipe for deployment.

**Route caching is not part of the verified procedure.** Current routes contain closures and repeated names; route:cache/optimize may fail or require code changes. Config/view caching is separate. Cache invalidation and permission/status caches must follow the approved release process; do not flush shared cache indiscriminately.

Frontend assets can be built using npm.cmd ci and npm.cmd run production in a controlled build checkout. Existing public custom assets and worker cache version also need inclusion. No build was executed by this docs task.

## Dedicated policy worker

[IngestPolicyDocumentJob](../app/Jobs/IngestPolicyDocumentJob.php) explicitly uses connection policy_ingestion and queue policy-ingestion; [queue config](../config/queue.php) uses table policy_jobs and retry_after=1200. Timeout=900, tries=1. This is independent of QUEUE_CONNECTION=sync for unrelated work. The generic database queue references jobs, which is not created by repository migrations.

On native Windows, the existing project procedure uses a listener with a bounded child process:

```powershell
php artisan queue:listen policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

For a compatible Linux worker with PCNTL:

```bash
php artisan queue:work policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

Configure the organization's actual service/process manager for continuous execution, restart-on-failure, project working directory, restricted logs and the correct PHP binary/environment. Do not invent a Supervisor/NSSM/systemd installation as existing infrastructure. Native Windows daemon timeout behavior differs; see [installed-runtime analysis](POLICY_INGESTION_QUEUE.md). Graceful shutdown needs more than the job's maximum run time, with process-tree termination before the reservation can be reclaimed.

For daemon code releases, queue:restart requires a shared persistent cache and supervision; it can signal other workers on the same cache. Windows listener parents should restart through their service manager after the current child finishes. Do not terminate active OCR while replacing its PDF without a coordinated procedure.

Monitor pending age, reserved jobs, failed_jobs and documents stuck processing. Saved/queued HTTP feedback is not completion. Retry through the policy re-run action after fixing worker/storage/schema and confirming old processes are stopped. A failed queue payload retry alone does not necessarily reset the document's state/generation.

## Storage and attachment operations

Ensure the private attachment root is not linked publicly. public/storage points only at storage/app/public. Apache has a direct legacy attachment deny rule; Nginx/IIS/development-server deployments need an equivalent rule or verified privatization before exposure.

Read-only operator diagnostics:

```powershell
php artisan attachments:reconcile
php artisan attachments:privatize --dry-run
php artisan users:sync-avatars --dry-run
php artisan hr:test-ocr
php artisan queue:failed
```

These commands may inspect database/files and logs. Restrict their output; --show-paths can reveal employee filenames. The avatar dry-run outputs identity details and should not be pasted into shared reports.

Mutating maintenance functions require separate authorization and backups:

| Function | Actual behavior / prerequisites |
| --- | --- |
| attachments:privatize | Hash-checks and moves legacy public attachment copies to private store; conflicts/unreadable paths stop safely |
| attachments:reconcile --cleanup --uploads-paused | Deletes only eligible old private orphans after full scans and fresh reference/metadata checks; uploads-paused is an operator assertion, not an automatic lock |
| --min-age-hours | Minimum 48, maximum 87600; public orphans require manual review and are not automatically deleted |
| users:sync-avatars | Exact employee-number match, preserve leading zeros, reject ambiguous matches, copy/verify WebP and update avatar_url |
| hr:ingest path | Synchronous filesystem import; --force deletes/recreates an existing filename match; not equivalent to browser in-place replacement |

Retention of uncertain files is intentional. Do not delete remaining files after a failed inventory simply to make a report clean. Source: [commands](IMPLEMENTATION-INVENTORY.md), [AttachmentInventory](../app/Services/AttachmentInventory.php).

## Backup and restoration procedure (recommended, not installed automation)

1. Select the engine-native backup mechanism and an approved protected destination; no backup agent/schedule is defined in this repository.
2. Coordinate a consistent database/files snapshot. Include private attachments, policies/extracted data dependencies, public avatars and the current release/lockfiles. Protect environment secrets/application key separately.
3. Encrypt/restrict backups, record timestamp/revision and define retention with the data owner. Do not place backups in public or source control.
4. Restore into an isolated environment using the appropriate engine tool, matching code/key/configuration and file roots.
5. Keep outbound mail/AI and workers disabled until target identity and data protections are verified. Reconcile references read-only and test representative authorized workflows.
6. Record a successful restore rehearsal and recovery objectives. Backup existence alone is not proof of recoverability.

## Monitoring, rollback and maintenance

Logs default to storage/logs/laravel.log for the single channel; daily/remote channels can change location/retention. Review provider status/usage, upload failures, assignment errors, queue lag, failed OCR, failed file cleanup, database errors and disk capacity. No complete centralized monitoring platform or log-retention deployment was verified.

Rollback means restoring an approved compatible code/dependency release and confirming schema compatibility. Do not automatically migrate:rollback: down methods can drop data/columns or deliberately retain changes. Data-loss/reconciliation risk may require the tested backup restoration procedure instead. Preserve queued generation compatibility; older payloads without versions are skipped by current job code.

| Interval | Recommended operator check |
| --- | --- |
| Each release | Backup/restore evidence, exact migration scope, runtime/config, authorized routes, worker restart and smoke test |
| Daily/operational cadence | Queue age/failures, disk space, errors and missing-file reports |
| Planned maintenance | Read-only attachment audit, confidential-data retention review, backup restore rehearsal |
| Dependency review | Current Composer/npm advisories and supported runtime plan, with separately approved changes |
| Kiosk update | Asset availability, safe worker activation, online submission and next-respondent reset |

[Console Kernel](../app/Console/Kernel.php) has no active scheduled task. These recommendations do not imply a cron job is already configured. See [troubleshooting](13-TROUBLESHOOTING.md) for symptom-based checks.
