# 19 - Functional Parity Checklist

Prepared **2026-10-28**. **Specification only; no item is implemented or accepted by this task.** FP identifiers pair one-to-one with BR identifiers in [17](17-BUSINESS-RULES.md). Proposed test names below are new-app requirements, not existing files or test results. Port relevant assertions from the [existing test inventory](12-TESTING-AND-QA.md) and [traceability matrix](TRACEABILITY-MATRIX.md); do not copy Backpack/Livewire2 test bootstraps.

For each checkbox, record implementation revision, new automated test output, relevant MySQL/browser/output evidence, migration reconciliation and approved deviations. A referenced D-ID must be resolved before dependent acceptance. Common requirements: correct authorized outcomes, denied forged requests, no file/data loss, no duplicate side effects and recoverable failures.


## Authentication

### FP-A01 - Email or username login

- [ ] **BR-A01** (Preserve; decisions: None).
- **Existing implementation:** [LoginController.php](../app/Http/Controllers/Admin/Auth/LoginController.php)
- **Expected behavior:** Email OR username, password and active=true.
- **New target:** Custom Filament Login; active-session checks.
- **Required tests:** AuthenticationParityTest: both identifiers, inactive, invalid password.
- **Acceptance criteria:** Both valid identifiers authenticate; inactive and bad credentials fail safely.
- **Migration considerations:** Preserve identity/active/hash; invalidate sessions.

### FP-A02 - External login and redirects

- [ ] **BR-A02** (Decision; decisions: D02,D12).
- **Existing implementation:** [LoginController.php](../app/Http/Controllers/Admin/Auth/LoginController.php), [session.php](../config/session.php)
- **Expected behavior:** Non-local login redirects externally; existing-session destination is hardcoded.
- **New target:** Approved auth adapter and configured return paths; no invented SSO.
- **Required tests:** ExternalAuthContractTest: login, session, logout, callback, base path.
- **Acceptance criteria:** Owner-approved HTTPS flow works; no open redirect or copied fixed host.
- **Migration considerations:** Integration contract Not Verified; never copy opaque sessions.

### FP-A03 - Registration and roleless membership

- [ ] **BR-A03** (Decision; decisions: D02,D16).
- **Existing implementation:** [RegisterController.php](../app/Http/Controllers/Admin/Auth/RegisterController.php), [base.php](../config/backpack/base.php)
- **Expected behavior:** Registration requires name/unique username/email/confirmed password min6; creates active roleless user.
- **New target:** Approved signup/provisioning; legitimate employees remain admitted.
- **Required tests:** RegistrationParityTest: disabled signup, uniqueness, confirmation, roleless access.
- **Acceptance criteria:** Only approved enrollment path/grants; no accidental admin-only panel.
- **Migration considerations:** Classify roleless/demo accounts; do not activate or assign roles silently.

### FP-A04 - Profile details

- [ ] **BR-A04** (Preserve; decisions: D02).
- **Existing implementation:** [MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php)
- **Expected behavior:** Name/username/authentication column required max255; unique except self.
- **New target:** Own MyAccount Page and shared rules.
- **Required tests:** AccountParityTest: own/foreign writes, duplicate and max length.
- **Acceptance criteria:** Only own allowed fields save; no forged role/organization privilege change.
- **Migration considerations:** Preserve nullable legacy identifiers; edit policy D02.

### FP-A05 - Skills and safe tags

- [ ] **BR-A05** (Hardening; decisions: None).
- **Existing implementation:** [MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php), [my_account.blade.php](../resources/views/vendor/backpack/base/my_account.blade.php)
- **Expected behavior:** Optional array; strings max100; empty removes skills.
- **New target:** TagsInput plus backend validation and literal text rendering.
- **Required tests:** SkillsParityTest; port tests/JavaScript/SkillTagsTest.cjs.
- **Acceptance criteria:** HTML/quotes/Unicode stay literal; empty list persists and assignment reads same skills.
- **Migration considerations:** Decode ordered arrays; quarantine malformed JSON.

### FP-A06 - Avatar upload/removal

- [ ] **BR-A06** (Preserve; decisions: None).
- **Existing implementation:** [MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php), [User.php](../app/Models/User.php)
- **Expected behavior:** JPG/JPEG/PNG/WebP max2MB; remove option; other details may save with avatar warning.
- **New target:** Safe avatar replacement with explicit partial-failure feedback.
- **Required tests:** AvatarParityTest: types/limit/removal/false write.
- **Acceptance criteria:** No missing-file reference; failed avatar does not silently discard other changes.
- **Migration considerations:** Copy/hash public avatars and approved fallback references.

### FP-A07 - Passwords and recovery

- [ ] **BR-A07** (Decision; decisions: D02,D09).
- **Existing implementation:** [MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php), [UserCrudController.php](../app/Http/Controllers/Admin/UserCrudController.php), [auth.php](../config/auth.php)
- **Expected behavior:** Profile delegates password rules; admin blank password retains hash; recovery exists, delivery unverified.
- **New target:** Approved password/reset rules with bcrypt compatibility and safe blank handling.
- **Required tests:** PasswordMigrationTest: synthetic PHP7 hash on PHP8.5; blank edit/reset expiry.
- **Acceptance criteria:** Hashes verify without double hashing; approved reset flow works.
- **Migration considerations:** Copy hash verbatim; reset identified unsafe credentials; revoke old tokens.

### FP-A08 - Login timestamp

- [ ] **BR-A08** (Preserve; decisions: None).
- **Existing implementation:** [UpdateLastLoginAt.php](../app/Listeners/UpdateLastLoginAt.php), [EventServiceProvider.php](../app/Providers/EventServiceProvider.php)
- **Expected behavior:** last_login_at update failure must not block successful login.
- **New target:** Small redacted login listener.
- **Required tests:** LoginTimestampTest: success and failed side-effect.
- **Acceptance criteria:** Login succeeds on timestamp failure; normal login updates time.
- **Migration considerations:** Import preserves history without fabricating logins.


## Authorization

### FP-B01 - Active shared panel

- [ ] **BR-B01** (Decision; decisions: D16).
- **Existing implementation:** [CheckIfAdmin.php](../app/Http/Middleware/CheckIfAdmin.php)
- **Expected behavior:** Panel includes all active authenticated roles, not admin role only; inactive session invalidated.
- **New target:** canAccessPanel and middleware plus resource policies.
- **Required tests:** PanelAccessTest: five roles, inactive session, agreed roleless case.
- **Acceptance criteria:** Employee pages work; inactive users cannot continue.
- **Migration considerations:** Preserve active flags; do not adopt admin-email example restriction.

### FP-B02 - Actual grants and full editor

- [ ] **BR-B02** (Hardening; decisions: D15).
- **Existing implementation:** [RolesAndPermissionsSeeder.php](../database/seeders/RolesAndPermissionsSeeder.php), [RoleCrudController.php](../app/Http/Controllers/Admin/RoleCrudController.php)
- **Expected behavior:** Five default roles; ticket.assign exists but grouped editor omits it and syncs submitted grants.
- **New target:** Spatie8; complete Role/Permission resources; fresh defaults separate from imported grants.
- **Required tests:** PermissionMigrationTest: custom/direct/multi-role grants and no-op save.
- **Acceptance criteria:** Every imported approved grant survives; no new privilege appears.
- **Migration considerations:** Map guard/morph/IDs; no sync-seeder reset on imported roles.

### FP-B03 - Ticket visibility

- [ ] **BR-B03** (Preserve; decisions: None).
- **Existing implementation:** [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** Active ticket.view: admin all; others own plus head department/division/incoming pending or HR assigned.
- **New target:** Shared visibility query for all read surfaces.
- **Required tests:** VisibilityParityTest: five roles, cross-scope, inactive, pending destination.
- **Acceptance criteria:** No forbidden ticket in detail/search/download/discussion/metrics; admitted set correct.
- **Migration considerations:** Preserve ownership/location/assignment/transfer references.

### FP-B04 - Head list and multi-role rules

- [ ] **BR-B04** (Decision; decisions: D04).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php), [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** List narrows broader visibleTo by current location and employee filters.
- **New target:** Approved visibility composition with explicit incoming-transfer navigation.
- **Required tests:** MultiRoleVisibilityTest; IncomingTransferInboxTest.
- **Acceptance criteria:** Chosen list/detail/incoming behavior recorded and tested as a deliberate change.
- **Migration considerations:** Never change stored destination merely to alter visibility.

### FP-B05 - Generic CRUD versus custom actions

- [ ] **BR-B05** (Preserve; decisions: None).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)
- **Expected behavior:** Admin/dept_head role gates intersect grants; div_head create/update; staff/employee create only despite ticket.update.
- **New target:** Explicit TicketPolicy; separate custom operation policies.
- **Required tests:** TicketOperationTest: seeded grants/direct forged edit/delete.
- **Acceptance criteria:** Staff cannot generic Edit; head delete needs actual delete grant.
- **Migration considerations:** Preserve grants; ignore legacy numeric role as authority.

### FP-B06 - Creator-only reopening

- [ ] **BR-B06** (Preserve; decisions: None).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)
- **Expected behavior:** Resolved ticket creator only, including admin.
- **New target:** Explicit reopen policy without blanket admin bypass.
- **Required tests:** ReopenAuthorizationTest: creator/noncreator admin/nonresolved.
- **Acceptance criteria:** Noncreator admin denied; eligible creator can reopen exactly once.
- **Migration considerations:** Preserve creator/resolution state.

### FP-B07 - Public lookup and API consumers

- [ ] **BR-B07** (Decision; decisions: D12).
- **Existing implementation:** [custom.php](../routes/backpack/custom.php), [api.php](../routes/api.php)
- **Expected behavior:** Divisions lookup public without declared limiter; api/user Sanctum; no custom token issuer.
- **New target:** Bounded compatible lookup and approved retained API/auth behavior.
- **Required tests:** CompatibilityEndpointTest: shape/auth/limits/confirmed consumers.
- **Acceptance criteria:** No silent consumer removal; authenticated user response stays private.
- **Migration considerations:** Reissue retained API credentials; do not copy runtime tokens blindly.


## Catalog

### FP-C01 - Departments

- [ ] **BR-C01** (Preserve; decisions: None).
- **Existing implementation:** [DepartmentCrudController.php](../app/Http/Controllers/Admin/DepartmentCrudController.php), [DepartmentRequest.php](../app/Http/Requests/DepartmentRequest.php)
- **Expected behavior:** Name required string max255 unique except self; default head read-only.
- **New target:** DepartmentResource/policy and coherent uniqueness.
- **Required tests:** DepartmentResourceTest: duplicate/self-edit/read-only direct write.
- **Acceptance criteria:** Valid edit succeeds; duplicate/unauthorized mutation fails.
- **Migration considerations:** Audit collation collisions before adding unique constraints.

### FP-C02 - Divisions

- [ ] **BR-C02** (Preserve; decisions: None).
- **Existing implementation:** [DivisionCrudController.php](../app/Http/Controllers/Admin/DivisionCrudController.php), [DivisionRequest.php](../app/Http/Requests/DivisionRequest.php)
- **Expected behavior:** Name max255 required; department required existing; no unique name rule.
- **New target:** DivisionResource and backend relationship validation.
- **Required tests:** DivisionResourceTest: invalid department and denied writes.
- **Acceptance criteria:** Valid duplicate-name semantics retained; relationships valid.
- **Migration considerations:** Preserve IDs and department mapping.

### FP-C03 - Issues

- [ ] **BR-C03** (Preserve; decisions: None).
- **Existing implementation:** [IssueCrudController.php](../app/Http/Controllers/Admin/IssueCrudController.php), [IssueRequest.php](../app/Http/Requests/IssueRequest.php)
- **Expected behavior:** Required coherent department/division/priority; description max255; optional icon max255; heads create/update global catalog.
- **New target:** IssueResource and shared rules; no invented head row restriction.
- **Required tests:** IssueResourceTest: tuple mismatch, missing priority, head delete denial.
- **Acceptance criteria:** Only coherent tuples save; seeded head access matches source.
- **Migration considerations:** Preserve issue IDs and stored ticket routes/priority.

### FP-C04 - Status and priority semantics/colors

- [ ] **BR-C04** (Hardening; decisions: D06).
- **Existing implementation:** [PriorityRequest.php](../app/Http/Requests/PriorityRequest.php), [StatusRequest.php](../app/Http/Requests/StatusRequest.php), [Status.php](../app/Models/Status.php)
- **Expected behavior:** Unique required names max255; color string max20; lifecycle names cached.
- **New target:** Safe colors and semantic status codes; guarded required lookup deletion.
- **Required tests:** StatusPriorityTest: duplicate/self-edit/color injection/lifecycle code.
- **Acceptance criteria:** Legitimate labels render safely; lifecycle meaning survives ID differences.
- **Migration considerations:** Map IDs/codes; no historical status relabeling.

### FP-C05 - Administrative users

- [ ] **BR-C05** (Decision; decisions: D02,D15).
- **Existing implementation:** [UserCrudController.php](../app/Http/Controllers/Admin/UserCrudController.php)
- **Expected behavior:** Name/email/password create; blank update retains hash; department/matching division/roles/active.
- **New target:** UserResource with explicit field policies and approved secure password rules.
- **Required tests:** UserResourceTest: unique email, scope/grant forgery, blank hash.
- **Acceptance criteria:** Only authorized fields/grants change; valid imported null identities remain viewable.
- **Migration considerations:** Import records rather than reseeding named accounts.

### FP-C06 - Roles and permissions

- [ ] **BR-C06** (Hardening; decisions: D15).
- **Existing implementation:** [RoleCrudController.php](../app/Http/Controllers/Admin/RoleCrudController.php), [PermissionCrudController.php](../app/Http/Controllers/Admin/PermissionCrudController.php)
- **Expected behavior:** Package create names unique/max255; update rules differ; custom grant IDs synchronized.
- **New target:** RoleResource/PermissionResource; complete grant list and collision-safe updates.
- **Required tests:** RolePermissionTest: custom grants/guard/no-op/unauthorized mutation.
- **Acceptance criteria:** No-op keeps ticket.assign/custom grants; permission creation grants nobody automatically.
- **Migration considerations:** Preserve custom names/guards/direct and role pivots.

### FP-C07 - Caches and referenced deletion

- [ ] **BR-C07** (Preserve; decisions: None).
- **Existing implementation:** [Department.php](../app/Models/Department.php), [Division.php](../app/Models/Division.php), [Status.php](../app/Models/Status.php)
- **Expected behavior:** 15-minute caches invalidated on save/delete; FKs can prevent referenced deletion.
- **New target:** Equivalent invalidation and clear dependency feedback.
- **Required tests:** ReferenceCacheTest: immediate refresh and referenced delete failure.
- **Acceptance criteria:** No stale labels after update and no unexpected history cascade.
- **Migration considerations:** Do not introduce soft-delete/restore or new cascades silently.


## Tickets

### FP-T01 - Create branch validation

- [ ] **BR-T01** (Preserve; decisions: None).
- **Existing implementation:** [TicketRequest.php](../app/Http/Requests/TicketRequest.php)
- **Expected behavior:** Boolean custom flag; issue OR custom title/destination; message required max65535; title max255.
- **New target:** Shared creation rules/normalization in CreateTicket action.
- **Required tests:** CreateTicketTest: both branches/boundaries/forged hidden fields.
- **Acceptance criteria:** Irrelevant branch cleared; invalid tuples cannot bypass backend rules.
- **Migration considerations:** Keep message/branch/issue/title/destination values.

### FP-T02 - Reference generation

- [ ] **BR-T02** (Preserve; decisions: None).
- **Existing implementation:** [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** TKT- six uppercase hex chars; unique and bounded five collision attempts.
- **New target:** Reference generator plus database uniqueness/retry.
- **Required tests:** ReferenceTest: forced collisions/concurrent create/exhaustion.
- **Acceptance criteria:** No duplicates/unbounded retry; imported references unchanged.
- **Migration considerations:** Preflight reference/collation collisions; import verbatim.

### FP-T03 - Catalog-derived routing

- [ ] **BR-T03** (Preserve; decisions: None).
- **Existing implementation:** [Ticket.php](../app/Models/Ticket.php), [TicketRequest.php](../app/Http/Requests/TicketRequest.php)
- **Expected behavior:** Issue supplies department/division/priority only on create/issue change.
- **New target:** Canonicalize in create/update, not every save.
- **Required tests:** TicketMetadataTest: issue switch and accepted transfer later save.
- **Acceptance criteria:** Issue change derives metadata; transfer location is not overwritten later.
- **Migration considerations:** Import stored routes rather than current catalog-derived routes.

### FP-T04 - Creator and initial state

- [ ] **BR-T04** (Hardening; decisions: D06).
- **Existing implementation:** [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** Creator session-derived if missing; empty status defaults numeric3.
- **New target:** Actor-selected creator and semantic Unassigned status.
- **Required tests:** TicketDefaultsTest: forged owner/status and changed lookup IDs.
- **Acceptance criteria:** Create uses correct actor/state; import does not invoke defaults.
- **Migration considerations:** Map status3 meaning; preserve historical owner/status.

### FP-T05 - Deterministic automatic assignment

- [ ] **BR-T05** (Preserve; decisions: None).
- **Existing implementation:** [TicketAutoAssignmentService.php](../app/Services/TicketAutoAssignmentService.php), [TicketObserver.php](../app/Observers/TicketObserver.php)
- **Expected behavior:** Active HR in department/optional division; normalized skill +5 for standard issues; custom ignores skills; ties active workload then lowest ID.
- **New target:** Shared eligibility/selection invoked once on create/accepted transfer.
- **Required tests:** AutoAssignmentTest: phrases/ties/custom/no candidates.
- **Acceptance criteria:** Same fixture selects same staff or stays unassigned; selected state Pending.
- **Migration considerations:** Preserve ID tie order; no assignment rerun during import.

### FP-T06 - Assignment failure and initial notifications

- [ ] **BR-T06** (Preserve; decisions: None).
- **Existing implementation:** [TicketObserver.php](../app/Observers/TicketObserver.php), [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** Assignment error logs without intentionally losing created ticket; initial Pending suppresses only status notice.
- **New target:** One transition/side-effect path and recoverable failure feedback.
- **Required tests:** AssignmentFailureTest; InitialNotificationTest.
- **Acceptance criteria:** No duplicate notices; later status changes still notify; ticket recoverable.
- **Migration considerations:** Importer emits no assignment/creation/status events.

### FP-T07 - Manual assignment

- [ ] **BR-T07** (Decision; decisions: D05).
- **Existing implementation:** [TicketAssignmentRules.php](../app/Services/TicketAssignmentRules.php), [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)
- **Expected behavior:** Admin/heads or explicit HR ticket.assign; active coherent targets scoped to actor; null clears; resolved UI/handler disagree.
- **New target:** AssignStaff action with approved state guard and shared candidates.
- **Required tests:** ManualAssignmentTest: scope, null, inactive, delegated HR, forged resolved request.
- **Acceptance criteria:** Bad actor/target rejected; valid change sets Pending/Unassigned with explicit-status semantics.
- **Migration considerations:** Preserve assigned_to; flag missing target rather than reassign.

### FP-T08 - Edit field boundaries

- [ ] **BR-T08** (Decision; decisions: D05).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)
- **Expected behavior:** Content/file UI uses creator/admin/div_head; server generic update lacks equivalent complete field policy.
- **New target:** Approved per-field whitelist in UpdateTicket for every caller.
- **Required tests:** TicketFieldAuthorizationTest: each actor x forged owner/status/content/files.
- **Acceptance criteria:** Only approved attributes change; hidden/disabled UI never enforces alone.
- **Migration considerations:** No content/ownership/location repair as incidental import.

### FP-T09 - Resolve

- [ ] **BR-T09** (Preserve; decisions: None).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php), [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** Admin/current head/assigned HR resolves; time/resolver set, reopen cleared, pending transfers cancelled.
- **New target:** Atomic ResolveTicket action/policy.
- **Required tests:** ResolveTest: actors, metadata and transfer cancellation.
- **Acceptance criteria:** Only eligible resolver changes state and related rows consistently.
- **Migration considerations:** Keep original resolver/time; never infer missing values.

### FP-T10 - Reopen and timestamp lifecycle

- [ ] **BR-T10** (Preserve; decisions: None).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php), [Ticket.php](../app/Models/Ticket.php)
- **Expected behavior:** Creator+Resolved; reopened time set and resolution fields cleared; other statuses clear stale metadata.
- **New target:** Reopen/UpdateStatus actions with invariant tests.
- **Required tests:** LifecycleTest: forbidden/nonresolved/clearing/status lookup failure.
- **Acceptance criteria:** Correct 403/409-equivalent handling; later save retains coherent state.
- **Migration considerations:** No invented historic reopen events from present fields.

### FP-T11 - Overdue boundary

- [ ] **BR-T11** (Decision; decisions: D03,D07).
- **Existing implementation:** [Ticket.php](../app/Models/Ticket.php), [TicketReportWidgets.php](../app/Services/TicketReportWidgets.php), [SqlDialectHelper.php](../app/Services/SqlDialectHelper.php)
- **Expected behavior:** Open older than3 days; resolved integer hours>72; original creation time after reopen; SQL path differs.
- **New target:** Approved per-metric compatibility duration functions.
- **Required tests:** SlaBoundaryTest: 71:59/72:00/72:59/73:00 plus UTC/fractional values.
- **Acceptance criteria:** Golden labels/buckets match or explicit correction approved.
- **Migration considerations:** Carbon2->3 and DATEDIFF->TIMESTAMPDIFF deltas recorded.

### FP-T12 - Ticket deletion and cleanup

- [ ] **BR-T12** (Preserve; decisions: None).
- **Existing implementation:** [Ticket.php](../app/Models/Ticket.php), [TicketComment.php](../app/Models/TicketComment.php)
- **Expected behavior:** Transactional deletion removes comments individually and files after commit; ticket transfers cascade.
- **New target:** DeleteTicket action, no bulk deletion shortcut.
- **Required tests:** TicketDeletionTest: rollback/files/comments/transfers/denied delete.
- **Acceptance criteria:** Failed deletion retains rows/files; success removes only eligible associated data.
- **Migration considerations:** Never delete history during migration; no implicit recycle bin.


## Transfers

### FP-X01 - Request transfer

- [ ] **BR-X01** (Decision; decisions: D05).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)
- **Expected behavior:** Admin/current head; unresolved visible ticket; different coherent target; one pending under parent lock.
- **New target:** RequestTransfer action; validated note bounds from D05.
- **Required tests:** TransferRequestTest: duplicate race, resolved, same/mismatched target.
- **Acceptance criteria:** Invalid request has no partial rows/notices; concurrent requests yield one pending.
- **Migration considerations:** Preserve source/from/to/requester/reason/status/time.

### FP-X02 - Accept transfer

- [ ] **BR-X02** (Preserve; decisions: None).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php), [TicketAutoAssignmentService.php](../app/Services/TicketAutoAssignmentService.php)
- **Expected behavior:** Receiving head/admin; move, clear assignment, rerun selection, record responder/time, notify requester.
- **New target:** AcceptTransfer transaction and fresh pending lock.
- **Required tests:** TransferAcceptTest: unauthorized/double accept/no staff/new staff.
- **Acceptance criteria:** Location/response commit together; no candidates leaves Unassigned; later save retains destination.
- **Migration considerations:** Do not reaccept/reassign imported history.

### FP-X03 - Reject and cancel

- [ ] **BR-X03** (Decision; decisions: D05).
- **Existing implementation:** [TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php), [TicketReassignmentRequest.php](../app/Models/TicketReassignmentRequest.php)
- **Expected behavior:** Receiving head/admin rejects with note; requester/admin cancels pending; resolve cancels pending.
- **New target:** Reject/Cancel actions with same lock and approved note validation.
- **Required tests:** TransferRejectCancelTest: actors, stale state, concurrent resolve.
- **Acceptance criteria:** Rejected/cancelled history retained; rejection leaves location unchanged.
- **Migration considerations:** Preserve every request state, not only accepted transfers.


## Files and discussion

### FP-F01 - Upload constraints and retained ownership

- [ ] **BR-F01** (Hardening; decisions: None).
- **Existing implementation:** [TicketRequest.php](../app/Http/Requests/TicketRequest.php), [TicketChat.php](../app/Http/Livewire/TicketChat.php), [AttachmentStorage.php](../app/Services/AttachmentStorage.php)
- **Expected behavior:** Max20 files,20MB each; jpg/jpeg/png/pdf/doc/docx/xls/xlsx/txt; retained paths already on record.
- **New target:** Private upload adapter/backend MIME/count/size/reference checks.
- **Required tests:** AttachmentValidationTest: 20/21, size/type/unusual keys/foreign retained path.
- **Acceptance criteria:** Allowed bytes persist; arbitrary private paths cannot attach.
- **Migration considerations:** Keep ordered arrays/bytes; do not reject historical file types out of import.

### FP-F02 - Write/transaction compensation

- [ ] **BR-F02** (Preserve; decisions: None).
- **Existing implementation:** [AttachmentStorage.php](../app/Services/AttachmentStorage.php), [AttachmentTransactions.php](../app/Services/AttachmentTransactions.php)
- **Expected behavior:** Verify stream writes; cleanup known failures; retain files on ambiguous DB/reference state.
- **New target:** Shared storage transaction service across HTTP/Livewire.
- **Required tests:** AttachmentAtomicityTest: partial/false write, rollback, uncertain commit.
- **Acceptance criteria:** No reference to failed new write; uncertainty remains reported/reconcilable.
- **Migration considerations:** Hash manifest preserves ambiguous legacy files.

### FP-F03 - Protected downloads

- [ ] **BR-F03** (Hardening; decisions: D09).
- **Existing implementation:** [TicketAttachmentController.php](../app/Http/Controllers/Admin/TicketAttachmentController.php), [AttachmentStorage.php](../app/Services/AttachmentStorage.php), [.htaccess](../public/.htaccess)
- **Expected behavior:** Visible ticket, child/index binding, path checks/no-store/nosniff; legacy public fallback.
- **New target:** Record-bound private download controller and deny public aliases.
- **Required tests:** DownloadAuthorizationTest: guest/inactive/foreign/traversal/invalid child/index.
- **Acceptance criteria:** Authorized bytes match; unauthorized/direct public routes expose no bytes.
- **Migration considerations:** Inventory both stores; quarantine hash conflict without overwrite.

### FP-F04 - Discussion

- [ ] **BR-F04** (Preserve; decisions: None).
- **Existing implementation:** [TicketChat.php](../app/Http/Livewire/TicketChat.php), [ticket-chat.blade.php](../resources/views/livewire/ticket-chat.blade.php)
- **Expected behavior:** 5s polling, oldest comments/authors; max65535 text required without files; resolved locked; errors visible.
- **New target:** Livewire4 discussion + AddComment action.
- **Required tests:** DiscussionTest: files-only, blank/resolved, forged mounted ID, failed persistence.
- **Acceptance criteria:** Draft clears only on success; errors recoverable; no cross-ticket leak.
- **Migration considerations:** Preserve IDs/authors/order/content/timestamps/files even on resolved imports.

### FP-F05 - Reconcile/privatize command

- [ ] **BR-F05** (Preserve; decisions: None).
- **Existing implementation:** [ReconcileTicketAttachments.php](../app/Console/Commands/ReconcileTicketAttachments.php), [PrivatizeTicketAttachments.php](../app/Console/Commands/PrivatizeTicketAttachments.php), [AttachmentInventory.php](../app/Services/AttachmentInventory.php)
- **Expected behavior:** Read-only default; private orphan cleanup requires pause assertion and age48-87600h; public orphans manual.
- **New target:** Safe new operator tools with roots/hash/fresh checks.
- **Required tests:** AttachmentOperationsTest: unreadable/link/conflict/recent/changed references.
- **Acceptance criteria:** Dry-run deletes nothing; errors retain remaining files; only approved old private orphan removed.
- **Migration considerations:** No cleanup during data copy; secure manifest first.

### FP-F06 - Avatar sync command

- [ ] **BR-F06** (Preserve; decisions: None).
- **Existing implementation:** [SyncUserAvatars.php](../app/Console/Commands/SyncUserAvatars.php)
- **Expected behavior:** Exact employee-number WebP match; leading zeros retained; duplicate matches fail; verified copy/dry-run.
- **New target:** Equivalent restricted operator command.
- **Required tests:** AvatarSyncTest: leading zeros/duplicates/missing source/false write.
- **Acceptance criteria:** Only exact approved account match changes; dry-run immutable.
- **Migration considerations:** emp_no not assumed globally unique or a safe identity migration key.


## Notifications

### FP-N01 - Database feed and history

- [ ] **BR-N01** (Preserve; decisions: None).
- **Existing implementation:** [TicketSystemNotification.php](../app/Notifications/TicketSystemNotification.php), [NotificationController.php](../app/Http/Controllers/Admin/NotificationController.php)
- **Expected behavior:** Database payload title/message/url/extra; feed10/unread; page20; own read-one/all/clear.
- **New target:** Database notification/display adapter for Filament.
- **Required tests:** NotificationHistoryTest: legacy payload/own mutation/paging/count/clear.
- **Acceptance criteria:** Count/list agree; no other-user read/mutation; no invented ticket email.
- **Migration considerations:** Keep UUID/type/data/read_at/time; map internal URLs.

### FP-N02 - Recipients and actors

- [ ] **BR-N02** (Hardening; decisions: D09).
- **Existing implementation:** [TicketNotificationService.php](../app/Services/TicketNotificationService.php)
- **Expected behavior:** Created admins/matching heads; assignment/status role/owner rules; comments creator/assignee/prior participants; remove actor/dedupe. Transfer receiver selection does not explicitly filter inactive accounts.
- **New target:** Preserved recipient selector with active/current scope checks.
- **Required tests:** RecipientParityTest: each event x role x actor x visibility.
- **Acceptance criteria:** Fixture recipient sets match; no duplicate/forbidden sensitive notice.
- **Migration considerations:** Import without sending; classify old now-revoked metadata.

### FP-N03 - Preferences and transfer notifications

- [ ] **BR-N03** (Preserve; decisions: None).
- **Existing implementation:** [MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php), [User.php](../app/Models/User.php), [TicketNotificationService.php](../app/Services/TicketNotificationService.php)
- **Expected behavior:** Four stored flags; absent transfer preferences default true; transfers notify receivers/admin or requester.
- **New target:** Explicit preserved defaults; no invented preference columns silently.
- **Required tests:** PreferenceParityTest: each disabled flag, transfer request/accepted/rejected.
- **Acceptance criteria:** Each flag controls its event; transfer default is documented and tested.
- **Migration considerations:** Keep boolean values; new preferences require approved change.


## Reports

### FP-R01 - Dashboard scope and employee landing

- [ ] **BR-R01** (Preserve; decisions: None).
- **Existing implementation:** [DashboardController.php](../app/Http/Controllers/Admin/DashboardController.php), [TicketReportQueries.php](../app/Services/TicketReportQueries.php)
- **Expected behavior:** Employee landing redirect; others ticket.view; visible ticket aggregate/query and latest10.
- **New target:** Scoped Dashboard widgets and SubmitTicket Page.
- **Required tests:** DashboardParityTest: invisible ticket effect on all widgets/transfer totals.
- **Acceptance criteria:** No forbidden ticket contributes; employee destination retained.
- **Migration considerations:** Compare synthetic normalized datasets, not confidential screenshots.

### FP-R02 - Periods

- [ ] **BR-R02** (Decision; decisions: D03,D07).
- **Existing implementation:** [ReportPeriodResolver.php](../app/Services/ReportPeriodResolver.php)
- **Expected behavior:** Month-to-date/all/calendar h1-h2/custom full days; invalid custom falls back with message.
- **New target:** Shared period resolver and same approved UI semantics.
- **Required tests:** PeriodTest: missing/reversed/invalid/leap/half defaults/timezone.
- **Acceptance criteria:** Ranges and fallback messages/state match fixtures.
- **Migration considerations:** Decide stored timezone before display conversion.

### FP-R03 - KPI formula compatibility

- [ ] **BR-R03** (Decision; decisions: D07).
- **Existing implementation:** [TicketReportWidgets.php](../app/Services/TicketReportWidgets.php), [TicketReportQueries.php](../app/Services/TicketReportQueries.php)
- **Expected behavior:** Current totals/resolved/pending; rounded mean integer minutes; overdue/reopen percentages1decimal; current reopen not full history.
- **New target:** Pure bounded metrics preserving approved units/rounding.
- **Required tests:** KpiTest: empty/null/fractional/500-row chunk boundary.
- **Acceptance criteria:** Fixture totals/rounded averages/percentages match; no invented historical count.
- **Migration considerations:** Current timestamps cannot recover all past reopens.

### FP-R04 - Chart and transfer datasets

- [ ] **BR-R04** (Decision; decisions: D07).
- **Existing implementation:** [TicketReportWidgets.php](../app/Services/TicketReportWidgets.php), [TicketReportQueries.php](../app/Services/TicketReportQueries.php)
- **Expected behavior:** Day<=92days/week<=730/else month; all forces month; duration<24/<72/<168/else; funnel; distinct any-state transfer tickets.
- **New target:** Equivalent zero-filled scoped datasets; retain hidden-chart decision.
- **Required tests:** WidgetTest: exact boundaries/zero-fill/any-state distinct transfer/scope.
- **Acceptance criteria:** Labels/counts/rates match and reveal no forbidden record.
- **Migration considerations:** Preserve transfer/status/date data; no accepted-only reinterpretation.

### FP-R05 - Staff and catalog report

- [ ] **BR-R05** (Decision; decisions: D06,D11).
- **Existing implementation:** [ReportsController.php](../app/Http/Controllers/Admin/ReportsController.php), [User.php](../app/Models/User.php)
- **Expected behavior:** Broad reports.view scope; department1 cohort; resolver/assignee distinctions; creation-date range; zero-activity toggle.
- **New target:** EmployeeCareReports queries with explicit permission/organization mapping.
- **Required tests:** StaffReportTest: resolver differs, zero rows, mapped department, dates.
- **Acceptance criteria:** Staff/category/division metrics match; no automatic report grant to heads.
- **Migration considerations:** Configure intended department without rewriting history.

### FP-R06 - Satisfaction aggregates

- [ ] **BR-R06** (Preserve; decisions: None).
- **Existing implementation:** [SurveyReportQueries.php](../app/Services/SurveyReportQueries.php)
- **Expected behavior:** Exact case/trailing-space four-dimension choice matches, staff groups and zero activity; counts sum dimensions.
- **New target:** Equivalent MySQL aggregate semantics.
- **Required tests:** SatisfactionReportTest: case/spaces/all dimensions/cohort/zero activity.
- **Acceptance criteria:** Four responses fields contribute four choice counts, not one synthetic overall score.
- **Migration considerations:** Do not trim/case-fold historical ratings.

### FP-R07 - Paged response history

- [ ] **BR-R07** (Preserve; decisions: D12).
- **Existing implementation:** [SurveyResponseTable.php](../app/Services/SurveyResponseTable.php)
- **Expected behavior:** length1-100; bounded/quoted search; wildcard escaping; fixed order columns; DataTables response envelope.
- **New target:** Filament table or compatibility JSON with same search/page ordering behavior.
- **Required tests:** ResponseTableTest: quotes/multiple terms/null N-A/bad sort/page limits.
- **Acceptance criteria:** Matching rows appear once across pages; malformed input cannot inject SQL.
- **Migration considerations:** Keep row IDs/times/joins and confirmed endpoint consumers.

### FP-R08 - PDF outputs

- [ ] **BR-R08** (Decision; decisions: D13).
- **Existing implementation:** [ReportsController.php](../app/Http/Controllers/Admin/ReportsController.php), [SurveyReportsController.php](../app/Http/Controllers/Admin/SurveyReportsController.php), [ArtaSurveyReportsController.php](../app/Http/Controllers/Admin/ArtaSurveyReportsController.php), [reports.php](../config/reports.php)
- **Expected behavior:** Ordered date range; ticket/CSS A4 portrait; ARTA8.5x13 landscape,2000row cap; generic failure feedback.
- **New target:** Dedicated PDF service/print Blade/fonts with same content/limits.
- **Required tests:** PdfParityTest:2000/2001, dates, Unicode/long text/failure; visual review.
- **Acceptance criteria:** Approved PDF readable/complete; over-cap rejected before unbounded rendering.
- **Migration considerations:** Compare nonsensitive golden samples through library upgrade.


## Surveys and PWA

### FP-S01 - Satisfaction staff/service selection

- [ ] **BR-S01** (Decision; decisions: D06).
- **Existing implementation:** [SurveyController.php](../app/Http/Controllers/SurveyController.php)
- **Expected behavior:** Department1 divisions excluding Department Head; ordered styles; listed head/HR staff and matching service; no explicit active filter.
- **New target:** Public survey service with approved organization/inactive-selection rule.
- **Required tests:** SurveySelectionTest: role/department/division/service/order.
- **Acceptance criteria:** Only approved tuples submit; panel login not required.
- **Migration considerations:** Map organization; rated user is staff, not respondent identity.

### FP-S02 - Ratings and HTTP result

- [ ] **BR-S02** (Preserve; decisions: None).
- **Existing implementation:** [SurveyController.php](../app/Http/Controllers/SurveyController.php)
- **Expected behavior:** Four exact required ratings; UUID; saved/errors422 JSON or form redirect/thank_you.
- **New target:** Public Blade/interactive form with shared backend rules.
- **Required tests:** SurveySubmitTest: choices/missing/invalid/JSON/form.
- **Acceptance criteria:** Only confirmed persistence gives saved=true/thank-you.
- **Migration considerations:** Preserve raw strings/user/issue/time; no ticket relation invented.

### FP-S03 - UUID idempotency

- [ ] **BR-S03** (Preserve; decisions: None).
- **Existing implementation:** [SurveySubmission.php](../app/Services/SurveySubmission.php), [2026_10_07_110000_add_survey_submission_ids.php](../database/migrations/2026_10_07_110000_add_survey_submission_ids.php)
- **Expected behavior:** Same UUID+same answers returns existing; different answers rejected; unique index resolves races.
- **New target:** Lowercase UUID and nullable unique MySQL constraint/transaction handling.
- **Required tests:** SurveyIdempotencyTest: simultaneous/changed/case variant/new respondent.
- **Acceptance criteria:** Retry creates exactly one row, never overwrites different answers.
- **Migration considerations:** Keep legacy null UUIDs; distinct IDs with identical answers remain distinct.

### FP-S04 - Submission CSRF and budgets

- [ ] **BR-S04** (Preserve; decisions: None).
- **Existing implementation:** [RouteServiceProvider.php](../app/Providers/RouteServiceProvider.php), [web.php](../routes/web.php)
- **Expected behavior:** 30/min session plus300/min actual peer; fresh CSRF no-store.
- **New target:** Web CSRF and matching peer/session caps with explicit proxy design.
- **Required tests:** SurveyRateTest: stale token/spoofed forwarded header/both caps.
- **Acceptance criteria:** Rejected requests persist nothing; peer aggregation understood.
- **Migration considerations:** No legacy session/CSRF credential import.

### FP-S05 - Offline viewing only

- [ ] **BR-S05** (Preserve; decisions: D12).
- **Existing implementation:** [sw-survey.js](../public/sw-survey.js), [survey.js](../public/survey-assets/js/survey.js), [manifest-survey.json](../public/manifest-survey.json)
- **Expected behavior:** Allowlisted public assets/eligible stripped-token HTML; reconnect to submit; no POST outbox.
- **New target:** Dedicated worker with safe asset versioning and no panel cache.
- **Required tests:** PwaBrowserTest: cached view/offline submit/current token/private URL exclusion.
- **Acceptance criteria:** Offline never claims saved; sensitive pages/tokens not cached.
- **Migration considerations:** Plan installed worker/base-path change; no old answer state copied.

### FP-S06 - Kiosk reset/update and QR

- [ ] **BR-S06** (Decision; decisions: D12,D13).
- **Existing implementation:** [survey.js](../public/survey-assets/js/survey.js), [survey_form.blade.php](../resources/views/survey_form.blade.php)
- **Expected behavior:** Same-document confirmed save/reset; retry same UUID; next client new UUID; update at safe boundary; QR external target.
- **New target:** Equivalent full-screen kiosk/reset and approved QR rendering.
- **Required tests:** KioskBrowserTest:lost response/retry/next client/waiting worker/QR scan.
- **Acceptance criteria:** No previous-client answers shown; safe update; QR scans to approved target.
- **Migration considerations:** Do not force client update mid-response; confirm URL/device list.


## ARTA

### FP-Q01 - Demographics and CC

- [ ] **BR-Q01** (Preserve; decisions: None).
- **Existing implementation:** [ArtaController.php](../app/Http/Controllers/ArtaController.php), [ArtaSurvey.php](../app/Models/ArtaSurvey.php)
- **Expected behavior:** Optional client/sex/age1-100/region1-17/service<=255; CC ranges1-4,1-5,1-4.
- **New target:** Public ARTA fields/constants and same optional validation.
- **Required tests:** ArtaValidationTest: ranges/null/unknown choice.
- **Acceptance criteria:** Valid optional data saves; invalid choices rejected.
- **Migration considerations:** Region numeric constants, not nonexistent regions FK.

### FP-Q02 - SQD and derived N/A

- [ ] **BR-Q02** (Preserve; decisions: None).
- **Existing implementation:** [ArtaController.php](../app/Http/Controllers/ArtaController.php)
- **Expected behavior:** At least one SQD0-8; six exact values; CC1=4 forces CC2=5,CC3=4; shared UUID save.
- **New target:** ARTA rule service and redirect/thank-you.
- **Required tests:** ArtaBusinessTest: none/one SQD/forced CC/idempotent retry.
- **Acceptance criteria:** No-SQD fails; derived values persist; success only after save.
- **Migration considerations:** Preserve historical/null choices without normalization loss.

### FP-Q03 - Public transport/offline distinction

- [ ] **BR-Q03** (Preserve; decisions: D12,D13).
- **Existing implementation:** [arta.js](../public/arta-assets/js/arta.js), [arta_form.blade.php](../resources/views/arta_form.blade.php), [sw-survey.js](../public/sw-survey.js)
- **Expected behavior:** Form redirects/QR; worker registration does not cache /arta-form or save offline.
- **New target:** Public ARTA outside panel, no invented offline feature.
- **Required tests:** ArtaBrowserTest: errors/retained data/thank-you/offline.
- **Acceptance criteria:** Usable form/redirect behavior without panel login or false offline saving.
- **Migration considerations:** Review URL/cache migration and QR destinations.


## Policy documents and OCR

### FP-P01 - Manager access and upload fields

- [ ] **BR-P01** (Preserve; decisions: None).
- **Existing implementation:** [HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php)
- **Expected behavior:** Admin/dept_head global; title<=255; four browser categories; optional effective date; PDF<=20MB.
- **New target:** Policy resource/custom pages with server role/field checks.
- **Required tests:** PolicyManagerTest: roles/categories/date/file boundaries/direct actions.
- **Acceptance criteria:** Only managers mutate; valid upload says queued, not ready.
- **Migration considerations:** Keep category/date/active/status; no department ownership invented.

### FP-P02 - Readiness and dispatch atomicity

- [ ] **BR-P02** (Preserve; decisions: None).
- **Existing implementation:** [PolicyIngestService.php](../app/Services/PolicyIngestService.php), [queue.php](../config/queue.php)
- **Expected behavior:** Check schema before storage; dedicated policy_jobs+marker transaction; duplicate marker prevents duplicate enqueue.
- **New target:** Same-connection database coordinator and versioned job.
- **Required tests:** PolicyQueueTest: missing schema/push failure/marker failure/duplicate queue.
- **Acceptance criteria:** No save on readiness failure; dispatch failure preserves recoverable pending file/record.
- **Migration considerations:** Do not deserialize/replay old jobs; approve fresh target generations.

### FP-P03 - Generation claim/commit

- [ ] **BR-P03** (Preserve; decisions: None).
- **Existing implementation:** [PolicyIngestService.php](../app/Services/PolicyIngestService.php), [IngestPolicyDocumentJob.php](../app/Jobs/IngestPolicyDocumentJob.php)
- **Expected behavior:** Claim pending current version; skip stale/duplicate/missing-version jobs; guarded atomic chunk/state swap.
- **New target:** Short claim/commit locks around off-transaction extraction.
- **Required tests:** PolicyGenerationTest: replacement/duplicate/delete/version race.
- **Acceptance criteria:** Stale job cannot overwrite replacement or resurrect deleted document.
- **Migration considerations:** Keep document/chunk IDs initially; new payloads only.

### FP-P04 - Extraction and cache

- [ ] **BR-P04** (Preserve; decisions: None).
- **Existing implementation:** [OcrService.php](../app/Services/OcrService.php), [PolicyIngestService.php](../app/Services/PolicyIngestService.php), [ocr.php](../config/ocr.php)
- **Expected behavior:** Native plus sparse-page OCR;100char/300dpi/oem1/psm3/60s-page/concurrency2 defaults; hash cache.
- **New target:** Safe subprocess OCR with private workdir and equivalent page outputs.
- **Required tests:** OcrParityTest: native/scanned/mixed/encrypted/corrupt/hash reuse/force.
- **Acceptance criteria:** Pages attributable, failures visible, force bypasses cache; no shell injection.
- **Migration considerations:** Keep hash/extracted page JSON/OCR metadata; no forced import re-extraction.

### FP-P05 - Chunking and quality

- [ ] **BR-P05** (Preserve; decisions: None).
- **Existing implementation:** [PolicyIngestService.php](../app/Services/PolicyIngestService.php), [HrPolicyDocument.php](../app/Models/HrPolicyDocument.php)
- **Expected behavior:** 300word/50overlap/min30; page/section; incomplete/unknown/<60 OCR -> needs_review; active eligibility guarded.
- **New target:** Equivalent chunker/quality gate and failure states.
- **Required tests:** ChunkQualityTest: boundaries/overlap/page/confidence59-60/incomplete.
- **Acceptance criteria:** Only eligible active state retrieved; toggle cannot approve needs_review.
- **Migration considerations:** Keep chunk IDs and historical citations; re-chunk separate.

### FP-P06 - Metadata/replace/re-run/toggle/delete

- [ ] **BR-P06** (Decision; decisions: D08).
- **Existing implementation:** [HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php), [PolicyIngestService.php](../app/Services/PolicyIngestService.php)
- **Expected behavior:** Metadata immediate; replace version/pending; processing blocks edit/re-run; active/inactive toggle; delete lacks edit lock.
- **New target:** Explicit actions and approved deletion concurrency/tombstone rule.
- **Required tests:** PolicyActionTest: no-job metadata, busy state, toggle422, delete race.
- **Acceptance criteria:** No bypass/partial stale state; legitimate edits/recovery retained.
- **Migration considerations:** Keep old bytes until commit/reference-safe cleanup; no invented review approval.

### FP-P07 - Worker lifetime and recovery

- [ ] **BR-P07** (Preserve; decisions: None).
- **Existing implementation:** [IngestPolicyDocumentJob.php](../app/Jobs/IngestPolicyDocumentJob.php), [queue.php](../config/queue.php), [POLICY_INGESTION_QUEUE.md](../docs/POLICY_INGESTION_QUEUE.md)
- **Expected behavior:** 900s timeout,1try,1200retry_after; Windows bounded listener; no worker => pending.
- **New target:** Host-appropriate supervised continuous worker/generation recovery.
- **Required tests:** WorkerIntegrationTest: timeout/crash/reservation/new generation.
- **Acceptance criteria:** Deadline before reservation; recovery cannot duplicate stale OCR.
- **Migration considerations:** Drain/reconcile source work; no per-upload worker or old payload replay.

### FP-P08 - CLI import/diagnostics

- [ ] **BR-P08** (Hardening; decisions: D08).
- **Existing implementation:** [IngestHrPolicyPdf.php](../app/Console/Commands/IngestHrPolicyPdf.php), [TestOcrSetup.php](../app/Console/Commands/TestOcrSetup.php)
- **Expected behavior:** Synchronous CLI; six categories incl conduct/discipline; filename duplicate check; --force destroy/recreate; tool diagnostics.
- **New target:** Safe operator command with explicit destructive semantics and validation before deletion.
- **Required tests:** PolicyCliTest: invalid category/duplicate/force/tool missing/sample PDF.
- **Acceptance criteria:** Normal web replacement never becomes destructive CLI force; no deletion before input validated.
- **Migration considerations:** Keep CLI categories/files; no forced recreation during migration.

### FP-P09 - Effective-date eligibility

- [ ] **BR-P09** (Decision; decisions: D08).
- **Existing implementation:** [PolicyRetriever.php](../app/Services/PolicyRetriever.php), [HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php)
- **Expected behavior:** Date metadata exists; retrieval filters active flag/status only.
- **New target:** Preserve eligibility until an approved date policy replaces it.
- **Required tests:** PolicyDateTest: null/future/past under chosen rule.
- **Acceptance criteria:** Observed answer eligibility matches documented approved rule.
- **Migration considerations:** No invented expiry column or silent historical date rewrite.


## HR assistant

### FP-H01 - Questions and greetings

- [ ] **BR-H01** (Preserve; decisions: None).
- **Existing implementation:** [HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php)
- **Expected behavior:** Question required<=600; optional positive conversation ID; greeting no provider/log.
- **New target:** Shared assistant action and greeting branch.
- **Required tests:** AssistantRequestTest: invalid/overlong/type/greeting.
- **Acceptance criteria:** Greeting has log_id=null/no charge; invalid input422.
- **Migration considerations:** Preserve raw historical questions; review overlong exceptions.

### FP-H02 - Keyword retrieval

- [ ] **BR-H02** (Preserve; decisions: D03).
- **Existing implementation:** [PolicyRetriever.php](../app/Services/PolicyRetriever.php)
- **Expected behavior:** Up to12 Unicode keywords>=3chars/stopwords; LIKE score thenID; max5chunks; active flag+status.
- **New target:** Equivalent deterministic keyword retrieval, no automatic vector search.
- **Required tests:** RetrievalTest: English/Filipino/Taglish/empty/ties/inactive states.
- **Acceptance criteria:** Selected order matches approved fixtures; no-keyword query does not match all.
- **Migration considerations:** Preserve chunk/search text; collation deltas reviewed.

### FP-H03 - Grounding/context/history

- [ ] **BR-H03** (Preserve; decisions: None).
- **Existing implementation:** [HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php), [PolicyRetriever.php](../app/Services/PolicyRetriever.php), [PolicyChatPrompt.php](../app/Services/PolicyChatPrompt.php)
- **Expected behavior:** Miss fixed referral/no AI; subject-free follows2questions;3exchanges;11000char context; same-language/source rules.
- **New target:** Bounded grounded PolicyChat service.
- **Required tests:** GroundingTest: miss/subject switch/followup/long/untrusted text/history isolation.
- **Acceptance criteria:** No paid call on miss; no other-conversation/user history; citations selected-source only.
- **Migration considerations:** Preserve matched_chunk_ids and prior answers; no regeneration.

### FP-H04 - Provider and sanitized failure

- [ ] **BR-H04** (Decision; decisions: D13).
- **Existing implementation:** [AnthropicService.php](../app/Services/AnthropicService.php), [GeminiService.php](../app/Services/GeminiService.php), [GPTService.php](../app/Services/GPTService.php), [services.php](../config/services.php)
- **Expected behavior:** Anthropic selected; timeout60/connect10; no retries; complete response required; alternatives not automatic failover.
- **New target:** Strict HTTP provider adapter with approved model.
- **Required tests:** ProviderContractTest: timeout/non2xx/partial/empty/bad UTF8/log redaction.
- **Acceptance criteria:** Failures are503/error, never persisted as successful policy response; no silent paid retry.
- **Migration considerations:** Keys re-provisioned privately, not migrated into data/fixtures.

### FP-H05 - Owned conversations/history/feedback

- [ ] **BR-H05** (Preserve; decisions: None).
- **Existing implementation:** [HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php), [HrConversation.php](../app/Models/HrConversation.php), [HrChatLog.php](../app/Models/HrChatLog.php)
- **Expected behavior:** Own history20/sidebar100 star+recent; rename<=120; +/-1 own feedback; unknown ask ID starts own new; foreign transcript404.
- **New target:** Owned conversation queries/actions and UI.
- **Required tests:** ConversationTest: isolation/stale ID/star/rename/feedback/batch citations.
- **Acceptance criteria:** No ID existence/content leak; ordering/limits/feedback shapes match.
- **Migration considerations:** Keep IDs/owner/title/star/times/feedback/citation arrays.

### FP-H06 - Delete/clear races

- [ ] **BR-H06** (Preserve; decisions: None).
- **Existing implementation:** [HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php)
- **Expected behavior:** Own logs+conversation deletion transactional; saveAnswer locks/rechecks and gives409 if deleted during answer.
- **New target:** Equivalent deletion and guarded final save.
- **Required tests:** ConversationRaceTest: clear/delete during provider call/repeated delete.
- **Acceptance criteria:** Deleted data not resurrected; no other-user or partial orphan deletion.
- **Migration considerations:** Do not rerun old conversation backfill over mapped history.

### FP-H07 - Budgets and full/floating safe UI

- [ ] **BR-H07** (Hardening; decisions: None).
- **Existing implementation:** [RouteServiceProvider.php](../app/Providers/RouteServiceProvider.php), [hr-chat-core.js](../public/js/hr-chat-core.js), [hr-float-widget.blade.php](../resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php)
- **Expected behavior:** Ask20/min; actions120/min; full page and floating widget share safe Markdown/requests/errors/private state.
- **New target:** Shared Livewire4/client implementation retaining both entrypoints.
- **Required tests:** ChatBrowserTest; port tests/JavaScript/HrChatCoreTest.cjs: XSS/stale/timeout/419/429.
- **Acceptance criteria:** No unsafe HTML/images/scripts or cross-account state; history does not consume ask budget.
- **Migration considerations:** No transcript in public cache; reset local state on account change.


## Operations and migration

### FP-O01 - New-stack compatibility

- [ ] **BR-O01** (Infrastructure; decisions: None).
- **Existing implementation:** [composer.json](../composer.json), [package.json](../package.json), [AppServiceProvider.php](../app/Providers/AppServiceProvider.php)
- **Expected behavior:** Legacy framework/providers/CrudTrait/Mix/Livewire2 are implementation dependencies.
- **New target:** Clean supported stable-locked Laravel13/PHP8.5/Filament5/Livewire4/Vite architecture.
- **Required tests:** StackAcceptance: real Composer solve/platform audit/build/auth/upload/resource smoke.
- **Acceptance criteria:** No copied Backpack/runtime lock; verified new PHP and assets.
- **Migration considerations:** Separate new project; no current source/dependency changes.

### FP-O02 - Import integrity and no side effects

- [ ] **BR-O02** (Infrastructure; decisions: D01,D03,D15).
- **Existing implementation:** [migrations](../database/migrations), [Ticket.php](../app/Models/Ticket.php), [TicketObserver.php](../app/Observers/TicketObserver.php), [AttachmentTransactions.php](../app/Services/AttachmentTransactions.php)
- **Expected behavior:** Create/save hooks can reroute/reassign/notify/timestamp; migration chain is not an importer.
- **New target:** Versioned resumable importer and immutable source manifest.
- **Required tests:** MigrationIntegrityTest: count/FK/hash/null/IDs/restart/divergence/no notifications/jobs.
- **Acceptance criteria:** Import reconciles exactly; rerun neither duplicates nor overwrites divergent target data.
- **Migration considerations:** Read-only source and explicit target; every repair approved.

### FP-O03 - Runtime state/cutover/rollback

- [ ] **BR-O03** (Infrastructure; decisions: D09,D10,D12).
- **Existing implementation:** [queue.php](../config/queue.php), [session.php](../config/session.php), [2019_12_14_000001_create_personal_access_tokens_table.php](../database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php)
- **Expected behavior:** Old queue/session/token runtime belongs to old classes/keys.
- **New target:** Drain/reconcile jobs, revoke sessions/reissue tokens; single-writer cutover and write-aware rollback.
- **Required tests:** CutoverRehearsal: freeze/final copy/switch/failure/new writes rollback.
- **Acceptance criteria:** No stale job replay or lost accepted new writes.
- **Migration considerations:** Archive protected evidence if needed; no operational PHP payload replay.

### FP-O04 - Scope exclusions and history limits

- [ ] **BR-O04** (Infrastructure; decisions: None).
- **Existing implementation:** [Kernel.php](../app/Console/Kernel.php), [web.php](../routes/web.php), [custom.php](../routes/backpack/custom.php)
- **Expected behavior:** No active escalation schedule/settings CRUD/offline outbox/ticket-email/complete immutable audit.
- **New target:** Preserve implemented scope; enhancements separately authorized.
- **Required tests:** ScopeReview: every old module mapped and exclusions explicit.
- **Acceptance criteria:** No absent feature counted as reconstructed; no existing feature dropped.
- **Migration considerations:** Current fields cannot reconstruct lost status/reopen event history.

## Cross-feature completion gates

- [ ] All ten CRUD resources and every custom page/component/outside-panel contract in [16](16-BACKPACK-TO-FILAMENT-MAPPING.md) have implemented navigation and acceptance evidence.
- [ ] Actual MySQL constraints, collation, locking and concurrency tests pass; SQLite alone is insufficient.
- [ ] Counts, IDs/FKs/grants/history/timestamps/notification read state/file hashes reconcile under [18](18-DATABASE-MIGRATION-PLAN.md).
- [ ] Visual/browser acceptance covers every role, kiosk update/reset, both forms, floating/full assistant, PDF fonts/page sizes and QR scanning.
- [ ] SEC-01 through SEC-11 are closed with direct/reactive tests and review; no new bulk/global-search leak exists.
- [ ] New dependency lock, PHP web/worker, build, current advisory review and external tool/provider contracts pass.
- [ ] Backup restore, writer freeze, final import, cutover and rollback after accepted new writes are rehearsed.
- [ ] Every blocking decision has owner/disposition; approved differences are reported rather than called exact parity.

There are **83 feature checkboxes** plus these cross-feature gates. Loading a Filament dashboard or saving generated CRUD records does not establish completion.
