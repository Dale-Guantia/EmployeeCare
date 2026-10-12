# Requested medium finding fixes

Scope: C8/C10/C11/D2/D4/D5/D6/D7/E2/E3/E5/F1/F2/H3/J1/J2. Target: Laravel 8 and PHP 7.4.

This inventory describes changes made for this request, compared with the working files at the start of the task. Existing uncommitted work from the earlier CRITICAL/HIGH remediation and notification-settings work was preserved. Automatic assignment and transfer rules were not changed by D4.

## Finding outcomes

| ID | Outcome | Result and verification |
| --- | --- | --- |
| C8 | Implemented; deployment migration required | New files use private storage; ticket/comment download routes authorize visibility and record ownership. Cross-ticket, mismatched comment, anonymous, inactive-account, and invalid-index tests pass. The legacy copy command verifies contents before removing public originals. |
| C10 | Fixed | Browser filenames use textContent. The Node test executes the actual preview function with HTML-significant filenames. |
| C11 | Fixed | Both public POST routes share limits of 30 requests/minute/session and 300 requests/minute/actual network peer. CSRF remains enabled. Tests prove a normal kiosk session works and its 31st request is blocked without saving another response. Forwarded headers do not supply the peer limit key. |
| D2 | Fixed | The existing notification-settings URI/name now invokes postNotificationsForm. Tests verify only the authenticated account changes. |
| D4 | Fixed for manual assignment only | Admin: any active eligible HR staff. Department head: active HR staff in the actor's department. Division head: active HR staff in the actor's division. HR staff: denied unless explicitly granted ticket.assign; delegated HR keeps the existing ticket department/division boundary. Candidate divisions must belong to their department. Quick actions, full ticket updates, and dropdowns share these rules. |
| D5 | Fixed | Ticket branch selection, required issue/message fields, types, existing foreign keys, text limits, and department/division consistency are checked. The standard issue branch derives location from its issue; the custom branch clears issue_id. Issue and user forms reject inconsistent locations. |
| D6 | Fixed | ARTA requires at least one actual SQD answer, accepts only its six displayed choices, and preserves optional demographics and CC1=4 normalization. Empty/unknown/oversized answers are rejected. |
| D7 | Fixed | Only resolved tickets can reopen. Moving to another unresolved status clears resolved_at and resolved_by as well as stale reopened_at. Valid reopen and invalid transitions are tested. |
| E2 | Fixed | Ticket/comment attachment JSON columns are widened to text. Uploads are capped at 20 files and filename fragments are bounded. Existing path-array storage is retained. |
| E3 | Fixed | The existing unique reference constraint decides collisions. Creation retries only that constraint, at most five times, retaining the TKT-six-hex format. Tests force a collision, exhaustion, and an unrelated FK failure. |
| E5 | Fixed | Each response has a unique submission UUID. Identical retries return the saved response; different UUIDs retain identical answers from different clients. Reusing an ID for changed or removed answers is rejected. The unique-winner exception branch is fault-injection tested; it is not a live concurrent production-engine test. |
| F1 | Fixed with verified parity | Ticket widgets and staff duration reads use bounded batches; customer-survey ratings use grouped SQL. Response tables search/order across all history through protected server-side paging. ARTA PDFs remain complete within the configured 2,000-response bound; oversized ranges are rejected with guidance, never silently truncated. |
| F2 | Fixed | Event candidate queries replace all-user loads and retain active-recipient preferences and multi-role precedence. Ordinary delivery remains synchronous. Mark-all-read uses one account-scoped UPDATE. Query and recipient-matrix tests pass. |
| H3 | Partially fixed | package-lock.json, a Mix-compatible Webpack pin, and the missing declared CSS entry produce a verified frozen installation/build. The abandoned PHP dependencies remain under the stated compatibility constraints; details below. |
| J1 | Fixed | Ordinary comment notifications intersect participants with current ticket visibility. A former head after transfer receives none; current permitted recipients still receive notifications. Transfer-result notifications retain their existing rules. |
| J2 | Fixed | False/exceptional writes are visible, partial batches are cleaned, record writes are transactional, and only newly staged unreferenced files are compensated. Nested rollback, retained-file safety, and after-commit failure paths are tested. Unverifiable commit state retains files and logs their paths for reconciliation. |

## F1 original implementation and parity

The original collection widget methods remain in TicketReportWidgets. The original SurveyReportsController calculator was kept in production until ReportQueryParityTest passed, then preserved as tests/Support/LegacySurveyReports.php.

Before controller replacement, the parity run passed **2 tests / 23 assertions**. Fixtures included 553 tickets, 507 survey responses, multiple chunk boundaries, day/week/month and empty windows, exact resolution/SLA boundaries, negative durations, reopened records, missing locations, request statuses, and zero-activity staff. Later parity coverage also includes malformed legacy rating casing/trailing spaces. No per-chunk rounded average is used: integer minutes are summed first, then the final average is rounded once.

The response-table implementation retains full-history search, ordering, and paging instead of filtering only a displayed page. Tests cover rows beyond the first page, quoted search, literal wildcard characters, N/A values, authorization, and export rejection.

## H3 remaining compatibility limit

Swiftmailer remains a direct dependency of the installed Laravel 8 framework. Its maintained successor uses different integration APIs; replacing the Composer package alone would break Laravel 8 mail behavior. See the [Swiftmailer documentation](https://swiftmailer.symfony.com/docs/introduction.html) and the installed vendor/laravel/framework/composer.json requirement.

The installed fruitcake/laravel-cors integration is abandoned. Its documented built-in replacement was introduced in Laravel 9.2 and is absent from this Laravel 8 installation. See the [maintainer's migration instructions](https://github.com/fruitcake/laravel-cors#note-for-users-upgrading-to-laravel-9-10-or-higher).

Neither composer.json nor composer.lock was changed. H3 is **not fully closed**: migrating these integrations needs a separately reviewed compatible backport/fork or an authorized runtime/framework migration. No advisory suppression or unsupported Composer package swap was introduced. npm's existing legacy transitive deprecation warnings also remain; a lockfile establishes reproducibility, not vulnerability clearance.

## Verification

Verification: **210 PHP tests / 1,510 assertions passed**, **30 JavaScript tests passed**, and the final locked **npm ci and production build passed**. The private-upload regressions were also rerun after aligning the upload field's disk metadata with its private storage implementation.

Commands: `php vendor/bin/phpunit --do-not-cache-result`, `node --test tests/JavaScript/*.cjs`, `npm.cmd ci --ignore-scripts --no-audit --no-fund`, and `npm.cmd run production -- --mix-config=<temporary-output-config>`. The temporary build config retained the application's existing entry points and compilation steps.

- All changed PHP files pass syntax checks on **PHP 7.4.14**.
- All database tests use **SQLite :memory:** with fake storage, offline outbound HTTP, and test mail settings. The application database and live attachment files were not migrated.
- SQL Server-specific filtered indexes, text alteration, GUID behavior, binary rating comparisons, and date expressions require a staging run on the deployed database engine; SQLite passing is not production-engine certification.
- The production asset build uses the actual application JS/CSS entry points with output redirected to a temporary directory, leaving the existing published assets intact.

## Deployment

1. Back up the database and legacy attachments; take a coordinated release window so old clients cannot POST without UUIDs.
2. Deploy the three new forward migrations and run `php artisan migrate --force`. Widening retains existing attachment arrays, submission IDs are nullable for old rows, and the permission migration preserves existing grants. Do not rerun the role seeder on an existing deployment to register this permission.
3. Ensure storage/app/ticket-attachments is writable by the application and is not publicly served. Before accepting traffic, block the old public attachment directory. The committed Apache rule does that; Nginx needs an equivalent `location ^~ /storage/attachments/ { return 403; }` (adjust for any application URL prefix).
4. Preview with `php artisan attachments:privatize --dry-run`, then run `php artisan attachments:privatize`. It moves all files below the old public attachments directory, including orphaned confidential files, and leaves other public assets alone. Stop and resolve any reported copy/checksum/deletion failure. Protected routes can read the legacy disk during this migration, but no public HTTP compatibility link is provided.
5. Use `npm ci` and the existing production build command for releases. Clear the application's normal view/config/route caches as required by the release process; kiosks receive service-worker cache v8. Cached old forms must refresh before submitting.
6. Explicitly grant ticket.assign only to HR staff authorized to delegate manual assignment. This permission does not change automatic assignment or transfer acceptance.
7. Monitor upload compensation logs. When commit state cannot be verified because the database is unavailable, reconcile the logged paths against persisted ticket/comment references before deleting anything. Recovery from process crashes or a failed storage delete still requires operational reconciliation.
8. The ARTA PDF bound is config/reports.php:arta_pdf_max_rows; use narrower date ranges for larger exports. Other report summaries still account for the complete requested history.

## Every changed file

| File | Why it changed |
| --- | --- |
| [app/Console/Commands/PrivatizeTicketAttachments.php](../app/Console/Commands/PrivatizeTicketAttachments.php) | C8: move legacy public attachments to the private disk; verify SHA-256 before deleting the public original; support dry runs and stop on conflicts. |
| [app/Http/Controllers/Admin/ArtaSurveyReportsController.php](../app/Http/Controllers/Admin/ArtaSurveyReportsController.php) | F1: page report details, expose the protected DataTables endpoint, and reject oversized PDF exports instead of truncating them. |
| [app/Http/Controllers/Admin/DashboardController.php](../app/Http/Controllers/Admin/DashboardController.php) | F1: replace the full-history ticket collection with the verified bounded report service. |
| [app/Http/Controllers/Admin/NotificationController.php](../app/Http/Controllers/Admin/NotificationController.php) | F2: mark the current account's unread notifications with one scoped UPDATE. |
| [app/Http/Controllers/Admin/ReportsController.php](../app/Http/Controllers/Admin/ReportsController.php) | F1: use bounded widget reads and exact staff-resolution averages. |
| [app/Http/Controllers/Admin/SurveyReportsController.php](../app/Http/Controllers/Admin/SurveyReportsController.php) | F1: use grouped survey statistics and a protected endpoint for complete-history paging/search. |
| [app/Http/Controllers/Admin/TicketAttachmentController.php](../app/Http/Controllers/Admin/TicketAttachmentController.php) | C8: authorize ticket and comment downloads through active-user ticket visibility and verify file association. |
| [app/Http/Controllers/Admin/TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | C8/D4/D7: private attachment links, matching assignment validation/dropdowns, and resolved-only reopen checks. |
| [app/Http/Controllers/Admin/UserCrudController.php](../app/Http/Controllers/Admin/UserCrudController.php) | D5: reject department/division mismatches while retaining nullable user locations and existing profile validation. |
| [app/Http/Controllers/ArtaController.php](../app/Http/Controllers/ArtaController.php) | C11/D6/E5: validate actual SQD choices and a non-empty response; preserve CC normalization; save idempotently. |
| [app/Http/Controllers/SurveyController.php](../app/Http/Controllers/SurveyController.php) | C11/E5: require a submission UUID and replace the ten-second content deduplication with idempotent persistence. |
| [app/Http/Livewire/TicketChat.php](../app/Http/Livewire/TicketChat.php) | C8/E2/J2: private uploads, attachment-count validation, transactional comment writes, visible failures, and safe compensation. |
| [app/Http/Requests/IssueRequest.php](../app/Http/Requests/IssueRequest.php) | D5: enforce integer foreign keys and the department/division relationship. |
| [app/Http/Requests/TicketRequest.php](../app/Http/Requests/TicketRequest.php) | D4/D5/E2: validate assignment eligibility, required form branches, text limits, organizational consistency, and attachment count. |
| [app/Models/ArtaSurvey.php](../app/Models/ArtaSurvey.php) | E5: permit the validated submission ID in mass assignment. |
| [app/Models/Survey.php](../app/Models/Survey.php) | E5: permit the validated submission ID in mass assignment. |
| [app/Models/Ticket.php](../app/Models/Ticket.php) | C8/D7/E3/J2: private staged attachments, transactional bounded reference retries, resolution cleanup, and compensation that preserves committed references. |
| [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) | J2: keep one transaction-file tracker per application instance. |
| [app/Providers/RouteServiceProvider.php](../app/Providers/RouteServiceProvider.php) | C11: shared session/actual-network-peer submission limits for both public surveys. |
| [app/Services/AttachmentStorage.php](../app/Services/AttachmentStorage.php) | C8/E2/J2: private disk helpers, bounded filenames, batch storage failure handling, authorized URLs, and reference-aware compensation. |
| [app/Services/AttachmentTransactions.php](../app/Services/AttachmentTransactions.php) | J2: clean up uploads when enclosing transactions roll back, including nested savepoints. |
| [app/Services/SurveyReportQueries.php](../app/Services/SurveyReportQueries.php) | F1: grouped ratings with legacy staff inclusion, zero activity, totals, and exact string comparison semantics. |
| [app/Services/SurveyResponseTable.php](../app/Services/SurveyResponseTable.php) | F1: bounded DataTables reads with complete-history search/order, literal wildcard escaping, and text-safe response data. |
| [app/Services/SurveySubmission.php](../app/Services/SurveySubmission.php) | E5: idempotent UUID persistence, unique-constraint race handling, GUID case tolerance, and changed-payload rejection. |
| [app/Services/TicketAssignmentRules.php](../app/Services/TicketAssignmentRules.php) | D4 only: share active HR eligibility and actor-location rules between validation and dropdowns. |
| [app/Services/TicketNotificationService.php](../app/Services/TicketNotificationService.php) | F2/J1: select event candidates in SQL, retain recipient precedence/preferences, select distinct participants, and check current comment visibility. |
| [app/Services/TicketReportQueries.php](../app/Services/TicketReportQueries.php) | F1: read tickets in batches of 500 and preserve original widget and Carbon averaging results. |
| [config/filesystems.php](../config/filesystems.php) | C8: define a private local disk for ticket/comment attachments. |
| [config/reports.php](../config/reports.php) | F1: bound complete ARTA detail PDF rendering to 2,000 responses by default. |
| [database/migrations/2026_10_07_100000_widen_ticket_attachment_columns.php](../database/migrations/2026_10_07_100000_widen_ticket_attachment_columns.php) | E2: widen both attachment-array columns without truncating existing values; intentionally avoid unsafe narrowing on rollback. |
| [database/migrations/2026_10_07_110000_add_survey_submission_ids.php](../database/migrations/2026_10_07_110000_add_survey_submission_ids.php) | E5: add nullable UUIDs for legacy compatibility and unique indexes; use filtered uniqueness for SQL Server's legacy NULL rows. |
| [database/migrations/2026_10_07_120000_add_explicit_ticket_assignment_permission.php](../database/migrations/2026_10_07_120000_add_explicit_ticket_assignment_permission.php) | D4: register ticket.assign on existing deployments without resetting role grants. |
| [database/seeders/RolesAndPermissionsSeeder.php](../database/seeders/RolesAndPermissionsSeeder.php) | D4: register ticket.assign on fresh installations; HR staff receive no default grant. |
| [package.json](../package.json) | H3: pin Webpack 5.105.0 because the current unrestricted resolution breaks Laravel Mix 6. |
| [package-lock.json](../package-lock.json) | H3: lock the validated frontend dependency resolution for npm ci. |
| [public/.htaccess](../public/.htaccess) | C8: deny direct HTTP access to legacy public ticket attachments on Apache. |
| [public/survey-assets/js/survey.js](../public/survey-assets/js/survey.js) | E5: create one UUID per response, retain it for manual retries, and clear it for the next client. |
| [public/sw-survey.js](../public/sw-survey.js) | E5: increment the cache version so deployed kiosks receive the submission-ID client code. |
| [resources/css/app.css](../resources/css/app.css) | H3: supply the missing CSS entry referenced by the existing Mix configuration so the validated build can complete. |
| [resources/views/admin/arta_survey_reports.blade.php](../resources/views/admin/arta_survey_reports.blade.php) | F1: enable server-side DataTables and escape returned cell text. |
| [resources/views/admin/survey_reports.blade.php](../resources/views/admin/survey_reports.blade.php) | F1: enable server-side response DataTables while retaining grouped staff statistics and escaped cell text. |
| [resources/views/arta_form.blade.php](../resources/views/arta_form.blade.php) | E5: send a submission UUID and retain it across validation errors. |
| [resources/views/livewire/ticket-chat.blade.php](../resources/views/livewire/ticket-chat.blade.php) | C8: use the authorized comment attachment route. |
| [resources/views/vendor/backpack/crud/fields/modern_upload.blade.php](../resources/views/vendor/backpack/crud/fields/modern_upload.blade.php) | C8/C10: authorized retained-file links and literal textContent for new filenames. |
| [routes/backpack/custom.php](../routes/backpack/custom.php) | C8/D2/F1: protected attachment/report routes and the notification-settings compatibility action. |
| [routes/web.php](../routes/web.php) | C11: attach the named submission limiter to both public survey POST routes. |
| [tests/Feature/AuditSecurityRegressionTest.php](../tests/Feature/AuditSecurityRegressionTest.php) | C8: update existing successful-upload assertions for private storage while preserving legacy retained-file coverage. |
| [tests/Feature/MediumFindingsRegressionTest.php](../tests/Feature/MediumFindingsRegressionTest.php) | Regression coverage for the requested security, validation, storage, notification, and bounded-report fixes. |
| [tests/Feature/ReportQueryParityTest.php](../tests/Feature/ReportQueryParityTest.php) | F1: compare original and optimized results before switching controllers and retain that comparison for future changes. |
| [tests/Feature/SurveyPwaTest.php](../tests/Feature/SurveyPwaTest.php) | E5: add submission-ID schema/fixtures and prove retry idempotency without discarding different clients' identical answers. |
| [tests/JavaScript/MediumSecurityTest.cjs](../tests/JavaScript/MediumSecurityTest.cjs) | C10/H3: execute the real filename preview function and verify direct dependency lock consistency. |
| [tests/JavaScript/SurveyPwaTest.cjs](../tests/JavaScript/SurveyPwaTest.cjs) | E5: simulate browser UUID generation/FormData and verify retry/reset behavior. |
| [tests/Support/IsolatedDatabase.php](../tests/Support/IsolatedDatabase.php) | C8/J2: fake the private attachment disk; all database tests remain on SQLite :memory:. |
| [tests/Support/LegacySurveyReports.php](../tests/Support/LegacySurveyReports.php) | F1: preserve the original survey calculator as the regression oracle after parity passed. |
| [docs/MEDIUM_FIXES.md](../docs/MEDIUM_FIXES.md) | Record finding outcomes, verification, deployment requirements, and every file changed by this request. |

## Follow-up: ticket discussion and initial Pending notification

The reported browser `net::ERR_CONNECTION_REFUSED` was consistent with Livewire using the configured APP_URL rather than the page's origin. Regression tests reproduced the mismatch: a page opened on a different host and port still rendered Livewire asset and message addresses for employeecare.test. Livewire now uses relative URLs with the current request's base path, retaining subdirectory installations and the browser's current host, protocol, and port. Signed temporary upload URLs also use that request's origin.

The discussion now displays attachment validation, temporary upload, storage, and comment persistence errors. Its Send controls no longer disable during ordinary polling, and Enter prevents an unintended form submission. The index-based attachment removal action has its own name, leaving Livewire's two-argument removeUpload protocol intact. Temporary upload validation now matches the existing 20MB per-file discussion limit. Permanent storage checks the actual write result because this installed Livewire version's TemporaryUploadedFile::storeAs returns a path even when a write returns false; failed writes preserve the draft and cannot create a comment referencing a missing file.

Automatic assignment saves a newly created ticket again to establish Pending. That nested save now suppresses only its status-change notification while initial creation is in progress. Creation and assignment notifications remain; later status changes, including on the same model instance, still notify. No assignment eligibility rules changed.

Verification: **97 relevant PHP tests / 1,007 assertions passed** on **PHP 7.4.14**, including signed message/upload requests at a domain root and in a subdirectory, persisted attachment bytes, visible errors, failed-write compensation, upload limits, authorization, notification preferences, reassignment, and initial-versus-subsequent status notifications. All eight changed PHP files pass syntax checks. Tests use SQLite :memory: and fake storage; they do not write to the application's database or live attachment directories.

For this follow-up, clear configuration and compiled views with `php artisan config:clear` and `php artisan view:clear`, then refresh the ticket page. No new migrations or asset build are required for these changes. Existing migration/deployment requirements above still apply to the earlier medium fixes.

### Every file changed in this follow-up

| File | Why it changed |
| --- | --- |
| [config/livewire.php](../config/livewire.php) | Use the page origin for assets/messages and match the existing 20MB attachment limit. |
| [resources/views/vendor/backpack/base/layouts/top_left.blade.php](../resources/views/vendor/backpack/base/layouts/top_left.blade.php) | Pass the request base path to Livewire, preserving subdirectory hosting. |
| [resources/views/livewire/ticket-chat.blade.php](../resources/views/livewire/ticket-chat.blade.php) | Show attachment/persistence errors, call the separate removal action, and target loading states to sending/uploading. |
| [app/Http/Livewire/TicketChat.php](../app/Http/Livewire/TicketChat.php) | Avoid overriding Livewire's upload-removal protocol; remove temporary files through its native implementation. |
| [app/Services/AttachmentStorage.php](../app/Services/AttachmentStorage.php) | Check the real streamed write result for both HTTP and Livewire uploads and close streams safely. |
| [app/Models/Ticket.php](../app/Models/Ticket.php) | Scope status-notification suppression to the initial creation call, including nested automatic assignment. |
| [tests/Feature/AuditSecurityRegressionTest.php](../tests/Feature/AuditSecurityRegressionTest.php) | Exercise actual signed message/upload endpoints at alternate origins and base paths; retain authorization coverage using the renamed removal action. |
| [tests/Feature/TicketChatAttachmentValidationTest.php](../tests/Feature/TicketChatAttachmentValidationTest.php) | Prove visible upload/persistence errors, temporary upload removal, failed-write safety, and the 20MB limit. |
| [tests/Feature/NotificationSettingsTest.php](../tests/Feature/NotificationSettingsTest.php) | Prove initial Pending does not send a status update and subsequent updates still do, while retaining creation/assignment notifications. |
| [tests/Feature/MediumFindingsRegressionTest.php](../tests/Feature/MediumFindingsRegressionTest.php) | Inject partial failure into the streamed write API and retain staged-file cleanup coverage. |
| [docs/MEDIUM_FIXES.md](../docs/MEDIUM_FIXES.md) | Record this follow-up's causes, behavior, verification, cache refresh, and complete file inventory. |
