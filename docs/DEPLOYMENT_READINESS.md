# Deployment and attachment operations

Status: implementation, local checks, and specifically authorized SQL Server data probes. Follow-up items 2 (deployment), 3 (complete deployed-engine verification), and 6 (operational reconciliation) remain open until the remaining checks and authorized live actions below are completed. No application database migration, live attachment move/deletion, permission grant, hosting change, or deployment was performed. Only uniquely tagged test records were created/updated/deleted under the user's subsequent explicit authorization.

## Evidence and prerequisites

- This workspace uses PHP 7.4.14, Laravel 8, and `.env` selects `sqlsrv` at a local host. `pdo_sqlsrv` is installed; there is no cached configuration. Read-only checks reached SQL Server `16.0.1000.6`; authorized probes verified `employeecare` on `DESKTOP-AQSB8I2:1433`. This identifies the configured local engine, not an unprovided remote deployment. Ordinary tests use SQLite `:memory:`.
- The three October 7 migrations are already recorded on that local connection. Both attachment columns are currently nullable `VARCHAR(MAX)`; both survey IDs are nullable `uniqueidentifier` with unique `IS NOT NULL` filtered indexes. These metadata checks do not retrospectively certify migration behavior or preservation of historical values.
- The local Laragon Apache configuration serves `EmployeeCare/public`, enables `mod_rewrite`, and allows `.htaccess`. `public/storage` is a junction to `storage/app/public`; the private disk is outside that directory at `storage/app/ticket-attachments`. A local HTTP probe could not reach the server. No deployed virtual host, aliases, proxy rules, or reachable deployment endpoint were supplied.
- No `ENGINE_TEST_*` connection variables were available. A DBA must provide an empty, disposable database on the deployed engine/version, with comparable collation, compatibility level, isolation mode, and TLS requirements. Do not reuse application credentials or an application database.
- Before release: obtain deployment authorization, a coordinated upload/submission pause, tested database and attachment backups, adequate private-disk space, and confirmation of the deployed web server and URL base. Private/public disk directories must already exist, be separate, and be readable; private storage must be writable by the application account. Read-only commands refuse unavailable roots rather than creating them.

## Ordered release checklist (live actions require authorization)

1. **Verify the target and staging results.** Confirm the effective deployed connection including any config cache or environment override without displaying credentials. Run the isolated engine suite below against a matching disposable database. Inspect `php artisan migrate:status` on the target and confirm the existing ticket, comment, survey, ARTA, and permission schemas and prior migrations are present. Rehearse the release on a staging copy with representative legacy data. Do not treat SQLite success as engine certification.
2. **Back up and pause writers.** Record the application release, migration status, database backup, both attachment trees, and authorized permission grants. Pause kiosk POSTs, HTTP/Livewire uploads, workers, scheduled tasks, imports, and other direct writers across every application instance. `php artisan down` alone does not pause workers or other services. Keep kiosks between respondents before refreshing them.
3. **Verify web access restrictions before reopening traffic.** Keep document root at `public/`; do not expose the project root or private disk through an alias/junction. Apache's committed rule denies `storage/attachments` case-insensitively, but requires `mod_rewrite` and effective `AllowOverride`. For Nginx, configure an equivalent deny rule for the actual base path (for a domain-root app: `location ^~ /storage/attachments/ { return 403; }`). Also inspect case/encoded-path handling and all aliases. Test anonymous requests to a known legacy attachment URL: expect 403/404, never attachment bytes. Verify normal public avatars still work, authorized attachment routes work, and an unrelated/inactive user cannot download. Do not create a live probe file without approval.
4. **Apply only reviewed forward migrations.** The three files are:

   - `2026_10_07_100000_widen_ticket_attachment_columns.php`: retains existing path-array JSON and NULLs. The SQL Server branch is corrected to native `NVARCHAR(MAX)`; other engines retain the existing DBAL path. Installed DBAL maps `text` to `VARCHAR(MAX)`, creating a confirmed Unicode-conversion risk. Correcting this historical branch is necessary for deployments where it is still pending; it does not rerun it on an already-migrated database. `down()` intentionally does not narrow columns.
   - `2026_10_07_110000_add_survey_submission_ids.php`: nullable IDs preserve legacy rows. MySQL has nullable unique indexes; SQL Server uses unique `IS NOT NULL` filtered indexes and `uniqueidentifier`. Verify actual column types/index definitions after migration. SQL Server filtered uniqueness avoids rejecting multiple legacy NULL rows ([Microsoft index guide](https://learn.microsoft.com/en-us/sql/relational-databases/sql-server-index-design-guide)).
   - `2026_10_07_120000_add_explicit_ticket_assignment_permission.php`: idempotently creates the `web` permission and adds it to the existing admin role. It does not reset any role's grants; `down()` preserves subsequent explicit grants.

   A new forward correction, `2026_10_14_100000_preserve_unicode_ticket_attachment_columns.php`, changes the two SQL Server columns to nullable `NVARCHAR(MAX)` for installations that already ran the original DBAL-based widening. It is a no-op on other engines. Unicode types avoid code-page conversion issues ([Microsoft Unicode guidance](https://learn.microsoft.com/en-us/sql/relational-databases/collations/write-international-transact-sql-statements?view=sql-server-ver15)). This conversion cannot reconstruct characters already lost by an earlier conversion; investigate historical references against backups.

   With prior migrations verified, run only pending reviewed files through `php artisan migrate --force --path=database/migrations/<filename>` in timestamp order, including the new forward correction. On the inspected local connection the original three are already applied: do not rerun them or delete migration-history rows. The new correction remains pending. No migration command was run here. Do not use `migrate:fresh`, `db:wipe`, or a production `--pretend` permission migration: pretending SQL does not guarantee that permission-cache side effects are read-only.
5. **Privatize legacy attachments.** Run `php artisan attachments:privatize --dry-run`; add `--show-paths` only in restricted operator output. Dry runs read/hash every public attachment and verify existing private copies without writing/deleting. A conflict, unsafe path/link, missing root, or read failure returns exit 1. After reviewing the preview and backups, run `php artisan attachments:privatize` while writers remain paused. It preflights the complete source listing, copies missing files, checks SHA-256 and source stability, and removes an original only after verification. Record `scanned`, `moved`, failure phase, and `path_id`. Files outside `attachments/` are untouched. Reruns safely finish verified copies whose public originals remain. A partial/different private copy is never overwritten: retain both, investigate against the backup, and authorize repair separately before rerunning. Stop on any failure; do not reopen traffic with an unresolved access restriction or conflict.
6. **Grant assignment only to approved delegates.** Use the existing permission-management interface to add `ticket.assign` to specific approved HR accounts or a reviewed role. Preserve all other permissions; never rerun `RolesAndPermissionsSeeder` to accomplish this. Verify admin retains existing permissions, ordinary HR lacks this permission unless delegated, and delegated HR still obeys department/division restrictions. Refresh the permission cache with `php artisan permission:cache-reset` if needed.
7. **Build and refresh caches.** Use the committed lockfile: `npm ci` then `npm run production` as part of the approved release. The safe local check is `node tests/Support/production-build-check.cjs`; it compiles the real Mix configuration into a temporary directory and removes only its own output. Survey JS/CSS, service worker, manifest, and offline HTML are standalone `public/` files and must also ship; Mix does not generate them. Clear config, route, and view caches using `php artisan config:clear`, `php artisan route:clear`, and `php artisan view:clear`; rebuild only caches used by the established release process and restart long-running workers. Do not flush unrelated application cache/session stores.
8. **Refresh and validate kiosks before accepting respondents.** Current survey assets expect domain-root `/survey`, `/sw-survey.js`, and shell paths. A subdirectory deployment needs separate PWA path work before release; Livewire's subdirectory support does not establish PWA support. Service-worker cache is `survey-cache-v8`; installation waits for all required shell assets. A waiting worker activates only at welcome/completed QR state, never during a POST. Activation claims clients and removes older survey caches, but does not replace JavaScript already loaded in a page. Refresh each idle kiosk online and confirm the new script is loaded and POSTs include `submission_id`. For an older client unable to signal activation, close all survey tabs/windows and reopen online after the response is complete. Verify shell requests are 200, controller/cache version, installation/fullscreen, offline viewing with submission blocked, reconnection/manual retry, same-UUID retry, and the next respondent's new UUID. Do not rely on cache activation alone to refresh an already-open form.
9. **Audit, reopen, and monitor.** Run the read-only reconciliation below with all writers paused and investigate missing references/conflicting copies. Resume traffic/workers only after smoke checks, migration verification, and operator sign-off. Monitor attachment compensation/reconciliation failures, survey validation/409/429 responses, and authorized download failures. Do not invoke cleanup as part of routine deployment.

### Release recovery

On migration/copy/build/client failure, keep writers paused and public attachment URLs blocked. Preserve the database backup, both file copies, reports, and current release. Do not blindly run `migrate:rollback`: widening is intentionally retained, permission grants are retained, and UUID rollback drops retry identities. Prefer a forward repair after diagnosing the specific phase. Reverting to an older client/server requires coordinated survey compatibility work; capture any post-backup submissions before an explicitly authorized database restore. If private storage is unavailable, restore it from backup or use the protected legacy fallback only while public URLs stay blocked. Never republish confidential attachments to recover downloads.

## Isolated deployed-engine suite

Run `php vendor/bin/phpunit -c phpunit.engine.xml --do-not-cache-result` in a separate process. The ordinary `phpunit.xml` remains SQLite-only; the engine suite skips if no engine is supplied and rejects incomplete/unsafe settings before connecting.

Set these process environment variables through a secure local mechanism, not committed files or chat:

| Variable | Required value |
| --- | --- |
| `ENGINE_TEST_DRIVER` | `sqlsrv` for the configured engine, or `mysql` when that is confirmed as deployed |
| `ENGINE_TEST_HOST`, `ENGINE_TEST_PORT` | Explicit test-only endpoint; no application fallback |
| `ENGINE_TEST_DATABASE` | DBA-created, completely empty `ec_engine_test_<8-32 lowercase letters/digits>` database |
| `ENGINE_TEST_USERNAME`, `ENGINE_TEST_PASSWORD` | Explicit test-only account restricted to this database; DDL/DML and complete schema-metadata visibility required |
| `ENGINE_TEST_CONFIRM` | Exact same isolated database name |
| `ENGINE_TEST_TRUST_CERTIFICATE` | Optional `1` only for an intentionally trusted staging certificate; default certificate validation stays enabled |

The harness rejects matching application/cached database names and inherited URLs, does not load the application `.env` as runtime configuration, disables config-cache reuse, removes other database connections, checks the actual connected database identity and empty schema, and claims a random ownership token. Separate worker processes must verify that token before writing. A naming convention alone is insufficient: the DBA must confirm this is disposable and restrict the test account's access. No database is created, dropped, wiped, or automatically reset; fixtures are left for inspection. Use a newly provisioned empty database for reruns and let the DBA dispose of the used test database separately.

Coverage: actual historical schemas plus the three original release migrations and new Unicode correction, preserved legacy arrays/NULL rows/unescaped Unicode and expanded attachment JSON, nullable UUID uniqueness, SQL Server filtered indexes, uppercase UUID retries, permission-grant preservation/idempotence, report parity, exact rating comparisons including malformed legacy casing/trailing spaces, date formatting/search and elapsed-time SQL, wildcard escaping, and genuine competing inserts for both survey models. Two PHP workers each finish the initial SELECT before a shared barrier releases either INSERT. Identical payloads must return the same row ID and persist one response; competing changed payloads must produce one save and one rejection, with later changed retries rejected. This exercises the real unique-constraint winner path, not a mocked exception.

Missing prerequisites for migration rehearsal: matching deployment collation/isolation confirmation, the isolated endpoint/database/account variables above, complete schema permissions, and a reachable staging server. `DeploymentEngineTest` was skipped, not passed. If a run fails, leave its synthetic database for investigation; never relax the isolation guard to get a passing test.

### Separately authorized existing-database data probes

The user specifically authorized test-record creation/modification/deletion in `employeecare` on `DESKTOP-AQSB8I2:1433`, excluding resets, existing-record deletion, schema changes, and live attachment cleanup. This exception uses a separate `AuthorizedEngineDataTest`, not the migration harness. Enable only for that authorization with `ENGINE_DATA_TEST_CONFIRM=employeecare@DESKTOP-AQSB8I2:1433`, then run:

```text
php vendor/bin/phpunit -c phpunit.engine.xml --do-not-cache-result --filter AuthorizedEngineDataTest
```

The probe fixes that exact endpoint, verifies server machine/database identity and authentication before writing, uses local credentials without displaying them, and supports the configured Windows integrated authentication. It creates one inactive synthetic account with a random `ec_engine_probe_<token>` username/email, owned synthetic ticket/comment records with JSON references but no actual files, and survey/ARTA fixtures. Existing issue/status IDs are read only. SQL deletes target only that account/tag and tracked UUIDs; they run in a cleanup transaction without Eloquent ticket-deletion events. No migration, seeder, schema reset/drop/truncation, permission change, or live disk mutation is on this execution path. Workers validate the inactive fixture owner and their payload ownership before executing the same real SELECT/INSERT barrier used by the isolated suite.

Result: **1 test / 45 assertions passed** against SQL Server `16.0.1000.6`, including current column/index metadata, oversized attachment JSON and NULLs, nullable UUID inserts, case/trailing-space rating comparisons, date searches/elapsed expressions, and genuine same-UUID concurrent submissions/retries for both models. Fixture cleanup completed for `ec_engine_probe_e14f73036ba8fb36f36f44685e97f8e2`. No attachment or schema was changed. This verifies current-engine data behavior; it does not verify running the original/new migrations with legacy data. If a process crash/database outage prevents cleanup, retain the fixture tag and tracked UUIDs and have an operator review narrowly scoped record cleanup; never delete all accounts/responses matching a generic prefix.

## Attachment reconciliation

Default: `php artisan attachments:reconcile` is read-only. Optional `--min-age-hours=72` can increase the age threshold; values below 48 hours are rejected. Paths are SHA-256 fingerprints by default; `--show-paths` reveals stored filenames only for a restricted operator. No ticket text, owner identity, file contents, or credentials are reported.

| Output | Operator action |
| --- | --- |
| `missing-reference` | Check both configured disks and the backup; restore the referenced file only after verifying its ownership/path. Do not silently remove the database reference. |
| `retained-reference` | Keep the file; any live ticket/comment reference protects both private and legacy copies. |
| `retained-recent` | Keep it; it may be an upload whose record is not committed yet. |
| `candidate-unreferenced` | Investigate; old age and absence from a complete scan are eligibility checks, not proof of authorization to delete. |
| `manual-review-legacy-orphan` | Keep it; reconciliation never deletes public files. Resolve/migrate through the authorized privatization procedure. |
| `manual-review-conflicting-copy` | Retain both copies and compare restricted backup/source evidence before deciding which bytes are correct. |
| `retained-changed` | Keep it; a new reference or changed file invalidated the original candidate snapshot. |
| Incomplete scan / nonzero exit | Keep all remaining files. Use the reported phase; malformed-reference errors identify only table/row ID. Fix connectivity, unreadable storage, malformed references, or unsafe links through separately reviewed work and rerun the audit. |

Raw `tickets.attachments` and `ticket_comments.attachment` must be NULL or JSON lists of safe `attachments/...` strings. Empty/invalid JSON, objects, non-string values, traversal, backslashes, absolute/drive paths, controls, Windows reserved names, unsafe links, unavailable/overlapping roots, and incomplete scans block cleanup. Legitimate legacy spaces and Unicode filenames are supported. All rows and both disks are scanned before the first deletion; the local scans hash contents and reject unreadable/changed files. Manual-review diagnostics expose fixed reasons, row IDs, disk names and fingerprints; arbitrary adapter/driver exception text is suppressed.

**Cleanup is a separate authorized operation:** back up candidate bytes and the database, enter a coordinated maintenance window, and pause every upload/database/file writer on every node. Then review a fresh audit. Only after approval run:

```text
php artisan attachments:reconcile --cleanup --uploads-paused --min-age-hours=48
```

`--uploads-paused` is an operator attestation, not a switch that stops writers. Immediately before each private-orphan deletion the command rescans all raw references and rechecks the path, link containment, age snapshot, size, timestamp, and SHA-256. If the database is unavailable, references malformed, or a file changed, it retains that file; errors stop further deletions with a nonzero exit and verified deletion count. Successful deletions are logged by path fingerprint. The database and filesystem do not share a transaction: pausing all writers is mandatory to close the interval between the fresh check and unlink. Cleanup is irreversible without a backup; restore mistakenly deleted bytes to the same private path from the approved backup. Never run cleanup against live uploads or an uncertain database state.

### Investigating compensation logs

1. Restrict access to application logs: older compensation entries contain `path`/`paths` and exception details. Do not paste complete logs into shared reports. Look for `[Attachments] Reference check failed; staged files retained for reconciliation.`, `Could not remove a staged file.`, `Cleanup failed.`, `Could not delete a file.`, and `File deletion failed.` These indicate work to investigate, not deletion instructions; some existing entries originate from regression fault injection.
2. Confirm database health and transaction completion on the correct instance with the operator/DBA. An unavailable database or unresolved active transaction means retain the files. Search both raw reference columns, including comments and retained/shared paths; Eloquent casts can obscure malformed JSON.
3. Run the read-only command. Match a log path to `path_id` using `hash('sha256', $path)` in a restricted local tool, or use `--show-paths` locally. Check the exact relative path and both disks; do not interpret a log path as a filesystem command. Investigate every malformed-reference/scan failure before considering cleanup.
4. For referenced/missing/conflicting/recent files, preserve or restore bytes as appropriate. For old private candidates, follow the separate backup/maintenance/authorization procedure above. Public candidates stay retained until reviewed privatization. Record evidence, backup location, fingerprints, operator approval, command exit status, and verified deletions in the incident record. No automatic schedule is installed.

## Local verification

- Final full PHP regression run: **263 tests / 1,759 assertions passed** on PHP 7.4.14, SQLite `:memory:` and fake storage. Targeted attachment/engine-safety/medium regressions also passed (56 tests / 279 assertions before the final guard addition).
- JavaScript: 30 tests passed, including actual service-worker installation, safe activation, cache isolation, retries, and UUID behavior.
- Production Mix build passed with temporary output; existing published assets were preserved. Installed Laravel Mix 6.0.49 / Webpack 5.105.0 match the locked build resolution. No dependency update was performed.
- Authorized SQL Server data probe: 1 test / 45 assertions passed; owned fixtures cleaned. Isolated engine migration rehearsal remains skipped; no engine migration was executed.
- Read-only reconciliation on the configured application connection completed: 3 private referenced files retained, 14 legacy public orphans flagged for manual review, no missing referenced files or private deletion candidates reported, and `deleted=0`. These are a point-in-time audit, not a cleanup authorization.
- Real legacy privatization preview passed: 14 files integrity-scanned, `moved=0`; no public original or private file was changed.
- Local legacy URL probe: HTTP status 000 (server unreachable), so runtime HTTP restrictions and deployed kiosk/browser behavior remain unverified.
- All 16 added/modified PHP files passed PHP 7.4 syntax checks; final regression loading also verified the final guard addition. `git diff --check` passed. Unconfigured engine-suite invocation correctly skipped its two opt-in tests; the separately enabled, authorized data-only probe passed as recorded above.

## Changed files

| Area | Files |
| --- | --- |
| Attachment operations | `app/Console/Commands/PrivatizeTicketAttachments.php`, `app/Console/Commands/ReconcileTicketAttachments.php`, `app/Services/AttachmentInventory.php`, `app/Exceptions/AttachmentInspectionException.php` |
| Unicode safety | `database/migrations/2026_10_07_100000_widen_ticket_attachment_columns.php`, `database/migrations/2026_10_14_100000_preserve_unicode_ticket_attachment_columns.php` |
| Engine probes | `phpunit.engine.xml`, `tests/Engine/DeploymentEngineTest.php`, `tests/Engine/AuthorizedEngineDataTest.php`, `tests/Engine/submission-worker.php`, `tests/Support/EngineDatabase.php`, `tests/Support/AuthorizedEngineDatabase.php`, `tests/Support/EngineSubmissionRace.php`, `tests/Support/EngineReportParity.php` |
| Local verification | `tests/Feature/AttachmentOperationsTest.php`, `tests/Unit/EngineDatabaseSafetyTest.php`, `tests/Support/IsolatedDatabase.php`, `tests/Support/production-build-check.cjs` |
| Runbook | `docs/DEPLOYMENT_READINESS.md`, `docs/MEDIUM_FIXES.md` |

Existing untracked `AGENTS.md` was preserved. Composer dependencies, deployed configuration, role grants, published assets, and unrelated application behavior were not changed.
