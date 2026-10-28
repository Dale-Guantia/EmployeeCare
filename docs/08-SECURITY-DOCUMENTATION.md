# 08 - Security Documentation

## Evidence and limits

This is a source-based control review, not a penetration test, dependency-advisory refresh or production security certification. Controls described here require correct deployment. Earlier [AUDIT_REPORT.md](../AUDIT_REPORT.md) is a dated historical report; several findings were subsequently changed. Its original vulnerability/pass counts are not current findings.

## Authentication, roles and permissions

| Control | Verified implementation | Boundary / limitation |
| --- | --- | --- |
| Session login | Backpack login, email-or-username credentials with active=true | External non-local login redirect/session relationship unverified |
| Active panel membership | [CheckIfAdmin](../app/Http/Middleware/CheckIfAdmin.php) logs out inactive accounts and invalidates session/token | Includes employees and any active authenticated account; not admin-role authorization |
| RBAC | Spatie HasRoles, role/permission tables, configured web guard | Legacy users.role number is not the source of role checks |
| CRUD gates | Entity.view plus individual write permissions in reference/user/role controllers | Ticket generic operations also have role restrictions; custom actions have separate checks |
| Record scope | Ticket::visibleTo and component/download rechecks | Ticket listing adds narrower role filters; report permission can expose broader aggregates |
| Policies | AuthServiceProvider has no active model-policy mapping | Do not claim centralized policies enforce all routes |
| Personal history | HR conversations/logs and notification actions scope to current user | No verified admin-wide conversation-audit interface |

Sources: [auth config](../config/auth.php), [Backpack config](../config/backpack/base.php), [role seeder](../database/seeders/RolesAndPermissionsSeeder.php), [Ticket](../app/Models/Ticket.php), [chat](../app/Http/Controllers/Admin/HrChatController.php), [notifications](../app/Http/Controllers/Admin/NotificationController.php).

Registration can create an active account without a Spatie role. The registration switch defaults to local-only, with environment override. Ticket visibility then rejects missing ticket.view, but HR assistant/account routes do not demand a named role. Review registration policy and external provisioning before deployment. Existing administrative create-password rules differ from registration rules; no first-login password-change enforcement was found.

## Request and session controls

- `web` middleware includes session, cookie encryption, CSRF and route bindings; VerifyCsrfToken has no explicit exemption list entries. Standard middleware trims strings and normalizes empty strings.
- Session config defaults to file, 120 minutes, secure=true, http_only=true and same_site=lax. Environment and cached configuration can override some values. Cookie flags at a deployed browser were **Not Verified**.
- Hashing uses framework configuration and bcrypt calls in registration/administrative callbacks. Hidden user serialization includes password/remember token; inspect model before adding sensitive fields.
- Global TrustProxies trusts `*`; trusted-host middleware is commented out. Do not assume forwarded client IP or host values are trustworthy on an exposed origin.
- Default authenticate redirect names `login`, whereas registered panel login is `backpack.auth.login`; relevant unauthenticated browser paths need regression verification.

Sources: [HTTP Kernel](../app/Http/Kernel.php), [CSRF](../app/Http/Middleware/VerifyCsrfToken.php), [session](../config/session.php), [proxies](../app/Http/Middleware/TrustProxies.php), [Authenticate](../app/Http/Middleware/Authenticate.php).

## CORS and API token configuration

[config/cors.php](../config/cors.php) applies to api/* and sanctum/csrf-cookie, permits all origins/methods/headers and disables credential support. This is a browser cross-origin policy, not an authorization boundary. [config/sanctum.php](../config/sanctum.php) uses the web guard and null personal-token expiration by default. The API group does not enable EnsureFrontendRequestsAreStateful in current Kernel, and no custom token-issuance endpoint was found. Do not assume a complete first-party SPA login flow or expiring API tokens from the presence of Sanctum alone.

## Validation, SQL and output

Form Requests enforce reference and ticket inputs; custom controllers validate surveys, assistant and PDFs. Eloquent and parameterized query bindings handle most persistence. SQL dialect helpers select fixed expressions for supported drivers. DataTables sorting uses an allowlist and bounded paging/search; retrieval builds keyword predicates with bindings. This does not prove every raw fragment or output sink is safe.

Blade/e(), safe DOM text handling for skill tags/filenames and tests for browser rendering protect selected sinks. Inline HTML/JSON construction remains in CRUD controllers; UserCrudController embeds raw division JSON inside script HTML and needs a focused review. Status/priority color input only has string/length constraints, not a color allowlist. These are review targets, not newly demonstrated exploits.

Custom ticket actions bypass generic FormRequest processing by branching first. Reassignment reason/response note have no explicit type/length validation there. UI-disabled edit fields do not establish server-side field protection. Quick-assignment handler does not prohibit resolved tickets even though the UI disables it.

## Uploads and filesystem access

| Protection | Source |
| --- | --- |
| Max 20 files, max 20 MB each, restricted ticket/comment file types | [TicketRequest](../app/Http/Requests/TicketRequest.php), [TicketChat](../app/Http/Livewire/TicketChat.php) |
| Random UUID plus sanitized name fragment; verified stream-write result | [AttachmentStorage](../app/Services/AttachmentStorage.php) |
| Retained attachment paths must already belong to the visible ticket | TicketRequest |
| Private disk for new ticket files | [filesystem config](../config/filesystems.php) |
| Parent visibility/child association/indexed reference checks; no-store/nosniff | [TicketAttachmentController](../app/Http/Controllers/Admin/TicketAttachmentController.php) |
| Direct legacy HTTP access blocked for Apache | [public/.htaccess](../public/.htaccess) |
| After-commit deletion and uncertain-state retention | Ticket model, AttachmentTransactions, AttachmentInventory |
| Policy upload PDF max 20 MB and manager role check | [policy controller](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php) |
| Avatar image allowlist max 2 MB | [account controller](../app/Http/Controllers/Admin/MyAccountController.php) |

MIME/extension validation is not antivirus scanning. No malware-scanning/quarantine service was found. File response uses the storage adapter's response behavior, not a blanket forced-attachment disposition. Non-Apache deployments require equivalent legacy deny rules. Public avatars and any static files under public/policyDocs bypass application authorization by design of the public document root; their distribution/classification must be reviewed.

## Throttling

[RouteServiceProvider](../app/Providers/RouteServiceProvider.php) defines:

| Limiter | Budget and key | Attached routes |
| --- | --- | --- |
| api | 60/minute; authenticated user ID or request IP | API middleware group |
| hr-chat | 20/minute; user ID or request IP | HR ask POST |
| hr-chat-actions | 120/minute; user ID or IP | Feedback/history/conversation operations |
| survey-submissions | 30/minute per session plus 300/minute per REMOTE_ADDR peer | Satisfaction and ARTA POST |

The survey peer cap explicitly avoids trusting spoofed forwarded IPs. A reverse proxy can still aggregate clients under the same peer ceiling. Named limiters require working shared cache for consistent multi-process/multi-node enforcement. Login/recovery throttling comes from installed Backpack traits/configuration; deployed lockout behavior was not exercised. The public divisions lookup has no declared auth or throttle.

## AI and sensitive information

Active Anthropic calls send current question, bounded recent exchanges and selected policy text to an external service. [PolicyChatPrompt](../app/Services/PolicyChatPrompt.php) instructs source-only answers, treats supplied text as untrusted and bounds context/history. Retrieval miss avoids an AI call. These reduce risk but do not guarantee grounding, resist all prompt injection or establish regulatory compliance.

Anthropic/Gemini adapters log status/error class/token usage rather than keys, prompts or response bodies. The older OpenRouter GPTService remains an alternate implementation with different prompting/error behavior; switching to it is not a config-only equivalent. Other application exception logs may include paths or database exception details. Restrict log access/retention and avoid dumping environment or requests during debugging.

Secrets belong in protected environment configuration. This package does not copy .env content, real employee records, default passwords or access tokens. Seeders contain demonstration identity data and shared passwords; their local/testing guards reduce accidental production seeding but do not remove repository exposure or rotate existing accounts.

## Logging and audit limits

Configured channels include stack/single/daily and optional remote handlers. Default file paths are under storage/logs. Login timestamp, notifications, transfer records and chat logs provide partial operational history. They are not an immutable, comprehensive audit system for every read/write. Clearing notifications/history is supported. Automated retention/deletion schedules and security alerting are **Not Verified**.

## Known weaknesses and proposed improvements

The following are observations/recommendations, not fixes made by documentation:

1. Legacy runtime/dependency stack and development framework lock require a separately authorized maintenance/security review. PHP 7.4 is outside the PHP project's supported branches; see [PHP support policy](https://www.php.net/supported-versions.php). No current composer/npm advisory counts were obtained here.
2. Fix ticket.assign omission in the grouped role editor before relying on that UI to preserve assignment grants.
3. Review server-side field authorization and custom action validation/state guards, plus broader report scope when granting permissions to heads.
4. Review public divisions lookup, static public policy files, wildcard proxy trust, certificate trust and hardcoded deployment redirects/connections.
5. Provide approved secure account provisioning and review existing demo/shared-password exposure without reseeding live data.
6. Define confidential-data retention, backups, provider data handling, log redaction and recovery validation.
7. Verify cross-role direct requests and deployed attachment deny rules before security acceptance.

See [gaps and inconsistencies](DOCUMENTATION-GAPS.md) and [manual test cases](12-TESTING-AND-QA.md).
