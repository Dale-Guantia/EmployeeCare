# Dedicated policy ingestion queue

This change addresses only dashboard ticket visibility, literal skill-tag rendering, and off-request policy ingestion. PHP 7.4, Laravel 8, Backpack, dependencies/lockfiles, account/avatar handling, and the application's default queue are retained. Earlier uncommitted deployment/reconciliation work is preserved.

## Release prerequisites and procedure

### Upload failure: missing ingestion schema

SQL Server upload failures reporting `Invalid column name 'ingestion_version'` mean the code was updated before the forward queue migration was applied. The upload page now keeps the entered metadata and shows an explicit setup error before storing a PDF when the generation columns or `policy_jobs` schema are missing. Replacement/re-ingestion also retain their existing document state in this case. This guard does not apply the migration automatically.

Apply only the migration listed below through an authorized release. It creates `policy_jobs` and adds three nullable ingestion fields, preserving existing policies and chunks. A running dedicated worker is then required to process uploads; setting the application's default queue to `sync` will not process this dedicated queue. Both text PDFs and scanned PDFs are accepted by the same upload form; the worker uses native extraction or OCR per page. PDFs remain unavailable to the chatbot until their status is Active. Existing files left by earlier failed uploads are not removed by this fix.

On 2026-10-14, the user authorized **this migration only**, and it was applied to the configured SQL Server `employeecare` database. Read-only follow-up confirmed its migration record, `policy_jobs`, nullable `uniqueidentifier` generation column, both nullable `datetime` markers, and the application's upload-readiness check. The database currently has zero policy documents, chunks and queued jobs. No live PDF uploads, document edits, cleanup, persistent worker startup or deployed configuration changes were performed. Worker supervision remains required; the commands below are ready for the operator to run.

Upload-fix checks: **38 tests / 189 assertions passed**, including legacy-schema handling, preserving legacy records during the forward migration, SQL Server SQL compilation (no engine writes), replacement/queue failures and ingestion regressions. **Three real-PDF upload/worker tests / 42 assertions passed** on SQLite `:memory:` and fake storage using copies from `public/policyDocs`: `MC No. 05, s. 2021 (1).pdf` (9 text pages, 14 chunks), `OMNIBUS RULE ON LEAVE (1).pdf` (49 text pages, 68 chunks), and the executive order scan (4 OCR pages, 6 chunks, 93% confidence). All became Active; original sample hashes were unchanged. Configured Tesseract 5.5.0 and Poppler 26.02.0 with Filipino language data passed the OCR setup check. No application policies were created by these tests.

Upload-fix files: `app/Services/PolicyIngestService.php`, `app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php`, `app/Exceptions/PolicyIngestionSetupException.php`, `resources/views/admin/hr_policy_upload.blade.php`, `resources/views/admin/hr_policy_update.blade.php`, `tests/Feature/PolicyUploadReadinessTest.php`, `tests/Feature/PolicyUploadSamplesTest.php`, and this document. The already-prepared queue migration was applied with authorization; its source did not need changing. Dependencies, versions, original samples and unrelated edits were preserved.

Repeat the real-sample check in PowerShell with `$env:POLICY_SAMPLE_TESTS='1'` followed by `php vendor/phpunit/phpunit/phpunit --filter PolicyUploadSamplesTest`; remove the temporary environment variable afterward. These tests require the sample folder and configured OCR binaries and are skipped without explicit opt-in.

1. Rehearse the new migration on an isolated database matching the deployed engine. Back up the policy tables and files. Stop policy uploads/replacements/re-runs and any previous policy workers during the coordinated release.
2. Apply the new forward migration after reviewing the target's existing schema:

   ```text
   php artisan migrate --force --path=database/migrations/2026_10_14_110000_add_policy_ingestion_queue.php
   ```

   It creates Laravel's database-queue schema in `policy_jobs` and adds nullable `ingestion_version`, `ingestion_queued_at`, and `ingestion_started_at` fields to existing documents. No existing document or file is deleted. Rollback intentionally retains queue/recovery state. Do not use `queue:table` to generate a second migration, rerun old migrations, or reset the database.
3. Deploy the matching code and refresh the config/view caches through the normal release procedure. Confirm `queue.connections.policy_ingestion` uses `database`, table `policy_jobs`, queue `policy-ingestion`, `retry_after=1200`, and `after_commit=false`. Its database connection must be the same connection used for policy documents (the committed setting is `null`, meaning the application's default database). Enqueue and the queued marker must commit together. No new environment variable or change to `QUEUE_CONNECTION` is required; it may remain `sync` for unrelated work.
4. The worker account needs the existing OCR binaries/language data, policy-disk read access, and application/database write access for queue rows, document state, chunks, and `failed_jobs`. HTTP and workers must see the same configured policy disk and database. Verify the existing failed-jobs migration/table and failure logging configuration. Do not add the private ticket attachment disk to web serving.

## Exact worker commands

For a Linux worker with PCNTL enabled:

```text
php artisan queue:work policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

Laravel 8's worker timeout requires PCNTL, and timeout must be below `retry_after` ([Laravel 8 queue documentation](https://laravel.com/framework/docs/8.x/queues#job-expirations-and-timeouts)). The job timeout is fixed at 900 seconds; reservation retry is 1200 seconds. The pipeline also uses a finite PHP execution limit; existing per-page OCR process timeouts remain intact.

For the existing Windows PHP runtime, where PCNTL is unavailable, use Laravel's listener with a bounded child process:

```text
php artisan queue:listen policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

The installed Laravel 8 listener runs `queue:work --once` in Symfony Process with the requested timeout; the installed Windows Process implementation terminates the process tree. Do not rely on `queue:work --timeout` alone on native Windows. PHP's execution-limit measurement includes wall time on Windows ([PHP manual](https://www.php.net/function.set-time-limit.php)); the listener provides the outer process deadline as well. Allow startup margin within the 300-second reservation gap, and monitor/restart the listener if a child timeout terminates it.

Run the chosen command under the existing service/process manager with the project working directory, hidden/non-interactive execution on Windows, automatic restart on exit, and restricted logs. Configure graceful stop time longer than 900 seconds (for example 930) and force-stop the process tree before its 1200-second reservation becomes eligible again. Monitor queued age, reserved jobs, failed jobs, and documents stuck processing. This task does not install supervision or start persistent workers.

After code/config releases, use `php artisan queue:restart` for daemon workers with a shared, persistent cache and let supervision restart them. This Laravel restart signal can affect other workers using the same cache; use the existing release procedure. Restart Windows listeners through their service manager after the current child finishes. A listener loads code in each child, but its parent still needs fresh configuration. Do not blindly flush caches or kill an active policy run while replacing its file.

## Lifecycle and recovery

- Upload saves the PDF and document as `pending`. A successful HTTP response says **queued**, not extracted/active. Without a worker it remains pending.
- A short transaction locks the current document, inserts one queue row for its generation, and records `ingestion_queued_at` together. Duplicate requests for an already queued generation do not add another row. Any insertion/marker failure rolls back both changes. Dispatch failure preserves the saved PDF and pending document, records a recoverable error, and displays a warning; re-run after fixing the queue.
- The worker atomically claims only `pending` with the expected generation and sets `processing`/start time. Stale jobs after replacement and duplicate deliveries skip OCR. Replacements and re-runs briefly lock/check the latest record and reject an active run, without holding a database lock during extraction. Replacements of pending documents create a new generation; old queued jobs cannot ingest the replacement.
- Chunks and extraction metadata commit together only if the same generation still owns `processing`. Successful extraction becomes `active` or `needs_review`; failures become `failed` without discarding the PDF or previous chunks. Existing activation/quality-gate behavior is retained.
- Exhausted/timed-out jobs use a generation-guarded failure callback. If the worker is abruptly killed before that callback, the document may temporarily remain processing. Once the reservation expires, a supervised worker's next attempt exceeds the single-attempt limit and marks that generation failed without running OCR again. Restore the worker/database, confirm the old process and OCR children are stopped, then use the policy UI's **Re-run** action. It creates a fresh generation. Retrying a failed queue payload alone does not reset its failed document to pending.
- Pre-release job payloads without generation identifiers are skipped, protecting replacement files. Review any legacy pending/processing records during rollout and re-run them only after confirming no old worker is active. Do not bulk-reset document states or delete files to recover a queue outage.
- Metadata-only policy edits stay immediate. The existing CLI import remains synchronous outside HTTP; its initial state is now pending so it can acquire the same ingestion claim correctly.

## Changed files and verification

Dashboard: `app/Http/Controllers/Admin/DashboardController.php`, `app/Services/TicketReportQueries.php`, and `tests/Feature/DashboardVisibilityTest.php`. Analytics use `ticket.view` and `Ticket::visibleTo()` throughout, including the separate reassignment count and all-time lower bound. Admin global access and employee redirects are preserved.

Skills: `resources/views/vendor/backpack/base/my_account.blade.php`, `tests/JavaScript/SkillTagsTest.cjs`, and `tests/Feature/SkillTagsTest.php`. Literal text nodes, event listeners, and direct input-value comparison preserve saved tags, removal, duplicate detection, and submission. Existing account validation remains unchanged.

Policies: `app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php`, `app/Services/PolicyIngestService.php`, `app/Jobs/IngestPolicyDocumentJob.php`, `app/Models/HrPolicyDocument.php`, `app/Exceptions/PolicyIngestionDispatchException.php`, `app/Console/Commands/IngestHrPolicyPdf.php`, `resources/views/admin/buttons/policy_reingest_btn.blade.php`, `config/queue.php`, the forward migration above, and `tests/Feature/PolicyQueueTest.php`.

Tests use SQLite `:memory:`, fake storage, and mocked OCR. They cover role-scoped dashboard aggregates and incoming transfers; actual skill JavaScript with quotes/brackets/HTML/Unicode; queued HTTP upload/replacement/re-run with a sync default; transactional dispatch failure; stale/duplicate jobs; active-run conflicts; worker success/quality review; worker failure and expired reservation recovery. Real queue rows are consumed for one isolated job at a time, without starting a persistent worker. Production OCR, wall-clock termination, process supervision, and migration execution on the deployed engine require staging/operational validation. Final test counts and syntax results are reported in the task response.

Executed checks: full PHP suite **280 tests / 1,955 assertions passed**; latest focused dashboard/policy/skills tests **18 tests / 201 assertions passed**, including the additional empty-visible-scope case added after full-suite discovery; JavaScript **33 tests passed**. PHP 7.4 syntax checks passed for all 13 changed PHP/config/test files and both Blade files; `git diff --check` passed. Dependency manifests/lockfiles, account update/avatar controller, ticket visibility implementation, and routes were unchanged. No application database fixtures, migration, real OCR run, deployment, or persistent worker were used.
