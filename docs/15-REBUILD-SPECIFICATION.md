# 15 - Modern Rebuild Specification

Prepared **2026-10-28**. Status: **implementation specification, not an implemented rebuild**. Source baseline: current working tree at HEAD `4927f4e`, including preexisting uncommitted changes. Existing application remains Laravel 8/PHP 7.4/Backpack. No dependency installation, migration, data access, rebuild scaffold or deployment was performed for this specification.

## 1. Assessment and evidence precedence

**The repository and guides are sufficient to specify and begin an isolated rebuild of the implemented modules. They are not sufficient to certify exact production integration, migrate live data, or authorize cutover without the decisions and evidence below.** UI generators alone cannot establish functional parity.

Use implementation and tests to resolve claims in the historical guides; use approved product decisions when deliberately replacing unsafe/inconsistent behavior. Migrations describe intended schema, not a verified live database. No live database, environment secrets, uploaded employee documents or production accounts were read for this task.

Read together:

- [16 Mapping](16-BACKPACK-TO-FILAMENT-MAPPING.md): resources, custom pages, fields, actions and outside-panel workflows.
- [17 Rules](17-BUSINESS-RULES.md): source-grounded behavior, security deviations and unresolved decisions.
- [18 Migration](18-DATABASE-MIGRATION-PLAN.md): table/field transformations, identity/files/history, verification and rollback.
- [19 Parity](19-FUNCTIONAL-PARITY-CHECKLIST.md): individually testable acceptance checklist. Unchecked means not implemented/accepted.
- [20 Roadmap](20-REBUILD-IMPLEMENTATION-ROADMAP.md): milestone dependencies and exit gates.
- Existing [functional guide](04-FUNCTIONAL-SPECIFICATIONS.md), [schema lineage](03-DATABASE-DOCUMENTATION.md), [route inventory](07-ROUTES-AND-API.md), [traceability](TRACEABILITY-MATRIX.md), [gaps](DOCUMENTATION-GAPS.md) and source/test links remain supporting evidence.

## 2. Technology decisions and compatibility evidence

Verified against the official sources linked here on **2026-10-28**. `Declared` means package/runtime constraints permit the combination; it does not mean Composer resolution or runtime tests were executed. All exact patches must be locked from stable releases in the new project at M01. Never transplant the old lockfile or its minimum-stability=dev setting.

| Layer | New-project baseline | Official evidence / qualification |
| --- | --- | --- |
| Runtime | PHP **8.5**, latest supported security patch in that family | [PHP supported branches](https://www.php.net/supported-versions.php); provision web, CLI and worker consistently |
| Framework | Latest stable **Laravel 13.x**, constraint ^13.0 or current 13.x skeleton minimum | [Laravel release policy](https://laravel.com/framework/docs/releases) supports PHP 8.3-8.5; [framework manifest](https://raw.githubusercontent.com/laravel/framework/13.x/composer.json) requires PHP ^8.3 and composer-runtime-api ^2.2 |
| Admin/UI | **Filament 5.x**, observed stable release **v5.11.0** | [Release](https://github.com/filamentphp/filament/releases/tag/v5.11.0); [tagged support manifest](https://raw.githubusercontent.com/filamentphp/filament/v5.11.0/packages/support/composer.json): PHP ^8.2, Laravel contracts ^11.28/^12/^13, Livewire ^4.4.8, ext-intl |
| Reactive components | **Livewire 4**, respecting Filament's resolved minimum | [Official manifest](https://raw.githubusercontent.com/livewire/livewire/4.x/composer.json) includes Laravel 13. Current v2 component classes/templates require redevelopment |
| Styling/build | Blade, Tailwind **4.1+**, Vite with Laravel plugin | [Filament installation](https://filamentphp.com/docs/5.x/introduction/installation); [Laravel 13 skeleton package.json](https://raw.githubusercontent.com/laravel/laravel/13.x/package.json) uses Vite ^8 and laravel-vite-plugin ^3.1. No Mix/jQuery/Bootstrap dependency in the new panel |
| Node | Supported LTS satisfying locked Vite/plugin engines; propose Node 24 LTS | [Vite requirements](https://vite.dev/guide/), [Node releases](https://nodejs.org/en/about/previous-releases). Confirm installed exact versions at M01 |
| Database | **MySQL**, proposed **8.4 LTS**, InnoDB, utf8mb4, strict mode | [Laravel database support](https://laravel.com/framework/docs/database), [MySQL 8.4 release notes](https://dev.mysql.com/doc/relnotes/mysql/8.4/en/). This is a deliberate LTS proposal, not a claim 8.4 is the newest MySQL family |
| RBAC | **Spatie Laravel Permission 8.x**, observed **8.3.2**; custom Filament management resources | [Tagged manifest](https://raw.githubusercontent.com/spatie/laravel-permission/8.3.2/composer.json) permits PHP ^8.3 and Laravel ^12/^13. No Backpack PermissionManager; no Shield dependency required |
| PDF | Candidate **barryvdh/laravel-dompdf 3.x**, observed stable **v3.1.2** | [Tagged manifest](https://raw.githubusercontent.com/barryvdh/laravel-dompdf/v3.1.2/composer.json) permits Laravel ^13.0/PHP ^8.1. Its Dompdf ^3.0 range must resolve to a current reviewed patch. Layout/font/runtime verification still required |
| OCR/extraction | Keep native extraction plus Tesseract/Poppler subprocess pipeline; candidate supported PDFParser release | [PDFParser manifest](https://raw.githubusercontent.com/smalot/pdfparser/master/composer.json) permits PHP >=7.1, requires iconv/zlib. That open constraint alone is not PHP 8.5 runtime certification; use real nonsensitive samples at M09 |
| QR | Preserve scannable SVG/visual behavior; candidate small renderer around BaconQrCode 3.x | [Bacon manifest](https://raw.githubusercontent.com/Bacon/BaconQrCode/master/composer.json). [Existing Simple QrCode manifest](https://raw.githubusercontent.com/SimpleSoftwareIO/simple-qrcode/master/composer.json) does not explicitly exclude PHP 8.5 but is not proof of Laravel 13 facade compatibility. Select tested stable package at M01/M09; do not label the old wrapper incompatible without a test |
| Processes/HTTP | Laravel HTTP client and framework-compatible Symfony Process | Accept the framework's resolved compatible packages; do not pin legacy Symfony ^5.4. Provider account/model and OCR executables remain external acceptance dependencies |
| Tests | PHPUnit compatible with the new skeleton; Filament/Livewire tests, MySQL integration and Node/browser tests | Preserve existing assertions/fixtures, not PHPUnit 9/bootstrap implementation. Coverage is defined by [19](19-FUNCTIONAL-PARITY-CHECKLIST.md) |

**Core compatibility conclusion:** the requested Laravel 13 / PHP 8.5 / Filament 5 stack has an explicit compatible constraint intersection. A clean dependency solve, security audit, PHP extension check and runtime smoke tests are mandatory M01 exit evidence. Supplemental PDF/OCR/QR/browser behavior is provisional until tested; no installation has been simulated by changing this repository's platform settings.

Do not add a new AI SDK, Redis, Horizon, Reverb, Elasticsearch or a permissions UI plugin solely because the stack is modern. The selected AI behavior can remain behind the existing HTTP-adapter concept. Choose a database queue initially; add infrastructure only with a measured requirement.

## 3. Preserved product scope

Preserve all ten CRUD modules (tickets, issues, departments, divisions, priorities, statuses, users, roles, permissions, policy documents), dashboard, ticket/assignment/transfer/discussion/resolution/reopen workflow, authorized file access, account/skills/avatar/preferences, notification list/feed/read/clear, three reporting areas and PDFs, both public questionnaires, survey kiosk/PWA, policy OCR/quality/chunking/queue, grounded HR chat/private histories/feedback and maintenance-command capabilities.

Preserve data/history and authorized outcomes, not Backpack classes, unsafe HTML, obsolete library internals or literal SQL Server syntax. Keep public forms/downloads/API contracts separate where appropriate. The rebuild is one Laravel monolith with one panel that admits employees as well as administrators; multi-panel/tenant splitting is unnecessary for the current rules. Proposed panel prefix remains `/employee-care` pending URL decision D12.

No feature can disappear because a default Filament resource lacks it. No new bulk delete/export/import/replicate/restore operation is implied. No new email delivery, automatic escalation, offline submission outbox, full audit-history reconstruction or vector search is baseline scope.

## 4. Target architecture

Proposed names/paths below are **future targets**, not existing implementation files:

```text
app/Models/                         # Eloquent relationships, casts, small scopes
app/Policies/                       # Resource + custom-operation authorization
app/Actions/Tickets/                # Create, Update, Assign, Resolve, Reopen, Transfer actions
app/Services/Assignments/           # Eligibility + deterministic skill/workload selection
app/Services/Attachments/           # Storage, compensation, inventory, protected response
app/Services/Reports/               # Periods, metrics, queries and PDF generation
app/Services/Policies/              # Ingestion, extraction, retrieval and provider adapter
app/Services/Surveys/               # Validation/persistence and stable submission IDs
app/Filament/Resources/             # Schemas/tables/pages invoking application actions
app/Filament/Pages/                 # Dashboard, reports, assistant, account, notifications
app/Livewire/                       # Ticket discussion and any public interactive components
app/Http/Controllers/               # Public surveys, downloads, compatibility JSON endpoints
app/Http/Requests/                  # HTTP validation sharing rule builders with panel forms
app/Jobs/                           # Versioned ingestion and justified queued side effects
app/Notifications/                  # Database payloads; no implicit new email channel
app/Providers/Filament/             # Panel, middleware, navigation and strict policy wiring
routes/web.php                     # Public/authenticated compatibility routes
routes/console.php                 # Actual scheduled work only, if approved
resources/views/ + resources/css/   # Blade/Tailwind; Vite entrypoints and public kiosk assets
```

Use Laravel 13 bootstrap/providers/middleware registration. Do not paste legacy Http/Console Kernel or Backpack service providers into the new skeleton. Define shared validation rules when both HTTP and panel paths write the same entity. Keep business transitions in actions; do not duplicate them in Filament callbacks, observers and controllers.

### Mutation and side-effect contract

1. Resolve authenticated actor and visible record; authorize the exact action.
2. Validate inputs and permitted field changes; normalize issue/destination/assignee consistently.
3. Lock the ticket/current pending transfer or policy generation where required; transact all related row updates.
4. Persist exactly one transition, maintain timestamps and cancel pending transfers when resolving.
5. Deliver required database notifications with a tested transaction/after-commit contract; file cleanup only after confirmed commit. No duplicate event/observer paths.
6. On storage/DB ambiguity, retain files and surface recoverable feedback; never silently discard data.

Use events/listeners only for clear side effects, not a second source of ticket business rules. Observers may maintain simple invariants, but must not rerun assignment during migration or send duplicate notifications. Keep policy enqueue + generation marker atomic in the same database transaction; this job is a deliberate exception to generic after-commit dispatch advice. Worker claims/commit guards prevent stale replacements. Import mode bypasses business side effects deliberately and is tested separately.

### Authorization boundary

Panel admission requires active membership and preserves legitimate employee access. Resource policies enforce view/create/update/delete; an actor-aware visible-ticket query is reused for list/detail/search/download/discussion/widgets. Custom policies cover assign/resolve/reopen/request/accept/reject/cancel. A blanket admin Gate::before that bypasses creator-only reopen is prohibited. See [17 role/action rules](17-BUSINESS-RULES.md#authorization-matrix).

Filament actions call policies and application actions server-side. Do not trust hidden/disabled fields, mounted record IDs, upload paths or navigation visibility. Notifications and chat transcripts remain user-scoped. Public survey controllers remain outside panel login and retain CSRF/throttling.

## 5. Mandatory security and correctness deviations

These are explicit rebuild requirements, not modifications to the current application. Preserve legitimate outcomes while closing these boundaries; product-sensitive scope changes remain decision-gated.

| ID | Existing evidence / risk | Rebuild requirement |
| --- | --- | --- |
| SEC-01 | Legacy PHP/framework/dependency baseline; historical audit counts are stale | Stable locked supported packages; current advisories and verified PHP 8.5 execution at M01/M11 |
| SEC-02 | Role editor drops non-rendered ticket.assign grant | Display/preserve all named permissions; test no-op edit preserves every grant |
| SEC-03 | UI-disabled fields and resolved assignment differ from server checks | Enforce approved per-field/state policy in actions; forged requests cannot bypass it (D05) |
| SEC-04 | Raw division JSON/HTML and color strings | Safe JSON/escaped output, constrained colors, sanitized assistant Markdown; no raw user HTML in labels/files |
| SEC-05 | Public/legacy file trees may bypass authorization | Private ticket/policy storage, record-bound downloads, classify static PDFs, verify no alias/public bypass |
| SEC-06 | Demo/shared-password source and differing account password rules | No demo credentials/PII in production seeders; approved provisioning/reset and credential-risk handling (D02/D09) |
| SEC-07 | Wildcard proxy trust, hardcoded redirects/connections and certificate trust | Environment-owned trusted hosts/proxies/TLS/session settings; external auth contract explicit; no disabled security checks to make integration work |
| SEC-08 | Dynamic resource actions/bulk APIs can grant new access | Strict missing-policy detection; no unrequested bulk actions; query scope and custom-action checks tested |
| SEC-09 | Unbounded transfer notes and independently mutable mounted IDs | Validate reason/response inputs and ownership; lock transitions; agreed bounds in D05 |
| SEC-10 | Provider/log data and stale job/runtime payload exposure | Bounded grounded prompts, redacted logs, no unsafe unserialization or old job/session/token replay |
| SEC-11 | Sensitive notification metadata may survive revoked ticket visibility | Check current scope before displaying/linking sensitive metadata; define imported-history treatment in D09 |

No new exploit, production vulnerability assessment or current advisory count is claimed. Detailed source findings are in the old [security guide](08-SECURITY-DOCUMENTATION.md) and the decision/rule registers.

## 6. Decision register and implementation gates

Recommendations are proposed defaults, **not user-approved changes**. Record owner/date/chosen outcome and update affected rules/tests before the dependent milestone is accepted. Unrelated approved modules can proceed in an isolated new project once rebuilding is separately requested.

| ID | Decision / missing evidence | Proposed default | Gate |
| --- | --- | --- | --- |
| D01 | Canonical source revision, live engine/schema and dirty changes | Freeze current working tree separately with a manifest; obtain approved schema metadata/snapshot | Before definitive import mapping and M10 |
| D02 | External HRDO login, registration, recovery, provisioning and approved account cohort | Standalone active-account auth only as a development fixture; no invented SSO protocol; production contract must be supplied | M02 production acceptance; unsafe demo accounts never carried |
| D03 | MySQL version/collation, timestamp meaning and deployment timezone | MySQL 8.4 LTS/utf8mb4; preserve UTC instants, display timezone explicitly; collision audit before collation choice | M03 schema/import design |
| D04 | Head list versus incoming-transfer visibility and multi-role precedence | One shared visibility query; show an incoming-transfer inbox; permissions additive but creator-only constraints remain | M04/M06; deliberate UI/scope change |
| D05 | Who may edit ticket content/files/status, resolved assignment, transfer note bounds | Creator/admin/division head content rule from UI intersected with operation access; heads retain permitted routing/status actions; forbid resolved assignment until reopen; 4000-character notes proposed | M06 security behavior; no silent policy choice |
| D06 | Numeric status 3 / department 1 / named HR group assumptions | Preserve IDs in import; seed semantic status codes and configured survey/report organization mappings | M03/M05/M08/M09 |
| D07 | Exact report/SLA/date/rounding semantics across Carbon 2 to 3 and SQL engines | Freeze existing per-metric fixtures first; preserve intended elapsed/integer semantics; approve any correction explicitly | M08 parity approval |
| D08 | Policy categories/effective dates, needs_review approval and deletion during processing | Preserve four browser and six CLI categories; no new review override; preserve current effective-date filter absence initially; lock/tombstone deletion coherently | Policy submilestone; product changes require sign-off |
| D09 | File classification, existing shared-password risk, notification/chat retention and inactive recipients | Private confidential files; preserve history in controlled import; revoke old sessions/tokens; reset identified unsafe credentials; do not invent historical events | M02/M07/M10 |
| D10 | RPO/RTO, downtime, final writer freeze and writes after cutover | Single-writer maintenance cutover; no dual-write system; post-write rollback needs new-write reconciliation | M10/M12 |
| D11 | Global report access and organization-specific policy administration | Preserve explicit current grant scope, not automatically give heads reports or narrow policy access | M04/M08 |
| D12 | Public URLs/base path, legacy links, notification URLs and API consumers | Preserve /employee-care, /survey and /arta-form; reviewed compatibility map; root-safe assets | M02/M09/M12 |
| D13 | PDF fonts/layout, QR destinations, kiosk browser/device support and provider model | Preserve current output formats/content; verify nonsensitive golden samples and scannable QR; provider selection stays Anthropic absent approval | M08/policy/M09 |
| D14 | Performance, expected volume, concurrent users, PDF/ingest limits and monitoring ownership | Preserve existing bounds; benchmark agreed representative fixtures; no invented SLA or capacity claim | M08/M11/M12 |
| D15 | Fresh versus imported RBAC grants and orphan/duplicate data repair | Seed fresh defaults; import actual grants without sync-reset; quarantine discrepancies for owner decision | M04/M10 |
| D16 | Active/roleless access, self-registration and employee first destination | Active membership is not administrator-only; employee landing retained; roleless behavior follows approved provisioning policy | M02/M04 |

## 7. Repository/documentation disagreements resolved by inspection

- Preliminary context says MySQL, while source and historical operational evidence include SQL Server. Treat origin engine as **Not Verified now**; write an explicit SQL Server-to-MySQL transformation plan.
- Ticket.update seeded to staff/employees does not enable generic edit. Custom workflow actions have their own checks. Do not build policies from seed grants alone.
- The TicketReportWidgets comment calls dashboard fixed all-time, while DashboardController calls ReportPeriodResolver. Use controller-selected periods as the runtime contract. The active query path is TicketReportQueries; legacy helper methods alone are not the whole report implementation.
- Source locks Carbon **2.73.0**; Laravel 13 uses a newer Carbon range. SQL Server DATEDIFF and MySQL TIMESTAMPDIFF also differ at boundaries. Existing description '72 hours' is insufficient without per-metric tests.
- Queue success is enqueue success. Metadata-only edits remain immediate. Browser and CLI policy category/replacement behavior differ intentionally in source.
- Policy model comments mention human approval of needs_review, but the actual toggle handler admits active/inactive only. Do not invent an approval action.
- Four stored ticket notification flags do not cover transfer flags; User::wantsNotification defaults absent flags to true. Preserve that distinction or explicitly design new preference columns.
- Prior guides/Node results are historical dated evidence. No new app test pass, live schema census, current production audit, or screenshot session was obtained for this specification.

## 8. Definition of done for the future rebuild

All [19 checklist](19-FUNCTIONAL-PARITY-CHECKLIST.md) items have implementation/test evidence or an explicitly approved deviation; blocking decisions are resolved; migrated counts/relationships/files/history reconcile; new MySQL and browser/security tests pass; provider/OCR/PDF outputs are accepted; workers/backup/restore/rollback are rehearsed; one authorized writer/cutover procedure is signed off. A running Filament dashboard or generated resource is insufficient evidence.

No production activation is authorized by this specification. The next implementation task must name a separate new-project location and approved scope.
