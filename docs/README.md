# EmployeeCare Documentation Index

Snapshot: **2026-10-27**, current working tree at HEAD 4927f4e including existing uncommitted changes. Source behavior is distinct from live deployment. Missing facts are marked **Not Verified**. No application changes or production operations were authorized/performed by this documentation package.

## Start here

- New developer: overview, architecture, database, installation and developer guide.
- Administrator/user: administrator and role-specific manuals.
- Operator: security, deployment, testing and troubleshooting.
- Reviewer/handover owner: traceability, gaps, diagrams and coverage report.

## Numbered guides

| Guide | Contents |
| --- | --- |
| [01-SYSTEM-OVERVIEW.md](01-SYSTEM-OVERVIEW.md) | Purpose, users, verified technology and limits |
| [02-SYSTEM-ARCHITECTURE.md](02-SYSTEM-ARCHITECTURE.md) | Components, request lifecycle, storage, integrations and sequences |
| [03-DATABASE-DOCUMENTATION.md](03-DATABASE-DOCUMENTATION.md) | Table catalog, FK distinctions, indexes, ER diagrams and all migration excerpts |
| [04-FUNCTIONAL-SPECIFICATIONS.md](04-FUNCTIONAL-SPECIFICATIONS.md) | Modules, inputs, validation, rules, role/action matrix and failures |
| [05-INSTALLATION-GUIDE.md](05-INSTALLATION-GUIDE.md) | Isolated development setup, configuration, OCR and startup |
| [06-DEVELOPER-GUIDE.md](06-DEVELOPER-GUIDE.md) | Code orientation, extension patterns and engineering safeguards |
| [07-ROUTES-AND-API.md](07-ROUTES-AND-API.md) | All registered route methods, names, actions, declared middleware and contracts |
| [08-SECURITY-DOCUMENTATION.md](08-SECURITY-DOCUMENTATION.md) | Verified controls, boundaries, weaknesses and proposed improvements |
| [09-DEPLOYMENT-AND-MAINTENANCE.md](09-DEPLOYMENT-AND-MAINTENANCE.md) | Release prerequisites, workers, backups, maintenance and rollback |
| [10-ADMINISTRATOR-MANUAL.md](10-ADMINISTRATOR-MANUAL.md) | Source-defined admin procedures and screenshot checklist |
| [11-USER-MANUAL.md](11-USER-MANUAL.md) | Employee, staff, heads, administrator and public respondent instructions |
| [12-TESTING-AND-QA.md](12-TESTING-AND-QA.md) | Suite inventory, safe execution, current results and manual cases |
| [13-TROUBLESHOOTING.md](13-TROUBLESHOOTING.md) | Symptom-based diagnostics and safe recovery guidance |
| [14-CHANGELOG-AND-MAINTENANCE.md](14-CHANGELOG-AND-MAINTENANCE.md) | Verified Git history, current-source changes and technical debt |

## Modern rebuild specifications - 2026-10-28

These six documents describe a proposed separate Laravel13/PHP8.5/Filament5/MySQL application. They do not change the existing application or authorize rebuilding/migration/cutover. Start with15; resolve its decisions before dependent milestone acceptance. Existing guides01-14 remain the current-application reference.

| Specification | Purpose |
| --- | --- |
| [15-REBUILD-SPECIFICATION.md](15-REBUILD-SPECIFICATION.md) | Verified official compatibility, architecture, security deviations, decisions and readiness |
| [16-BACKPACK-TO-FILAMENT-MAPPING.md](16-BACKPACK-TO-FILAMENT-MAPPING.md) | All ten CRUD modules, fields, validations, actions, custom components and public workflows |
| [17-BUSINESS-RULES.md](17-BUSINESS-RULES.md) | Authorization/formula register and 83 source-grounded business rules |
| [18-DATABASE-MIGRATION-PLAN.md](18-DATABASE-MIGRATION-PLAN.md) | Table/field/identity/grant/file/history transformations, verification and write-aware rollback |
| [19-FUNCTIONAL-PARITY-CHECKLIST.md](19-FUNCTIONAL-PARITY-CHECKLIST.md) | 83 paired acceptance items with source, target, required tests and migration considerations |
| [20-REBUILD-IMPLEMENTATION-ROADMAP.md](20-REBUILD-IMPLEMENTATION-ROADMAP.md) | Twelve milestones, mandatory policy/chat workstream, dependencies and acceptance gates |

## Evidence and supporting references

- [IMPLEMENTATION-INVENTORY.md](IMPLEMENTATION-INVENTORY.md): analysis inventory, plan, source/test/frontend files and dependency constraints.
- [TRACEABILITY-MATRIX.md](TRACEABILITY-MATRIX.md): features mapped to routes, controllers/services/models and available tests.
- [DOCUMENTATION-GAPS.md](DOCUMENTATION-GAPS.md): unverified environment facts, source inconsistencies and confirmation priorities.
- [DIAGRAMS.md](DIAGRAMS.md): nine consolidated architecture/sequence/ER/workflow diagrams.
- [DOCUMENTATION-COVERAGE-REPORT.md](DOCUMENTATION-COVERAGE-REPORT.md): final coverage, validation, scope and limitations.
- [QA-JAVASCRIPT-RESULTS.md](QA-JAVASCRIPT-RESULTS.md): actual 48-test Node execution output.
- [route-inventory.json](route-inventory.json): machine-readable snapshot of 149 registered routes.
- [documentation-validation.json](documentation-validation.json): final link/anchor/fence, inventory and action-reflection results.

## Existing operational and historical documents

Retained documents may describe earlier states/results. Read the gap register before treating them as current evidence.
- [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md)
- [HR_AI_CHATBOT.md](HR_AI_CHATBOT.md)
- [MEDIUM_FIXES.md](MEDIUM_FIXES.md)
- [POLICY_INGESTION_QUEUE.md](POLICY_INGESTION_QUEUE.md)
- [superpowers/plans/2026-07-22-reports-analytics-widgets.md](superpowers/plans/2026-07-22-reports-analytics-widgets.md)
- [superpowers/plans/2026-07-22-reports-page-revisions.md](superpowers/plans/2026-07-22-reports-page-revisions.md)
- [superpowers/plans/2026-07-23-audit-fixes.md](superpowers/plans/2026-07-23-audit-fixes.md)
- [Historical full audit](../AUDIT_REPORT.md).

## Documentation rules

Link important technical claims to exact source paths; keep diagrams and inventories aligned when behavior changes. Never copy environment secrets, employee records or published default passwords. Preserve distinctions between role grants and runtime gates, migration declarations and live schema, available tests and executed results, and existing features and proposals. Keep unresolved facts in the gap register.
