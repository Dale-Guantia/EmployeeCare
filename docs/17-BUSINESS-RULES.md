# 17 - Business Rules and Security Deviations

Prepared **2026-10-28**. This is the authoritative rebuild rule register. Proposed targets are not implemented classes. Preserve means reproduce verified legitimate behavior; Hardening is an explicit security/correctness deviation; Decision needs the listed D-ID resolved; Infrastructure concerns delivery/import safety. No current application behavior was changed. Decisions are defined in [15](15-REBUILD-SPECIFICATION.md#6-decision-register-and-implementation-gates).

Each BR ID has a corresponding testable FP ID in [19](19-FUNCTIONAL-PARITY-CHECKLIST.md). Existing source takes precedence over framework defaults or role-name assumptions. Historical test results are not current rebuild acceptance.

## Authorization matrix

Sources: [role grants](../database/seeders/RolesAndPermissionsSeeder.php), [controller](../app/Http/Controllers/Admin/TicketCrudController.php), [visibility](../app/Models/Ticket.php), [assignment](../app/Services/TicketAssignmentRules.php). Actual imported direct/role grants may differ. Multi-role composition is D04.

| Operation | Admin | Department Head | Division Head | HR Staff | Employee |
| --- | --- | --- | --- | --- | --- |
| Visible tickets | All with ticket.view | Own + department + incoming pending | Own + division + incoming pending | Own + assigned | Own |
| Create | Role plus create/view grant | Same | Same | Same | Same |
| Generic edit | update grant | update grant | update grant | Denied | Denied |
| Delete | delete grant | Potential role gate but no seeded delete grant | Denied | Denied | Denied |
| Manual assign | Eligibility rules | Department staff | Division staff | Explicit ticket.assign; ticket-location targets | Denied |
| Resolve | Allowed | Current department | Current division | Assigned ticket | Denied |
| Reopen | Creator only | Creator only | Creator only | Creator only | Creator only |
| Request transfer | Allowed | Current department | Current division | Denied | Denied |
| Accept/reject transfer | Allowed | Receiving department | Receiving division | Denied | Denied |
| Cancel transfer | Allowed | If requester | If requester | No normal request path | No normal request path |
| Issue catalog | CRUD | View/create/update | View/create/update | Denied | Denied |
| Departments/divisions | CRUD | View only | Denied | Denied | Denied |
| Users/roles/permissions/status/priority | Actual grants | No seeded access | No seeded access | No seeded access | No seeded access |
| Reports | Seeded report grants | No seeded access | No seeded access | No seeded access | No seeded access |
| Policy manager | Yes, global | Yes, global | Denied | Denied | Denied |
| Assistant/own account/notifications | Active member | Active member | Active member | Active member | Active member |

Discussion/download require current record visibility; discussion forbids resolved state. Creator-only reopen cannot be bypassed by a broad administrator convention. Query scoping must protect list, direct detail, search, widgets, downloads and reactive requests. Report grants do not automatically provide department filtering. No bulk actions follow from this matrix.

## Report formulas and date compatibility

Sources: [TicketReportQueries](../app/Services/TicketReportQueries.php), [TicketReportWidgets](../app/Services/TicketReportWidgets.php), [ReportsController](../app/Http/Controllers/Admin/ReportsController.php), [User relations](../app/Models/User.php), [SurveyReportQueries](../app/Services/SurveyReportQueries.php), [SQL dialect](../app/Services/SqlDialectHelper.php).

- KPI denominator is admitted tickets in the creation-date range; resolved/pending use current named status. Average resolution is rounded mean of source integer elapsed minutes over dated currently resolved rows, zero when empty. Percentages round to one decimal.
- Reopen count uses current Reopened/non-null reopened_at, not all historic reopen events. Old lifecycle hooks clear that data.
- Open overdue is created_at earlier than now minus three elapsed days. Resolved widget/model uses integer elapsed hours greater than 72; user SQL-based overdue uses driver-specific hour difference. Test these separately.
- Trend uses day for date distance <=92, week <=730, otherwise month; all-time forces month. Preserve zero-fill and period start conventions. Duration buckets use source integer hours: <24, 24-<72, 72-<168, >=168.
- Funnel order is Unassigned/Pending/Resolved/Reopened. Transfer count is distinct admitted tickets having any request state; it is not accepted-transfer count. HR-division rates/zero-fill retain the source cohort.
- Staff report distinguishes resolved_by from assigned_to, dates tickets by creation, uses its department1 cohort and zero-activity rules. Catalog SQL-duration averages are a separate metric path.
- Satisfaction counts exact matches across four dimensions; one response can contribute four choice counts. Historical case/trailing spaces are significant. Source cohort is HR staff plus department1 division heads, not an invented universal department filter.
- Source locks Carbon **2.73.0**; newer Carbon differences can be signed/fractional. Preserve explicit units, absolute/truncation/rounding where intended. SQL Server DATEDIFF counts boundaries; MySQL TIMESTAMPDIFF measures complete units. D03/D07 must approve exact fixture results before unifying calculations.

Official comparison: [Carbon 2.73 Difference implementation](https://raw.githubusercontent.com/briannesbitt/Carbon/2.73.0/src/Carbon/Traits/Difference.php) returns integer hours/minutes with absolute default; [Carbon 3.8.4 implementation](https://raw.githubusercontent.com/briannesbitt/Carbon/3.8.4/src/Carbon/Traits/Difference.php) uses floating values and a different default sign policy. [Laravel13 manifest](https://raw.githubusercontent.com/laravel/framework/13.x/composer.json) requires Carbon ^3.8.4. These are concrete compatibility changes requiring tests, not a hypothetical architecture concern.

## Rule register

### Authentication

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-A01** Email or username login | Email OR username, password and active=true.<br>[LoginController.php](../app/Http/Controllers/Admin/Auth/LoginController.php) | Custom Filament Login; active-session checks. | Preserve; None |
| **BR-A02** External login and redirects | Non-local login redirects externally; existing-session destination is hardcoded.<br>[LoginController.php](../app/Http/Controllers/Admin/Auth/LoginController.php)<br>[session.php](../config/session.php) | Approved auth adapter and configured return paths; no invented SSO. | Decision; D02,D12 |
| **BR-A03** Registration and roleless membership | Registration requires name/unique username/email/confirmed password min6; creates active roleless user.<br>[RegisterController.php](../app/Http/Controllers/Admin/Auth/RegisterController.php)<br>[base.php](../config/backpack/base.php) | Approved signup/provisioning; legitimate employees remain admitted. | Decision; D02,D16 |
| **BR-A04** Profile details | Name/username/authentication column required max255; unique except self.<br>[MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php) | Own MyAccount Page and shared rules. | Preserve; D02 |
| **BR-A05** Skills and safe tags | Optional array; strings max100; empty removes skills.<br>[MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php)<br>[my_account.blade.php](../resources/views/vendor/backpack/base/my_account.blade.php) | TagsInput plus backend validation and literal text rendering. | Hardening; None |
| **BR-A06** Avatar upload/removal | JPG/JPEG/PNG/WebP max2MB; remove option; other details may save with avatar warning.<br>[MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php)<br>[User.php](../app/Models/User.php) | Safe avatar replacement with explicit partial-failure feedback. | Preserve; None |
| **BR-A07** Passwords and recovery | Profile delegates password rules; admin blank password retains hash; recovery exists, delivery unverified.<br>[MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php)<br>[UserCrudController.php](../app/Http/Controllers/Admin/UserCrudController.php)<br>[auth.php](../config/auth.php) | Approved password/reset rules with bcrypt compatibility and safe blank handling. | Decision; D02,D09 |
| **BR-A08** Login timestamp | last_login_at update failure must not block successful login.<br>[UpdateLastLoginAt.php](../app/Listeners/UpdateLastLoginAt.php)<br>[EventServiceProvider.php](../app/Providers/EventServiceProvider.php) | Small redacted login listener. | Preserve; None |

### Authorization

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-B01** Active shared panel | Panel includes all active authenticated roles, not admin role only; inactive session invalidated.<br>[CheckIfAdmin.php](../app/Http/Middleware/CheckIfAdmin.php) | canAccessPanel and middleware plus resource policies. | Decision; D16 |
| **BR-B02** Actual grants and full editor | Five default roles; ticket.assign exists but grouped editor omits it and syncs submitted grants.<br>[RolesAndPermissionsSeeder.php](../database/seeders/RolesAndPermissionsSeeder.php)<br>[RoleCrudController.php](../app/Http/Controllers/Admin/RoleCrudController.php) | Spatie8; complete Role/Permission resources; fresh defaults separate from imported grants. | Hardening; D15 |
| **BR-B03** Ticket visibility | Active ticket.view: admin all; others own plus head department/division/incoming pending or HR assigned.<br>[Ticket.php](../app/Models/Ticket.php) | Shared visibility query for all read surfaces. | Preserve; None |
| **BR-B04** Head list and multi-role rules | List narrows broader visibleTo by current location and employee filters.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)<br>[Ticket.php](../app/Models/Ticket.php) | Approved visibility composition with explicit incoming-transfer navigation. | Decision; D04 |
| **BR-B05** Generic CRUD versus custom actions | Admin/dept_head role gates intersect grants; div_head create/update; staff/employee create only despite ticket.update.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | Explicit TicketPolicy; separate custom operation policies. | Preserve; None |
| **BR-B06** Creator-only reopening | Resolved ticket creator only, including admin.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | Explicit reopen policy without blanket admin bypass. | Preserve; None |
| **BR-B07** Public lookup and API consumers | Divisions lookup public without declared limiter; api/user Sanctum; no custom token issuer.<br>[custom.php](../routes/backpack/custom.php)<br>[api.php](../routes/api.php) | Bounded compatible lookup and approved retained API/auth behavior. | Decision; D12 |

### Catalog

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-C01** Departments | Name required string max255 unique except self; default head read-only.<br>[DepartmentCrudController.php](../app/Http/Controllers/Admin/DepartmentCrudController.php)<br>[DepartmentRequest.php](../app/Http/Requests/DepartmentRequest.php) | DepartmentResource/policy and coherent uniqueness. | Preserve; None |
| **BR-C02** Divisions | Name max255 required; department required existing; no unique name rule.<br>[DivisionCrudController.php](../app/Http/Controllers/Admin/DivisionCrudController.php)<br>[DivisionRequest.php](../app/Http/Requests/DivisionRequest.php) | DivisionResource and backend relationship validation. | Preserve; None |
| **BR-C03** Issues | Required coherent department/division/priority; description max255; optional icon max255; heads create/update global catalog.<br>[IssueCrudController.php](../app/Http/Controllers/Admin/IssueCrudController.php)<br>[IssueRequest.php](../app/Http/Requests/IssueRequest.php) | IssueResource and shared rules; no invented head row restriction. | Preserve; None |
| **BR-C04** Status and priority semantics/colors | Unique required names max255; color string max20; lifecycle names cached.<br>[PriorityRequest.php](../app/Http/Requests/PriorityRequest.php)<br>[StatusRequest.php](../app/Http/Requests/StatusRequest.php)<br>[Status.php](../app/Models/Status.php) | Safe colors and semantic status codes; guarded required lookup deletion. | Hardening; D06 |
| **BR-C05** Administrative users | Name/email/password create; blank update retains hash; department/matching division/roles/active.<br>[UserCrudController.php](../app/Http/Controllers/Admin/UserCrudController.php) | UserResource with explicit field policies and approved secure password rules. | Decision; D02,D15 |
| **BR-C06** Roles and permissions | Package create names unique/max255; update rules differ; custom grant IDs synchronized.<br>[RoleCrudController.php](../app/Http/Controllers/Admin/RoleCrudController.php)<br>[PermissionCrudController.php](../app/Http/Controllers/Admin/PermissionCrudController.php) | RoleResource/PermissionResource; complete grant list and collision-safe updates. | Hardening; D15 |
| **BR-C07** Caches and referenced deletion | 15-minute caches invalidated on save/delete; FKs can prevent referenced deletion.<br>[Department.php](../app/Models/Department.php)<br>[Division.php](../app/Models/Division.php)<br>[Status.php](../app/Models/Status.php) | Equivalent invalidation and clear dependency feedback. | Preserve; None |

### Tickets

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-T01** Create branch validation | Boolean custom flag; issue OR custom title/destination; message required max65535; title max255.<br>[TicketRequest.php](../app/Http/Requests/TicketRequest.php) | Shared creation rules/normalization in CreateTicket action. | Preserve; None |
| **BR-T02** Reference generation | TKT- six uppercase hex chars; unique and bounded five collision attempts.<br>[Ticket.php](../app/Models/Ticket.php) | Reference generator plus database uniqueness/retry. | Preserve; None |
| **BR-T03** Catalog-derived routing | Issue supplies department/division/priority only on create/issue change.<br>[Ticket.php](../app/Models/Ticket.php)<br>[TicketRequest.php](../app/Http/Requests/TicketRequest.php) | Canonicalize in create/update, not every save. | Preserve; None |
| **BR-T04** Creator and initial state | Creator session-derived if missing; empty status defaults numeric3.<br>[Ticket.php](../app/Models/Ticket.php) | Actor-selected creator and semantic Unassigned status. | Hardening; D06 |
| **BR-T05** Deterministic automatic assignment | Active HR in department/optional division; normalized skill +5 for standard issues; custom ignores skills; ties active workload then lowest ID.<br>[TicketAutoAssignmentService.php](../app/Services/TicketAutoAssignmentService.php)<br>[TicketObserver.php](../app/Observers/TicketObserver.php) | Shared eligibility/selection invoked once on create/accepted transfer. | Preserve; None |
| **BR-T06** Assignment failure and initial notifications | Assignment error logs without intentionally losing created ticket; initial Pending suppresses only status notice.<br>[TicketObserver.php](../app/Observers/TicketObserver.php)<br>[Ticket.php](../app/Models/Ticket.php) | One transition/side-effect path and recoverable failure feedback. | Preserve; None |
| **BR-T07** Manual assignment | Admin/heads or explicit HR ticket.assign; active coherent targets scoped to actor; null clears; resolved UI/handler disagree.<br>[TicketAssignmentRules.php](../app/Services/TicketAssignmentRules.php)<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | AssignStaff action with approved state guard and shared candidates. | Decision; D05 |
| **BR-T08** Edit field boundaries | Content/file UI uses creator/admin/div_head; server generic update lacks equivalent complete field policy.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | Approved per-field whitelist in UpdateTicket for every caller. | Decision; D05 |
| **BR-T09** Resolve | Admin/current head/assigned HR resolves; time/resolver set, reopen cleared, pending transfers cancelled.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)<br>[Ticket.php](../app/Models/Ticket.php) | Atomic ResolveTicket action/policy. | Preserve; None |
| **BR-T10** Reopen and timestamp lifecycle | Creator+Resolved; reopened time set and resolution fields cleared; other statuses clear stale metadata.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)<br>[Ticket.php](../app/Models/Ticket.php) | Reopen/UpdateStatus actions with invariant tests. | Preserve; None |
| **BR-T11** Overdue boundary | Open older than3 days; resolved integer hours>72; original creation time after reopen; SQL path differs.<br>[Ticket.php](../app/Models/Ticket.php)<br>[TicketReportWidgets.php](../app/Services/TicketReportWidgets.php)<br>[SqlDialectHelper.php](../app/Services/SqlDialectHelper.php) | Approved per-metric compatibility duration functions. | Decision; D03,D07 |
| **BR-T12** Ticket deletion and cleanup | Transactional deletion removes comments individually and files after commit; ticket transfers cascade.<br>[Ticket.php](../app/Models/Ticket.php)<br>[TicketComment.php](../app/Models/TicketComment.php) | DeleteTicket action, no bulk deletion shortcut. | Preserve; None |

### Transfers

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-X01** Request transfer | Admin/current head; unresolved visible ticket; different coherent target; one pending under parent lock.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | RequestTransfer action; validated note bounds from D05. | Decision; D05 |
| **BR-X02** Accept transfer | Receiving head/admin; move, clear assignment, rerun selection, record responder/time, notify requester.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)<br>[TicketAutoAssignmentService.php](../app/Services/TicketAutoAssignmentService.php) | AcceptTransfer transaction and fresh pending lock. | Preserve; None |
| **BR-X03** Reject and cancel | Receiving head/admin rejects with note; requester/admin cancels pending; resolve cancels pending.<br>[TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php)<br>[TicketReassignmentRequest.php](../app/Models/TicketReassignmentRequest.php) | Reject/Cancel actions with same lock and approved note validation. | Decision; D05 |

### Files and discussion

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-F01** Upload constraints and retained ownership | Max20 files,20MB each; jpg/jpeg/png/pdf/doc/docx/xls/xlsx/txt; retained paths already on record.<br>[TicketRequest.php](../app/Http/Requests/TicketRequest.php)<br>[TicketChat.php](../app/Http/Livewire/TicketChat.php)<br>[AttachmentStorage.php](../app/Services/AttachmentStorage.php) | Private upload adapter/backend MIME/count/size/reference checks. | Hardening; None |
| **BR-F02** Write/transaction compensation | Verify stream writes; cleanup known failures; retain files on ambiguous DB/reference state.<br>[AttachmentStorage.php](../app/Services/AttachmentStorage.php)<br>[AttachmentTransactions.php](../app/Services/AttachmentTransactions.php) | Shared storage transaction service across HTTP/Livewire. | Preserve; None |
| **BR-F03** Protected downloads | Visible ticket, child/index binding, path checks/no-store/nosniff; legacy public fallback.<br>[TicketAttachmentController.php](../app/Http/Controllers/Admin/TicketAttachmentController.php)<br>[AttachmentStorage.php](../app/Services/AttachmentStorage.php)<br>[.htaccess](../public/.htaccess) | Record-bound private download controller and deny public aliases. | Hardening; D09 |
| **BR-F04** Discussion | 5s polling, oldest comments/authors; max65535 text required without files; resolved locked; errors visible.<br>[TicketChat.php](../app/Http/Livewire/TicketChat.php)<br>[ticket-chat.blade.php](../resources/views/livewire/ticket-chat.blade.php) | Livewire4 discussion + AddComment action. | Preserve; None |
| **BR-F05** Reconcile/privatize command | Read-only default; private orphan cleanup requires pause assertion and age48-87600h; public orphans manual.<br>[ReconcileTicketAttachments.php](../app/Console/Commands/ReconcileTicketAttachments.php)<br>[PrivatizeTicketAttachments.php](../app/Console/Commands/PrivatizeTicketAttachments.php)<br>[AttachmentInventory.php](../app/Services/AttachmentInventory.php) | Safe new operator tools with roots/hash/fresh checks. | Preserve; None |
| **BR-F06** Avatar sync command | Exact employee-number WebP match; leading zeros retained; duplicate matches fail; verified copy/dry-run.<br>[SyncUserAvatars.php](../app/Console/Commands/SyncUserAvatars.php) | Equivalent restricted operator command. | Preserve; None |

### Notifications

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-N01** Database feed and history | Database payload title/message/url/extra; feed10/unread; page20; own read-one/all/clear.<br>[TicketSystemNotification.php](../app/Notifications/TicketSystemNotification.php)<br>[NotificationController.php](../app/Http/Controllers/Admin/NotificationController.php) | Database notification/display adapter for Filament. | Preserve; None |
| **BR-N02** Recipients and actors | Created admins/matching heads; assignment/status role/owner rules; comments creator/assignee/prior participants; remove actor/dedupe. Transfer receiver selection does not explicitly filter inactive accounts.<br>[TicketNotificationService.php](../app/Services/TicketNotificationService.php) | Preserved recipient selector with active/current scope checks. | Hardening; D09 |
| **BR-N03** Preferences and transfer notifications | Four stored flags; absent transfer preferences default true; transfers notify receivers/admin or requester.<br>[MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php)<br>[User.php](../app/Models/User.php)<br>[TicketNotificationService.php](../app/Services/TicketNotificationService.php) | Explicit preserved defaults; no invented preference columns silently. | Preserve; None |

### Reports

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-R01** Dashboard scope and employee landing | Employee landing redirect; others ticket.view; visible ticket aggregate/query and latest10.<br>[DashboardController.php](../app/Http/Controllers/Admin/DashboardController.php)<br>[TicketReportQueries.php](../app/Services/TicketReportQueries.php) | Scoped Dashboard widgets and SubmitTicket Page. | Preserve; None |
| **BR-R02** Periods | Month-to-date/all/calendar h1-h2/custom full days; invalid custom falls back with message.<br>[ReportPeriodResolver.php](../app/Services/ReportPeriodResolver.php) | Shared period resolver and same approved UI semantics. | Decision; D03,D07 |
| **BR-R03** KPI formula compatibility | Current totals/resolved/pending; rounded mean integer minutes; overdue/reopen percentages1decimal; current reopen not full history.<br>[TicketReportWidgets.php](../app/Services/TicketReportWidgets.php)<br>[TicketReportQueries.php](../app/Services/TicketReportQueries.php) | Pure bounded metrics preserving approved units/rounding. | Decision; D07 |
| **BR-R04** Chart and transfer datasets | Day<=92days/week<=730/else month; all forces month; duration<24/<72/<168/else; funnel; distinct any-state transfer tickets.<br>[TicketReportWidgets.php](../app/Services/TicketReportWidgets.php)<br>[TicketReportQueries.php](../app/Services/TicketReportQueries.php) | Equivalent zero-filled scoped datasets; retain hidden-chart decision. | Decision; D07 |
| **BR-R05** Staff and catalog report | Broad reports.view scope; department1 cohort; resolver/assignee distinctions; creation-date range; zero-activity toggle.<br>[ReportsController.php](../app/Http/Controllers/Admin/ReportsController.php)<br>[User.php](../app/Models/User.php) | EmployeeCareReports queries with explicit permission/organization mapping. | Decision; D06,D11 |
| **BR-R06** Satisfaction aggregates | Exact case/trailing-space four-dimension choice matches, staff groups and zero activity; counts sum dimensions.<br>[SurveyReportQueries.php](../app/Services/SurveyReportQueries.php) | Equivalent MySQL aggregate semantics. | Preserve; None |
| **BR-R07** Paged response history | length1-100; bounded/quoted search; wildcard escaping; fixed order columns; DataTables response envelope.<br>[SurveyResponseTable.php](../app/Services/SurveyResponseTable.php) | Filament table or compatibility JSON with same search/page ordering behavior. | Preserve; D12 |
| **BR-R08** PDF outputs | Ordered date range; ticket/CSS A4 portrait; ARTA8.5x13 landscape,2000row cap; generic failure feedback.<br>[ReportsController.php](../app/Http/Controllers/Admin/ReportsController.php)<br>[SurveyReportsController.php](../app/Http/Controllers/Admin/SurveyReportsController.php)<br>[ArtaSurveyReportsController.php](../app/Http/Controllers/Admin/ArtaSurveyReportsController.php)<br>[reports.php](../config/reports.php) | Dedicated PDF service/print Blade/fonts with same content/limits. | Decision; D13 |

### Surveys and PWA

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-S01** Satisfaction staff/service selection | Department1 divisions excluding Department Head; ordered styles; listed head/HR staff and matching service; no explicit active filter.<br>[SurveyController.php](../app/Http/Controllers/SurveyController.php) | Public survey service with approved organization/inactive-selection rule. | Decision; D06 |
| **BR-S02** Ratings and HTTP result | Four exact required ratings; UUID; saved/errors422 JSON or form redirect/thank_you.<br>[SurveyController.php](../app/Http/Controllers/SurveyController.php) | Public Blade/interactive form with shared backend rules. | Preserve; None |
| **BR-S03** UUID idempotency | Same UUID+same answers returns existing; different answers rejected; unique index resolves races.<br>[SurveySubmission.php](../app/Services/SurveySubmission.php)<br>[2026_10_07_110000_add_survey_submission_ids.php](../database/migrations/2026_10_07_110000_add_survey_submission_ids.php) | Lowercase UUID and nullable unique MySQL constraint/transaction handling. | Preserve; None |
| **BR-S04** Submission CSRF and budgets | 30/min session plus300/min actual peer; fresh CSRF no-store.<br>[RouteServiceProvider.php](../app/Providers/RouteServiceProvider.php)<br>[web.php](../routes/web.php) | Web CSRF and matching peer/session caps with explicit proxy design. | Preserve; None |
| **BR-S05** Offline viewing only | Allowlisted public assets/eligible stripped-token HTML; reconnect to submit; no POST outbox.<br>[sw-survey.js](../public/sw-survey.js)<br>[survey.js](../public/survey-assets/js/survey.js)<br>[manifest-survey.json](../public/manifest-survey.json) | Dedicated worker with safe asset versioning and no panel cache. | Preserve; D12 |
| **BR-S06** Kiosk reset/update and QR | Same-document confirmed save/reset; retry same UUID; next client new UUID; update at safe boundary; QR external target.<br>[survey.js](../public/survey-assets/js/survey.js)<br>[survey_form.blade.php](../resources/views/survey_form.blade.php) | Equivalent full-screen kiosk/reset and approved QR rendering. | Decision; D12,D13 |

### ARTA

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-Q01** Demographics and CC | Optional client/sex/age1-100/region1-17/service<=255; CC ranges1-4,1-5,1-4.<br>[ArtaController.php](../app/Http/Controllers/ArtaController.php)<br>[ArtaSurvey.php](../app/Models/ArtaSurvey.php) | Public ARTA fields/constants and same optional validation. | Preserve; None |
| **BR-Q02** SQD and derived N/A | At least one SQD0-8; six exact values; CC1=4 forces CC2=5,CC3=4; shared UUID save.<br>[ArtaController.php](../app/Http/Controllers/ArtaController.php) | ARTA rule service and redirect/thank-you. | Preserve; None |
| **BR-Q03** Public transport/offline distinction | Form redirects/QR; worker registration does not cache /arta-form or save offline.<br>[arta.js](../public/arta-assets/js/arta.js)<br>[arta_form.blade.php](../resources/views/arta_form.blade.php)<br>[sw-survey.js](../public/sw-survey.js) | Public ARTA outside panel, no invented offline feature. | Preserve; D12,D13 |

### Policy documents and OCR

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-P01** Manager access and upload fields | Admin/dept_head global; title<=255; four browser categories; optional effective date; PDF<=20MB.<br>[HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php) | Policy resource/custom pages with server role/field checks. | Preserve; None |
| **BR-P02** Readiness and dispatch atomicity | Check schema before storage; dedicated policy_jobs+marker transaction; duplicate marker prevents duplicate enqueue.<br>[PolicyIngestService.php](../app/Services/PolicyIngestService.php)<br>[queue.php](../config/queue.php) | Same-connection database coordinator and versioned job. | Preserve; None |
| **BR-P03** Generation claim/commit | Claim pending current version; skip stale/duplicate/missing-version jobs; guarded atomic chunk/state swap.<br>[PolicyIngestService.php](../app/Services/PolicyIngestService.php)<br>[IngestPolicyDocumentJob.php](../app/Jobs/IngestPolicyDocumentJob.php) | Short claim/commit locks around off-transaction extraction. | Preserve; None |
| **BR-P04** Extraction and cache | Native plus sparse-page OCR;100char/300dpi/oem1/psm3/60s-page/concurrency2 defaults; hash cache.<br>[OcrService.php](../app/Services/OcrService.php)<br>[PolicyIngestService.php](../app/Services/PolicyIngestService.php)<br>[ocr.php](../config/ocr.php) | Safe subprocess OCR with private workdir and equivalent page outputs. | Preserve; None |
| **BR-P05** Chunking and quality | 300word/50overlap/min30; page/section; incomplete/unknown/<60 OCR -> needs_review; active eligibility guarded.<br>[PolicyIngestService.php](../app/Services/PolicyIngestService.php)<br>[HrPolicyDocument.php](../app/Models/HrPolicyDocument.php) | Equivalent chunker/quality gate and failure states. | Preserve; None |
| **BR-P06** Metadata/replace/re-run/toggle/delete | Metadata immediate; replace version/pending; processing blocks edit/re-run; active/inactive toggle; delete lacks edit lock.<br>[HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php)<br>[PolicyIngestService.php](../app/Services/PolicyIngestService.php) | Explicit actions and approved deletion concurrency/tombstone rule. | Decision; D08 |
| **BR-P07** Worker lifetime and recovery | 900s timeout,1try,1200retry_after; Windows bounded listener; no worker => pending.<br>[IngestPolicyDocumentJob.php](../app/Jobs/IngestPolicyDocumentJob.php)<br>[queue.php](../config/queue.php)<br>[POLICY_INGESTION_QUEUE.md](../docs/POLICY_INGESTION_QUEUE.md) | Host-appropriate supervised continuous worker/generation recovery. | Preserve; None |
| **BR-P08** CLI import/diagnostics | Synchronous CLI; six categories incl conduct/discipline; filename duplicate check; --force destroy/recreate; tool diagnostics.<br>[IngestHrPolicyPdf.php](../app/Console/Commands/IngestHrPolicyPdf.php)<br>[TestOcrSetup.php](../app/Console/Commands/TestOcrSetup.php) | Safe operator command with explicit destructive semantics and validation before deletion. | Hardening; D08 |
| **BR-P09** Effective-date eligibility | Date metadata exists; retrieval filters active flag/status only.<br>[PolicyRetriever.php](../app/Services/PolicyRetriever.php)<br>[HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php) | Preserve eligibility until an approved date policy replaces it. | Decision; D08 |

### HR assistant

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-H01** Questions and greetings | Question required<=600; optional positive conversation ID; greeting no provider/log.<br>[HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php) | Shared assistant action and greeting branch. | Preserve; None |
| **BR-H02** Keyword retrieval | Up to12 Unicode keywords>=3chars/stopwords; LIKE score thenID; max5chunks; active flag+status.<br>[PolicyRetriever.php](../app/Services/PolicyRetriever.php) | Equivalent deterministic keyword retrieval, no automatic vector search. | Preserve; D03 |
| **BR-H03** Grounding/context/history | Miss fixed referral/no AI; subject-free follows2questions;3exchanges;11000char context; same-language/source rules.<br>[HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php)<br>[PolicyRetriever.php](../app/Services/PolicyRetriever.php)<br>[PolicyChatPrompt.php](../app/Services/PolicyChatPrompt.php) | Bounded grounded PolicyChat service. | Preserve; None |
| **BR-H04** Provider and sanitized failure | Anthropic selected; timeout60/connect10; no retries; complete response required; alternatives not automatic failover.<br>[AnthropicService.php](../app/Services/AnthropicService.php)<br>[GeminiService.php](../app/Services/GeminiService.php)<br>[GPTService.php](../app/Services/GPTService.php)<br>[services.php](../config/services.php) | Strict HTTP provider adapter with approved model. | Decision; D13 |
| **BR-H05** Owned conversations/history/feedback | Own history20/sidebar100 star+recent; rename<=120; +/-1 own feedback; unknown ask ID starts own new; foreign transcript404.<br>[HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php)<br>[HrConversation.php](../app/Models/HrConversation.php)<br>[HrChatLog.php](../app/Models/HrChatLog.php) | Owned conversation queries/actions and UI. | Preserve; None |
| **BR-H06** Delete/clear races | Own logs+conversation deletion transactional; saveAnswer locks/rechecks and gives409 if deleted during answer.<br>[HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php) | Equivalent deletion and guarded final save. | Preserve; None |
| **BR-H07** Budgets and full/floating safe UI | Ask20/min; actions120/min; full page and floating widget share safe Markdown/requests/errors/private state.<br>[RouteServiceProvider.php](../app/Providers/RouteServiceProvider.php)<br>[hr-chat-core.js](../public/js/hr-chat-core.js)<br>[hr-float-widget.blade.php](../resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php) | Shared Livewire4/client implementation retaining both entrypoints. | Hardening; None |

### Operations and migration

| Rule | Existing behavior / evidence | Rebuild rule | Treatment / decision |
| --- | --- | --- | --- |
| **BR-O01** New-stack compatibility | Legacy framework/providers/CrudTrait/Mix/Livewire2 are implementation dependencies.<br>[composer.json](../composer.json)<br>[package.json](../package.json)<br>[AppServiceProvider.php](../app/Providers/AppServiceProvider.php) | Clean supported stable-locked Laravel13/PHP8.5/Filament5/Livewire4/Vite architecture. | Infrastructure; None |
| **BR-O02** Import integrity and no side effects | Create/save hooks can reroute/reassign/notify/timestamp; migration chain is not an importer.<br>[migrations](../database/migrations)<br>[Ticket.php](../app/Models/Ticket.php)<br>[TicketObserver.php](../app/Observers/TicketObserver.php)<br>[AttachmentTransactions.php](../app/Services/AttachmentTransactions.php) | Versioned resumable importer and immutable source manifest. | Infrastructure; D01,D03,D15 |
| **BR-O03** Runtime state/cutover/rollback | Old queue/session/token runtime belongs to old classes/keys.<br>[queue.php](../config/queue.php)<br>[session.php](../config/session.php)<br>[2019_12_14_000001_create_personal_access_tokens_table.php](../database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php) | Drain/reconcile jobs, revoke sessions/reissue tokens; single-writer cutover and write-aware rollback. | Infrastructure; D09,D10,D12 |
| **BR-O04** Scope exclusions and history limits | No active escalation schedule/settings CRUD/offline outbox/ticket-email/complete immutable audit.<br>[Kernel.php](../app/Console/Kernel.php)<br>[web.php](../routes/web.php)<br>[custom.php](../routes/backpack/custom.php) | Preserve implemented scope; enhancements separately authorized. | Infrastructure; None |

## Approval and maintenance rule

Do not resolve a source inconsistency by silently removing a capability or reproducing a vulnerability. Record the chosen D-ID outcome, affected BR/FP entries and UI/API/data impacts. Direct/reactive request tests must enforce rules even when the original UI disabled the control. Every actual old module in [16](16-BACKPACK-TO-FILAMENT-MAPPING.md) must remain mapped.

This register contains **83 paired business/acceptance rules**. Security deviations supplement legitimate parity; they do not authorize changes to the current application.
