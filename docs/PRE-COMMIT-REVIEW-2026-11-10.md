# EmployeeCare pre-commit review — 2026-11-10

**Verdict: Hold.** Application fixes and regression checks pass. Dependency security remediation remains incomplete: Composer reports seven advisories affecting four locked Symfony packages, and npm reports 12 vulnerability entries, including two high entries in the development-server dependency chain. Visual browser acceptance also remains unverified. This is a commit recommendation, not deployment approval.

## Repository and scope

- Branch: `main`.
- HEAD: `9ded73ce136acc7f5d88f8b87374f234440533e4`.
- Initial substantive inventory: 131 modified tracked files and 118 untracked files. No staged changes. The two untracked LSP files are generated artifacts.
- The index SHA-256 at entry was `12297a70d4d6a8a55ad637803a45005713209cb3423c810e982acc44cad51ab2`; the final comparison confirms identical index bytes. Nothing was staged, committed, pushed, merged, or deployed.
- All changes were reviewed by feature, including their routes, authorization, model/service/query paths, migrations, templates/assets, and relevant tests. The appendix enumerates every substantive changed/untracked path. Test coverage is evidence for the cases executed, not proof of every possible concurrency or production scenario.
- Git also reports `public/packages/backpack/base/css/custom.css` as modified, although `git diff` is empty and its normalized blob hash equals the index (`259a67977750e7413e0396a3030cac3900d048f2`). This is stat/line-ending noise; no content patch was made to it.

| Feature group | Review and observed status |
| --- | --- |
| Platform upgrade and shared UI | PHP/Laravel/Backpack/Livewire/Sanctum compatibility, kernels and providers, middleware, sessions/CSRF, authentication, Blade overrides, CoreUI/Bootstrap attributes, shared Inter font loading, icon assets, and frontend dependencies. Platform checks and isolated Apache HTTP checks passed. Visual appearance was not verified. |
| Master data | Department/division/issue/priority/status lifecycle guards, immutable codes, archive/reactivate permissions, audit snapshots, retained historical references, individual child state, selection rules, deletion blockers, migrations and seeders. SQLite regressions and native lifecycle/code tests passed. |
| Tickets and discussion | Catalog/custom submission, assignment eligibility and workload/skill selection, transfer requests and transitions, row scoping, attachment storage/rollback and private delivery, resolution/reopening, and Livewire events/uploads. Full PHP suite passed; native HTTP exercised assignment, a signed Livewire comment, resolution/reopening, and unauthorized access. |
| Dashboard and reports | Creation-date and office filters, independent division charts, operational metrics, attention worklist, leaderboard, reassignment calculations, tables and PDF exports. Preserved the current dashboard design as explicitly requested. Scoped-data tests and native report/PDF HTTP checks passed. |
| Surveys | Permanent HRDO identity, department/division choices, archived-selection rules, idempotent submissions, CSS/ARTA summaries and paging, date filters, charts, PWA assets and retry behavior. PHP/JavaScript tests and native concurrent survey-retry tests passed. |
| Notifications | User-owned feeds/read operations, escaped client rendering, sender lookup, preferences, deadline boundaries/deduplication, transaction failure/retry and scheduled command registration. Tests passed; the actual production scheduler was not run. |
| HR Assistant and policies | Conversation ownership and follow-up grounding, active-policy retrieval, escaped transcript rendering, upload/replacement and status polling, queue generation markers and extraction failures. Automated HTTP providers were blocked or faked. Three supplied PDFs completed real local extraction/OCR and a bounded test queue worker. |
| Documentation/test infrastructure | Dated historical evidence distinguished from this review; database identity/ownership guards, fake storage, offline HTTP factory, engine fixtures, build isolation and generated artifacts reviewed. No application-database data probes were run. |

## Exact versions and runtime verification

HEAD's manifest declares PHP `^7.3|^8.0`, Laravel `^8.75`, Backpack CRUD `^5.6`, Livewire `^2.12`, Sanctum `^2.11`, and Spatie permissions `^5.11`. The working tree's requirements and lockfile implement the newer stack below.

| Component | Locked and installed version |
| --- | --- |
| Laravel | `v13.39.0` |
| Backpack CRUD | `7.1.22` |
| Backpack PermissionManager | `7.3.1` |
| Backpack CoreUI v4 theme | `1.2.7` |
| Backpack Basset | `2.0.16` |
| Livewire | `v3.8.11` |
| Sanctum | `v4.3.3` |
| Spatie permissions | `6.25.0` |
| Dompdf Laravel integration | `v3.1.2` |
| PHPUnit | `12.5.42` |
| Carbon | `3.15.1` |

All **131 Composer packages** matched their locked versions. Composer's install dry run reported nothing to install, update or remove. This checks metadata/requirements; a fresh dependency installation was not completed because GitHub downloads failed.

CLI PHP is **8.5.12**, ZTS, x64, using `C:\laragon\bin\php\php-8.5.12-1-Win32-vs17-x64\php.ini`. `php -m` confirmed the required extensions, including ctype, curl, dom, fileinfo, gd, iconv, intl, mbstring, openssl, PDO, pdo_mysql, pdo_sqlite, pdo_sqlsrv, session, sodium, tokenizer, XML/XMLReader/XMLWriter, zip and zlib. Composer's platform check passed every requirement. `pcntl` is unavailable on this Windows PHP installation.

A separate loopback **Apache 2.4.66** instance loaded the actual Laragon PHP module and reported **PHP 8.5.12 / `apache2handler`**, the same ini path, and MySQL/SQLite/SQL Server PDO drivers. Its own test database, key, sessions and storage were used. The normal application virtual host was not changed or certified as running; its configured PHP path was inspected separately.

Node is **26.12.1**, npm **11.20.0**. Installed top-level packages are Axios `1.21.0`, Laravel Mix `6.0.49`, Lodash `4.18.1`, PostCSS `8.5.29`, and Webpack `5.105.0`. The only dependency change made by this review was the transitive `follow-redirects` patch from `1.16.1` to `1.16.3`. `package.json` remained byte-for-byte unchanged from the initial working tree.

## Upgrade-guide checks

The official [Laravel 9](https://laravel.com/docs/9.x/upgrade), [10](https://laravel.com/docs/10.x/upgrade), [11](https://laravel.com/docs/11.x/upgrade), [12](https://laravel.com/docs/12.x/upgrade), and [13](https://laravel.com/docs/13.x/upgrade) guides were consulted, together with [Backpack 6](https://backpackforlaravel.com/docs/6.x/upgrade-guide), [Backpack 7](https://backpackforlaravel.com/docs/7.x/upgrade-guide), and [Livewire 3](https://livewire.laravel.com/docs/3.x/upgrading).

- Retained the established kernel/provider bootstrap. Laravel supports upgrading applications without adopting the new skeleton. No application-structure rewrite was introduced.
- Checked framework CORS middleware, proxy-header constants, Spatie middleware namespaces, Laravel 13 request-forgery middleware, Sanctum middleware keys and the additive token expiry migration. Valid and expired token tests passed; real Apache requests rejected invalid CSRF tokens.
- Checked Carbon 3 elapsed-time behavior and explicit integer/absolute calculations, query-expression/API compatibility, model casts, Flysystem 3 adapter calls, and column-change/FK behavior. Native migration/report checks complement the SQLite grammar adapter.
- Checked Backpack's separation of base/UI configuration, custom-view namespace and fallback, theme assets, select-column model metadata, show layout, Bootstrap 5 controls and notification/delete response contracts. Custom file handling was retained.
- Checked Livewire's `dispatch()` events, model binding/upload behavior, temporary-upload removal, signed update payloads, script/update URLs and mounted-path tests. Native HTTP successfully submitted a signed Livewire comment.
- Existing cache/session prefix configuration remains explicit. The Laravel 13 cache unserialization change was examined: this legacy cache configuration omits the new option, and the installed cache manager retains its compatibility fallback. Introducing a cache allow-list requires a separate inventory of cached objects; it was not silently enabled or disabled here.

## Defects fixed during this review

| Defect | Focused change and evidence |
| --- | --- |
| Stored HTML injection in Assign Staff options | [TicketCrudController.php:935](../app/Http/Controllers/Admin/TicketCrudController.php#L935) now escapes staff names. A name containing closing select tags and an image event handler failed the new regression before the patch and passed afterward. See `TicketAssignmentWorkflowTest::test_quick_assignment_escapes_staff_names_in_option_labels`. |
| HRDO report identity still hardcoded to ID 1 | [SurveyReportQueries.php:72](../app/Services/SurveyReportQueries.php#L72) now selects HRDO through its permanent code. The new regression reproduced an omitted division head when HRDO had another ID, then passed. |
| Valid department-only issues labelled archived | [IssueCrudController.php:66](../app/Http/Controllers/Admin/IssueCrudController.php#L66) accepts an active department without a division when rendering availability. The regression first received `Office archived`, then `Active`. |
| Role editing silently removed non-CRUD permissions | [RoleCrudController.php:125](../app/Http/Controllers/Admin/RoleCrudController.php#L125) appends assignment/lifecycle/custom permissions after the established CRUD actions. A new regression parses the actual checked form fields and saves them, confirming all assigned permissions survive. No new permissions are granted automatically. |
| Dashboard tests described retired sections | Updated the existing tests to check the current operational summary and attention worklist, while retaining scoped metrics, office-boundary, chart and leaderboard assertions. The current dashboard design was explicitly confirmed by the user. |
| Native-engine fixture incompatible with current model guards | Updated `DeploymentEngineTest` to insert historical survey rows without current model events, provide canonical synthetic reference identities, apply lifecycle/code migrations before current writes, and explicitly request all-time report searches. Native regressions now pass on both engines. One intermediate fixture correction also supplied the required priority color. |
| Vulnerable `follow-redirects` patch | Applied the reviewed `1.16.3` patch with scripts disabled. npm's reported high entries decreased from three to two. |
| Generated LSP files offered for commit | Added `/storage/framework/lsp-*.php` to `.gitignore`. The two original files were retained and are now ignored. |

## Remaining findings, in priority order

1. **High review priority — unresolved dependency advisories.** `composer.lock` still contains Symfony HTTP Foundation at line 5417, HTTP Kernel at 5498, Routing at 6751, and YAML at 9669, all `8.1.10`. Composer reports seven advisories. The reviewed patch-only resolution was exactly these four packages to `8.1.11`; GitHub archive downloads repeatedly failed with connection-reset/TLS errors, including a check of the official archive fallback. The attempted lockfile changes were restored to the exact initial working-tree bytes; all installed packages again match the lock. No security fix is claimed for these packages.

   Upstream severities and applicability differ. The [YAML parsing advisory](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx) is rated high and concerns a development dependency here; [generated URL host handling](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693) is rated moderate. Other advisories involve method/host matchers, proxy shortcuts, path handling and Symfony fragments. An application exploit was not demonstrated, and Symfony SecurityBundle/fragment features are not assumed to be in use merely because a component is installed. The matched locked advisories still require remediation or an explicit, documented applicability decision before a clean security sign-off.

   Once downloads are available, the reviewed command is:

   ```powershell
   composer update symfony/http-foundation symfony/http-kernel symfony/routing symfony/yaml --with-dependencies --minimal-changes --patch-only --no-scripts --no-interaction
   ```

   Review the resulting lockfile, confirm installation, repeat the audit and rerun affected verification. This command was attempted here and did not complete installation.

2. **Medium review priority — development toolchain vulnerabilities remain.** npm reports **12 entries: 2 high, 5 moderate, 5 low**. The high entries are `webpack-dev-middleware` 5.3.4 ([package-lock.json:9676](../package-lock.json#L9676)) and `webpack-dev-server` 4.15.2 ([line 9757](../package-lock.json#L9757)). npm advertises no compatible automatic fix for the remaining chain. These are development-server/build dependencies; a production-site exploit was not established. Build-tool migration or major dependency changes remain pending approval. No forced audit fix was applied.

3. **Acceptance gap — visual browser checks were blocked.** The browser access host was unavailable, and native Chrome access timed out waiting for approval. No screenshot/layout, responsive interaction, chart/modal interaction, or actual rendered Inter-font claim is made. Source rendering and 55 real Apache HTTP checks passed. The current UI still needs a browser acceptance pass.

4. **Deployment gaps — real environment acceptance remains separate.** Production data was not inspected or migrated. Existing-record label preflight, deployed reverse-proxy/TLS configuration, persistent workers, scheduler installation and live SSO/mail/AI integrations remain unverified. `TrustProxies.php:16` retains its preexisting wildcard trust; its correctness depends on the actual trusted edge and header sanitization. No deployment topology was assumed and no production configuration was changed.

The dependency findings hold this recommendation. Browser and deployment gaps define the remaining practical limits of the successful local checks.

## Verification commands and outcomes

`<evidence>` below is `C:\Users\ADMIN\AppData\Local\Temp\ec-native-review-bfc7f1ad`. Scratch evidence also exists under `ec-precommit-java3ikq` in the same temporary parent. These are local review artifacts, not commit content.

| Command/check actually executed | Outcome |
| --- | --- |
| `git branch --show-current`; `git rev-parse HEAD`; `git status --porcelain=v1 -uall`; staged/unstaged name lists and diffs; `git ls-files --others --exclude-standard` | Branch/HEAD recorded; complete path inventory; staging remained empty. Index bytes preserved. |
| `php -v`; `php --ini`; `php -m` | PHP 8.5.12 and extension/ini evidence recorded. |
| `composer validate --strict --no-check-publish` | Passed. |
| `composer check-platform-reqs` | Passed all requirements. |
| `composer install --dry-run --no-scripts --no-interaction` | Passed with network access; nothing to install/update/remove. Initial sandbox run had a Packagist connectivity warning. |
| Locked-versus-installed metadata comparison over all Composer packages | 131 packages; zero mismatches. |
| `node --version`; `npm.cmd --version`; `npm.cmd ls --depth=0` | Versions recorded above; top-level dependencies resolved. |
| `php artisan test --filter='EngineDatabaseSafetyTest\|TestDatabaseIsolationTest\|IsolatedTestInfrastructureTest\|GlobalFontRenderingTest\|TicketAssignmentWorkflowTest'` | Initial focused check: 24 passed, 41 assertions. |
| `php artisan test --log-junit <scratch>/php-full-initial.xml` | Initial run: 545 passed, 12 failed, 3 skipped, 4,581 assertions. Eleven failures were stale dashboard expectations; one was Windows sandbox denial of subprocess termination. |
| Focused regression runs for option escaping, permanent HRDO identity, department-only state and role permission preservation | Each material application defect was reproduced before its patch; affected checks passed afterward. Dashboard-focused run passed 22 tests/435 assertions; report-focused run passed 4/29; role editor passed 1/4. |
| `php artisan test --filter=test_real_process_polling_enforces_per_page_timeout` outside sandbox | Passed, 1 test/2 assertions. A separate Symfony Process diagnostic confirmed `taskkill` was denied inside the sandbox and succeeded outside it. |
| `$env:POLICY_SAMPLE_TESTS='1'; php artisan test --log-junit <evidence>/php-full-final-with-samples.xml` outside sandbox | **564 passed, 4,931 assertions; 0 failures, 0 errors, 0 skips.** Final duration 403.72 seconds. No later application-code change was made. |
| `$env:POLICY_SAMPLE_TESTS='1'; php artisan test --filter=PolicyUploadSamplesTest` | Earlier separate opt-in run: 3 passed/42 assertions. Real PDFs: 9-page and 49-page native extraction, 4-page OCR. Originals retained their hashes. These are SQLite/fake-storage/one-test-worker results with real local extraction tools, not production ingestion evidence. All three are also included in the final full run. |
| `node --test tests/JavaScript/*Test.cjs` | **128 passed; 0 failed, 0 skipped**, including the final rerun after the npm patch. DOM/test doubles do not establish browser acceptance. |
| `node tests/Support/production-build-check.cjs` | Passed twice. Runs the real production Mix configuration into temporary output; published application assets were preserved. |
| `php -l <file>` loop over first-party PHP in app/bootstrap/config/database/routes/tests, excluding bootstrap cache | **290 files passed**, including the final application/test patches. |
| Blade compiler `compileString()` followed by `php -l` on temporary compiled files | **67 templates passed**; no application view-cache rebuild. |
| `composer audit --locked --no-interaction --format=json` | Completed with network access; exit 1, seven advisories/four packages, no abandoned packages. Initial sandbox attempt could not connect. |
| `npm.cmd audit --json` | Completed with network access; exit 1, 12 entries (2 high/5 moderate/5 low). Initially 13 entries (3 high). |
| `composer update ... --patch-only --dry-run --no-scripts --no-interaction`, then actual update and `composer install --no-scripts --no-interaction --prefer-dist` | Dry run resolved exactly four 8.1.11 patches. Both installation attempts failed downloading GitHub archives. Original lockfile restored and hash-verified; no installed/locked mismatch remains. |
| `npm.cmd update follow-redirects --ignore-scripts --dry-run --no-audit --no-fund`, followed by the same command without `--dry-run` | Reviewed one-package patch and installed it successfully. |
| `git diff --check`; `git diff --cached --check` | Both passed. Relevant untracked text was also checked separately before completion. |
| Credential-pattern scan over the initial 249 changed/untracked paths | Zero matching private-key/AWS/GitHub/Anthropic credential patterns. This heuristic is not a guarantee; `.env` and private data remain excluded. |

### Native database and HTTP evidence

The native harness validates an explicit `ec_engine_test_<suffix>` name, rejects application database names, avoids application `.env`/cached configuration, checks `DB_NAME()`/`DATABASE()`, claims an empty database with an ownership token, and blocks external HTTP. Each native test received its own empty database. `AuthorizedEngineDataTest` was not run.

- SQL Server **16.0.1000.6**, local integrated authentication: clean install and upgrade from the 33 HEAD migration files to all 41 working-tree migrations passed. The historical permission migration used the current schema-equivalent Spatie pivot-key API adaptation because the old static API is unavailable in Spatie 6. These are synthetic-schema/data rehearsals, not a restored production backup or execution on Laravel 8 binaries.
- MySQL **8.4.3** was provisioned from the existing binary with a new data directory and loopback-only port 13316. The same clean-install and synthetic upgrade checks passed.
- Both upgrade checks preserved the entire legacy ticket row, IDs/references/attachments, backfilled permanent identities independently of seed ordering, verified new tables/Sanctum expiry, and created/assigned a new ticket after migration. Clean installs also seeded the current schema and wrote lifecycle audits.
- For **each engine**, the actual regression command was `php vendor/bin/phpunit -c phpunit.engine.xml --do-not-cache-result --filter <class>`, once per fresh explicitly confirmed database. Classes were `PermanentMasterDataCodesEngineTest`, `PriorityLifecycleEngineTest`, and `DeploymentEngineTest`.
- SQL Server: **3 tests, 101 assertions** in total (9 + 12 + 80). MySQL: **3 tests, 89 assertions** (9 + 12 + 68). Includes actual native SQL/FKs, report matching and concurrent survey retries. Initial deployment-fixture failures were corrected and retested in new databases rather than wiping/reusing failed fixtures.
- The native environment explicitly set `ENGINE_TEST_DRIVER`, `ENGINE_TEST_HOST`, `ENGINE_TEST_PORT`, `ENGINE_TEST_DATABASE` and matching `ENGINE_TEST_CONFIRM`; SQL Server used `ENGINE_TEST_INTEGRATED_AUTH=1`, localhost and explicit local certificate trust. MySQL used temporary test credentials. Application-data confirmation was unset.
- Separate Apache/mod_php harness: **55 HTTP assertions passed**, covering synthetic admin/employee login, guest redirect, nine main pages, nine create forms and DataTables endpoints, invalid/valid assignment, signed Livewire comment persistence, resolve/reopen, employee 403/foreign-ticket 404, CSRF 419, private notifications, policy status, three real PDF exports, public surveys, protected-office blocker, role permission controls and serving the Inter CSS declaration.
- The Apache and MySQL test servers were stopped; both loopback listeners were confirmed absent. The disposable MySQL data directory and temporary credentials were removed. Initial cleanup attempts encountered Apache's service-only shutdown interface and MySQL shutdown file locks; cleanup subsequently succeeded after verifying the owned processes/paths and waiting for termination.
- Seven SQL Server test databases are retained for inspection: `ec_engine_test_reviewcleanbfc7f1ad`, `ec_engine_test_reviewupgradebfc7f1ad`, `ec_engine_test_reviewcodesbfc7f1ad`, `ec_engine_test_reviewprioritybfc7f1ad`, and `ec_engine_test_reviewdeploybfc7f1ad`, `ec_engine_test_reviewdeploybfc7f1ae`, `ec_engine_test_reviewdeploybfc7f1af`. The first two deployment attempts retain their failed synthetic fixtures. No application database was migrated, reset, seeded or cleaned.

## Migration and release implications

The eight new migrations run in timestamp order: Sanctum expiry; priority lifecycle; issue archiving; office archiving; lifecycle audits/restrictive priority FKs; permanent reference codes; nullable issue division; deadline notification ledger. Existing installations must use forward migrations with their real migration history. The historical permission migration edit supports clean installations and is not a request to rerun an executed migration.

The permanent-code migration intentionally refuses missing or ambiguous canonical labels before schema changes. An installation whose labels were already renamed needs a reviewed identity mapping/preflight; synthetic success does not establish that production data satisfies it. Historical IDs and individual archive flags were preserved in the tests.

Rollback is not uniformly reversible: lifecycle audits/grants/restrictive FKs are deliberately retained, code rollback removes the new identities, and nullable-division rollback refuses to discard department-only issues. A release needs a compatible code/database rollback point and backup, rather than assuming an arbitrary rollback batch is safe. No production rollback was attempted.

Policy ingestion needs a supervised `policy_ingestion` worker consuming `policy-ingestion`/`policy_jobs`. Its 900-second job timeout is below the configured 1,200-second retry interval. Restart long-running workers with the release; inspect queued payload compatibility. On Windows, `pcntl` worker-level timeout handling is unavailable, so deployment supervision must account for that. Real local OCR subprocess timeout enforcement passed outside the restrictive test sandbox.

The new `tickets:notify-deadlines` schedule requires the deployment's scheduler to invoke Laravel every minute. `withoutOverlapping()` and the database ledger were tested; system scheduler installation, uptime and shared-cache topology were not verified.

Deployment must refresh/rebuild the established configuration/routes/views as appropriate for its release process, preserve the intended guard/cookie/proxy settings and restart workers. No live cache flush, scheduler/worker startup, production configuration change or deployment was performed here.

## Commit contents and exclusions

Include the application/configuration/assets/migrations/tests/lockfiles belonging to the reviewed features, including required untracked files. In particular, the new `config/backpack/ui.php`, theme configuration, lifecycle/code/deadline migrations, services/traits, JavaScript/CSS files and icon catalog are required source assets. A commit containing only already-tracked modifications would be incomplete.

Exclude the two `storage/framework/lsp-*.php` files (now ignored), `.env`, credentials, local caches/logs/sessions/testing storage, `vendor`, `node_modules`, private `storage/app/*-backup` trees, uploaded attachments, database exports, and OS-temporary review artifacts. The existing ignore rules already cover the private/runtime directories inspected. Do not stage `custom.css` merely to clear its stat/line-ending status. Existing demonstration user seed data contains names/employee identifiers inherited from the repository; review its suitability before sharing the repository outside its intended audience. No such values or credentials are reproduced in this report.

The final source changes remain unstaged. Major dependency/build-tool upgrades and business-rule changes were left pending approval. The next concrete steps are resolving the Symfony patch download/audit findings, deciding the development-toolchain remediation, and completing browser acceptance before deployment.

## Complete substantive working-tree inventory

The following inventory groups every substantive modified/untracked source path. Cross-cutting files appear once under a primary group. Generated ignored files and the stat-only `custom.css` path are described above.

Inventory: **132 modified tracked paths + 118 untracked source/report paths = 250 substantive paths; 0 staged paths.**

### Platform upgrade, authentication and shared UI (56)

- Modified: `.gitignore`
- Modified: `AGENTS.md`
- Modified: `app/Exceptions/Handler.php`
- Modified: `app/Http/Controllers/Admin/RoleCrudController.php`
- Modified: `app/Http/Controllers/Admin/UserCrudController.php`
- Modified: `app/Http/Kernel.php`
- Modified: `app/Http/Middleware/TrustProxies.php`
- Modified: `app/Http/Middleware/VerifyCsrfToken.php`
- Modified: `app/Models/User.php`
- Modified: `composer.json`
- Modified: `composer.lock`
- Modified: `config/backpack/base.php`
- Modified: `config/backpack/operations/show.php`
- Untracked: `config/backpack/theme-coreuiv4.php`
- Untracked: `config/backpack/ui.php`
- Modified: `config/database.php`
- Modified: `config/sanctum.php`
- Modified: `database/migrations/2026_02_23_070500_create_permission_tables.php`
- Untracked: `database/migrations/2026_10_29_090000_add_expiry_to_personal_access_tokens.php`
- Modified: `database/seeders/RolesAndPermissionsSeeder.php`
- Modified: `database/seeders/UsersTableSeeder.php`
- Modified: `package-lock.json`
- Modified: `package.json`
- Untracked: `public/css/backpack-theme.css`
- Untracked: `public/js/icon-picker.js`
- Untracked: `resources/data/fontawesome-icons.json`
- Untracked: `resources/views/admin/partials/record_badge.blade.php`
- Modified: `resources/views/errors/layout.blade.php`
- Modified: `resources/views/vendor/backpack/base/auth/login.blade.php`
- Modified: `resources/views/vendor/backpack/base/auth/register.blade.php`
- Modified: `resources/views/vendor/backpack/base/inc/footer.blade.php`
- Modified: `resources/views/vendor/backpack/base/inc/head.blade.php`
- Untracked: `resources/views/vendor/backpack/base/inc/sidebar.blade.php`
- Modified: `resources/views/vendor/backpack/base/inc/sidebar_content.blade.php`
- Modified: `resources/views/vendor/backpack/base/inc/topbar_right_content.blade.php`
- Modified: `resources/views/vendor/backpack/base/layouts/top_left.blade.php`
- Modified: `resources/views/vendor/backpack/base/my_account.blade.php`
- Modified: `resources/views/vendor/backpack/crud/fields/iconpicker.blade.php`
- Modified: `resources/views/vendor/backpack/crud/fields/modern_upload.blade.php`
- Modified: `routes/backpack/custom.php`
- Modified: `tests/Engine/DeploymentEngineTest.php`
- Untracked: `tests/Feature/AdminWorkflowUpdatesTest.php`
- Modified: `tests/Feature/AuditSecurityRegressionTest.php`
- Untracked: `tests/Feature/BrowserRegressionTest.php`
- Untracked: `tests/Feature/CoreUiThemeTest.php`
- Untracked: `tests/Feature/CrudDeleteConstraintTest.php`
- Modified: `tests/Feature/CrudRequestUniqueIgnoreSelfTest.php`
- Untracked: `tests/Feature/GlobalFontRenderingTest.php`
- Modified: `tests/Feature/MediumFindingsRegressionTest.php`
- Untracked: `tests/Feature/RolePermissionEditorTest.php`
- Untracked: `tests/Feature/SanctumUpgradeTest.php`
- Modified: `tests/Feature/SyncUserAvatarsTest.php`
- Untracked: `tests/Feature/UpgradeAdminSmokeTest.php`
- Untracked: `tests/JavaScript/IconLibraryTest.cjs`
- Untracked: `tests/JavaScript/IconPickerTest.cjs`
- Untracked: `tests/JavaScript/dom-harness.cjs`

### Notifications and scheduling (12)

- Untracked: `app/Console/Commands/NotifyTicketDeadlines.php`
- Modified: `app/Console/Kernel.php`
- Modified: `app/Http/Controllers/Admin/NotificationController.php`
- Untracked: `app/Services/TicketDeadlineNotifier.php`
- Modified: `app/Services/TicketNotificationService.php`
- Untracked: `database/migrations/2026_11_04_120000_create_ticket_deadline_notifications_table.php`
- Untracked: `public/js/notifications.js`
- Modified: `resources/views/admin/notifications/index.blade.php`
- Untracked: `tests/Feature/NotificationDropdownTest.php`
- Modified: `tests/Feature/NotificationSettingsTest.php`
- Untracked: `tests/Feature/TicketDeadlineNotificationsTest.php`
- Untracked: `tests/JavaScript/NotificationsTest.cjs`

### Surveys and survey reporting (19)

- Modified: `app/Http/Controllers/Admin/ArtaSurveyReportsController.php`
- Modified: `app/Http/Controllers/Admin/SurveyReportsController.php`
- Modified: `app/Http/Controllers/SurveyController.php`
- Modified: `app/Models/Survey.php`
- Untracked: `app/Services/SurveyReportFilters.php`
- Modified: `app/Services/SurveyReportQueries.php`
- Modified: `app/Services/SurveyResponseTable.php`
- Untracked: `public/js/survey-report-charts.js`
- Untracked: `public/js/survey-report-filters.js`
- Modified: `resources/views/admin/arta_survey_reports.blade.php`
- Untracked: `resources/views/admin/partials/survey_report_charts.blade.php`
- Untracked: `resources/views/admin/partials/survey_report_filters.blade.php`
- Modified: `resources/views/admin/survey_reports.blade.php`
- Modified: `resources/views/survey_form.blade.php`
- Modified: `tests/Feature/SurveyPwaTest.php`
- Untracked: `tests/Feature/SurveyReportGlobalFiltersTest.php`
- Untracked: `tests/Feature/SurveyReportPresentationTest.php`
- Untracked: `tests/JavaScript/SurveyReportChartsTest.cjs`
- Untracked: `tests/JavaScript/SurveyReportFiltersTest.cjs`

### Dashboard and ticket reporting (35)

- Modified: `app/Http/Controllers/Admin/DashboardController.php`
- Modified: `app/Http/Controllers/Admin/ReportsController.php`
- Untracked: `app/Services/DashboardTicketMetrics.php`
- Untracked: `app/Services/ReportOfficeScope.php`
- Modified: `app/Services/TicketReportQueries.php`
- Modified: `app/Services/TicketReportWidgets.php`
- Untracked: `public/css/analytics.css`
- Untracked: `public/css/dashboard-charts.css`
- Untracked: `public/css/report-tables.css`
- Untracked: `public/js/analytics-charts.js`
- Untracked: `public/js/department-division-charts.js`
- Untracked: `public/js/report-office-filters.js`
- Untracked: `public/js/report-tables.js`
- Untracked: `resources/views/admin/partials/average_resolution_card.blade.php`
- Untracked: `resources/views/admin/partials/dashboard_attention.blade.php`
- Untracked: `resources/views/admin/partials/dashboard_summary.blade.php`
- Untracked: `resources/views/admin/partials/department_division_chart.blade.php`
- Untracked: `resources/views/admin/partials/reassignment_rate_card.blade.php`
- Untracked: `resources/views/admin/partials/report_kpi_cards.blade.php`
- Untracked: `resources/views/admin/partials/report_table_assets.blade.php`
- Untracked: `resources/views/admin/partials/resolution_average.blade.php`
- Untracked: `resources/views/admin/partials/staff_leaderboard.blade.php`
- Modified: `resources/views/admin/reports.blade.php`
- Modified: `resources/views/vendor/backpack/base/dashboard.blade.php`
- Untracked: `tests/Feature/DashboardLeaderboardTest.php`
- Untracked: `tests/Feature/DashboardOperationalMetricsTest.php`
- Modified: `tests/Feature/DashboardVisibilityTest.php`
- Untracked: `tests/Feature/ReportOfficeFiltersTest.php`
- Modified: `tests/Feature/ReportQueryParityTest.php`
- Untracked: `tests/Feature/ReportTablePresentationTest.php`
- Modified: `tests/Feature/ReportsPageWidgetsRenderTest.php`
- Modified: `tests/Feature/ReportsWidgetsTest.php`
- Untracked: `tests/JavaScript/DashboardChartsTest.cjs`
- Untracked: `tests/JavaScript/ReportTablesTest.cjs`
- Untracked: `tests/JavaScript/ReportsChartsTest.cjs`

### Master-data lifecycle and reference codes (67)

- Modified: `app/Http/Controllers/Admin/DepartmentCrudController.php`
- Modified: `app/Http/Controllers/Admin/DivisionCrudController.php`
- Modified: `app/Http/Controllers/Admin/IssueCrudController.php`
- Untracked: `app/Http/Controllers/Admin/Operations/MasterDataArchivingOperation.php`
- Untracked: `app/Http/Controllers/Admin/Operations/OfficeArchivingOperation.php`
- Modified: `app/Http/Controllers/Admin/PriorityCrudController.php`
- Modified: `app/Http/Controllers/Admin/StatusCrudController.php`
- Untracked: `app/Http/Requests/Concerns/AuthorizesMasterDataLifecycle.php`
- Modified: `app/Http/Requests/DepartmentRequest.php`
- Modified: `app/Http/Requests/DivisionRequest.php`
- Modified: `app/Http/Requests/IssueRequest.php`
- Modified: `app/Http/Requests/PriorityRequest.php`
- Modified: `app/Http/Requests/StatusRequest.php`
- Untracked: `app/Models/Concerns/HasMasterDataLifecycle.php`
- Untracked: `app/Models/Concerns/HasPermanentCode.php`
- Untracked: `app/Models/Concerns/LocksOfficeReferences.php`
- Modified: `app/Models/Department.php`
- Modified: `app/Models/Division.php`
- Modified: `app/Models/Issue.php`
- Modified: `app/Models/Priority.php`
- Modified: `app/Models/Status.php`
- Untracked: `app/Services/DivisionChartQueries.php`
- Untracked: `app/Services/IssueSelectionRules.php`
- Untracked: `app/Services/MasterDataLocks.php`
- Untracked: `app/Services/MasterDataRules.php`
- Untracked: `app/Services/OfficeRetirementRules.php`
- Untracked: `app/Services/OfficeSelectionRules.php`
- Untracked: `app/Services/PermanentCodeRules.php`
- Untracked: `app/Services/PrioritySelectionRules.php`
- Untracked: `database/migrations/2026_11_03_090000_add_priority_lifecycle.php`
- Untracked: `database/migrations/2026_11_03_100000_add_issue_archiving.php`
- Untracked: `database/migrations/2026_11_03_110000_add_office_archiving.php`
- Untracked: `database/migrations/2026_11_04_090000_enforce_master_data_lifecycle.php`
- Untracked: `database/migrations/2026_11_04_100000_add_permanent_master_data_codes.php`
- Untracked: `database/migrations/2026_11_04_110000_allow_department_level_issues.php`
- Modified: `database/seeders/DepartmentTableSeeder.php`
- Modified: `database/seeders/DivisionTableSeeder.php`
- Modified: `database/seeders/IssueTableSeeder.php`
- Modified: `database/seeders/PriorityTableSeeder.php`
- Modified: `database/seeders/StatusTableSeeder.php`
- Untracked: `public/js/issue-archive.js`
- Untracked: `public/js/issue-office-selection.js`
- Untracked: `public/js/office-archive.js`
- Untracked: `public/js/priority-actions.js`
- Untracked: `resources/views/admin/office_filters.blade.php`
- Untracked: `resources/views/vendor/backpack/crud/buttons/issue_archive.blade.php`
- Untracked: `resources/views/vendor/backpack/crud/buttons/master_data_delete.blade.php`
- Untracked: `resources/views/vendor/backpack/crud/buttons/office_archive.blade.php`
- Untracked: `resources/views/vendor/backpack/crud/buttons/priority_actions.blade.php`
- Untracked: `resources/views/vendor/backpack/crud/buttons/status_archive.blade.php`
- Untracked: `resources/views/vendor/backpack/crud/fields/issue_office_selection.blade.php`
- Untracked: `tests/Engine/PermanentMasterDataCodesEngineTest.php`
- Untracked: `tests/Engine/PriorityLifecycleEngineTest.php`
- Untracked: `tests/Feature/DepartmentLevelIssueMigrationTest.php`
- Untracked: `tests/Feature/IndependentDivisionChartsTest.php`
- Untracked: `tests/Feature/IssueArchivingTest.php`
- Untracked: `tests/Feature/MasterDataLifecycleRulesTest.php`
- Untracked: `tests/Feature/OfficeRetirementTest.php`
- Untracked: `tests/Feature/PermanentMasterDataCodesTest.php`
- Untracked: `tests/Feature/PriorityLifecycleTest.php`
- Untracked: `tests/JavaScript/DepartmentDivisionChartsTest.cjs`
- Untracked: `tests/JavaScript/IssueArchiveTest.cjs`
- Untracked: `tests/JavaScript/OfficeArchiveTest.cjs`
- Untracked: `tests/JavaScript/OfficeSelectionTest.cjs`
- Untracked: `tests/JavaScript/PriorityActionsTest.cjs`
- Modified: `tests/Unit/DepartmentDivisionCacheTest.php`
- Modified: `tests/Unit/StatusIdsByNameTest.php`

### HR Assistant and policy ingestion (18)

- Modified: `app/Http/Controllers/Admin/HrChatController.php`
- Modified: `app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php`
- Modified: `app/Models/HrPolicyDocument.php`
- Modified: `app/Services/PolicyChatPrompt.php`
- Modified: `app/Services/PolicyRetriever.php`
- Untracked: `public/js/policy-status.js`
- Modified: `resources/views/admin/hr_chat.blade.php`
- Untracked: `resources/views/admin/hr_policy_list.blade.php`
- Modified: `resources/views/admin/hr_policy_update.blade.php`
- Modified: `resources/views/admin/hr_policy_upload.blade.php`
- Modified: `tests/Feature/HrChatConversationMemoryTest.php`
- Modified: `tests/Feature/HrChatReliabilityTest.php`
- Modified: `tests/Feature/PolicyChatIngestionTest.php`
- Modified: `tests/Feature/PolicyChatProvidersTest.php`
- Modified: `tests/Feature/PolicyIngestAtomicityTest.php`
- Modified: `tests/Feature/PolicyQueueTest.php`
- Modified: `tests/Feature/PolicyUploadSamplesTest.php`
- Untracked: `tests/JavaScript/PolicyStatusTest.cjs`

### Tickets, assignment, transfers and chat (25)

- Modified: `app/Http/Controllers/Admin/TicketCrudController.php`
- Modified: `app/Http/Livewire/TicketChat.php`
- Modified: `app/Http/Requests/TicketRequest.php`
- Modified: `app/Models/Ticket.php`
- Modified: `app/Models/TicketReassignmentRequest.php`
- Modified: `app/Services/AttachmentInventory.php`
- Modified: `app/Services/TicketAssignmentRules.php`
- Modified: `app/Services/TicketAutoAssignmentService.php`
- Modified: `database/seeders/TicketTableSeeder.php`
- Untracked: `public/css/reassignment-card.css`
- Untracked: `resources/views/admin/partials/latest_tickets_table.blade.php`
- Modified: `resources/views/admin/ticket/reassignment_widget.blade.php`
- Modified: `resources/views/livewire/ticket-chat.blade.php`
- Modified: `resources/views/submit-ticket.blade.php`
- Modified: `resources/views/vendor/backpack/crud/edit.blade.php`
- Modified: `resources/views/vendor/backpack/crud/show.blade.php`
- Modified: `tests/Feature/AttachmentOperationsTest.php`
- Untracked: `tests/Feature/TicketActionPerformanceTest.php`
- Untracked: `tests/Feature/TicketAssignmentWorkflowTest.php`
- Modified: `tests/Feature/TicketAttachmentDeletionTest.php`
- Modified: `tests/Feature/TicketAutoAssignmentQueryCountTest.php`
- Modified: `tests/Feature/TicketChatAttachmentValidationTest.php`
- Modified: `tests/Feature/TicketPerformanceTest.php`
- Modified: `tests/Feature/TicketReassignmentTest.php`
- Untracked: `tests/JavaScript/AttachmentPreviewTest.cjs`

### Documentation (11)

- Modified: `docs/01-SYSTEM-OVERVIEW.md`
- Modified: `docs/05-INSTALLATION-GUIDE.md`
- Modified: `docs/06-DEVELOPER-GUIDE.md`
- Modified: `docs/08-SECURITY-DOCUMENTATION.md`
- Untracked: `docs/BROWSER_FIXES.md`
- Untracked: `docs/COREUI4_MIGRATION.md`
- Modified: `docs/HR_AI_CHATBOT.md`
- Untracked: `docs/PHP85_UPGRADE.md`
- Untracked: `docs/PRE-COMMIT-REVIEW-2026-11-10.md`
- Untracked: `docs/PROJECT-HANDOVER.md`
- Modified: `docs/README.md`

### Test infrastructure and isolation (7)

- Modified: `phpunit.xml`
- Modified: `tests/Support/EngineDatabase.php`
- Modified: `tests/Support/EngineSubmissionRace.php`
- Modified: `tests/Support/IsolatedDatabase.php`
- Modified: `tests/Unit/EngineDatabaseSafetyTest.php`
- Modified: `tests/Unit/IsolatedTestInfrastructureTest.php`
- Modified: `tests/Unit/TestDatabaseIsolationTest.php`
