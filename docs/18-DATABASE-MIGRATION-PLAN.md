# 18 - Database and File Migration Plan

Prepared **2026-10-28**. **Plan only.** No database was connected, queried, migrated or modified for this task; no application/employee files were copied or deleted. Source engine/schema, row counts, collation, historical timezone and actual deployed revision are **Not Verified**. Historical SQL Server evidence in [deployment notes](DEPLOYMENT_READINESS.md) is not a current metadata inventory.

The target is a separately provisioned **MySQL** database for the new Laravel 13 app. Do not point the existing application's DB_CONNECTION at MySQL and run its migration chain expecting a cross-engine transfer. Old schema migrations are lineage/evidence, not a tested ETL tool. See [existing schema and all migrations](03-DATABASE-DOCUMENTATION.md), [decisions D01-D15](15-REBUILD-SPECIFICATION.md#6-decision-register-and-implementation-gates) and BR-O02/O03 in [17](17-BUSINESS-RULES.md).

## 1. Migration prerequisites and gates

Before writing/running a real importer:

1. Freeze the **approved code baseline**, including reviewed uncommitted work and matching frontend assets. Record hashes, lockfiles and chosen schema mapping version. Git HEAD alone does not capture this checkout.
2. Obtain approved read-only metadata/data access or a consistent protected source snapshot. Inventory all real tables/views/triggers/defaults/indexes/FKs, custom columns, migration history, maximum IDs/lengths and data volumes. No source credentials go into documentation or fixtures.
3. Confirm origin engine/version, collation, identity semantics and timestamp meaning; source supports MySQL and SQL Server. Reconcile deployed schema with migration files before finalizing target DDL.
4. Approve MySQL version/collation/UTC policy, data-retention/classification, credential risk, external identity relationship and exceptions (D02/D03/D09/D15).
5. Provision an empty isolated MySQL target with restricted import/application accounts, tested backups, space for data/files/temporary copies and engine-specific tests. Confirm target identity before any writes. Never reuse production connection names implicitly.
6. Define single-writer freeze/cutover ownership, acceptable downtime/RPO/RTO and post-write rollback (D10). Pending jobs and all HTTP/Livewire/kiosk/CLI writers need an explicit plan.

A DBA inventory can reveal additional objects. Every unexpected object gets a disposition; no table/data is dropped merely because it is absent from repository migrations. Maintain a counts/exceptions manifest covering it.

## 2. Target schema strategy

Initial migration preserves **primary identifiers and domain table/column names** where feasible. This reduces ticket links, relationship and citation risk. Add explicit semantic configuration/status codes and modern constraints only after profiling/approval. Avoid a simultaneous normalization of every model.

Required target behavior:

- InnoDB, utf8mb4, strict SQL mode and approved collation; application/import session timezone explicitly controlled.
- Positive integer domain keys preserved within MySQL type ranges; target sequences advance past imported maxima. If IDs cannot remain, use a complete versioned source-table/source-ID -> target-ID mapping; remap every FK, polymorphic ID, JSON reference and URL.
- Ticket/comment attachment arrays, skills and matched_chunk_ids become validated **native JSON** where the target design uses array casts. Preserve order, null/empty distinctions where meaningful and every referenced logical path/ID. Do not introduce an attachment child table in phase one without a separate approved normalization plan.
- Long employee messages/policy extracted text/chunk content/chat answers use sufficient UTF-8 text capacity. Document exact target types during M03 after profiling; no historical truncation to satisfy new-request limits.
- Preserve custom status rows. Proposed immutable statuses.code values identify the four lifecycle meanings; custom statuses need distinct owner-approved codes, not rejection by a four-case enum. D06 establishes mapping instead of assuming ID3 means Unassigned in every source.
- New public requests retain validation limits. Import validation is a separate historical-data contract: legacy content can exceed current limits and must be reported/reviewed, not silently discarded.
- Add/validate FKs and indexes in dependency order. Historical unconstrained relations require orphan review before adding constraints. Do not blindly use cascadeOnDelete everywhere.

## 3. Table disposition and mapping

There are **26 migration-declared tables** plus Laravel's migration repository in the source lineage. This is not a live-table census. Permission names/table configuration can vary.

| Source tables | Target disposition | Key transformations / checks |
| --- | --- | --- |
| departments | Preserve rows/IDs/names/times | Configured survey/report organization must map to the intended department, not merely numeric1 |
| divisions | Preserve IDs/department/name/times | FK department; duplicate names not silently deduplicated; verify division membership |
| priorities | Preserve IDs/name/color | Safe presentation of historical colors; review malformed values rather than executing them |
| statuses | Preserve IDs/name/color + approved semantic codes | Resolve duplicate/missing lifecycle meanings; no reseed relabeling of historical tickets |
| users | Preserve all domain identity/profile/auth/preference fields | Full mapping below; emp_no is a string and not guaranteed unique; nullable unique identities/collation collisions profiled |
| issues | Preserve ID/department/division/priority/description/icon/times | Validate coherent organization; keep custom catalog rows and nullable priority from older schema |
| tickets | Preserve IDs/reference/creator/location/status/issue/custom flag/title/priority/message/attachments/resolution/reopen/assignment/times | Never derive destination from current issue or rerun assignment; verify every FK and text/JSON field |
| ticket_comments | Preserve ID/ticket/author/comment/attachment/times | Keep empty comment with attachment, chronological order and all files; target author/ticket relation checked |
| ticket_reassignment_requests | Preserve every state/source/destination/requester/reason/responder/note/time | Keep pending/accepted/rejected/cancelled; profile multiple pending/orphans; do not replay transitions |
| surveys | Preserve ID/rated user/service/four rating strings/submission_id/times | user_id is rated staff, not respondent; keep exact strings/case/spaces; no ticket link or nonexistent submission_date invented |
| arta_surveys | Preserve optional demographics/service/CC1-3/SQD0-8/submission_id/times | Region numeric choice, not nonexistent regions FK; do not retroactively recalculate CC answers unless approved correction |
| hr_policy_documents | Preserve ID/title/original filename/path/category/active/effective date/status/count/error/OCR/hash/extracted text/version/queue/start times | Keep extraction cache and failed/review states; do not mark active because file exists; runtime generation reconciliation below |
| hr_policy_chunks | Preserve ID/document/index/page/section/content/search_vector/times | Existing matched_chunk_ids rely on IDs; no forced re-chunk or embedding generation during import |
| hr_conversations | Preserve ID/user/title/star/last_message/times | User relation currently not FK-enforced; resolve orphan exceptions before stronger constraint |
| hr_chat_logs | Preserve ID/user/conversation/session identifier/question/answer/matched IDs/feedback/times | session_id is historical grouping, not a live browser session; remap JSON chunk references if IDs change; keep missing-source citation limitations |
| roles, permissions | Preserve actual ID/name/guard/times, approved custom entries | Translate to new package schema/config; inspect Spatie5 ->8 migration/config changes; do not install old schema migrations blindly |
| model_has_roles, model_has_permissions, role_has_permissions | Preserve approved role/direct grants | Remap morph class strings/IDs/guard; preserve composite uniqueness; no sync-seeder reset |
| notifications | Preserve retained UUID/type/notifiable/data/read_at/times | Type/URL/payload adapter required; privacy review for notices pointing to no-longer-visible records; preserve unread state |
| sessions | Do not operationally import | Invalidate/re-authenticate. External identity contract D02 must be agreed before logout/cookie behavior is finalized |
| password_resets | Do not replay live reset tokens | Expire/reissue under new password_reset_tokens or selected Laravel schema; protected retention only if approved |
| personal_access_tokens | Default revoke/reissue under agreed consumer plan | Do not copy stale token privileges/auth context as a migration convenience; retain protected audit evidence only if needed |
| policy_jobs | Drain/reconcile/archive, never replay serialized payloads | New target queue starts clean; approved documents receive fresh compatible versioned jobs after activation |
| failed_jobs | Protected forensic archive or approved retention; not automatic retries | Serialized class/payload/exception data can be sensitive and incompatible; new failures use new schema/classes |
| migrations (Laravel repository) | New target migration history | Keep source history in protected migration evidence, not falsely mark old migrations executed in new project |

No regions table is evidenced by repository migrations despite Region model. No additional table is inferred from a class name. Target-only infrastructure (cache/jobs/sessions/reset token tables) follows the chosen Laravel 13 configuration and has its own migration lineage.

## 4. Users, passwords, roles and identity

Preserve users.id, emp_no (including leading zeros), legacy role number as historical data if retained, name/nickname, username/email, department/division, is_active, avatar_url, email_verified_at, password hash, skills, last_login_at, all four stored notification flags and timestamps. No person details/hash values are copied into this specification.

### Password and account contract

- Inspect only protected metadata for hash algorithm/prefix/cost/length and invalid/empty values. Source bcrypt hashes should be copied **verbatim**, not passed through Hash::make or a model callback/cast that rehashes them. Implement an import path that suppresses create/update callbacks deliberately.
- Prove compatibility using synthetic PHP7-created bcrypt fixtures and the new PHP8.5/Laravel hasher, then approved login tests. Bcrypt initially preserves the installed algorithm; stronger cost can rehash on successful login. [Laravel hashing documentation](https://laravel.com/framework/docs/hashing) describes algorithm verification/rehashing; do not disable algorithm verification indiscriminately.
- External-auth accounts may depend on a separate credential authority. Hash copy alone does not implement SSO/session continuity. D02 is a production blocker.
- Demo/shared-password risk must be classified and remediated through approved reset/provisioning. Do not bulk assign a shared default password, create new administrators from seeders or promise every existing password is safe.
- Preserve inactive status. Invalidate remember/session/reset tokens as approved; coordinate installed kiosks and external login. No sessions or keys are published.

### Permission contract

Profile actual roles/permissions/direct grants, guards, composite pivots and model_type values, including old App\\User/App\\Models\\User/Backpack model strings. Define an explicit allowlisted morph/ID mapping to the new User model; do not blindly string-replace every serialized payload. Confirm web guard and imported custom permission names. Rebuild default roles only on a fresh empty system; imported actual grants take precedence over fresh seed assumptions. Role editor must render/preserve ticket.assign and all custom grants.

## 5. Cross-engine transformations

| Source risk | Target transformation / verification |
| --- | --- |
| SQL Server IDENTITY and signed bigint | Preserve valid IDs in explicit target inserts; check negative/out-of-range values; advance AUTO_INCREMENT beyond maximum; no resetting IDs to force clean sequences |
| NVARCHAR(MAX)/VARCHAR(MAX) | Sufficient utf8mb4 TEXT/LONGTEXT or JSON by field; compare decoded/canonical values and original bytes where relevant; no lossy ANSI conversion |
| uniqueidentifier | Canonical UUID string or chosen UUID representation with exact reversible encoding; lower-case text normalization, collision check and JSON/URL compatibility |
| Nullable unique survey UUID | Native nullable unique MySQL index permits multiple NULLs; no SQL Server filtered-index syntax copied |
| bit/tinyint/booleans | Explicit true/false/NULL semantics and casts; preserve optional survey numerics separately from flags |
| SQL Server datetime/naive dates | Confirm historical zone/precision; target DATETIME precision/session policy defined explicitly; preserve instants without double conversion. Do not assume workstation Manila time proves stored Manila time |
| JSON stored as text/double encoding | Accept only profiled recognized shapes, validate arrays/scalars/ID/path types, preserve null/empty; invalid/truncated values go to exception ledger, not silent empty arrays |
| Collation/case/accent/trailing spaces | Profile username/email/reference/name collisions under proposed target collation; exact questionnaire counts keep binary/explicit comparison. Do not merge people or trim answers silently |
| Source default/nullable/FK differences | Reproduce approved meaning; profile unconstrained chat/session/morph relations before stronger constraints; quarantine unresolved orphans |
| DATEDIFF vs TIMESTAMPDIFF and Carbon2 vs3 | Freeze per-metric golden results; explicitly handle units, sign, truncation, averaging and timezone. No assumption that equivalent-looking SQL is numerically identical |
| Very large identifiers | Check maximum IDs and JSON/browser safe-integer behavior; define string encoding compatibility if needed rather than losing precision |

Field-length/null/default/constraint manifest is finalized against approved actual metadata. Old migration excerpts remain reference, not permission to truncate legacy rows to fit current form constraints.

## 6. Attachments, avatars, policy PDFs and static files

1. Inventory private ticket disk, legacy public attachment tree, public avatars/fallback references, private/default policy paths and separately published static policy PDFs. Do not crawl outside approved roots or follow symlinks/junctions blindly.
2. Build a **restricted** manifest: owner table/ID, logical path/index, disk/root identity, byte length, hash, mtime where reliable, existence/readability and classification. Documentation/reporting uses totals and opaque IDs, not employee filenames.
3. Copy into approved target private/public roots without changing source bytes. Preserve logical ticket/comment arrays initially; map paths in a complete table if renaming is necessary. Check target write and re-read hash before declaring copied.
4. Same logical path on two source disks with different hashes is a conflict, not an overwrite rule. Missing/unreadable files remain explicit exceptions. Shared path references require reference-aware cleanup; never delete the last copy merely because one row was removed.
5. Avatars can remain public as approved. Ticket/policy confidential material must not be exposed through public URLs, aliases, junctions or temporary FileUpload previews. Classify public/policyDocs content before copying/distributing; it is not automatically the private upload store.
6. Keep original source stores intact through rollback/retention window. No orphan cleanup, privatization move, deduplication or destructive sync is part of copying. Those are separately authorized maintenance tasks after accepted reconciliation.
7. Verify authorized target downloads return matching bytes and unauthorized/direct-static requests return no bytes. Historical unknown/unsafe file types must not acquire an inline executable preview merely because copying succeeded; use approved protected download/quarantine handling without deleting historical evidence. File hash totals alone do not prove access controls.

## 7. Import order, idempotency and business-side-effect suppression

Proposed target importer, not code created here:

1. Generate target schema/infrastructure in the approved isolated new project; record target identity and migration version.
2. Import departments, divisions, statuses, priorities; users and issues after their dependencies; permissions/roles then their mapped pivots. If constraints/sequence require variation, state it in the manifest rather than turning FK checks off globally.
3. Import tickets, comments, transfer history, satisfaction/ARTA responses; policy documents/chunks; conversations/logs; retained notifications. Respect every FK and remap embedded IDs/URLs when required.
4. Copy/verify files and reconcile DB references before making records generally accessible. Import can stage records/files behind an inaccessible target environment until both are verified.
5. Preserve IDs/timestamps/statuses/assignment/creator/resolver and raw historical text. Bypass assignment/status/notification/model timestamp side effects through a narrowly defined importer; no CLI/user-browser business action is replayed.
6. Record run ID, source snapshot/hash/watermark, schema/transform version, per-table source/accepted/rejected counts and row/checksum batches. Rerun against the **same snapshot** is idempotent; different source/target content produces a conflict, never silent overwrite.
7. Checkpoint chunks and file copies; on failure resume only after validating ownership/checkpoint/content. A transaction per bounded target batch gives atomicity; it does not make an entire filesystem/database transfer one transaction.
8. Apply approved exception repairs only with a signed mapping and separate counts. No placeholder user/status, merged account, lost log, dropped row or guessed path can hide an unresolved exception.

Do not run old backfill migrations again over already-associated history. Existing new-ticket hooks must not rewrite imported records. Prove zero notices/jobs/AI calls/automatic assignments during import with explicit tests.

## 8. Queue and policy state handover

Stop/drain origin ingestion before the final consistent snapshot. Inspect pending/reserved/processing/failed states with approved tools; confirm old OCR subprocesses have stopped. A queued marker is not evidence extraction finished.

Preserve document/chunk/log identifiers and extraction metadata. Old serialized queue jobs are not portable. Classify documents as completed, intentionally inactive/review/failed, or interrupted pending work. For interrupted work, agree a target reconciliation strategy: retain completed chunks but keep unavailable state, create a fresh generation only after code/files are ready, and enqueue a new-app job once. Do not carry a stale queued marker as if it guarantees target work. Worker activation must not race final import or source worker execution.

## 9. Verification and acceptance ledger

| Check | Acceptance condition |
| --- | --- |
| Row counts and exception totals | For every source object: source count = accepted + individually accounted exceptions; no unexplained omission or duplicate |
| Primary/unique IDs | Preserved or completely mapped; max/sequence checks; no reference/UUID/identity collision |
| Relationships and grants | All required FKs/pivots/morphs/JSON IDs resolve, or approved retained historic missing-source behavior documented; permissions exactly match approved set |
| Content and timestamps | Canonical field checksums match approved transformations; long/Unicode/null/empty/date samples validated; no unintended timestamp update |
| Ticket/transfer history | Status/assignee/location/resolver/request-state counts reconcile; current pending records valid; import emits no transitions |
| File manifests | Every retained reference is copied/hash-verified or explicit approved exception; no conflicting path overwritten; access tests pass |
| Policy/chat | Chunk IDs/counts/cache/citations/ownership/feedback preserved; no regenerated answer or unsafe activation |
| Notifications | UUID/read state/payload retained as approved; URLs/types render safely; no replayed delivery |
| Passwords/auth | Synthetic hash compatibility and approved representative account tests; inactive/shared-credential policy enforced; no credential disclosure |
| Reports/public submission | Golden metrics and exact rating totals; MySQL idempotency/concurrency tests; old links/client response contracts accepted |
| Restart and isolation | Repeated/interrupted import is safe; source hash/rows unchanged; target identity never ambiguous |

No actual count, file hash or production login success is supplied because data access was outside this task. All runtime verification above is a future acceptance requirement, not a passed result.

## 10. Cutover and rollback

**Default plan: single-writer maintenance cutover**, not an unimplemented dual-write/CDC system.

1. Rehearse snapshot import, verification, backup restore, worker recovery and URL/kiosk changes on isolated MySQL. Record duration and approve RPO/RTO/downtime.
2. Back up source DB/files/key/configuration under approved access. Pause ALL origin HTTP/Livewire/kiosk/CLI/import/worker writers across instances; maintenance mode alone does not stop every writer. Capture final consistent snapshot and manifests.
3. Import final snapshot or a proven final delta including changes **and deletions**, within frozen consistency boundary. Re-run full reconciliation; keep target public writes disabled until approved.
4. Switch routes/traffic/session contracts, issue new sessions/tokens and activate only the target worker. Controlled smoke test with agreed record-handling policy. Resume one write authority.
5. Before target writes are accepted, rollback can point to the unchanged origin with origin runtime restored and target jobs stopped. Verify no target-only accepted mutations existed.
6. **After target writes are accepted, switching traffic back alone is unsafe.** Freeze target too; capture all new/updated/deleted tickets, comments, files, responses, identities/permissions and policy state. Use a rehearsed reverse reconciliation into compatible origin or a controlled forward repair. If neither is proven, do not promise lossless rollback after writes. RPO/data-loss acceptance must be explicit.
7. Keep DB/files/release/key backups and source manifests through the agreed retention window. Do not drop source tables, delete files or run reverse migrations as routine cleanup. Retire source only under a new authorized task.

This plan is development-ready as an importer contract and test plan. Actual field mappings/exception decisions/cutover timings remain blocked by the required source metadata, identity integration and operational approvals.
