# Browser fixes and verification — 2026-10-29

This records changes to the upgraded PHP 8.5 / Laravel 13 / Backpack 7 / CoreUI v4 application. Existing unrelated working-tree changes were preserved. Root environment settings and the main application database were not changed. Results below are local evidence, not deployment or production-capacity certification.

## Changes

| Reported area | Implemented behavior |
| --- | --- |
| Transparent dropdowns | Application CSS supplies opaque backgrounds and readable text for native selects, Tom Select and dropdown menus, including Bootstrap/CoreUI variable compatibility. |
| Dashboard widgets | Explicit contrasting icon/text colors, larger values and responsive card/chart columns. |
| Attachment selection | Fixed the JavaScript exception caused by an obsolete remove-button selector. Selecting, dropping and removing files updates the preview and actual submitted file list; filenames use text output. |
| Ticket validation | Friendly issue, custom-description, department/division, message and attachment messages. Department/division coherence remains validated. |
| Automatic assignment | Shared active HR-staff eligibility and coherent office restrictions between automatic and manual selection. Standard issues retain skill-first ranking, then active workload and ID; custom issues use workload and ID. Missing department leaves the ticket unassigned. Concurrent submissions can observe the same workload; strict round-robin fairness under concurrency is not guaranteed. |
| Notifications | Badge anchored to the bell, bounded responsive dropdown and wrapping notification text. |
| Resolve/reopen performance | Action-only requests skip building the entire edit form. Resolve, reopen and quick assignment use the existing transaction/row-lock action path and recheck fresh state and visibility. |
| Icon picker | Searchable, paginated local Font Awesome glyph library with preview and selectable icons. Existing stored classes remain supported. |
| Policy processing | Authorized status endpoint and automatic list refresh every four seconds while documents are pending/processing. Hidden tabs pause polling; errors provide a manual-refresh instruction. Completion displays the document result, including failure/review states. |
| Chat memory | Conversation-owned recent questions and validated source references preserve the latest subject for follow-ups. Retrieval rechecks currently active policies and supports topic changes; provider history is bounded. |
| Chat readability | Bounded, independently scrolling transcript, scrollable conversation list and contrasting text. |
| Maintenance | Compatible npm dependency fixes, regression coverage and documentation/runtime corrections. No broad rewrite or live data cleanup. |

Main source locations: [theme CSS](../public/css/backpack-theme.css), [ticket controller](../app/Http/Controllers/Admin/TicketCrudController.php), [upload field](../resources/views/vendor/backpack/crud/fields/modern_upload.blade.php), [validation](../app/Http/Requests/TicketRequest.php), [assignment rules](../app/Services/TicketAssignmentRules.php), [assignment service](../app/Services/TicketAutoAssignmentService.php), [icon picker](../public/js/icon-picker.js), [policy polling](../public/js/policy-status.js), [retrieval](../app/Services/PolicyRetriever.php) and [chat controller](../app/Http/Controllers/Admin/HrChatController.php).

The policy list refresh does not start a worker. Keep one supervised listener/worker consuming the configured `policy_ingestion` connection and `policy-ingestion` queue. A saved or queued PDF is not evidence that extraction finished. The final status must become active, needs_review or failed.

Chat memory remains bounded, keyword-based policy retrieval rather than unlimited or semantic memory. Tests cover English/Taglish procedural follow-ups, several successive turns, explicit topic changes, inactive documents and conversation ownership. Ambiguous questions may still require clarification. No live paid-provider quality evaluation was performed.

Follow-up correction: a browser check found that Backpack's `common.css` forces
`overflow: visible !important` on all cards and card bodies, overriding both earlier
chat scroll rules. The HR Assistant view now overrides that rule only for the chat card
and transcript. Chrome confirmed a loaded conversation scrolls inside the card while
the header stays in place. Retrieval now keeps the named policy for English-to-
Tagalog follow-ups such as “How to file it?” and “Pwede bang Tagalog ang pagsagot mo
sakin?”, while a newly named policy changes the topic. Focused regression checks:
60 PHP tests and 272 assertions passed. These are separate from the earlier full-suite
result below; live Claude wording was not exercised.

## Verification

- Full isolated PHP regression suite, including sample PDF/OCR fixtures: **370 tests, 2,766 assertions passed** (3 minutes 38 seconds).
- JavaScript: **57 tests passed**, including execution of the actual attachment script, icon search/pagination/selection and policy polling transitions/failures.
- Production build: passed using temporary output; published assets preserved.
- Changed PHP syntax, CSS parsing, Composer manifest/platform checks and whitespace review: passed.
- Composer locked dependency audit: **zero advisories and zero abandoned packages** on this date.
- npm audit after compatible fixes: **12 entries: 5 low, 5 moderate, 2 high**. Remaining high entries are in the webpack development-server dependency chain; no forced major upgrades were applied. Review a separately scoped build-tool migration before treating the dependency review as clean. Avoid exposing the development server publicly.
- The 36 existing documentation Markdown files were scanned for structure/runtime references and relative links. Current-runtime addenda and relevant setup, development, security and chatbot guides were updated. Final relative-link validation covered 37 Markdown files, including this report, with no broken links. Dated rebuild proposals and historical result counts remain explicitly historical.

### SQL Server and performance

Tests used a separately named synthetic SQL Server test database, not the database selected by the application's root environment file. The test database was retained for inspection.

| Check | Actual result |
| --- | --- |
| Model workflow stress | Three concurrent workers, 10,000 seeded tickets, 90 create → automatic assignment → resolve → reopen cycles: **270 operations, zero errors**. Mean complete cycle 642 ms, p95 2,725 ms. |
| HTTP resolve/reopen comparison | 20 requests per version, zero errors: mean **533 ms before / 171 ms after**; median 298 / 164 ms. These small local samples are not an SLA. |
| Concurrent mixed HTTP load | **Did not pass:** requests timed out at 60 seconds. Intermittent local SQL Server connection/integrated-authentication failures were also observed. The complete timeout cause and sustained web capacity remain unresolved; successful model stress does not certify HTTP capacity. |
| MySQL | No new MySQL engine certification in this task. |

Native Chrome inspection covered selected dashboard, notification and ticket-preview surfaces. Interactive checks were interrupted by user input; final responsive changes and all browser workflows have not received complete visual acceptance. Automated DOM tests do not replace a real-browser layout check.

Private local evidence is under `storage/app/browser-fixes-backup/`: source snapshots, PHP/JUnit and JavaScript results, production-build output, dependency audits and synthetic SQL Server metrics. Temporary executable load helpers and synthetic login settings are removed after testing.

## Remaining acceptance work

1. Recheck the reported screens in a fresh browser load at desktop and mobile widths, including ticket file selection/removal and icon selection.
2. Retest sustained HTTP concurrency on the intended web server with stable SQL Server connectivity; investigate timeouts before capacity sign-off.
3. Verify live AI answers, external SSO, mail delivery and deployment-specific permissions/storage separately. Provider tests use fakes; no external messages were sent.
4. Address the remaining dependency and previously documented security/deployment findings in [security documentation](08-SECURITY-DOCUMENTATION.md). This work is not a penetration test or assurance that every historical finding is resolved.
