# 06 - Developer Guide

## Orientation and conventions

Read [AGENTS.md](../AGENTS.md), [architecture](02-SYSTEM-ARCHITECTURE.md), [functional specification](04-FUNCTIONAL-SPECIFICATIONS.md) and [implementation inventory](IMPLEMENTATION-INVENTORY.md) first. Preserve PHP 7.4/Laravel 8 compatibility. Use App namespaces, Eloquent/Query Builder, focused patches and existing service boundaries. Current working-tree changes are part of the documented snapshot and must not be discarded.

| Folder | Contents |
| --- | --- |
| app/Http/Controllers/Admin | Backpack CRUD, account, reports, assistant and file access |
| app/Http/Requests | Catalog/ticket validation |
| app/Http/Livewire | Ticket discussion |
| app/Models | Eloquent, casts, scopes, lifecycle hooks |
| app/Services | Assignment, attachments, reports, OCR, ingestion and providers |
| app/Jobs, app/Notifications, app/Observers, app/Listeners | Policy job, database notification, ticket assignment, login timestamp |
| app/Console | Maintenance commands and currently empty schedule |
| routes/backpack | Custom panel routes and permission-manager overrides |
| database/migrations, database/seeders | Schema lineage and reference/demo initialization |
| resources/views/vendor/backpack | Local Backpack overrides, account, menu, dashboard and layouts |
| public/survey-assets, public/arta-assets, public/js/hr-chat-core.js | Hand-maintained browser behavior |
| tests/Unit, tests/Feature, tests/Engine, tests/JavaScript | Runtime-isolated and opt-in verification |

## Add a module using existing patterns

1. Confirm requirements, roles, data scope, validation and failure behavior. Inspect the [schema](03-DATABASE-DOCUMENTATION.md); do not infer tables from model names.
2. Create additive migration/model/controller/request files compatible with this runtime. Available Laravel commands include:

```powershell
php artisan make:model ExampleRecord
php artisan make:migration create_example_records_table
php artisan make:controller Admin/ExampleRecordCrudController
php artisan make:request ExampleRecordRequest
```

These commands generate code and are examples for future development, not commands executed by this task. Backpack generators are installed; consult `php artisan list`/command help before using generator flags rather than assuming a newer version's syntax.

3. Follow existing CRUD setup: model/route/entity names; require entity.view; deny create/update/delete by default; allow each operation only with the corresponding permission; configure fields/columns and FormRequest.
4. Register actual routes in the appropriate file and role permissions in an approved seeder/migration. Updating a seeder alone does not update existing installations until deliberately executed.
5. Add menu entries only after backend enforcement; navigation visibility is not authorization.
6. Add focused tests for ordinary, invalid, denied and cross-scope actions. Review file, route and schema documentation alongside the change.

## Authorization and validation

No application policy classes are currently registered. Reuse controller gates and Ticket::visibleTo for current behavior, or introduce policies only as an intentional scoped change. Do not describe CheckIfAdmin as an administrator-only gate. Match Spatie's configured guard; users.role is a legacy numeric field.

FormRequest::authorize returning authenticated is only one layer. Custom `_ticket_action` handlers branch before generic update validation; each must validate and authorize independently. Client-side disabled fields do not protect a server attribute. Validate related IDs together, including division/department and assignee scope.

Source patterns: [TicketRequest](../app/Http/Requests/TicketRequest.php), [IssueRequest](../app/Http/Requests/IssueRequest.php), [TicketAssignmentRules](../app/Services/TicketAssignmentRules.php), [TicketCrudController](../app/Http/Controllers/Admin/TicketCrudController.php).

## Modify ticket behavior carefully

- Trace controller action, model save hooks, observer, assignment service and notifications. A create can trigger a nested save; keep SQL Server identity handling and initial-status notification suppression intact.
- Preserve the issue-change guard: rewriting issue-derived location on every save would undo transfers.
- Use shared eligibility logic for dropdowns and backend writes. Skill scoring/workload tie-breaking are observable rules, not incidental implementation details.
- Transfer actions lock parent/pending state in a transaction. Preserve after-commit notifications and file cleanup.
- Assignment/status changes update timestamps. Reopening clears resolved metadata; overdue uses original creation time. Test exact boundary semantics before changing them.
- Do not bypass model deletion for comments/tickets without arranging attachment cleanup. Query-builder bulk deletes skip lifecycle callbacks.

## Notifications and jobs

To add a notification, use the existing service's candidate/role/preference filtering and TicketSystemNotification payload shape. Adding a Queueable trait does not queue it; this class is database-channel only and lacks ShouldQueue. Any email or queued-delivery feature requires separate behavior/configuration/tests.

Policy work explicitly uses policy_ingestion/policy-ingestion. Keep timeout 900 below retry_after 1200, version/claim guards, atomic enqueue marker and chunk commit. Do not switch browser work to the default sync connection or add a per-upload worker launcher. See [queue guide](POLICY_INGESTION_QUEUE.md).

## Files, Blade and JavaScript

Use configured disks, randomized names, validated retained paths and reference-aware compensation. Keep protected links pointing to ticket/comment routes; public/storage is for public assets, not new ticket documents.

Escape HTML with Blade/e(), use safe JSON embedding, and use textContent for filenames/skills. Rich assistant text goes through the shared chat-core handling; verify its escaping/sanitization tests when modifying it. Backpack field HTML and inline scripts require particular review. Existing styling mixes local Bootstrap/jQuery with CDN libraries; Mix builds resources/js/app.js and resources/css/app.css, not every custom public file.

The survey worker has a versioned allowlist. Do not cache protected panel pages, response-bearing documents or CSRF credentials. Submission UUID must survive retries and change for the next respondent. Offline viewing must not claim offline persistence.

## Verification and debugging

See [testing guide](12-TESTING-AND-QA.md) for exact commands and current results. PHPUnit XML forces in-memory SQLite; the shared helper asserts isolation and fakes storage/HTTP. Never relax those checks to make tests connect to application data. Engine tests and specifically authorized data probes require separate review.

Useful tools: PHP syntax checking, route metadata inspection, Node tests, browser developer tools, protected logs and targeted PHPUnit filters. Trace actual browser uploads when diagnosing UI issues. Check pending policy job/worker state before blaming OCR. Never log keys, questions, transcripts or employee file contents during diagnostics.

## Common mistakes

| Mistake | Consequence |
| --- | --- |
| Treating view permission as CRUD authorization | Unauthorized writes |
| Relying on a hidden button/disabled field | Direct requests bypass presentation |
| Hardcoding new status IDs | Environments drift; existing default 3 is already debt |
| Recomputing issue location on every save | Accepted transfer is reverted |
| Switching database queue default to jobs without a migration | Missing queue table |
| Serving private files through public links | Scope checks are bypassed |
| Retrying provider calls automatically | A lost response may already have been billed |
| Running all engine tests against a real application DB | Unapproved data/schema changes |
| Reusing old audit pass counts as current evidence | False verification claim |

## Documentation upkeep

Update the affected numbered guide, traceability row and gaps when behavior changes. Regenerate route/migration inventories using an isolated configuration; `artisan route:list` may instantiate password-recovery controllers that query schema. A docs-only route inspection must not inherit live database credentials. Record snapshot commit plus dirty state, tests actually executed and every unresolved assumption.
