# 16 - Backpack to Filament Mapping

Prepared **2026-10-28**. All Filament class names below are **proposed new-app targets**, not classes created by this task. Source evidence is the current controllers/requests/models/views linked here and [existing route inventory](07-ROUTES-AND-API.md). The target is Filament **5**/Livewire **4**, not a copy of Filament 3 examples.

## Mapping contract

The panel is shared by all legitimate employee roles. Admission is distinct from resource/record/action permission. Filament schema validation must share server rules with HTTP/application actions; legacy FormRequests do not automatically run in a Filament save. Remove CrudTrait, backpack_user/backpack_auth helpers, CRUD facades, package request inheritance and Backpack view overrides from new code, replacing each dependency deliberately.

Use registered policies and strict authorization detection; scope actual resource queries as well as actions. Make active-account/authorization middleware persistent for subsequent Livewire requests, as well as rechecking the application action; an initial-page check alone is insufficient. Filament's resource `viewAny` controls resource access, not row visibility, and custom actions require their own checks. This distinction is documented in the [official resource security section](https://filamentphp.com/docs/5.x/resources/overview#authorization). [Panel configuration](https://filamentphp.com/docs/5.x/panel-configuration) covers strict policy configuration.

Bulk actions are **disabled for baseline parity**: no local controller uses BulkDeleteOperation or declares a verified bulk workflow. Do not add export/import/replicate/restore/deleteAny simply because a generator offers them. A future bulk operation needs per-record authorization, cleanup and separate acceptance. Current list/search/order/pagination behavior is retained; adding new status/date filters is an optional approved UI improvement, not a discovered existing custom filter.

## Resource-by-resource mapping

| Existing module / evidence | Proposed target | Form / validation | Table and record actions | Authorization / custom work |
| --- | --- | --- | --- | --- |
| [DepartmentCrudController](../app/Http/Controllers/Admin/DepartmentCrudController.php), [DepartmentRequest](../app/Http/Requests/DepartmentRequest.php) | DepartmentResource | TextInput department_name; required string max 255; unique except self | Name; list/search/order; view/create/edit/delete as allowed | department.view + individual write permission; default Department Head read-only; deletion respects references; preserve reference-cache invalidation |
| [DivisionCrudController](../app/Http/Controllers/Admin/DivisionCrudController.php), [DivisionRequest](../app/Http/Requests/DivisionRequest.php) | DivisionResource | division_name required string max 255; department_id required existing department | Name and department relation; view/create/edit/delete | division.view + individual write permission; no invented name uniqueness or department ownership restriction |
| [IssueCrudController](../app/Http/Controllers/Admin/IssueCrudController.php), [IssueRequest](../app/Http/Requests/IssueRequest.php) | IssueResource | Department/Division/Priority selects; required existing integer IDs; division belongs to department; description required max 255; icon nullable max 255 | Department/division/priority, description, icon behavior; ordinary CRUD/search | issue.view and operation grant; default heads create/update global catalog entries; do not silently scope them to own department |
| [PriorityCrudController](../app/Http/Controllers/Admin/PriorityCrudController.php), [PriorityRequest](../app/Http/Requests/PriorityRequest.php) | PriorityResource | Name required unique except self/max 255; color required max 20; target ColorPicker plus backend color constraint is an explicit hardening delta | Name/color badge; CRUD | priority grants; keep priority labels/colors/history IDs; no priority-specific SLA invented |
| [StatusCrudController](../app/Http/Controllers/Admin/StatusCrudController.php), [StatusRequest](../app/Http/Requests/StatusRequest.php) | StatusResource | Name required unique except self/max 255; constrained color; preserve Resolved/Pending/Unassigned/Reopened semantics | Name/color; permitted CRUD with guarded lifecycle-code administration | status grants; named lookup/cache migrated to immutable semantic codes (D06); do not allow casual deletion of required workflow status |
| [UserCrudController](../app/Http/Controllers/Admin/UserCrudController.php) | UserResource | Name/email/password, department/division, role checklist, active toggle; see detailed matrix below | Name, department, division, roles, active; permitted view/create/edit/delete | user grants; active and organization scope checks remain; no mass password/reset/impersonation feature assumed |
| [RoleCrudController](../app/Http/Controllers/Admin/RoleCrudController.php) | RoleResource | Name + complete grouped permission relationship; retain all actual grants including custom names/ticket.assign | Name and permissions; create/edit/delete | role grants; display/save ALL permission names, not only four standard suffixes; no-op edit cannot revoke omitted grants |
| [PermissionCrudController](../app/Http/Controllers/Admin/PermissionCrudController.php) and installed PermissionManager requests | PermissionResource | Name required max 255; create uniqueness; guard web; update collision handling consistent with target schema | Name/guard and permitted CRUD | permission grants; generated policies do not auto-grant newly created permission names |
| [TicketCrudController](../app/Http/Controllers/Admin/TicketCrudController.php), [TicketRequest](../app/Http/Requests/TicketRequest.php), [Ticket](../app/Models/Ticket.php) | TicketResource + CreateTicket/EditTicket/ViewTicket pages | Dedicated create/update schemas and explicit application actions; detailed field/action matrices below | Reference, creator, issue/custom title, priority/status badges, overdue; detail infolist/discussion/actions | ticket.view plus actor-aware row scope and operation policies; no staff/employee generic Edit even though they possess ticket.update; existing list removes line buttons, so provide a deliberate safe view/navigation entry |
| [HrPolicyDocumentCrudController](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php), [upload](../resources/views/admin/hr_policy_upload.blade.php), [update](../resources/views/admin/hr_policy_update.blade.php) | HrPolicyDocumentResource + UploadPolicy/UpdatePolicy custom pages | Title/category/date/PDF; metadata-only update versus replacement must remain separate service paths | Title/category/chunks/date/status/OCR/review note/uploaded; upload/update/toggle/re-run/delete; no generic content CRUD | admin/dept_head role globally; version/state guards in service; no new needs_review approval toggle |

List sources above configure standard Backpack lists rather than a comprehensive explicit filter set. Observe browser/search behavior before promising identical tokenization/sort defaults; freeze deterministic ordering/paging fixtures. Filter changes require [D12/D14](15-REBUILD-SPECIFICATION.md#6-decision-register-and-implementation-gates).

## Ticket fields and persistence

| Field / behavior | Target component / server behavior | Source constraint / acceptance |
| --- | --- | --- |
| reference_id | Read-only TextEntry/column; generated by CreateTicket action | TKT- plus six uppercase hex characters, unique with bounded collision retry; preserve imported references |
| user_id | Server-selected actor on create; creator display relation | Ignore forged ownership; preserve imported creator separately |
| is_custom_issue | Checkbox/Toggle driving visible fields | Required boolean; changes which issue fields are required, not just UI visibility |
| issue_id | Select of catalog issues | Required for catalog mode; integer/existing; derive department/division/priority on create or issue change |
| custom_issue | TextInput | Required for custom mode, string max 255; clear irrelevant branch during normalization |
| message | Textarea | Required string max 65535 |
| department_id | Select for custom destination | Required for custom mode, integer/existing; server canonicalizes catalog mode |
| division_id | Reactive destination Select | Required for custom mode; existing integer matching selected department; server rechecks changed ID |
| priority_id | Catalog-derived display; authorized update only as decision permits | Nullable existing integer; do not accept hidden forged value on create |
| status_id | Read-only during ordinary create; controlled edit/actions | Default semantic Unassigned replaces hardcoded numeric 3; approved head/admin status edits preserve lifecycle timestamps |
| assigned_to | Eligible staff Select/custom AssignStaff action | Nullable integer from shared candidates; targets active HR staff with coherent department/division; actor scope rechecked server-side |
| attachments | Multiple FileUpload/custom attachment picker bound to private service | Max 20; 20 MB per file; jpg/jpeg/png/pdf/doc/docx/xls/xlsx/txt; retained paths must belong to current record |
| resolved_at/reopened_at/resolved_by | Read-only detail | Set/clear by transition action; never client-controlled |

Files need explicit adapters around Filament's upload lifecycle: stage safely, verify write, associate only after validation, compensate failed writes, retain on ambiguous commit and delete unreferenced old files after confirmed commit. Default temporary-upload settings and save callbacks are not equivalent to current [AttachmentStorage](../app/Services/AttachmentStorage.php)/[AttachmentTransactions](../app/Services/AttachmentTransactions.php). [Official FileUpload security guidance](https://filamentphp.com/docs/5.x/forms/file-upload) also requires ownership-aware existing-path handling. Do not enable direct public URLs for private stored paths.

## Ticket actions and related components

| Current trigger | New target | Rules / side effects | Required access checks |
| --- | --- | --- | --- |
| POST ticket | CreateTicket action invoked by resource/landing page | Normalize branch, derive metadata, generate reference, select staff, create/assignment notifications without duplicate initial Pending notification | Create policy + eligible role + ticket.create/view |
| Ordinary PUT update | UpdateTicket action | Approved fields only; same shared validation; issue-change routing guard; attachment compensation | Generic edit role/grant + visible record + D05 field matrix |
| _ticket_action=quick_assign | AssignStaffAction | Nullable assignee; Pending/Unassigned lifecycle; resolved behavior explicitly decided in D05 | Assignment eligibility + candidate scope |
| resolve | ResolveTicketAction | Set Resolved/resolver/time, clear reopened and cancel pending transfer | Admin/current head/assigned HR; revalidate current record |
| reopen | ReopenTicketAction | Only Resolved; clear resolution data, set reopened time | Creator only, including for admin |
| reassign_request | RequestTransferAction/modal | Different coherent destination; unresolved; one pending under lock; reason | Admin/current head; lock parent |
| reassign_accept | AcceptTransferAction/modal | Move destination, clear assignment, rerun deterministic matching, record responder/time and notify | Receiving head/admin; fresh pending state |
| reassign_reject | RejectTransferAction/modal | Record responder/time/note, retain ticket location and notify requester | Receiving head/admin |
| reassign_cancel | CancelTransferAction | Cancel pending request | Requester/admin |
| TicketChat | Custom TicketDiscussion Livewire 4 component on ViewTicket | Five-second polling, oldest-first comments/users, text/files, errors and resolved lock | Current visibility rechecked on every render/action; mounted IDs untrusted |
| Transfer history widget | Read-only TransfersRelationManager or custom section | Show status/from/to/request/response history; no generic create/edit/delete bypass | Parent visibility + specific transition actions |
| Ticket comments relation | Read-only relation display plus discussion composer | Do not expose generated generic comment CRUD/bulk deletion | Parent and child association checked |
| Ticket/comment download URLs | Dedicated controller outside panel resource routing | Index/record-bound response with no-store/nosniff and path validation | Visible parent; comment must belong to ticket |

## User/account form details

- Administrative user create: name required; email required/email/unique; password required by current field configuration; nullable department and matching division; roles relationship; active flag. Target must use approved secure password rules (D02), never replicate shared demo passwords.
- Administrative update: unique email ignoring self; blank password retains exact stored hash. Do not accidentally dehydrate blank password or double-hash imported hashes. Imported nullable username/email remains viewable; edit rules/provisioning need D02/D15.
- Account page: name/username/authentication column required/max 255/unique except self; avatar JPG/JPEG/PNG/WebP max 2 MB, remove toggle; skills array/string items max 100; four notification checkboxes; inherited password-change contract adapted and tested.
- Department/division reactivity replaces inline division JSON/jQuery, but backend membership validation remains. Role/permission grant controls must prevent unauthorized privilege changes.
- Custom Login page must accept **email OR username**. Filament's default email-only field/validation is insufficient. Deactivation must invalidate existing access, not merely hide menus. External HRDO integration is D02, not an invented Filament SSO feature.

## Custom pages, reports and widgets

| Existing implementation | Proposed target | Must be recreated |
| --- | --- | --- |
| [DashboardController](../app/Http/Controllers/Admin/DashboardController.php), [dashboard view](../resources/views/vendor/backpack/base/dashboard.blade.php) | Custom Dashboard with StatsOverview/Chart/Table widgets | Shared period controls, visible-scope counts/charts/latest 10, active staff/organization displays actually present; employee redirect to SubmitTicket |
| [SubmitTicketController](../app/Http/Controllers/Admin/SubmitTicketController.php), [landing](../resources/views/submit-ticket.blade.php) | SubmitTicket Page | Employee first destination, explanatory entry point, authorized create link |
| [ReportsController](../app/Http/Controllers/Admin/ReportsController.php) | EmployeeCareReports Page + report query service/PDF controller | Staff workload/averages, catalog/division overview, KPIs, trends, duration distribution, SLA, funnel and transfer statistics; do not unhide source-hidden charts without approval |
| [SurveyReportsController](../app/Http/Controllers/Admin/SurveyReportsController.php) | SatisfactionReports Page + custom table/widgets | Exact four-dimensional response totals, staff grouping/zero activity, paged/searchable full history, A4 summary PDF |
| [ArtaSurveyReportsController](../app/Http/Controllers/Admin/ArtaSurveyReportsController.php) | ArtaReports Page + table | Response paging/search/order; 8.5x13-inch landscape PDF with configured 2000-row cap |
| [MyAccountController](../app/Http/Controllers/Admin/MyAccountController.php) | MyAccount Page/custom profile component | Details, avatar, skills, password/preferences, partial-avatar-failure feedback; no new system-settings page implied |
| [NotificationController](../app/Http/Controllers/Admin/NotificationController.php) | Notifications Page + header feed adapter | Latest 10 feed/unread count, 20/page list, own read-one/read-all/clear; adapt legacy notification payloads to Filament display rather than assuming native payload compatibility |
| [HrChatController](../app/Http/Controllers/Admin/HrChatController.php), [chat view](../resources/views/admin/hr_chat.blade.php), [floating widget](../resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php) | HrAssistant Page + shared chat component and floating launcher | Full-page conversations and floating history interfaces share safe requests/rendering, owned state, feedback, language/source output and separate limits |

PDF generation remains a server output service using dedicated Blade print templates. Tailwind panel styling is not a replacement for PDF-supported CSS/fonts. Report query/range/rounding rules are in [17](17-BUSINESS-RULES.md); compare results before visual redesign.

## Workflows outside the panel

| Existing surface | Target / boundary |
| --- | --- |
| [SurveyController](../app/Http/Controllers/SurveyController.php), [survey form](../resources/views/survey_form.blade.php), [survey JS](../public/survey-assets/js/survey.js) | Public Blade/Livewire-assisted kiosk at /survey, outside panel auth. Preserve slides/staff-service rules, current CSRF refresh and submission UUID; online submission only |
| [ArtaController](../app/Http/Controllers/ArtaController.php), [ARTA view](../resources/views/arta_form.blade.php), [ARTA JS](../public/arta-assets/js/arta.js) | Public /arta-form; CC/SQD rules, redirects/thank-you and QR behavior; do not require Filament account |
| [Service worker](../public/sw-survey.js), [manifest](../public/manifest-survey.json) | Dedicated allowlisted kiosk worker with preserved safe update/reset boundaries; never cache authenticated panel pages |
| Protected ticket/comment files | Controller authorization shared with policies; do not expose directly through FileUpload temporary/public URLs |
| Public divisions lookup in [custom routes](../routes/backpack/custom.php) | Preserve actual id/division_name JSON for confirmed consumers; bound input/response and rate budget. Adding auth changes a public contract and needs D12 |
| /api/user in [API routes](../routes/api.php) | Retain only if required by confirmed consumers, using compatible Sanctum/auth; no invented token-issuance API or silent deletion (D12) |
| Login/recovery/external HRDO redirects | Explicit auth integration and URL map; no copying hardcoded deployment addresses; package GET logout replaced by CSRF-protected write as a security deviation |
| [CLI commands](../app/Console/Commands) | New-app commands for OCR diagnostics/import, avatar sync, privatization/reconciliation; maintain safe dry-run/cleanup gates; no shell launcher in web UI |

Existing API/route names and notification links require a compatibility list, including old account aliases, ticket PUT action protocol, policy JSON return shapes and package routes that are implementation details. Match documented business contracts, not Livewire 2 transport URLs; update clients and test old-link redirects without redirecting unsafe writes blindly.

## Mapping acceptance

Each of the ten resource mappings must have authorized/denied direct action tests, field/relationship validation, correct row scope and expected outputs. Every custom page/component/command above must map to [19](19-FUNCTIONAL-PARITY-CHECKLIST.md). No resource is complete on generation alone; no bulk actions are enabled without a new decision. Actual class/method API signatures and custom theme compilation are verified against the locked Filament 5 version in the new app, not guessed from legacy examples.
