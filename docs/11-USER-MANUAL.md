# 11 - End-User Manual

## Access and shared guidance

Use the application URL supplied by your organization and an active approved account. The local panel starts at `/employee-care/login`; deployed login may redirect to an external HRDO site. Menus depend on permissions. This manual describes source-defined screens; live browser behavior and screenshots are **Not Verified**. Capture the placeholders below with synthetic data.

### Submit a concern

1. Choose **Create Ticket**, then open the ticket form from the landing page.
2. Select an issue. If it is absent, check **My issue is not on the list**, enter a short custom issue and select the responsible department/division.
3. Explain the concern in **Message**. Add optional supporting files.
4. Save. Keep the generated TKT reference and review the ticket under **Tickets**.

A message is required. A custom title is max 255 characters; message max 65535. Catalog issues determine routing/priority. Automatic assignment may set Pending; no eligible staff leaves the concern unassigned.

**Screenshot placeholder USR-01:** Create Ticket landing page and its entry button.

**Screenshot placeholder USR-02:** Ticket form with catalog/custom controls, message, organization and attachments.

### Follow a ticket and discuss

1. Open **Tickets** and select a record you are allowed to see.
2. Review status, assignment and discussion. Discussion refreshes by polling.
3. Enter text and/or attach files, then send. Wait for success before sending again.
4. Open attachments through the links shown on the ticket; copying a direct private filesystem path is not supported.

Allowed files: JPG/JPEG/PNG/PDF/DOC/DOCX/XLS/XLSX/TXT; maximum 20 files and 20 MB per file. A discussion message can be omitted when a file is attached. Resolved tickets stop discussion until the creator reopens them. Validation/storage errors are shown; do not assume an error means a comment was saved.

**Screenshot placeholder USR-03:** Authorized ticket detail and discussion, including attachment validation area.

### Account, skills and notifications

1. Open **My Account** using the account menu or `/employee-care/my-account`.
2. Update name, username, configured authentication field, avatar or skills; save the relevant form.
3. Use the password form to change your password. Use notification settings to choose ticket event alerts.
4. Open **Notifications** to read entries or mark them read. Clearing the list deletes your own notification records.

Avatar: JPG/JPEG/PNG/WebP up to 2 MB. Skill tags are strings up to 100 characters each; skills can influence assignment for catalog issues. Other account changes may save even if avatar update fails, with feedback. Changing your profile does not grant a role or organizational transfer.

**Screenshot placeholder USR-04:** My Account details/skills/avatar and password/notification sections.

**Screenshot placeholder USR-05:** Notification list and read/clear controls.

### Ask the HR assistant

1. Choose **HR Assistant**. Enter a policy question in English, Filipino or Taglish, max 600 characters.
2. Read the answer and supplied source names. Contact HRDO when the system cannot find the policy or the answer needs official confirmation.
3. Use the conversation controls to start/select a conversation, rename/star it, or delete it. Feedback applies to your own logged answers.
4. Use clear-history only when you intend to delete your conversations/logs.

The assistant searches currently active ingested policies. It can fail because of service, queue, OCR or missing-source problems. It does not create a ticket or approve a leave/benefit request. Wait after a rate-limit message; failure messages are not policy advice.

**Screenshot placeholder USR-06:** HR Assistant with synthetic conversation sidebar, question box, answer and source list.

## Employee

- After sign-in/dashboard entry, the employee role is redirected to the Submit Ticket landing page.
- Create concerns and view your own tickets. Generic ticket Edit/Delete is not offered by default.
- Discuss while unresolved and open the files referenced by your own visible tickets.
- If your concern was marked Resolved but remains unresolved, open it, choose **Reopen** and confirm. Only the creator can do this; it restores Reopened status and discussion.
- Use your account, notification list and HR assistant. Default Employee has no reports, policy-manager or catalog-administration access.

**Screenshot placeholder EMP-01:** Employee sidebar with Create Ticket/Tickets and a resolved own ticket showing Reopen.

## HR Staff

- Open Dashboard to review the ticket data visible to you. Open Tickets for your own and assigned records.
- Review assigned concern, use discussion/files and resolve the assigned ticket when handled.
- Generic ticket Edit/Delete is denied by the default controller role rules. Do not confuse the seeded update permission with an available edit form.
- Manual staff assignment is available only if an explicit ticket.assign grant exists; it is not enabled for all HR staff by default.
- Reopen only your own created resolved tickets. Contact the appropriate head to request an organizational transfer; normal staff cannot submit transfer actions.
- Maintain skills responsibly because they can affect catalog-issue matching. Reports and policy document management are not default staff functions.

**Screenshot placeholder STF-01:** Assigned unresolved ticket with Resolve and discussion, without generic Edit/Delete.

## Division Head

1. Open Dashboard/Tickets for current division work.
2. Create or edit tickets within allowed scope; assign active eligible staff in the division.
3. Resolve a ticket in your current division when completed.
4. Request transfer by choosing a different destination department/division in the ticket's reassignment section.
5. For a transfer addressed to your division, review the supplied ticket link and accept or reject. Only a receiving head/admin can respond. A normal list may not show incoming pending tickets because it filters current location; use the authorized notification/detail link.
6. Cancel a transfer you requested when it is still pending.

Default Division Head can view/create/update Issues but cannot delete them. It has no default Departments/Divisions administration, reports or policy-manager access. The issue catalog is not row-scoped to your own division by these controllers; use organizational change procedures.

**Screenshot placeholder DIV-01:** Division Head ticket detail showing assignment and reassignment controls.

## Department Head

1. Open Dashboard/Tickets for current department work; assign eligible department staff or resolve department tickets.
2. Review/manage department/division transfer requests addressed to your scope. Use the incoming detail link where the normal list does not include a pending destination ticket.
3. View Departments/Divisions and view/create/update Issues under default grants. No default department/division writes or issue deletes are available.
4. Open **Policy Documents** to upload/update/re-run/toggle successfully ingested PDFs; follow the policy procedure in the [administrator manual](10-ADMINISTRATOR-MANUAL.md#policy-documents-and-hr-assistant).

Policy-management access is global in the source, not restricted to documents owned by your department. Default Department Head does not have report permissions or ticket.delete, even though some controller role gates could permit those operations if separately granted.

**Screenshot placeholder DEP-01:** Department Head sidebar and policy document list.

## Administrator

Follow the [administrator manual](10-ADMINISTRATOR-MANUAL.md) for accounts, roles, catalog, tickets, reports and policy management. Admin sees all tickets, but reopening remains creator-only. Your notification list/history remain your own. Do not assume admin role provides a backup/worker/settings UI that is not implemented.

## Public survey respondents and kiosk use

### Customer satisfaction

1. Open `/survey`; no panel login is required.
2. Select service division, rated staff and the service availed.
3. Answer timeliness, handling, quality and overall satisfaction using the offered four choices.
4. Submit while online and wait for confirmation. Allow the kiosk to reset before the next respondent.

**Screenshot placeholder PUB-01:** Survey division/staff/service selection and rating slides.

**Screenshot placeholder PUB-02:** Survey thank-you screen and next-respondent reset controls.

Offline mode allows viewing previously cached survey UI/assets. It **does not save or queue your response**. Reconnect and submit. Do not close a failed submission until you understand the displayed state. The app reuses its UUID on a retry to avoid duplicate saves and uses a new one for the next respondent.

The source restricts this survey to department ID 1 and listed staff/services. If no suitable staff/service is offered, report the catalog issue; do not rate a different person just to proceed. QR links are external destinations shown by the current templates, not an internal survey API guarantee.

### ARTA

1. Open `/arta-form`.
2. Enter applicable demographic/service information and Citizen's Charter choices.
3. Answer at least one service-quality (SQD) question and submit.
4. Wait for the thank-you screen. If CC1 indicates no knowledge of the Charter, the app records CC2/CC3 as their Not Applicable choices.

**Screenshot placeholder PUB-03:** ARTA demographic/CC/SQD slides and thank-you state.

Age, when supplied, must be 1-100; region uses the offered 17 choices. The page's service-worker registration does not establish offline ARTA submission or page caching.

## Common errors and FAQ

| Question / message | Explanation / next action |
| --- | --- |
| Why is a menu missing? | Your account may lack the required permission/role. Ask the administrator; do not try another user's URL |
| Why can I not see a ticket? | Ownership, assignment, department/division scope or active status can restrict it |
| Why is my ticket unassigned? | No eligible active HR staff may match its location; contact the responsible head |
| Why can I not reopen this ticket? | Reopen requires the ticket creator and Resolved state |
| Why can I not send a comment? | Check resolution state, file type/size/count, session and displayed errors |
| Does overdue automatically escalate? | No automated escalation was found; it is a calculated deadline/status display |
| Why did my survey retry fail? | A submission UUID reused with changed answers is rejected; start a new survey if appropriate |
| Why did policy upload succeed but chat cannot find it? | The policy may still be queued/processing, inactive, failed, needs_review, or not match the question |
| Are notifications email? | Ticket notifications in this implementation are database entries |
| What should I report to support? | Screen, timestamp, role, safe record reference and exact visible error; omit credentials/private message contents |

Source evidence: [sidebar](../resources/views/vendor/backpack/base/inc/sidebar_content.blade.php), [functional specifications](04-FUNCTIONAL-SPECIFICATIONS.md), [traceability](TRACEABILITY-MATRIX.md).
