# 14 - Change History and Maintenance Register

## History provenance

Git HEAD inspected: **4927f4e**, with existing uncommitted code/config/migration/test/documentation changes. There are **39 commits** reachable from HEAD and no tags returned by git tag --list at inspection. Dates below are Git commit dates, **not verified release/deployment dates**. A commit subject identifies recorded intent; it is not proof that the change was deployed or that every related defect is resolved.

| Commit | Git date | Recorded subject |
| --- | --- | --- |
| 4927f4e | 2026-10-12 | Apply EmployeeCare security and workflow remediations |
| ba75ce6 | 2026-10-06 | Update PWA feature for Kiosk mode |
| 0c098c2 | 2026-07-23 | update policy document file manager re-run and delete feature |
| fc637a1 | 2026-07-23 | add date range filter for dashboard page |
| 47a116c | 2026-07-23 | apply optimization on audit reports |
| b2a94f2 | 2026-07-23 | improve reports and dashboard page |
| ddf2d36 | 2026-07-22 | Add new submit ticket page and update each user roles access and guards |
| 2ca7b35 | 2026-07-21 | optimization |
| 23c976d | 2026-07-20 | revise chatbot recents and memory retention feature |
| c522bf1 | 2026-07-15 | ticket assignement/reassignement improvements, ai chatbot improvements, ocr uploads support, notification improvements |
| 730a02e | 2026-07-13 | OCR file support update |
| 1898539 | 2026-07-13 | 07/13/2026 |

Use git log/show for the actual patch when investigating a historical behavior. The documentation does not rewrite commit history or assign release versions that do not exist.

## Current-source changes beyond HEAD

Source inspection includes versioned policy ingestion markers/queue readiness, conservative attachment reconciliation, Unicode attachment migration, chat reliability changes and associated tests that appear in the dirty working tree. Their operational notes are in [POLICY_INGESTION_QUEUE.md](POLICY_INGESTION_QUEUE.md), [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md), [MEDIUM_FIXES.md](MEDIUM_FIXES.md) and [HR_AI_CHATBOT.md](HR_AI_CHATBOT.md). Some of those documents are themselves untracked/modified. Do not assign them a release number or deployment date from their filenames.

## Documentation change - 2026-10-27

Added the numbered system/developer/operations/user guides, implementation and route inventories, traceability matrix, consolidated diagrams, gaps, verification evidence and coverage report. Updated the project README to preserve project identity, repository provenance, OCR setup references and license declaration while removing published login credentials from quick-start instructions. Existing supplemental documents were retained. No application source, migrations, configuration or dependencies were intentionally changed for documentation.

## Technical debt and known inconsistencies

| Item | Evidence | Maintenance action proposed |
| --- | --- | --- |
| Legacy runtime/framework lock | PHP 7.4 project, Laravel 8.x-dev lock | Separately scoped upgrade/advisory review |
| Distributed authorization | Controllers/scopes/components; empty policy mapping | Document/test boundaries; consider deliberate policy consolidation |
| ticket.assign omitted by grouped role UI | RoleCrudController renders four standard actions only | Correct editor and preserve unrendered grants |
| Numeric initial status / organizational assumptions | Ticket default 3; survey/report department 1 | Migrate carefully toward configured/named semantics |
| Incoming transfer list mismatch | visibleTo destination allowance plus current-location list filter | Review receiving-head navigation and acceptance tests |
| Generic edit field restrictions are partly UI rules | TicketCrudController setupUpdateOperation | Enforce approved field boundaries server-side |
| Resolved assignment mismatch | UI disabled, quick handler lacks state prohibition | Decide expected rule and test direct request |
| Model/schema drift | Region has no migration; Survey submission_date absent | Confirm intended usage before introducing schema |
| Policy channels differ | Browser async/categories vs CLI synchronous/additional categories | Maintain explicit contracts; align only with approval |
| Policy deletion concurrency | destroy lacks processing guard; jobs version/missing-record checks | Rehearse deletion during queue/extraction safely |
| Empty automatic schedule | Console Kernel has no active tasks | Define retention/backup/supervision operational ownership |
| Historical docs/advisories | AUDIT_REPORT predates multiple changes | Refresh findings rather than copying old counts |
| Missing license file | composer/original README declare MIT; no LICENSE file found | Confirm ownership/license and add approved license text |

For full detail see [DOCUMENTATION-GAPS.md](DOCUMENTATION-GAPS.md). These are not changes implemented by this package.

## Maintenance procedure

1. Record source revision, dirty state, intended behavior and exact environment.
2. Reproduce in isolated test storage/database; inspect relevant guards, migrations and operational dependencies.
3. Implement a focused separately authorized change; preserve PHP/Laravel compatibility unless upgrade scope explicitly changes it.
4. Run targeted verification and engine/browser checks where relevant; never substitute old results.
5. Update affected guide, route/schema inventory, traceability and known-gap status.
6. Release using the approved backup/rollback/worker procedure and record actual deployment evidence.

Keep operator checklists in [deployment](09-DEPLOYMENT-AND-MAINTENANCE.md), detailed test evidence in [QA](12-TESTING-AND-QA.md), and unresolved facts in the gap register rather than duplicating them across change entries.
