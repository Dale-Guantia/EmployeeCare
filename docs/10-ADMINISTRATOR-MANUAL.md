# 10 - System Administrator Manual

## Before using this manual

These instructions describe the source-defined interface, not a recorded browser walkthrough. Menu visibility depends on your current permissions. Screenshots are intentionally placeholders; capture a test environment with synthetic identities and no employee information. `/employee-care` is the configured panel prefix. Operational shell actions belong to [deployment and maintenance](09-DEPLOYMENT-AND-MAINTENANCE.md), not ordinary panel use.

## Sign in and review the dashboard

1. Open the organization's application entry URL. Local installation provides `/employee-care/login`; non-local login-page requests redirect to the configured external HRDO site in controller code.
2. Use your approved active account. Never use demonstration/shared credentials for a production account.
3. Open **Dashboard**. Review counts, latest tickets, charts and the period selector.
4. Select month-to-date, all time, a half-year, or custom dates. Invalid custom ranges fall back to this month with a message.

**Screenshot placeholder ADM-01:** Administrator Dashboard showing period selector, KPI cards and latest-ticket list, with synthetic records.

If the account is inactive, access is revoked. Missing menu entries usually mean missing permissions; an external-login failure requires the integration owner, not repeated account creation.

## Maintain organizational reference data

1. Open **Departments**; create/edit the department name and save. Duplicate names fail request validation.
2. Open **Divisions**; enter division name and select its department. Save.
3. Open **Priorities** or **Status** to maintain labels/colors.
4. Open **Issues**; choose department, matching division and priority, enter description and optional icon, then save.
5. Review the resulting row before allowing users to submit tickets against it.

**Screenshot placeholder ADM-02:** Issue create/edit form with department, division, priority, description and icon fields.

Do not rename/delete required Resolved/Pending/Unassigned/Reopened statuses casually. The code uses those names and still assumes initial status ID 3. Deleting referenced catalog entries can fail foreign-key checks. Existing ticket routing is updated from an issue on create/issue change, not every save.

## Manage user accounts

1. Open **Authentication > Users**.
2. Choose Create or Edit. Enter name/email, department and matching division, roles and active flag.
3. Supply an approved unique password for creation. On update, leave replacement password blank to retain the existing password.
4. Save and review the account's intended role/scope.
5. Test with a separate authorized test login when changing access; a displayed role is not proof of every permission.

**Screenshot placeholder ADM-03:** User edit form showing role and organization controls; hide real email/name/password data.

Deactivating an account blocks panel access. The form does not provide a full invite/mandatory-first-password-change workflow. Avoid deleting users with ticket/survey references. Existing self-service account details and avatar/skills are documented in [user manual](11-USER-MANUAL.md).

## Manage roles and permissions

1. Open **Authentication > Permissions** to inspect permission names.
2. Open **Authentication > Roles** and inspect the grouped action checkboxes.
3. Change only approved grants. Save and verify direct access with a test account, including denied actions.
4. Review report permissions carefully: granting reports.view/survey-reports.view can expose broad report data, rather than automatically scoped department results.

**Screenshot placeholder ADM-04:** Role edit page with grouped view/create/update/delete checkboxes.

**Known limitation:** the grouped editor omits ticket.assign. Saving the role synchronizes submitted permissions and can remove an existing omitted grant. There is no verified working checkbox for that grant; arrange a reviewed developer correction/provisioning procedure before using the role form to manage it. Do not use a database workaround without authorization.

Default roles are admin, dept_head, div_head, hr_staff and employee. Actual authority also depends on active status, record scope and controller rules. Generic ticket edit is denied to staff/employees despite the seeded ticket.update permission. See the [access matrix](04-FUNCTIONAL-SPECIFICATIONS.md#access-control-matrix).

## Administer tickets

1. Open **Tickets**, search and open the relevant ticket detail.
2. Review creator, issue/message, destination, status, assignee and files.
3. Use **Assign Staff** for eligible staff; a blank selection clears assignment. Resolved detail UI disables this control.
4. Use discussion to add text/files while unresolved. Files must satisfy the allowlist, max 20 per submission and 20 MB per file.
5. Use **Resolve**, confirm, then verify status/resolution details. Only the ticket creator can use **Reopen**, including when that creator is an administrator.
6. For a transfer, use the reassignment section, select a different target department/division and provide context. Accept/reject/cancel follow the workflow permissions.
7. Delete only when specifically authorized; ticket comments and attachments participate in cleanup. No recycle-bin restore action is implemented.

**Screenshot placeholder ADM-05:** Ticket detail showing assignment, resolve confirmation, discussion and transfer controls for a synthetic unresolved ticket.

No automatic escalation function is documented because no active implementation was found. Overdue is a calculated label; it does not itself send an escalation or transfer ownership.

## Reports

1. Expand **Reports > Employee Care**, **CSS** or **ARTA** as appropriate.
2. Inspect metrics or response history. CSS/ARTA detail tables support paged search/order.
3. Open the PDF controls, enter start/end dates and any displayed zero-activity option, then generate the report.
4. Review the PDF before distributing it; protect survey/staff data according to organizational policy.

**Screenshot placeholder ADM-06:** Reports menu and Employee Care report period/PDF controls.

**Screenshot placeholder ADM-07:** CSS/ARTA response history with synthetic rows and paging/search controls.

End date must not precede start. ARTA refuses more than 2000 rows for one PDF; use a narrower period. Reports may intentionally use department ID 1 in staff-related calculations. A PDF render error shows generic feedback and should be checked in protected logs.

## Policy documents and HR assistant

1. Open **Policy Documents** and choose the upload action.
2. Enter title, supported category, optional effective date and PDF no larger than 20 MB.
3. Submit and return to the list. **Pending means queued, not ready for use.** Refresh to observe processing and final state.
4. Use update-file action for replacement, or save metadata alone without choosing a PDF.
5. Use Re-run after fixing OCR/worker problems. Use active/inactive toggle only for successfully ingested documents; needs_review cannot be approved by that toggle.
6. Check a representative question in **HR Assistant** after ingestion. Active retrieval requires active flag and active status.

**Screenshot placeholder ADM-08:** Policy document list with status/chunk/OCR columns and upload/update/toggle/re-run actions.

**Screenshot placeholder ADM-09:** Policy upload form and processing feedback, with a nonsensitive sample document.

There is no panel button to start a worker. A queue backlog requires an operator's supervised-worker procedure. Document deletion removes database content and attempts file cleanup; coordinate with active processing. No blanket approval of low-confidence OCR is implemented.

## Notifications and settings

Open **Notifications** to review your own list, mark read or clear it. Clear permanently deletes your notifications. Use My Account notification controls to choose ticket event preferences. These notifications are database records, not guaranteed email messages.

No general **System Settings** CRUD, mail-server editor, worker control console or backup UI was found. Application/environment configuration changes require an authorized operator and the deployment guide.

## Maintenance and escalation to support

For a failure, record the affected screen, timestamp, ticket/document identifier, role and exact user-visible message. Avoid copying employee messages, filenames, keys or confidential PDFs into tickets unnecessarily. Check the [troubleshooting guide](13-TROUBLESHOOTING.md). Backups, migrations, attachment cleanup and account provisioning require the approved operator procedure; a panel admin role is not blanket authorization for live infrastructure changes.

UI evidence: [sidebar](../resources/views/vendor/backpack/base/inc/sidebar_content.blade.php), [account view](../resources/views/vendor/backpack/base/my_account.blade.php), [controllers](IMPLEMENTATION-INVENTORY.md).
