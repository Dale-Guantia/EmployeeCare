# 07 - Routes and API Reference

## Inventory provenance

149 registered routes were captured on 2026-10-27 from the installed Laravel/Backpack/Livewire route collection. Bootstrap used an intentionally nonexistent environment file, APP_ENV=testing, SQLite :memory:, array cache/session and no application credentials. No migrations or endpoint requests were executed. Registration is evidence of a route, **not proof that an endpoint succeeds**.

The ordinary `route:list --json` attempt failed because the installed Backpack password-reset controller checks for the users.email column, absent in the intentionally empty database. Inventory was therefore read directly from the registered collection without constructing controllers. [Machine-readable snapshot](route-inventory.json).

The table lists **declared route middleware**, not expanded global middleware or constructor middleware. `web` means cookies/session/CSRF/bindings from [Kernel](../app/Http/Kernel.php); `admin` is active Backpack membership from [CheckIfAdmin](../app/Http/Middleware/CheckIfAdmin.php). Report permission middleware and package guest/auth checks are additional. Blank declared middleware does not mean global HTTP middleware is absent.

## Route families and authorization

| Family / key | Authentication and actual checks | Response / request contract |
| --- | --- | --- |
| AUTH | Package guest/login/recovery behavior; custom local/external login redirect; registration availability config | HTML/forms/redirects; login email-or-username, password and active flag |
| ACCOUNT | Package account paths have controller checks; custom my-account group uses admin | Own account forms, never a user-ID update API |
| TICKET | Active session, ticket.view, role/operation gates and visibleTo; custom action guards | Backpack forms/DataTables; PUT uses _ticket_action for workflow branches |
| CATALOG | Active session and entity.view; write requires entity.create/update/delete | Backpack HTML/forms/DataTables; see request rules |
| REPORT | Active session and reports.view or survey-reports.view in constructor middleware | HTML, DataTables JSON or streamed PDF |
| NOTIFY | Active session; all queries scoped to current user's notifications | JSON feed/mutations or HTML list |
| HRCHAT | Active session; conversation/log ownership; ask/action limiters | JSON except initial HTML page |
| POLICY | Active session, admin/dept_head role; service state checks | HTML/forms, JSON toggle/reingest, delete scalar 1 |
| SURVEY | Public web session/CSRF; POST survey-submissions limiter | HTML or satisfaction JSON; ARTA redirects |
| LOOKUP | No declared authentication or throttle; public divisions lookup | JSON array of id/division_name |
| APIUSER | api group, auth:sanctum; default 60/min budget | Authenticated user JSON; no custom token-issuance endpoint found |
| PACKAGE | Installed Livewire/Sanctum behavior, component checks and signed upload/preview controls where implemented | Framework protocol/assets, not a supported business REST API |
| ROOT | Root redirects to Backpack; panel-root redirects through installed controller | Redirect; constructor checks can apply |

Source definitions: [web](../routes/web.php), [api](../routes/api.php), [panel](../routes/backpack/custom.php), [permissions](../routes/backpack/permissionmanager.php), [provider](../app/Providers/RouteServiceProvider.php), installed packages. Detailed role and field rules are in [functional specifications](04-FUNCTIONAL-SPECIFICATIONS.md).

## Web endpoints versus API routes

Most `/employee-care/...` JSON endpoints are **session-authenticated web actions** and need the current CSRF token for writes. The divisions URL starts with `api/` but is declared in routes/backpack/custom.php outside its web/protected groups, so it is not the api middleware group. `/api/user` is the actual endpoint in routes/api.php, protected by Sanctum. No full ticket REST API, OAuth server or API versioning was found.

The installed package also registers GET logout and account aliases alongside custom POST logout and my-account routes. Some names are repeated. Existing source includes closure routes; a successful route cache was not verified and must not be assumed.

## Complete registered inventory

`GET|HEAD` denotes the implicit HEAD method. URI patterns are relative to the web server's configured application base. An em dash means no declared value. Actions are exact registered names, including inherited package methods.

| Methods | URI | Name | Action | Declared middleware | Key |
| --- | --- | --- | --- | --- | --- |

| GET\|HEAD | `/employee-care/login` | backpack.auth.login | `App\Http\Controllers\Admin\Auth\LoginController@showLoginForm` | web | AUTH |
| POST | `/employee-care/login` | ? | `App\Http\Controllers\Admin\Auth\LoginController@login` | web | AUTH |
| GET\|HEAD | `/employee-care/logout` | backpack.auth.logout | `Backpack\CRUD\app\Http\Controllers\Auth\LoginController@logout` | web | AUTH |
| POST | `/employee-care/logout` | backpack.auth.logout | `App\Http\Controllers\Admin\Auth\LoginController@logout` | web | AUTH |
| GET\|HEAD | `/employee-care/register` | backpack.auth.register | `App\Http\Controllers\Admin\Auth\RegisterController@showRegistrationForm` | web | AUTH |
| POST | `/employee-care/register` | ? | `App\Http\Controllers\Admin\Auth\RegisterController@register` | web | AUTH |
| GET\|HEAD | `/employee-care/password/reset` | backpack.auth.password.reset | `Backpack\CRUD\app\Http\Controllers\Auth\ForgotPasswordController@showLinkRequestForm` | web | AUTH |
| POST | `/employee-care/password/reset` | ? | `Backpack\CRUD\app\Http\Controllers\Auth\ResetPasswordController@reset` | web | AUTH |
| GET\|HEAD | `/employee-care/password/reset/{token}` | backpack.auth.password.reset.token | `Backpack\CRUD\app\Http\Controllers\Auth\ResetPasswordController@showResetForm` | web | AUTH |
| POST | `/employee-care/password/email` | backpack.auth.password.email | `Backpack\CRUD\app\Http\Controllers\Auth\ForgotPasswordController@sendResetLinkEmail` | web, backpack.throttle.password.recovery:3,10 | AUTH |
| GET\|HEAD | `/employee-care/dashboard` | backpack.dashboard | `App\Http\Controllers\Admin\DashboardController@dashboard` | web, admin | TICKET |
| GET\|HEAD | `/employee-care` | backpack | `Backpack\CRUD\app\Http\Controllers\AdminController@redirect` | web | ROOT |
| GET\|HEAD | `/employee-care/edit-account-info` | backpack.account.info | `Backpack\CRUD\app\Http\Controllers\MyAccountController@getAccountInfoForm` | web | ACCOUNT |
| POST | `/employee-care/edit-account-info` | backpack.account.info.store | `Backpack\CRUD\app\Http\Controllers\MyAccountController@postAccountInfoForm` | web | ACCOUNT |
| POST | `/employee-care/change-password` | backpack.account.password | `Backpack\CRUD\app\Http\Controllers\MyAccountController@postChangePasswordForm` | web | ACCOUNT |
| GET\|HEAD | `/api/department/{id}/divisions` | ? | `Closure` | ? | LOOKUP |
| GET\|HEAD | `/employee-care/submit-ticket` | submit-ticket.show | `App\Http\Controllers\Admin\SubmitTicketController@show` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket` | ticket.index | `App\Http\Controllers\Admin\TicketCrudController@index` | web, admin | TICKET |
| POST | `/employee-care/ticket/search` | ticket.search | `App\Http\Controllers\Admin\TicketCrudController@search` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket/{id}/details` | ticket.showDetailsRow | `App\Http\Controllers\Admin\TicketCrudController@showDetailsRow` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket/create` | ticket.create | `App\Http\Controllers\Admin\TicketCrudController@create` | web, admin | TICKET |
| POST | `/employee-care/ticket` | ticket.store | `App\Http\Controllers\Admin\TicketCrudController@store` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket/{id}/edit` | ticket.edit | `App\Http\Controllers\Admin\TicketCrudController@edit` | web, admin | TICKET |
| PUT | `/employee-care/ticket/{id}` | ticket.update | `App\Http\Controllers\Admin\TicketCrudController@update` | web, admin | TICKET |
| DELETE | `/employee-care/ticket/{id}` | ticket.destroy | `App\Http\Controllers\Admin\TicketCrudController@destroy` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket/{id}/show` | ticket.show | `App\Http\Controllers\Admin\TicketCrudController@show` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket/{ticket}/attachments/{index}` | ticket.attachments.show | `App\Http\Controllers\Admin\TicketAttachmentController@show` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/ticket/{ticket}/comments/{comment}/attachments/{index}` | ticket.comment.attachments.show | `App\Http\Controllers\Admin\TicketAttachmentController@comment` | web, admin | TICKET |
| GET\|HEAD | `/employee-care/issue` | issue.index | `App\Http\Controllers\Admin\IssueCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/issue/search` | issue.search | `App\Http\Controllers\Admin\IssueCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/issue/{id}/details` | issue.showDetailsRow | `App\Http\Controllers\Admin\IssueCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/issue/create` | issue.create | `App\Http\Controllers\Admin\IssueCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/issue` | issue.store | `App\Http\Controllers\Admin\IssueCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/issue/{id}/edit` | issue.edit | `App\Http\Controllers\Admin\IssueCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/issue/{id}` | issue.update | `App\Http\Controllers\Admin\IssueCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/issue/{id}` | issue.destroy | `App\Http\Controllers\Admin\IssueCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/issue/{id}/show` | issue.show | `App\Http\Controllers\Admin\IssueCrudController@show` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/department` | department.index | `App\Http\Controllers\Admin\DepartmentCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/department/search` | department.search | `App\Http\Controllers\Admin\DepartmentCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/department/{id}/details` | department.showDetailsRow | `App\Http\Controllers\Admin\DepartmentCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/department/create` | department.create | `App\Http\Controllers\Admin\DepartmentCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/department` | department.store | `App\Http\Controllers\Admin\DepartmentCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/department/{id}/edit` | department.edit | `App\Http\Controllers\Admin\DepartmentCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/department/{id}` | department.update | `App\Http\Controllers\Admin\DepartmentCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/department/{id}` | department.destroy | `App\Http\Controllers\Admin\DepartmentCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/department/{id}/show` | department.show | `App\Http\Controllers\Admin\DepartmentCrudController@show` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/division` | division.index | `App\Http\Controllers\Admin\DivisionCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/division/search` | division.search | `App\Http\Controllers\Admin\DivisionCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/division/{id}/details` | division.showDetailsRow | `App\Http\Controllers\Admin\DivisionCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/division/create` | division.create | `App\Http\Controllers\Admin\DivisionCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/division` | division.store | `App\Http\Controllers\Admin\DivisionCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/division/{id}/edit` | division.edit | `App\Http\Controllers\Admin\DivisionCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/division/{id}` | division.update | `App\Http\Controllers\Admin\DivisionCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/division/{id}` | division.destroy | `App\Http\Controllers\Admin\DivisionCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/division/{id}/show` | division.show | `App\Http\Controllers\Admin\DivisionCrudController@show` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/priority` | priority.index | `App\Http\Controllers\Admin\PriorityCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/priority/search` | priority.search | `App\Http\Controllers\Admin\PriorityCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/priority/{id}/details` | priority.showDetailsRow | `App\Http\Controllers\Admin\PriorityCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/priority/create` | priority.create | `App\Http\Controllers\Admin\PriorityCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/priority` | priority.store | `App\Http\Controllers\Admin\PriorityCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/priority/{id}/edit` | priority.edit | `App\Http\Controllers\Admin\PriorityCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/priority/{id}` | priority.update | `App\Http\Controllers\Admin\PriorityCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/priority/{id}` | priority.destroy | `App\Http\Controllers\Admin\PriorityCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/priority/{id}/show` | priority.show | `App\Http\Controllers\Admin\PriorityCrudController@show` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/status` | status.index | `App\Http\Controllers\Admin\StatusCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/status/search` | status.search | `App\Http\Controllers\Admin\StatusCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/status/{id}/details` | status.showDetailsRow | `App\Http\Controllers\Admin\StatusCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/status/create` | status.create | `App\Http\Controllers\Admin\StatusCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/status` | status.store | `App\Http\Controllers\Admin\StatusCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/status/{id}/edit` | status.edit | `App\Http\Controllers\Admin\StatusCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/status/{id}` | status.update | `App\Http\Controllers\Admin\StatusCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/status/{id}` | status.destroy | `App\Http\Controllers\Admin\StatusCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/status/{id}/show` | status.show | `App\Http\Controllers\Admin\StatusCrudController@show` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/user` | user.index | `App\Http\Controllers\Admin\UserCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/user/search` | user.search | `App\Http\Controllers\Admin\UserCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/user/{id}/details` | user.showDetailsRow | `App\Http\Controllers\Admin\UserCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/user/create` | user.create | `App\Http\Controllers\Admin\UserCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/user` | user.store | `App\Http\Controllers\Admin\UserCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/user/{id}/edit` | user.edit | `App\Http\Controllers\Admin\UserCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/user/{id}` | user.update | `App\Http\Controllers\Admin\UserCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/user/{id}` | user.destroy | `App\Http\Controllers\Admin\UserCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/user/{id}/show` | user.show | `App\Http\Controllers\Admin\UserCrudController@show` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/reports` | page.reports.index | `App\Http\Controllers\Admin\ReportsController@index` | web, admin | REPORT |
| POST | `/employee-care/reports/download-pdf` | page.reports.download_pdf | `App\Http\Controllers\Admin\ReportsController@downloadPdf` | web, admin | REPORT |
| GET\|HEAD | `/employee-care/survey-reports` | page.survey_reports.index | `App\Http\Controllers\Admin\SurveyReportsController@index` | web, admin | REPORT |
| GET\|HEAD | `/employee-care/survey-reports/responses` | page.survey_reports.responses | `App\Http\Controllers\Admin\SurveyReportsController@responses` | web, admin | REPORT |
| POST | `/employee-care/survey-reports/download-pdf` | page.survey_reports.download_pdf | `App\Http\Controllers\Admin\SurveyReportsController@downloadPdf` | web, admin | REPORT |
| GET\|HEAD | `/employee-care/arta-survey-reports` | page.arta_survey_reports.index | `App\Http\Controllers\Admin\ArtaSurveyReportsController@index` | web, admin | REPORT |
| GET\|HEAD | `/employee-care/arta-survey-reports/responses` | page.arta_survey_reports.responses | `App\Http\Controllers\Admin\ArtaSurveyReportsController@responses` | web, admin | REPORT |
| POST | `/employee-care/arta-survey-reports/download-pdf` | page.arta_survey_reports.download_pdf | `App\Http\Controllers\Admin\ArtaSurveyReportsController@downloadPdf` | web, admin | REPORT |
| GET\|HEAD | `/employee-care/notifications/feed` | notifications.feed | `App\Http\Controllers\Admin\NotificationController@feed` | web, admin | NOTIFY |
| POST | `/employee-care/notifications/{id}/read` | notifications.read | `App\Http\Controllers\Admin\NotificationController@markAsRead` | web, admin | NOTIFY |
| POST | `/employee-care/notifications/read-all` | notifications.read_all | `App\Http\Controllers\Admin\NotificationController@markAllAsRead` | web, admin | NOTIFY |
| GET\|HEAD | `/employee-care/notifications` | notifications.index | `App\Http\Controllers\Admin\NotificationController@index` | web, admin | NOTIFY |
| DELETE | `/employee-care/notifications/clear-all` | notifications.clear_all | `App\Http\Controllers\Admin\NotificationController@clearAll` | web, admin | NOTIFY |
| GET\|HEAD | `/employee-care/my-account` | backpack.account.info | `App\Http\Controllers\Admin\MyAccountController@getAccountInfoForm` | web, admin | ACCOUNT |
| POST | `/employee-care/my-account/info` | backpack.account.info.store | `App\Http\Controllers\Admin\MyAccountController@postAccountInfoForm` | web, admin | ACCOUNT |
| POST | `/employee-care/my-account/password` | backpack.account.password | `App\Http\Controllers\Admin\MyAccountController@postChangePasswordForm` | web, admin | ACCOUNT |
| POST | `/employee-care/my-account/notifications` | backpack.account.notifications | `App\Http\Controllers\Admin\MyAccountController@postNotificationsForm` | web, admin | ACCOUNT |
| POST | `/employee-care/my-account/notification-settings` | backpack.account.notification_settings | `App\Http\Controllers\Admin\MyAccountController@postNotificationsForm` | web, admin | ACCOUNT |
| GET\|HEAD | `/employee-care/hr-assistant` | hr.chat.index | `App\Http\Controllers\Admin\HrChatController@index` | web, admin | HRCHAT |
| POST | `/employee-care/hr-assistant/ask` | hr.chat.ask | `App\Http\Controllers\Admin\HrChatController@ask` | web, admin, throttle:hr-chat | HRCHAT |
| POST | `/employee-care/hr-assistant/feedback` | hr.chat.feedback | `App\Http\Controllers\Admin\HrChatController@feedback` | web, admin, throttle:hr-chat-actions | HRCHAT |
| GET\|HEAD | `/employee-care/hr-assistant/history` | hr.chat.history | `App\Http\Controllers\Admin\HrChatController@history` | web, admin, throttle:hr-chat-actions | HRCHAT |
| DELETE | `/employee-care/hr-assistant/clear-history` | hr.chat.clear_history | `App\Http\Controllers\Admin\HrChatController@clearHistory` | web, admin, throttle:hr-chat-actions | HRCHAT |
| GET\|HEAD | `/employee-care/hr-assistant/conversations` | hr.chat.conversations | `App\Http\Controllers\Admin\HrChatController@conversations` | web, admin, throttle:hr-chat-actions | HRCHAT |
| GET\|HEAD | `/employee-care/hr-assistant/conversations/{id}/messages` | hr.chat.conversation.messages | `App\Http\Controllers\Admin\HrChatController@conversationMessages` | web, admin, throttle:hr-chat-actions | HRCHAT |
| PATCH | `/employee-care/hr-assistant/conversations/{id}` | hr.chat.conversation.update | `App\Http\Controllers\Admin\HrChatController@updateConversation` | web, admin, throttle:hr-chat-actions | HRCHAT |
| DELETE | `/employee-care/hr-assistant/conversations/{id}` | hr.chat.conversation.delete | `App\Http\Controllers\Admin\HrChatController@deleteConversation` | web, admin, throttle:hr-chat-actions | HRCHAT |
| GET\|HEAD | `/employee-care/hr-policy-documents/upload` | hr.policy.upload.form | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@uploadForm` | web, admin | POLICY |
| POST | `/employee-care/hr-policy-documents/upload` | hr.policy.upload.store | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@uploadStore` | web, admin | POLICY |
| GET\|HEAD | `/employee-care/hr-policy-documents/{document}/update-file` | hr.policy.update.form | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@updateForm` | web, admin | POLICY |
| POST | `/employee-care/hr-policy-documents/{document}/update-file` | hr.policy.update.store | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@updateStore` | web, admin | POLICY |
| POST | `/employee-care/hr-policy-documents/{document}/toggle` | hr.policy.toggle | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@toggleActive` | web, admin | POLICY |
| POST | `/employee-care/hr-policy-documents/{document}/reingest` | hr.policy.reingest | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@reingest` | web, admin | POLICY |
| GET\|HEAD | `/employee-care/hr-policy-documents` | hr-policy-documents.index | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@index` | web, admin | POLICY |
| POST | `/employee-care/hr-policy-documents/search` | hr-policy-documents.search | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@search` | web, admin | POLICY |
| GET\|HEAD | `/employee-care/hr-policy-documents/{id}/details` | hr-policy-documents.showDetailsRow | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@showDetailsRow` | web, admin | POLICY |
| DELETE | `/employee-care/hr-policy-documents/{id}` | hr-policy-documents.destroy | `App\Http\Controllers\Admin\HrPolicyDocumentCrudController@destroy` | web, admin | POLICY |
| GET\|HEAD | `/employee-care/role` | role.index | `App\Http\Controllers\Admin\RoleCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/role/search` | role.search | `App\Http\Controllers\Admin\RoleCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/role/{id}/details` | role.showDetailsRow | `App\Http\Controllers\Admin\RoleCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/role/create` | role.create | `App\Http\Controllers\Admin\RoleCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/role` | role.store | `App\Http\Controllers\Admin\RoleCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/role/{id}/edit` | role.edit | `App\Http\Controllers\Admin\RoleCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/role/{id}` | role.update | `App\Http\Controllers\Admin\RoleCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/role/{id}` | role.destroy | `App\Http\Controllers\Admin\RoleCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/permission` | permission.index | `App\Http\Controllers\Admin\PermissionCrudController@index` | web, admin | CATALOG |
| POST | `/employee-care/permission/search` | permission.search | `App\Http\Controllers\Admin\PermissionCrudController@search` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/permission/{id}/details` | permission.showDetailsRow | `App\Http\Controllers\Admin\PermissionCrudController@showDetailsRow` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/permission/create` | permission.create | `App\Http\Controllers\Admin\PermissionCrudController@create` | web, admin | CATALOG |
| POST | `/employee-care/permission` | permission.store | `App\Http\Controllers\Admin\PermissionCrudController@store` | web, admin | CATALOG |
| GET\|HEAD | `/employee-care/permission/{id}/edit` | permission.edit | `App\Http\Controllers\Admin\PermissionCrudController@edit` | web, admin | CATALOG |
| PUT | `/employee-care/permission/{id}` | permission.update | `App\Http\Controllers\Admin\PermissionCrudController@update` | web, admin | CATALOG |
| DELETE | `/employee-care/permission/{id}` | permission.destroy | `App\Http\Controllers\Admin\PermissionCrudController@destroy` | web, admin | CATALOG |
| GET\|HEAD | `/sanctum/csrf-cookie` | ? | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` | web | PACKAGE |
| POST | `/livewire/message/{name}` | livewire.message | `Livewire\Controllers\HttpConnectionHandler` | web | PACKAGE |
| POST | `/{locale}/livewire/message/{name}` | livewire.message-localized | `Livewire\Controllers\HttpConnectionHandler` | web | PACKAGE |
| POST | `/livewire/upload-file` | livewire.upload-file | `Livewire\Controllers\FileUploadHandler@handle` | web | PACKAGE |
| GET\|HEAD | `/livewire/preview-file/{filename}` | livewire.preview-file | `Livewire\Controllers\FilePreviewHandler@handle` | web | PACKAGE |
| GET\|HEAD | `/livewire/livewire.js` | ? | `Livewire\Controllers\LivewireJavaScriptAssets@source` | ? | PACKAGE |
| GET\|HEAD | `/livewire/livewire.js.map` | ? | `Livewire\Controllers\LivewireJavaScriptAssets@maps` | ? | PACKAGE |
| GET\|HEAD | `/api/user` | ? | `Closure` | api, auth:sanctum | APIUSER |
| GET\|HEAD | `/` | ? | `Closure` | web | ROOT |
| GET\|HEAD | `/survey` | survey.form | `App\Http\Controllers\SurveyController@showForm` | web | SURVEY |
| POST | `/survey` | survey.submit | `App\Http\Controllers\SurveyController@submitForm` | web, throttle:survey-submissions | SURVEY |
| GET\|HEAD | `/survey/csrf-token` | survey.csrf | `App\Http\Controllers\SurveyController@csrfToken` | web | SURVEY |
| GET\|HEAD | `/arta-form` | arta.form | `App\Http\Controllers\ArtaController@showForm` | web | SURVEY |
| POST | `/arta-form` | arta.submit | `App\Http\Controllers\ArtaController@submitForm` | web, throttle:survey-submissions | SURVEY |

## Actual request examples

These are examples for an authorized test session, not requests executed during documentation. Use synthetic text and test records. Browser fetch sends same-origin cookies; never paste production session cookies into shared examples.

### Public divisions lookup

```javascript
const response = await fetch('/api/department/1/divisions', {
    headers: { Accept: 'application/json' }
});
const divisions = await response.json();
// Array of objects with id and division_name; an empty array is valid.
```

The ID is an example catalog identifier. Public access and absence of a declared limiter are actual implementation observations; authorization hardening is proposed work.

### Ask the HR assistant

On the authenticated page with its CSRF meta tag:

```javascript
const response = await fetch('/employee-care/hr-assistant/ask', {
    method: 'POST',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ question: 'What are the documented leave requirements?' })
});
const result = await response.json();
```

Success: answer/sources/log_id and normally conversation_id. A greeting has no stored log. Questions max 600 chars; conversation_id optional positive integer. Ask limit 20/minute by user/IP. History/feedback/conversation operations use a separate 120/minute limiter. A missing/unowned supplied conversation_id starts a new owned conversation; transcript/delete unknown ownership returns 404.

### Notification feed

Authenticated `GET /employee-care/notifications/feed` returns:

```json
{
  "unread_count": 0,
  "notifications": []
}
```

Feed rows have id, title, message, url, read_at and created_at_human. It returns latest 10; the HTML page paginates 20. POST read-one/read-all and DELETE clear-all require the active web session and CSRF.

### Public satisfaction submission

Fetch `/survey/csrf-token` with same-origin credentials and no-store, then POST `/survey` with X-CSRF-TOKEN and Accept: application/json. Supply submission_id UUID, valid rated user_id, matching issue_id and all four rating fields. Use the same UUID and answers for retries. Success is saved=true/message; validation returns saved=false/errors with 422. The actual page already handles this flow; use that page for normal operation.

### Sanctum user endpoint

```text
GET /api/user
Accept: application/json
Authorization: Bearer <test-token>
```

The token is a placeholder; this repository does not provide a custom token-issuance route. No live token was created or verified. API middleware budget is 60/minute by user ID or IP.

## Errors and workflow requests

| Condition | Implemented behavior |
| --- | --- |
| Missing/inactive panel session | Redirect for browser or 401 for AJAX/wantsJson in CheckIfAdmin |
| Permission denied | Usually 403 |
| Invisible/missing ticket, child or file index | 404 |
| Standard JSON validation | 422 with errors; ordinary web forms redirect with errors/input |
| Missing/stale CSRF | Framework 419 response |
| Throttle budget exhausted | Framework 429 |
| Reopen nonresolved ticket | 409 |
| Policy toggle unfinished state | JSON 422 |
| Policy reingest busy | JSON 409 |
| Policy queue/setup failure | JSON 503 on reingest; upload/update use page feedback |
| HR provider unavailable | 503 answer/sources/log_id response |
| HR conversation deleted during answer | 409 |
| Unexpected HR request exception | Generic JSON 500 |

Ticket workflow writes all use registered **PUT** `/employee-care/ticket/{id}` with `_ticket_action` equal to resolve, reopen, quick_assign, reassign_request, reassign_cancel, reassign_accept or reassign_reject. HTML forms submit POST with `_method=PUT` and CSRF. There are no separate registered `/resolve` or `/reassign` endpoints. See [functional rules](04-FUNCTIONAL-SPECIFICATIONS.md).

