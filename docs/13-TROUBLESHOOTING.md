# 13 - Troubleshooting and FAQ

## Diagnostic boundaries

Start with the exact screen/action, timestamp, role, safe record ID and user-visible message. Check the configured log channel; the single-channel default is `storage/logs/laravel.log`. Protect logs and never paste environment dumps, passwords, provider keys, full employee messages or confidential filenames into reports.

Commands below are diagnostics/instructions, not actions already executed on the live application. Some inspect database/files or write runtime caches. Confirm target/environment before running them. Database cleanup, migrations, worker termination, reseeding and file deletion require the separately approved maintenance procedure.

## Installation and database

| Symptom | Likely checks / source evidence |
| --- | --- |
| Composer refuses PHP/extensions | Compare web/CLI php -v and php -m with composer check-platform-reqs; use lockfiles; do not ignore platform requirements |
| App boot or package-discovery fails | Check installed vendor, PHP compatibility, config/cache permissions and the actual exception; a legacy framework lock can differ from a clean modern install |
| Could not find driver | Install/enable selected PDO extension in the PHP runtime actually serving request/worker |
| SQL Server TLS/certificate error | Review PHP SQLSRV/ODBC compatibility and approved TLS configuration; source trusts certificates, not proof of correct production validation |
| Cannot update identity column | Check the nested automatic-assignment save and its syncOriginal guard before blaming form IDs |
| Invalid ingestion_version or policy_jobs | Queue migration/schema readiness is missing or target/cache is wrong; inspect migration status and exact approved pending migration |
| Question GROUP BY fails on SQL Server | Inspect bounded question-column migration and actual schema; text/MAX types cannot be assumed groupable |
| Attachment JSON truncates or Unicode is lost | Verify widening and Unicode migrations for tickets.attachments and ticket_comments.attachment |
| Migration change() error | Check transitive DBAL lock/installed extension and engine support; never solve by wiping established tables |

```powershell
php -v
php --ini
php -m
composer check-platform-reqs
php artisan migrate:status
```

`migrate:status` is read-only database inspection, not authorization to apply every migration. Fresh database installation and production schema reconciliation are different tasks.

## Login, permissions and sessions

- **Login loops/419:** confirm actual scheme, APP_URL, cookie secure flag, session domain/storage, CSRF and reverse-proxy behavior. Source defaults secure cookies=true; plain HTTP development needs its explicit local setting.
- **Redirect to external login:** non-local LoginController behavior is intentional in source. The session/identity integration is **Not Verified**; contact its owner instead of disabling auth.
- **Hardcoded wrong dashboard path:** the already-authenticated login-page redirect contains a fixed subdirectory path; deploying elsewhere requires a reviewed change.
- **403 despite a role:** check active flag, web guard, actual permissions, controller operation gate and record scope. Staff/employee ticket.update does not enable generic ticket edit.
- **Incoming transfer missing from list:** visibleTo includes pending destination, but normal head lists further filter current department/division. Use the authorized notification/detail link while reviewing this UI inconsistency.
- **Role save removed assignment rights:** grouped role editor does not render ticket.assign and syncs submitted permissions. This requires a developer correction/provisioning decision; no working checkbox is documented.
- **route:list fails on a clean empty DB:** installed Backpack password-reset constructor checks users.email. Migrate only an approved development target or inspect route registration without controller construction. The docs inventory used the latter.
- **route:cache fails:** current route closures/repeated names mean route caching is not a verified release command. Do not remove endpoints merely to silence it.

Sources: [login](../app/Http/Controllers/Admin/Auth/LoginController.php), [middleware](../app/Http/Middleware/CheckIfAdmin.php), [role editor](../app/Http/Controllers/Admin/RoleCrudController.php), [ticket controller](../app/Http/Controllers/Admin/TicketCrudController.php).

## Ticket workflows

| Symptom | Check |
| --- | --- |
| Ticket remains Unassigned | Active HR staff in ticket department/division, required named statuses, observer registration and assignment error logs |
| Unexpected staff selection | Catalog skill phrase normalization, Pending/Reopened workload counts and lowest-id tie break; custom issues intentionally skip skill scores |
| Selected issue changes location/priority | Model derives them on create/issue change; expected for catalog selection |
| Transfer location reverts | Confirm issue-change guard still exists; ordinary saving must not always overwrite location |
| Cannot resolve | Admin/current head/assigned HR eligibility and visible ticket scope |
| Cannot reopen | Creator only and current Resolved state; missing named status is a setup problem |
| Discussion locked | Resolved state; creator must reopen before commenting |
| Overdue seems inconsistent near boundary | Open uses three elapsed days; resolved uses integer diffInHours >72; original creation time persists on reopen |
| Concurrent transfer conflict | Parent/pending row lock, transaction, stale state and destination checks; do not insert another pending row manually |

There is no automatic escalation scheduler. Reassignment, overdue and resolution are different mechanisms. See [functional specification](04-FUNCTIONAL-SPECIFICATIONS.md).

## Files, storage and avatars

1. Read the exact validation error: ticket/comment allowlist, max 20 files and 20 MB/file; avatar image max 2 MB; policy PDF max 20 MB.
2. Check upload_max_filesize, post_max_size, max_file_uploads, reverse-proxy body limits and temporary upload storage. Total request size may exceed server limits before per-file validation runs.
3. Verify private ticket storage exists/writable to web user; worker also needs policy/OCR access. A path returned by an adapter is not sufficient if the write failed; current AttachmentStorage checks actual result.
4. Check ticket scope, child association and file index for protected downloads; an inaccessible ticket intentionally appears missing.
5. Check public/storage link for avatars, not private ticket files. Confirm Apache deny rule/equivalent server rule for legacy attachments.
6. For suspected orphans/missing files, use read-only attachments:reconcile and review failures. Uncertain state intentionally retains staged files. Never delete the directory to resolve a warning.

```powershell
php artisan attachments:reconcile
php artisan attachments:privatize --dry-run
php artisan users:sync-avatars --dry-run
```

The last command can display identity information; restrict its output. Mutation/cleanup requires [maintenance prerequisites](09-DEPLOYMENT-AND-MAINTENANCE.md).

## Policy upload, OCR and queues

| State / symptom | Diagnostic order |
| --- | --- |
| Pending with zero chunks | Confirm saved/queued marker, policy_jobs row and continuous correct-connection worker; HTTP upload success is not extraction |
| Pending with dispatch warning | Restore dedicated database queue/schema, then re-run in UI; do not reset all records |
| Processing too long | Inspect worker/child process, timeout, reservation, logs and failed_jobs; confirm old process stopped before re-running |
| needs_review | Incomplete OCR, missing confidence or average below threshold; improve scan/languages/tool setup and re-run; toggle cannot approve it |
| Failed extraction | File readability, encrypted/corrupt PDF, insufficient text, OCR tools/languages and useful minimum chunk size |
| Replacement/re-run rejected | Processing generation is protected; wait or perform coordinated recovery |
| Deleted while job queued/running | Current job skips missing/stale generations; deletion is not blocked by a processing-state check, so investigate concurrency and cleanup rather than assuming a lock |

```powershell
tesseract --version
tesseract --list-langs
pdftoppm -v
php artisan hr:test-ocr
php artisan queue:failed
```

Use the continuous listener/worker commands in [deployment guide](09-DEPLOYMENT-AND-MAINTENANCE.md). Do not start one worker per file. Check executable paths under the service account, not only the interactive terminal. A failed queue-payload retry alone does not necessarily establish a new pending generation.

## Assistant and notifications

- **No policy found:** verify active flag AND active status, chunks and meaningful keyword matching. Effective date is metadata; retrieval does not enforce an effective-date eligibility filter.
- **503 provider unavailable:** inspect configured selected provider/key/model, network/timeout and sanitized status logs. Do not paste keys or full provider bodies into diagnostics. Gemini/OpenRouter configuration does not automatically activate failover.
- **Conversation missing:** ownership is enforced; concurrent deletion can produce 409. Start a new conversation rather than trying someone else's ID.
- **429:** wait for the named request budget; fix abusive/repeated client behavior rather than removing limits.
- **No ticket email:** ticket notification channel is database only. For missing database entries, review candidate/role rules, actor exclusion and preferences. Password-recovery email is a separate package/mail path and was not exercised.

## Reports and frontend

- Check report permissions before query/rendering. Current seeded reports are administrator-only; delegated access can be broad.
- Invalid custom dashboard/report range falls back to month with feedback. PDF POST requires ordered start/end dates. ARTA >2000 rows is intentionally refused.
- Confirm required named statuses, department ID 1 assumptions, timestamp timezone (source defaults UTC) and deployed SQL dialect. Narrow PDFs after memory/render errors.
- Inspect browser console/network for CDN/library availability, published assets, base URL and Livewire message/upload endpoints. Source Livewire configuration uses current-request origin/base path; clear only the relevant stale cache after an approved release.
- Survey service worker deliberately caches only allowlisted public resources and eligible clean survey HTML. Reconnect to submit and refresh CSRF; no offline outbox exists. Root-relative paths can fail under subdirectories. Do not force update activation in the middle of a respondent's session.

## Support checklist

Record what was observed, expected behavior, safe reproduction, source revision/dirty state and checks actually run. Separate hypotheses from confirmed failures. Use the [test guide](12-TESTING-AND-QA.md) and [gaps](DOCUMENTATION-GAPS.md) before declaring a fix or production readiness.
