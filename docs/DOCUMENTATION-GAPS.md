# Documentation Gaps and Inconsistencies

Snapshot: **2026-10-27**. `Not Verified` means evidence is absent or the operation was not safely executed. A source inconsistency is not automatically a reproduced production defect. This task changes documentation only; no remediation or live operational approval is implied.

## Unverified environment and operational facts

| ID | Item | Evidence available | Required confirmation / safe next step |
| --- | --- | --- | --- |
| G01 | Deployed revision and dirty changes | HEAD 4927f4e; many working-tree changes | Identify exactly what is deployed; do not infer release from Git date |
| G02 | Actual database schema/version/collation/indexes | 33 migrations and engine-specific code | Approved read-only metadata inventory, then isolated engine rehearsal |
| G03 | Browser PHP/runtime/extensions | CLI previously observed 7.4.14 | Verify web/worker binaries and php.ini without exposing phpinfo publicly |
| G04 | External login / shared identity/session contract | LoginController redirects outside local; separate connectsession config | Integration owner must define session/guard/provisioning/logout behavior; configured connection does not prove active use |
| G05 | Server, TLS, proxy and legacy-file denial | Apache .htaccess, wildcard trusted proxy, cookie and SQL TLS defaults | Verify deployed web server rules and certificate/trusted-proxy boundaries |
| G06 | Continuous worker/service manager | Dedicated queue/job and documented listener command | Confirm service owner, restart strategy, effective timeout and backlog health |
| G07 | OCR installation and languages | Tool paths/config/diagnostic code | Run approved diagnostics under actual web/worker identity with nonsensitive samples |
| G08 | Provider keys/model availability and data handling | Config names and selected Anthropic adapter | Confirm provider account/model and confidential-data policy; no external call made |
| G09 | Email delivery | Mail config and registered recovery routes | Verify approved password-recovery flow; ticket notification is database-only |
| G10 | Backups/restoration, RPO/RTO and retention | No implemented backup schedule or active application scheduler | Assign owner, document infrastructure and rehearse isolated restoration |
| G11 | Browser/mobile/kiosk acceptance | Source/UI assets and Node VM tests | Execute real browser tests, secure context, safe worker update/reset and subdirectory checks |
| G12 | Current PHP/engine/build/security verification | Existing tests and historic reports | Run separately approved checks; PHP/engine/build suites not executed here; advisory counts not refreshed |
| G13 | Secure production admin provisioning | Demo seeder restricted to local/testing; no dedicated provisioning command | Confirm approved account bootstrap, unique passwords and rotation of previously seeded shared credentials |
| G14 | License ownership and artifact | Composer/original README say MIT; no LICENSE file | Obtain owner-approved license text and distribution decision |

## Confirmed source inconsistencies and limitations

| ID | Observation | Source and implication |
| --- | --- | --- |
| I01 | Preliminary context says MySQL; repository supports and contains SQL Server-specific behavior | config/database.php, SqlDialectHelper and SQL-specific migrations. Engine defaults are not the deployed-engine inventory |
| I02 | Role editor omits ticket.assign | RoleCrudController renders view/create/update/delete only; syncPermissionsFromRequest replaces grants with submitted IDs. Existing hidden grant can be removed |
| I03 | ticket.update grant differs from generic operation availability | Seeder grants staff/employees update, but TicketCrudController allows their generic create only. Custom workflow checks are separate |
| I04 | Pending incoming-transfer scope differs from list scope | Ticket::visibleTo includes destination; setupListOperation narrows current location. Incoming detail may be valid but absent from normal list |
| I05 | Generic edit read-only fields rely partly on presentation | setupUpdateOperation disables/removes UI controls; ordinary update delegates to Backpack. No equivalent comprehensive server field policy was verified |
| I06 | Resolved quick-assignment UI differs from handler | Show disables control, handleQuickAssign lacks explicit resolved-state prohibition |
| I07 | Hardcoded lifecycle/environment assumptions | Initial status=3, survey/report department=1, fixed already-authenticated redirect path and separate hardcoded database connection |
| I08 | Region model lacks repository table/module | No regions create migration found; ARTA region labels are constants, not a region relationship |
| I09 | Survey fillable includes nonexistent migration field | submission_date appears in Survey but no migration column; runtime requests do not supply it |
| I10 | Generic jobs connection has no jobs migration | Policy queue uses separately migrated policy_jobs; do not switch default database queue expecting jobs to exist |
| I11 | Browser and CLI policy ingestion differ | Browser queues, supports four categories and in-place replacement; CLI is synchronous, supports conduct/discipline, checks filename duplicates and --force destroys/recreates |
| I12 | Policy deletion is not protected by processing-state edit guard | PolicyIngestService::destroy deletes transactionally but does not call editableDocument; job missing/version checks mitigate stale work, not a tested deletion lock |
| I13 | Effective date is not a retrieval eligibility filter | PolicyRetriever checks active flag/status only; future/expired-date handling not implemented |
| I14 | Overdue boundary is not a configurable business-day SLA | Open three elapsed days, resolved integer hours >72; original creation time on reopen, no escalation task |
| I15 | Registration and password paths differ | Registration assigns no role; admin user form has different password validation; no first-use password-change workflow found |
| I16 | Exposed registration/lookup/package aliases need deployment review | Divisions lookup lacks declared auth/throttle; GET logout and duplicate named account aliases remain registered by installed packages |
| I17 | Some route machinery queries schema | Ordinary isolated route:list fails when recovery constructor checks users.email; raw route collection works without controller construction |
| I18 | Route cache is not verified | Closures and duplicate names remain; do not list route:cache/optimize as known successful deployment steps |
| I19 | Static policy files have separate exposure path | Files under public/policyDocs are public-root artifacts, unlike default private uploaded policies. Classification/access intent unverified; no contents read |
| I20 | Alternate providers are not equivalent failover | Active controller injects Anthropic; Gemini/OpenRouter source exists. GPTService has different prompt/fallback semantics |
| I21 | Report access is permission-based and broad | reports.view/survey-reports.view do not automatically apply Ticket::visibleTo; some staff data is department-1 specific |
| I22 | Input/output hardening review remains | Inline division JSON, color strings and unbounded transfer note inputs require focused review; no exploit was attempted |
| I23 | Database FK and application relationship differ | Chat users/conversations/log IDs, notification morphs and session user IDs lack the matching user/conversation FKs; see database guide |
| I24 | Data-changing migration compatibility not established | Bounded question column may reject/truncate overlong legacy values depending on engine settings; backfill mutates historical conversation data. No safe live rehearsal performed |

## Existing documentation discrepancies

| Document | How to interpret it now |
| --- | --- |
| Original project README | Retained project identity/repository/OCR references/MIT declaration; replaced broad unverified database claims, one-step migrate/seed and published credential instructions |
| AUDIT_REPORT.md | Historical 2026-07-23 review; source now has operation-specific CRUD gates, comment type validation, randomized/private attachments, chat throttles and local/testing demo-seeder guards. Do not repeat the original findings/counts as freshly reproduced |
| MEDIUM_FIXES.md | Historical remediation and follow-up results; current source may include later uncommitted behavior. Pass counts are not this run |
| DEPLOYMENT_READINESS.md | Useful operational/engine notes; not proof this documentation task deployed, migrated, backed up or certified a server |
| POLICY_INGESTION_QUEUE.md | Dedicated async browser ingestion/current operator procedure; no authorization to start/stop a live worker from documentation |
| HR_AI_CHATBOT.md | Earlier AI overview; source now bounds context/history, isolates conversations and uses sanitized provider failures. Source and numbered functional guide take precedence for current contract |
| docs/superpowers/plans | Proposed/historical plans, not guaranteed shipped features or current acceptance results |

## Explicitly absent or not established

No Filament implementation, general system-settings CRUD, automatic ticket escalation scheduler, offline survey submission outbox, comprehensive immutable audit trail, verified WebSocket ticket transport or ticket email notification channel was found. Backup/retention/supervision infrastructure may exist outside the repository but is **Not Verified**.

## Developer confirmation priorities

1. Confirm deployment/runtime/database identity and external authentication/session ownership.
2. Decide expected ticket.assign editor behavior, server edit-field restrictions, resolved assignment, incoming-transfer list visibility and deletion-during-ingestion policy.
3. Confirm department-1/status-3 assumptions, global report/policy access and future policy effective-date requirements.
4. Establish safe production account provisioning, static-file classification, provider data handling, retention and backup/restore ownership.
5. Approve isolated engine/browser/PHP checks and a dependency review if current operational certification is required.

No questions above block documenting the verified source. They block claims about live configuration, expected policy decisions or complete operational readiness.
