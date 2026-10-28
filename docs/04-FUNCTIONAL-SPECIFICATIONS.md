# 04 - Features and Functional Specifications

## Conventions and common behavior

Panel URLs use `/employee-care` from [Backpack config](../config/backpack/base.php); it is not `/admin` in this checkout. The [route reference](07-ROUTES-AND-API.md) lists every registered route. Unless specified otherwise, panel operations require an active session through `web` and `admin`, use CSRF for writes, and return Blade HTML or redirects with flash/validation feedback. JSON clients generally receive Laravel validation errors with HTTP 422; not-found records give 404 and denied operations give 403. Specific custom responses are documented below.

The following matrix combines **seeded permissions with actual controller restrictions**, assuming one listed role per account and unchanged grants. It is not a live permission inventory. Multi-role accounts can encounter additional intersections because list filters are applied independently.

## Access-control matrix

| Module / operation | Administrator | Department Head | Division Head | HR Staff | Employee |
| --- | --- | --- | --- | --- | --- |
| Dashboard | Visible tickets globally | Visible tickets in scope | Visible tickets in scope | Own/assigned scope | Redirect to Submit Ticket |
| Ticket create | Yes | Yes | Yes | Yes | Yes |
| Ticket list/detail | Global | Department list; detail scope below | Division list; detail scope below | Own or assigned | Own |
| Generic ticket edit | Yes | Yes | Yes | No | No |
| Ticket delete | Yes | No under seeded grants | No | No | No |
| Quick assignment | Yes | Eligible department staff | Eligible division staff | Only with explicit ticket.assign | No |
| Resolve custom action | Yes | Current department | Current division | Assigned ticket | No unless another qualifying role |
| Reopen custom action | Only own resolved ticket | Only own resolved ticket | Only own resolved ticket | Only own resolved ticket | Own resolved ticket |
| Request transfer | Yes | Current department | Current division | No | No |
| Accept/reject transfer | Yes | Receiving department | Receiving division | No | No |
| Cancel transfer | Yes | If requester | If requester | No normal request path | No normal request path |
| Discussion/file access | Any visible ticket | Any visible ticket | Any visible ticket | Own/assigned | Own |
| Issue view/create/update | Yes | Yes | Yes | No | No |
| Issue delete | Yes | No | No | No | No |
| Departments/divisions | CRUD | View only | No | No | No |
| Priorities/statuses/users/roles/permissions | CRUD | No | No | No | No |
| Ticket reports | Yes | No seeded reports.view | No | No | No |
| Survey/ARTA reports | Yes | No seeded survey-reports.view | No | No | No |
| Policy document management | Yes | Yes, not department-scoped | No | No | No |
| HR assistant | Yes | Yes | Yes | Yes | Yes |
| Own account/preferences/notifications | Yes | Yes | Yes | Yes | Yes |
| Public questionnaires | Public | Public | Public | Public | Public |

Source: [role seeder](../database/seeders/RolesAndPermissionsSeeder.php), CRUD controllers in [inventory](IMPLEMENTATION-INVENTORY.md), [visibility](../app/Models/Ticket.php), [assignment eligibility](../app/Services/TicketAssignmentRules.php). `ticket.update` is seeded to staff/employees but generic edit remains denied by controller role gates. A Department Head's potential delete operation still requires `ticket.delete`, absent from its default grants. Custom actions use their own checks rather than the generic edit permission. Granting report permissions gives access to the report's implemented data scope; it does not automatically introduce department filtering.

## Authentication, registration and account

**Sources:** [LoginController](../app/Http/Controllers/Admin/Auth/LoginController.php), [RegisterController](../app/Http/Controllers/Admin/Auth/RegisterController.php), [MyAccountController](../app/Http/Controllers/Admin/MyAccountController.php), [User](../app/Models/User.php).

- Login: `GET/POST /employee-care/login`; email or username plus password, with `is_active=true` in credentials. Local environment shows Backpack login. Other environments redirect the login-page request to an external HRDO URL. This is a redirect, not a verified SSO protocol implementation. Existing-session redirect contains a fixed subdirectory path.
- Registration: `GET/POST /employee-care/register`, subject to inherited Backpack registration availability. Name/username max 255; username unique; email required/email/unique/max 255; password confirmed/min 6. Creates active user with hashed password, but no role assignment. Active panel membership alone does not give ticket access.
- Account: `GET /employee-care/my-account`; save info, password or preference forms to their corresponding POST actions. Name required/max 255; username and authentication column required/unique ignoring self. Skills array of strings max 100 each. Avatar is optional JPG/JPEG/PNG/WebP image max 2 MB; remove flag boolean. Account updates cannot change organizational roles through this form.
- Avatar update failure logs an error and can save other details with warning feedback. Password change delegates to the installed Backpack parent controller. Notification flags are determined by checkbox presence, not a general settings API.
- Password-recovery routes are registered by Backpack; actual email delivery/recovery and external-login integration are **Not Verified**.

## Reference-data CRUD modules

**Workflow for each:** open the allowed module, list/search, open Create/Edit, supply fields, save, then review persisted row. Delete requires the entity's delete permission and may fail if referenced by foreign keys. Permission `entity.view` is required before operation checks. These controllers do not establish department-specific row ownership for reference data.

| Module / controller | Inputs and server validation | Persistence / output |
| --- | --- | --- |
| [DepartmentCrudController](../app/Http/Controllers/Admin/DepartmentCrudController.php) | [DepartmentRequest](../app/Http/Requests/DepartmentRequest.php): department_name required string max 255; unique ignoring route id | departments row; listing/details; cache invalidation in model |
| [DivisionCrudController](../app/Http/Controllers/Admin/DivisionCrudController.php) | [DivisionRequest](../app/Http/Requests/DivisionRequest.php): division_name required string max 255; department_id required and exists | divisions row linked to department; names are not required unique |
| [IssueCrudController](../app/Http/Controllers/Admin/IssueCrudController.php) | [IssueRequest](../app/Http/Requests/IssueRequest.php): department/division/priority required integer and exist; division must belong to department; description required string max 255; icon optional string max 255 | issues row supplies ticket routing/priority; permitted heads can manage global catalog entries |
| [PriorityCrudController](../app/Http/Controllers/Admin/PriorityCrudController.php) | [PriorityRequest](../app/Http/Requests/PriorityRequest.php): unique name required max 255; color required string max 20 | priorities row; color is not validated as a hex color |
| [StatusCrudController](../app/Http/Controllers/Admin/StatusCrudController.php) | [StatusRequest](../app/Http/Requests/StatusRequest.php): unique name required max 255; color required string max 20 | statuses row; name cache invalidated; renaming lifecycle labels can disrupt workflow lookups |

Routes for each entity are the registered Backpack list/search/details/create/store/edit/update/delete/show operations. No soft-delete/recycle-bin workflow is declared for these models. Name-level request uniqueness is not necessarily enforced by a database index; see [schema](03-DATABASE-DOCUMENTATION.md).

## User, role and permission administration

**Sources:** [UserCrudController](../app/Http/Controllers/Admin/UserCrudController.php), [RoleCrudController](../app/Http/Controllers/Admin/RoleCrudController.php), [PermissionCrudController](../app/Http/Controllers/Admin/PermissionCrudController.php), [permission routes](../routes/backpack/permissionmanager.php).

User form fields include name, email, password, department, division, roles and active flag. Name/email are required; email is validated and unique ignoring the current row on update. Creation requires password; update retains original hash when no replacement is supplied. Controller-registered model callbacks hash new passwords. Department is nullable/exists; division is nullable and must belong to submitted department. Role checklist updates role pivots. User administration does not impose registration's confirmed/minimum-length password rule on its create field.

Role administration delegates storage to Backpack, then resolves submitted permission IDs and synchronizes the role's permissions. Name is required string max 255; the installed create request requires unique name, while update request does not include that uniqueness rule. Permission CRUD uses the installed [create request](../vendor/backpack/permissionmanager/src/app/Http/Requests/PermissionStoreCrudRequest.php) (required unique name, string max 255) and [update request](../vendor/backpack/permissionmanager/src/app/Http/Requests/PermissionUpdateCrudRequest.php) (required name, string max 255, without that uniqueness rule).

**Important current limitation:** grouped role permission UI renders only view/create/update/delete. It does not render `ticket.assign`, although the permission is seeded and checked by assignment logic. Saving that form synchronizes only submitted permissions, which can remove an unrendered grant. Do not describe a working ticket.assign checkbox in administrator training.

## Ticket creation and routing

**Sources:** [TicketCrudController](../app/Http/Controllers/Admin/TicketCrudController.php), [TicketRequest](../app/Http/Requests/TicketRequest.php), [Ticket model](../app/Models/Ticket.php), [SubmitTicketController](../app/Http/Controllers/Admin/SubmitTicketController.php).

Preconditions: active session, `ticket.view`, supported role and `ticket.create`. The Submit Ticket landing page links to `/employee-care/ticket/create`; its landing action itself is not a substitute for the create gate.

1. Choose a catalog issue, or enable `is_custom_issue` and enter a custom title.
2. Supply a message and optional files. Custom issues require a department and matching division.
3. Submit. Form preparation clears the irrelevant issue/title and derives organization for a catalog issue.
4. The model generates `TKT-` plus six uppercase hexadecimal characters, assigns creator from session if missing, and defaults empty status to numeric `3`.
5. Catalog issue selection supplies department, division and priority on create/issue change. Automatic assignment then selects eligible staff if available.
6. Save returns normal Backpack feedback/redirect; the ticket appears in the allowed list.

| Input | Validation |
| --- | --- |
| is_custom_issue | Required boolean |
| issue_id | Required when custom flag is 0; nullable integer existing issue |
| custom_issue | Required when custom flag is 1; nullable string max 255 |
| message | Required string max 65535 |
| department_id/division_id | Required for custom issue; nullable integer existing entries; division constrained by department |
| status_id/priority_id | Sometimes nullable integer existing entry |
| assigned_to | Shared actor/ticket eligibility validator |
| attachments | Nullable array max 20; each item is an allowed new file or a retained path already on the current visible ticket |

New ticket files: JPG/JPEG/PNG/PDF/DOC/DOCX/XLS/XLSX/TXT, max 20 MB each. Unexpected retained strings are rejected. Stored paths are randomized and retained as a JSON array in the widened text field. Reference collisions are recognized and retried up to five attempts by the model, then failure propagates.

## Ticket visibility and editing

`Ticket::visibleTo()` rejects missing/inactive users and users lacking ticket.view. Admin gets all; other users get own tickets plus department/division membership, incoming pending transfer scope for heads, or assigned tickets for HR staff. **List operations narrow further:** Department Head to current department, Division Head to current division and Employee to creator. Consequently an incoming transfer may be accessible by direct detail URL without appearing in the receiving head's normal list.

Generic edits require controller role/permission access and TicketRequest validation. Some content/attachment controls become read-only in the UI according to creator/admin/division-head checks. That UI behavior must not be documented as a complete server-side field authorization boundary; the ordinary update delegates to Backpack and needs separate hardening review.

## Automatic and manual assignment

[TicketAutoAssignmentService](../app/Services/TicketAutoAssignmentService.php) selects active HR staff in ticket department, and division if present. Standard issues score normalized skill phrase matches at five points per match; custom issues skip skill scoring. Ties prefer fewer Pending/Reopened tickets, then lowest user ID. There is no machine-learning model or round-robin scheduler in this algorithm. No eligible staff leaves the ticket unassigned. Successful selection sets Pending if that status name exists. [TicketObserver](../app/Observers/TicketObserver.php) logs assignment exceptions without intentionally failing the already-created ticket.

Manual `quick_assign` submits `assigned_to` through `PUT /employee-care/ticket/{id}` with `_ticket_action`. Eligible actors: active admin/heads, or HR staff with ticket.assign. Eligible targets: active HR staff whose division belongs to their department; actor scope limits candidates. Clearing the assignee is allowed. Assignment changes update status to Pending/Unassigned unless an explicit status change is already dirty. The detail UI disables quick assignment when resolved, but the handler itself does not enforce a resolved-state prohibition: record as an inconsistency, not a guaranteed lock.

## Resolution, reopening and overdue behavior

The same ticket PUT endpoint dispatches `_ticket_action=resolve` or `reopen` before the ordinary CRUD update path.

- Resolve: admin, current department head, current division head or assigned HR staff; changes status to named Resolved. Model stores resolution time and current resolver, clears reopened time and cancels pending transfers.
- Reopen: creator only, currently Resolved; otherwise 403 or 409. Named Reopened must exist. Model sets reopened_at and clears resolution fields.
- Missing required status lookup returns an error rather than inventing a status.
- Open tickets are overdue after three elapsed days from creation. Resolved classification uses integer `diffInHours(...) > 72`, so boundary semantics differ from a precise minute comparison. Priority does not select a separate deadline. Reopening does not reset created_at.
- No automatic escalation, escalation notifications or scheduled reassignment was found.

## Organizational reassignment

**Inputs:** `_ticket_action=reassign_request`, `to_department_id`, `to_division_id`, optional `reassign_reason`; reject can include `response_note`. Cancel/accept/reject use the corresponding `_ticket_action` value at the ticket PUT endpoint.

1. Admin/current department or division head requests transfer of an unresolved visible ticket.
2. Reject missing target, invalid department/division pairing, unchanged destination or an existing pending request with flash feedback.
3. Create pending transfer with source/destination/requester; notify receiving parties after commit.
4. Receiving department/division head or admin accepts or rejects. Requester/admin can cancel.
5. Acceptance moves location, clears assignee, reruns automatic assignment and records response actor/time; rejection records note without moving the ticket.

Transition requests lock the same visible parent row and reread pending request under transaction. There is no dedicated unique pending-request index. Receiving-head visibility and normal-list filtering differ as described above. The reason/response note do not have explicit length/type rules in these handlers; do not infer FormRequest coverage for these action paths.

## Discussion and attachment lifecycle

[TicketChat](../app/Http/Livewire/TicketChat.php) rechecks active ticket.view and visibility. The [view](../resources/views/livewire/ticket-chat.blade.php) polls every five seconds; there is no verified WebSocket transport. Resolved tickets reject comments. Provide text max 65535 or files; blank text is allowed with attachments. Maximum 20 files, same allowed types/20 MB limit as tickets. Validation, upload and persistence errors are shown. Save creates `ticket_comments`, notifies eligible participants and clears successful draft state.

[AttachmentStorage](../app/Services/AttachmentStorage.php) verifies write success; [AttachmentTransactions](../app/Services/AttachmentTransactions.php) tracks staged files. Failed persistence compensates only when references can be checked; ambiguous state retains files for reconciliation. Removal/deletion schedules file cleanup after commit. [TicketAttachmentController](../app/Http/Controllers/Admin/TicketAttachmentController.php) serves only files referenced by a visible ticket/comment, with private no-store and nosniff headers; invalid indices or missing files give 404.

## Notifications

[TicketNotificationService](../app/Services/TicketNotificationService.php) constructs created/assigned/status/comment and reassignment payloads. It filters active candidates, removes duplicates/actors where specified and honors preference flags. Creation recipients are administrators and matching heads; assignment/status recipients follow role and ownership rules; comments consider creator, assignee and prior participants. Reassignment has dedicated recipient logic. This is not a broadcast to every panel user.

`GET notifications/feed` returns unread_count and latest 10. Full page paginates 20. Mark-one/read-all and clear-all operate only on current user's notifications. Clear is a permanent deletion. The four preference flags map to creation, assignment, status change and comments. The notification class uses database only; no verified ticket email channel.

## Dashboard and reports

[DashboardController](../app/Http/Controllers/Admin/DashboardController.php) redirects employees to the landing page and requires ticket.view for others. Every dashboard ticket aggregate starts from visible scope; latest tickets are limited to 10. Period resolver supports month-to-date (default), all, h1/h2 of a year and custom start/end. Invalid custom dates fall back to this month with a visible message.

[ReportsController](../app/Http/Controllers/Admin/ReportsController.php) requires reports.view; [SurveyReportsController](../app/Http/Controllers/Admin/SurveyReportsController.php) and [ArtaSurveyReportsController](../app/Http/Controllers/Admin/ArtaSurveyReportsController.php) require survey-reports.view. These permissions are seeded only to admin. Ticket reports include staff workloads, issue/organization statistics, resolution/SLA metrics and trends; some calculations intentionally restrict staff to department ID 1. Reports are not automatically scoped by Ticket::visibleTo.

PDF requests require start_date and end_date with end on/after start. Ticket/satisfaction PDFs support include_zero_activity boolean. Ticket and satisfaction PDF use A4 portrait; ARTA uses 8.5-by-13-inch landscape and refuses over 2000 rows by config. Rendering errors log details and redirect with a generic message. Satisfaction report uses SQL aggregate summaries and latest preview; full history is paged separately. Response endpoints validate DataTables draw/start/length (1-100), bounded search and allowed order direction; order columns are selected from a fixed list. Response JSON follows draw/recordsTotal/recordsFiltered/data.

## Customer satisfaction survey and kiosk

[SurveyController](../app/Http/Controllers/SurveyController.php) provides public GET/POST `/survey` and GET `/survey/csrf-token`. The rated staff must belong to department ID 1 with a matching division and div_head/hr_staff role; service must belong to that staff division. The staff query does not explicitly require is_active. User IDs represent rated staff, not a logged-in respondent.

Workflow: select division, staff and service; answer timeliness/handling/quality/overall; submit online; show thank-you/reset interface. Four required answers: Very Dissatisfied, Dissatisfied, Satisfied or Very Satisfied. Required submission_id is a UUID. [SurveySubmission](../app/Services/SurveySubmission.php) makes identical retries idempotent; reuse with different answers fails. JSON submission returns `{saved:true,message:...}`; validation returns saved=false/errors with 422; ordinary forms redirect.

[PWA worker](../public/sw-survey.js) caches allowlisted shell assets and eligible clean survey HTML after removing CSRF input values. It never queues POST requests. [survey.js](../public/survey-assets/js/survey.js) refreshes CSRF before submission, preserves UUID across retry and requires reconnection. Worker updates activate at safe boundaries. Root-relative paths require deployment review for subdirectories. See [manual](11-USER-MANUAL.md).

## ARTA questionnaire

[ArtaController](../app/Http/Controllers/ArtaController.php) handles public GET/POST `/arta-form`. Required UUID; optional client_type Citizen/Business/Government; sex Male/Female; age integer 1-100; region 1-17; service string max 255; CC1 1-4, CC2 1-5, CC3 1-4. SQD0-SQD8 accept Strongly Agree, Agree, Neither, Disagree, Strongly Disagree or N/A. At least one SQD response is required even though fields individually are nullable. If CC1=4, force CC2=5 and CC3=4. Save via the shared idempotent service and redirect with thank_you=1. Validation uses redirects, not the satisfaction survey's explicit JSON response contract. Region labels are model constants. Service-worker registration alone does not cache `/arta-form`.

## HR policy document management

[HrPolicyDocumentCrudController](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php) allows admin/dept_head only, globally. Custom upload/update pages replace ordinary create/edit. Required title string max 255, category general/leave/benefits/performance, optional effective date; upload requires PDF max 20 MB, update allows omitted PDF for metadata-only changes.

1. Upload validates queue schema readiness, writes randomized policy file and document record.
2. Versioned database enqueue returns immediately; browser success means saved/queued, not extracted.
3. Worker claims pending generation, uses native PDF text and OCR only deficient pages, caches extraction by hash, builds ~300-word chunks with 50-word overlap and a 30-word minimum.
4. Atomic replacement of chunks sets active/inactive, needs_review for incomplete/low-confidence OCR, or failed with error. Default OCR confidence threshold is 60.
5. File replacement invalidates extraction and queues a new generation; metadata-only update avoids reingestion. Processing documents are guarded against overlapping replacement/reingestion. Deletion uses a separate service path without that processing-state guard; queued/running jobs use missing-record/version checks.
6. Toggle only active/inactive states; needs_review cannot be approved using that toggle. Reingestion forces extraction. Delete removes chunks and document through the service and attempts referenced-file cleanup.

Setup/dispatch failures return feedback or 503 for JSON reingestion. Busy reingestion gives 409. Toggle invalid state gives 422. CLI `hr:ingest` is synchronous and differs from browser workflow, including additional conduct/discipline categories and destructive --force behavior. See [queue operations](09-DEPLOYMENT-AND-MAINTENANCE.md).

## HR assistant and conversation management

[HrChatController](../app/Http/Controllers/Admin/HrChatController.php) is available to active panel members; no separate role permission gates it. `ask` requires question string max 600 and optional positive conversation_id. Greeting path skips provider/log creation. An unknown or unowned conversation_id starts a new owned conversation.

[PolicyRetriever](../app/Services/PolicyRetriever.php) uses bounded keyword LIKE scoring, up to 12 extracted keywords and five chunks, from documents with is_active=true and status=active. It is not embedding/vector search despite the stored search_vector column. Subject-free follow-ups can use the two recent questions; provider history includes up to three recent exchanges. Context is bounded to 11000 characters. Retrieval miss returns a fixed HRDO referral without provider call. Retrieval/provider failures must not be described as a successful policy answer.

Successful answer JSON includes answer, sources, log_id and conversation_id; greeting lacks conversation_id and has log_id=null. Anthropic failures return 503, deleted-during-answer conversation 409, unexpected failure 500. Prompt rules request English/Filipino/Taglish matching and source grounding; they do not guarantee factual correctness or eliminate prompt injection.

History returns latest 20 owned logs; sidebar latest 100 conversations sorted by star/recency. Rename accepts optional nullable title max 120; star flag boolean. Transcript/delete require owned conversation (404 otherwise). Feedback accepts own log_id and +/-1. Clear-history deletes all of the current user's logs/conversations in a transaction. There is no verified administrator conversation-audit UI.

## Operator-only functions and absent modules

CLI commands cover OCR diagnostics, synchronous policy ingestion, avatar synchronization, legacy attachment privatization and conservative reconciliation. These are deployment operations, not buttons available to every administrator. See [maintenance guide](09-DEPLOYMENT-AND-MAINTENANCE.md).

No implemented general settings CRUD, automated escalation schedule, guaranteed ticket-email delivery, offline response queue, full event audit log, or Filament panel was found. Proposals and historical plan files are not feature specifications.
