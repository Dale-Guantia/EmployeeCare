# EmployeeCare Project Handover

Prepared: **2026-11-03 (Asia/Shanghai)**

Project folder: `C:\laragon\www\EmployeeCare`

Audience: the next developer, maintainer, or ChatGPT/Codex account continuing this project.

## 1. Read this first

**This folder contains the upgraded EmployeeCare Backpack application: PHP 8.5, Laravel 13, Backpack 7, Livewire 3, and CoreUI 4.** Older notes describing PHP 7.4 / Laravel 8 / Backpack 5 describe an earlier baseline. A Filament rebuild is a separate initiative, not the implementation in this folder.

This handover combines current source/lockfile inspection with dated repository reports and prior project context. Evidence is labeled as follows:

- **Verified now:** inspected in this folder on 2026-11-03, or a command actually completed during this documentation task.
- **Recorded result:** an earlier report says the work/check was completed; it was not repeated for this handover.
- **Not Verified:** live behavior, deployment state, or acceptance has not been established by this task.
- **Proposed:** follow-up work or a design plan, not a shipped feature.

Source and lockfiles take precedence over outdated prose. A passing test report does not establish deployment or production readiness. Migration files describe schema changes; their presence does not prove application to a database.

Repository snapshot: branch `main`, HEAD `9ded73c` (`Final`, commit date 2026-10-28), with **109 preexisting changed/untracked paths** before creating this document. Much of the upgrade and subsequent work is represented by the working tree. HEAD alone is insufficient to reproduce it. Preserve those files; do not reset or replace them. No application files, environment settings, databases, or workers were changed for this handover.

## 2. Purpose and implemented scope

EmployeeCare is an employee-concern/helpdesk system. Employees submit catalog or custom issues; organizational heads and HR staff handle assignment, discussion, transfers, resolution, and reopening. It also provides customer satisfaction and ARTA surveys, reports/PDFs, database notifications, account preferences, and an HR policy assistant.

Implemented source areas:

| Area | Behavior and primary entry points |
| --- | --- |
| Tickets | Submission, catalog/custom issues, references, routing, assignment, transfers, discussion, attachments, resolution, reopening. [Ticket controller](../app/Http/Controllers/Admin/TicketCrudController.php), [Ticket model](../app/Models/Ticket.php), [discussion component](../app/Http/Livewire/TicketChat.php). |
| Administration | Ten CRUD areas: tickets, issues, departments, divisions, priorities, statuses, users, roles, permissions, policy documents. [Panel routes](../routes/backpack/custom.php), [permission routes](../routes/backpack/permissionmanager.php). |
| Dashboard/reports | Scoped ticket dashboard, ticket reports, satisfaction reports, ARTA reports, and three PDF exports. [Dashboard controller](../app/Http/Controllers/Admin/DashboardController.php), [report queries](../app/Services/TicketReportQueries.php). |
| Public surveys | `/survey` and `/arta-form`, validation, throttling, submission identities/retry handling, satisfaction kiosk/PWA assets. [Web routes](../routes/web.php), [survey controller](../app/Http/Controllers/SurveyController.php), [ARTA controller](../app/Http/Controllers/ArtaController.php). |
| HR policy documents | PDF upload/replacement, native text extraction/OCR, queued ingestion, chunks, status polling, activation and re-ingestion. [Policy controller](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php), [ingestion service](../app/Services/PolicyIngestService.php). |
| HR assistant | Policy-grounded answers, user-owned conversations/history, feedback, bounded follow-up context and rate budgets. [Chat controller](../app/Http/Controllers/Admin/HrChatController.php), [retriever](../app/Services/PolicyRetriever.php), [prompt service](../app/Services/PolicyChatPrompt.php). |
| Accounts/notifications | Profile, avatar, skills, password and notification preferences; database notification feed/read/clear actions. [Account controller](../app/Http/Controllers/Admin/MyAccountController.php), [notification controller](../app/Http/Controllers/Admin/NotificationController.php). |
| Operations | Attachment inventory/reconciliation/privatization, OCR diagnostics, CLI policy ingestion, avatar synchronization. [Console commands](../app/Console/Commands). |

Source presence confirms implementation exists, not that every feature has passed current end-to-end acceptance. Detailed behavior is in [functional specifications](04-FUNCTIONAL-SPECIFICATIONS.md) and the [traceability matrix](TRACEABILITY-MATRIX.md).

## 3. Current versions and environment

PHP packages below were checked against both `composer.lock` and `vendor/composer/installed.json`; listed installed versions match the lockfile. JavaScript versions come from `package-lock.json`, not a fresh package installation.

| Component | Current evidence |
| --- | --- |
| PHP | CLI **8.5.12**; Composer requirement `^8.5` |
| Laravel | **13.39.0**; requirement `^13.0` |
| Backpack CRUD / PermissionManager | **7.1.22 / 7.3.1** |
| Backpack CoreUI 4 theme | **1.2.7** |
| Livewire | **3.8.11** |
| Spatie Laravel Permission | **6.25.0** |
| Sanctum | **4.3.3** |
| Laravel Dompdf integration | **3.1.2** |
| Smalot PDFParser / Symfony Process | **2.13.1 / 8.1.10** |
| PHPUnit | Locked/installed package **12.5.42** |
| Laravel Mix / Webpack | **6.0.49 / 5.105.0** |
| Axios / Lodash / PostCSS | **1.21.0 / 4.18.1 / 8.5.29** |
| Local tools observed | Node **26.12.1**, npm **11.20.0**, Composer **2.10.1** |
| UI | Blade, jQuery, CoreUI 4 / Bootstrap 5; public forms and PDF templates have separate styling |
| Database | MySQL configuration default; SQL Server connection and engine-specific implementation also present |

Sources: [Composer manifest](../composer.json), [Composer lockfile](../composer.lock), [npm manifest](../package.json), [npm lockfile](../package-lock.json), [database configuration](../config/database.php).

The current CLI PHP resolves under Laragon. `AGENTS.md` names Windows/XAMPP, while this folder and observed CLI use Laragon paths. **Not Verified:** active Apache/web PHP, service-manager/worker PHP, `php.ini`, production OS/server, actual database configuration/version, and deployed revision. Do not infer these from the CLI or configuration defaults. The application config defaults to UTC, independently of the handover's Asia/Shanghai date.

Use the exact lockfiles when reproducing the environment. Keep web, CLI, and policy workers on the compatible PHP runtime. This task did not install packages, inspect `.env`, connect to a database, inspect employee uploads, or call external providers.

## 4. Architecture and code map

EmployeeCare is a Laravel MVC monolith. Backpack handles administration, Blade renders pages, Livewire handles ticket discussion, and application services manage assignment, reports, files, OCR, retrieval, and provider requests. It is not a separate frontend SPA.

```text
Browser / public kiosk
  -> routes + HTTP middleware
  -> Backpack controllers / public controllers / Livewire TicketChat
  -> models + application services
  -> configured database + Laravel storage disks

Policy upload -> policy_jobs -> policy worker -> PDFParser / Poppler / Tesseract
                                             -> policy chunks -> HR assistant
HR question -> authorized retrieval + bounded history -> Anthropic HTTP API
```

| Location | Responsibility |
| --- | --- |
| [bootstrap/app.php](../bootstrap/app.php), [HTTP kernel](../app/Http/Kernel.php), [providers](../app/Providers) | Existing kernel-based bootstrap, middleware, bindings, rate limits, events and framework integration. The upgrade retains this layout. |
| [routes/backpack/custom.php](../routes/backpack/custom.php) | Panel routes, custom ticket/report/account/notification/chat/policy endpoints. |
| [routes/web.php](../routes/web.php), [routes/api.php](../routes/api.php) | Public survey routes and Sanctum-protected API user route. |
| [app/Models](../app/Models) | Users/organization, ticket lifecycle/visibility, comments/transfers, surveys, policy documents/chunks, conversations/logs. |
| [app/Services](../app/Services) | Assignment rules, file transactions/inventory, report queries/dialects, ingestion/OCR, policy retrieval/prompts/providers. |
| [resources/views](../resources/views) | Custom panel pages, Backpack overrides, public forms and PDFs. |
| [config/backpack/ui.php](../config/backpack/ui.php), [theme configuration](../config/backpack/theme-coreuiv4.php) | Current Backpack appearance/theme configuration. Custom views retain the application namespace with CoreUI 4 fallback. |
| [public/css/backpack-theme.css](../public/css/backpack-theme.css), [public/js](../public/js) | Shared theme fixes, icon picker, policy status polling and other browser assets. |
| [database/migrations](../database/migrations), [database/seeders](../database/seeders) | 34 migration files and initial/demo data definitions; not a live-schema inventory. |
| [tests](../tests) | PHPUnit unit/feature suites, Node JavaScript tests, opt-in native-engine tests and isolation helpers. |

See [architecture](02-SYSTEM-ARCHITECTURE.md), [database guide](03-DATABASE-DOCUMENTATION.md), [route reference](07-ROUTES-AND-API.md) and [diagrams](DIAGRAMS.md). These include older snapshots; recheck source before relying on exact counts/contracts.

## 5. Decisions and business rules to preserve

### Authentication and permissions

- The panel route prefix is **`employee-care`**, configured in [Backpack base config](../config/backpack/base.php). An `/admin` fallback in route declarations is not the effective configured prefix. Any parent URL directory comes from hosting/mount configuration.
- The panel uses the `web` guard. Initial Spatie roles are `admin`, `dept_head`, `div_head`, `hr_staff`, and `employee`. The old numeric `users.role` field is not equivalent to Spatie authorization.
- Panel membership does not mean administrator privileges. Active account checks, explicit permissions, ticket visibility, and action checks jointly determine access. Authorization is distributed across controllers, scopes, services and Livewire; [AuthServiceProvider](../app/Providers/AuthServiceProvider.php) has no active policy mapping.
- [LoginController](../app/Http/Controllers/Admin/Auth/LoginController.php) supports email or username and requires active status. Outside `local`, its login page redirects to an external HRDO login. The external SSO/session/provisioning/logout contract is **Not Verified**. A separate database connection does not establish SSO.
- `Ticket::visibleTo()` rejects inactive/missing users or users without `ticket.view`; admins have global scope; others get own tickets plus their role-specific organizational/assignment scope, including pending incoming transfers for heads. Apply the scope to every relevant query/aggregate.
- Policy management directly checks `admin`/`dept_head` roles inside controller setup. Do not replace this with middleware registered too late in Backpack's setup lifecycle.

### Ticket workflow

- Catalog issues supply routing/priority metadata. Custom issues use separately validated descriptions and destinations.
- [TicketAssignmentRules](../app/Services/TicketAssignmentRules.php) centralizes active HR-staff eligibility and coherent department/division membership. Heads and explicitly delegated HR staff retain their assignment boundaries.
- [Automatic assignment](../app/Services/TicketAutoAssignmentService.php) filters by destination. Catalog issues rank skill match first, then active workload, then user ID. Custom issues use workload then ID. No eligible staff or no department leaves the ticket unassigned. This does not guarantee strict fairness under concurrent submissions.
- Ticket persistence/file handling uses transactions and compensation; created-event assignment synchronizes Eloquent original attributes before its nested save to avoid SQL Server identity-column updates. Preserve this behavior.
- Resolve/reopen/quick-assignment actions use the transactional, row-locking action path and recheck visibility/current state. Resolution is limited to the permitted admin/head/assigned-staff cases. Only the creator can reopen a resolved ticket.
- Notifications use the **database channel**, with preference/recipient filtering. Ticket email notifications are not implemented by that channel.

### Files and storage

- [Filesystem configuration](../config/filesystems.php) defines private ticket files at `storage/app/ticket-attachments`, public files at `storage/app/public`, and the local disk at `storage/app`.
- Ticket/comment attachment references remain relative strings in JSON arrays. Protected routes resolve authorized records and stored indices; they do not accept arbitrary download paths.
- Private ticket storage, authorized download behavior, legacy-file denial rules and transaction compensation must remain consistent. Apache deny rules need equivalent protection on other web servers.
- Public avatars/Basset assets use the public disk and `public/storage` link. Default uploaded policy PDFs use `policies/...` on the configured default filesystem. Static `public/policyDocs` is a separate public-root surface.
- Attachment reconciliation defaults to read-only. Cleanup is a separately authorized operation requiring its safety conditions, including paused writers. Retain files when reference/storage state is uncertain; do not infer deletion authority from an orphan-candidate report.

### Policy ingestion and assistant

- Browser uploads/replacements are asynchronous, even if the general queue default is `sync`. Inspect the real [upload view](../resources/views/admin/hr_policy_upload.blade.php), controller, service, job and queue when diagnosing browser behavior; CLI ingestion has a different contract.
- Flow: upload -> `pending` -> dedicated `policy_ingestion` connection / `policy-ingestion` queue / `policy_jobs` table -> worker -> `processing` -> `active`, `needs_review`, or `failed`. `inactive` is also a document state.
- Schema readiness is checked before storage; the service does not automatically migrate. Ingestion versions protect replacement documents from stale jobs. Processing-state guards apply to replacement/re-ingestion paths.
- [IngestPolicyDocumentJob](../app/Jobs/IngestPolicyDocumentJob.php) has a 900-second timeout and one attempt; [queue config](../config/queue.php) has `retry_after=1200`. One supervised worker must continuously consume the dedicated queue. Status polling refreshes the UI; it does not start a worker.
- Extraction uses PDFParser and falls back to Poppler/Tesseract for sparse/scanned pages. Chunks use approximately 300 words with 50-word overlap and page attribution. OCR configuration keys are `OCR_TESSERACT_PATH`, `OCR_PDFTOPPM_PATH`, and `OCR_LANGUAGES` (default `eng+fil`); values belong in protected configuration.
- Only active documents with `status=active` enter retrieval. `needs_review` content requires review before eligibility. Current retrieval does not enforce an effective-date window.
- Chat uses user-owned conversations, bounded recent exchanges and validated source references. Retrieval rechecks active sources and supports topic changes/English-Taglish follow-ups. It is keyword-based retrieval, not unlimited or semantic memory.
- [AnthropicService](../app/Services/AnthropicService.php) is the selected provider. Gemini/OpenRouter source does not establish automatic failover. Retrieval misses avoid paid calls. Do not expose keys, prompts or employee information in diagnostics.

Operational reference: [policy queue guide](POLICY_INGESTION_QUEUE.md). After operator review, the documented Windows listener command is:

```powershell
php artisan queue:listen policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

This handover did not start it. A successful HTTP upload means saved/queued, not extracted. Pending documents with unreserved jobs and no chunks are a reason to check the worker first.

## 6. Completed work and recorded verification

Current source contains the following work. Results in this table are **recorded in repository reports**, not rerun today and not production certification.

| Workstream | Implemented/recorded outcome | Evidence and limits |
| --- | --- | --- |
| Security/workflow remediation | Scoped dashboard, safer skill rendering, private/protected attachments, survey submission identities, authorization/validation improvements, report/query work, guarded asynchronous policy ingestion and operator tools. | [MEDIUM_FIXES.md](MEDIUM_FIXES.md), [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md), current source/tests. Historical audit findings are not automatically all closed. |
| Policy upload repair | Guarded schema readiness, metadata preservation, versioned queued ingestion and real-PDF/OCR coverage. | [POLICY_INGESTION_QUEUE.md](POLICY_INGESTION_QUEUE.md). Earlier project history records a separately authorized queue migration; current application/production schema and worker health were not checked today. |
| PHP/Laravel upgrade | PHP 8.5/Laravel 13/Backpack 7/Livewire 3/Sanctum 4 adaptations, filesystem/date compatibility and forward token-expiry migration. | [PHP85_UPGRADE.md](PHP85_UPGRADE.md): 2026-10-29 record of 354 PHP tests / 2,559 assertions, 48 JS tests, build/platform/syntax checks, isolated SQL Server engine checks and HTTP workflows. The named Sanctum migration was reported applied locally; this does not prove deployment elsewhere. |
| CoreUI 4 migration | Theme/layout/Bootstrap adaptations, local asset compatibility, icon selection and mounted-app Livewire URL fixes. | [COREUI4_MIGRATION.md](COREUI4_MIGRATION.md): 358 PHP tests / 2,617 assertions, 51 JS tests, 76 direct HTTP requests, plus a later focused 41-test / 311-assertion run. Full interactive acceptance remained incomplete. |
| Browser/workflow follow-ups | Dropdown/dashboard/notification styles, attachment preview selection/removal, friendly validation, consistent assignment eligibility, faster actions, local icon library, policy polling and bounded chat follow-ups. | [BROWSER_FIXES.md](BROWSER_FIXES.md): full isolated suite 370 PHP tests / 2,766 assertions and 57 JS tests; temporary-output production build passed. Later chat-focused result: 60 PHP tests / 272 assertions. Do not add these counts together as one run. |
| HR chat scrolling | Scoped CSS overrides address Backpack's `overflow: visible !important`, allowing the transcript to scroll inside its card. | [BROWSER_FIXES.md](BROWSER_FIXES.md), [HR_AI_CHATBOT.md](HR_AI_CHATBOT.md), [HR chat view](../resources/views/admin/hr_chat.blade.php). Selected Chrome behavior was checked historically; this does not establish full responsive acceptance. |
| Rebuild specifications | Functional/business-rule/parity/migration/roadmap documentation for a separate Filament application. | [Specifications 15-20](15-REBUILD-SPECIFICATION.md) are plans. Their earlier runtime descriptions are historical; current Backpack source is upgraded. |

Private source snapshots/results are reported under `storage/app/php85-upgrade-backup/`, `storage/app/coreuiv4-upgrade-backup/`, and `storage/app/browser-fixes-backup/`. Their contents were not opened or revalidated for this handover. Do not upload those directories wholesale to another account; they may contain sensitive configuration, data, or dependencies.

## 7. Known issues, limitations and uncertainties

| Item | Current evidence and consequence | Next action |
| --- | --- | --- |
| Role editor omits `ticket.assign` | **Verified now:** [RoleCrudController](../app/Http/Controllers/Admin/RoleCrudController.php) renders only view/create/update/delete, then replaces role grants with submitted permission IDs. Saving a role can remove the hidden assignment grant. | Make a separately scoped fix with preservation/authorization regression coverage before relying on this editor for delegated assignment. |
| Concurrent HTTP capacity | **Recorded failure:** mixed HTTP load timed out at 60 seconds; intermittent local SQL Server connection/integrated-authentication errors were observed. Successful model stress is not web-capacity certification. | Reproduce on the intended server with stable database connectivity; diagnose before capacity sign-off. See [browser report](BROWSER_FIXES.md). |
| Browser acceptance incomplete | Selected Chrome checks and automated tests exist; all final desktop/mobile flows were not visually accepted. | Exercise login, dashboard, notifications, ticket actions/files, icon picker, chat scrolling and policy polling in real browsers. |
| Dependency review needs refreshing | 2026-10-29 report: no locked Composer advisories; npm retained 12 entries, including 2 high in the development-server dependency chain. **Not a current audit.** | Refresh audits with approved network access, assess actual exposure, and scope build-tool changes separately. |
| Quick-assignment state mismatch | **Verified now:** the show UI disables assignment for resolved tickets, but `handleQuickAssign()` has no explicit resolved-state prohibition. Action visibility/authorization checks do not settle the intended lifecycle rule. | Confirm intended behavior, then test direct requests and apply the smallest agreed fix. |
| Other authorization gaps | Older [gap register](DOCUMENTATION-GAPS.md) flags generic edit-field enforcement, incoming-transfer list/detail mismatch and broad report permissions. Not all were re-reviewed for this handover. | Reproduce against current source; do not claim historical findings remain exploitable or have been fixed without evidence. |
| Deployment assumptions | Hardcoded authenticated redirect, initial ticket status ID `3`, department ID `1` in survey/report logic, root-relative PWA paths, and proxy/TLS/static-file assumptions are documented. External login redirect is verified in source. | Confirm intended deployment paths, lookup IDs, access boundaries and trusted infrastructure. |
| Policy edge cases | Retrieval has no effective-date eligibility window. The gap register also flags deletion during ingestion; replacement guards do not establish a deletion lock. | Confirm product rules and test deletion/version races before changing behavior. |
| Live integrations | External SSO, paid AI response quality, actual mail delivery, current OCR runtime under service identity, MySQL engine behavior and production deployment are **Not Verified** here. | Test each explicitly with approved accounts, nonsensitive fixtures and the actual deployment/runtime. |
| Operational ownership | Current queue supervision/backlog, backups/restoration, retention, secure admin provisioning and deployed web-server file denial are **Not Verified**. | Assign owners, verify configuration and rehearse recovery in isolation. |
| Feature boundaries | No automatic escalation scheduler, complete immutable audit trail, general settings CRUD, verified WebSocket ticket transport or offline submission outbox is established. Ticket notifications are database-only; ARTA is not proven offline-capable. | Treat additions as new requirements. Do not advertise these as existing capabilities. |
| Documentation drift | Older guides contain earlier commit/runtime/route/schema counts and test statuses. The 34 current migrations do not certify live schema. | Use dated evidence and current source; update affected guides as part of future scoped changes. |

The detailed [security guide](08-SECURITY-DOCUMENTATION.md) and [gap register](DOCUMENTATION-GAPS.md) remain required context, not proof of an exhaustive current security audit.

## 8. Testing and operational boundaries

The ordinary [phpunit.xml](../phpunit.xml) forces testing mode, SQLite `:memory:`, array sessions/mail/cache and sync general queues. [CreatesApplication](../tests/CreatesApplication.php) invokes [IsolatedDatabase](../tests/Support/IsolatedDatabase.php), which verifies isolation, fakes storage, substitutes offline HTTP, and runs migrations/seeders only on the guarded in-memory connection. Keep these protections intact.

Available developer checks, **not executed as regression suites for this documentation task**:

```powershell
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
node --test tests/JavaScript/*.cjs
node tests/Support/production-build-check.cjs
```

`POLICY_SAMPLE_TESTS=1` opts into the real sample-PDF/OCR coverage. Confirm tool paths/languages and use only approved nonsensitive fixtures. The production-build check writes temporary output rather than replacing published assets. Windows subprocess-termination restrictions may affect timeout tests.

The separate [phpunit.engine.xml](../phpunit.engine.xml) suite requires explicit test configuration and guarded, separately named synthetic databases. Read [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md) and [EngineDatabase](../tests/Support/EngineDatabase.php) first. Do not use the application/production database for migration rehearsals or infer engine compatibility from SQLite results. Historical one-off permission to probe an existing database is not standing permission for another operator/account.

The upgrade adds [2026_10_29_090000_add_expiry_to_personal_access_tokens.php](../database/migrations/2026_10_29_090000_add_expiry_to_personal_access_tokens.php), a forward nullable indexed token-expiry column. Verify target schema and approved migration scope before execution. Do not run every pending migration, reseed roles/users, or rerun historical migrations as a setup shortcut.

For release preparation, review runtime parity, exact lockfiles, storage/asset links, Basset cache, frontend build, worker restart, backups and authorized migration scope. Do not assume `route:cache` or a blanket `optimize` is verified: older documentation records route-name/closure/constructor-query complications. This handover authorizes no deployment, data cleanup, migration, commit, push or service restart.

## 9. Recommended next steps

1. **Preserve and identify the actual delivery state.** Review the dirty tree with the owner, establish which files are intended for release, and compare with the deployed revision without overwriting local work. Confirm web/CLI/worker versions and database identity through safe diagnostics.
2. **Address the confirmed role-editor grant-loss risk.** Agree scope, preserve hidden/unrelated grants, enforce authorization, and run targeted tests. Review the resolved quick-assignment rule and other direct-request authorization gaps next.
3. **Finish browser acceptance.** Test the reported UI surfaces at desktop/mobile widths, including selected/dropped/removed files, mounted URLs, chat topics/scrolling, document statuses and public survey retries/offline viewing.
4. **Resolve HTTP concurrency failures.** Stabilize SQL Server connectivity, capture timings/errors on a representative server and repeat bounded synthetic load tests. Do not publish a capacity/SLA claim from the earlier model stress run.
5. **Verify operations and integrations.** Confirm supervised policy processing, controlled OCR samples, private downloads/static-file denial, external login/logout, approved AI calls, recovery mail, backup restoration and retention owners.
6. **Refresh dependency/security evidence.** Run current audits when network access is available and agree minimal remediation. Major package/build/runtime changes require separate scope and approval.
7. **If continuing the Filament rebuild, work in its separate project.** Reconcile the dated specifications with the now-upgraded Backpack baseline, inspect that target independently, choose a named milestone, and retain authorization/files/reports/surveys/OCR/chat behavior. This task did not inspect or certify another project folder.

## 10. Continuing from another ChatGPT/Codex account

Upload this document, the project's `AGENTS.md`, and the relevant source/docs for the next task. Relative source links work when the folder structure is preserved; uploading this document alone supplies context but not access to the codebase. A Git clone of HEAD alone omits the current uncommitted work. Provide a reviewed snapshot or owner-approved commit containing the intended files.

Transfer only necessary project material. Omit `.env`, application keys, credentials/tokens, employee records, real attachments, chat logs, database exports, private backups, and unnecessary dependency directories. Configure credentials separately through the new environment's protected mechanism.

Suggested first message:

> Use PROJECT-HANDOVER.md as dated context for EmployeeCare. Inspect the supplied folder and AGENTS.md before changing code. The current application is Laravel 13/PHP 8.5/Backpack 7/CoreUI 4; older Laravel 8 notes and the separate Filament rebuild plans are historical context. Preserve all unrelated working-tree changes. Distinguish current source, recorded test results, live verification and proposals. Do not invent features or treat old operation approvals as standing authorization. My next task is: [state one concrete outcome].

Project working rules: make the smallest safe change, preserve existing business behavior and runtime compatibility, inspect before editing, protect secrets/data, and report only checks actually performed. Do not automatically commit, push, merge, deploy, change production configuration/data, or undertake a major upgrade. For rebuild work, preserve parity beyond CRUD and keep the current application read-only unless explicitly included in scope.

## 11. Verification performed for this handover

- Inspected Git branch/HEAD/status, current instructions, manifests/lockfiles/installed PHP package metadata, selected routes/controllers/models/services, storage/queue configuration, migration/test isolation code, and relevant repository reports. Prior project notes were used to locate historical context; their old runtime claims were superseded by current evidence.
- Observed CLI PHP 8.5.12, Node 26.12.1, npm 11.20.0 and Composer 2.10.1. Web/worker runtimes were not checked.
- `composer validate --no-check-publish --no-interaction`: **passed** (`composer.json is valid`).
- `composer check-platform-reqs --lock --no-interaction`: **passed**, with all listed locked platform requirements reported successful.
- `php vendor/phpunit/phpunit/phpunit --version`: **completed**, reporting PHPUnit 12.5.42; this did not execute tests.
- Documentation validation: **90 local links resolved**, all three code fences were paired, required handover sections were present, and the new-file whitespace/diff review passed.
- No application regression suite, production build, OCR sample, native-engine suite, current dependency audit, browser acceptance or external integration was run for this handover. No live database/service action was performed.

## 12. Reading order

1. This document and [AGENTS.md](../AGENTS.md).
2. [System overview](01-SYSTEM-OVERVIEW.md), [architecture](02-SYSTEM-ARCHITECTURE.md), [functional guide](04-FUNCTIONAL-SPECIFICATIONS.md).
3. [Upgrade](PHP85_UPGRADE.md), [CoreUI migration](COREUI4_MIGRATION.md), [browser fixes/results](BROWSER_FIXES.md).
4. [Security](08-SECURITY-DOCUMENTATION.md), [gaps](DOCUMENTATION-GAPS.md), [deployment/engine guidance](DEPLOYMENT_READINESS.md), [policy queue](POLICY_INGESTION_QUEUE.md).
5. [Documentation index](README.md) for the full guides and rebuild specification package.
