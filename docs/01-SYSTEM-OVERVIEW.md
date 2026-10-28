# 01 - System Overview

Source review date: **2026-10-27**. This describes the current checkout, including uncommitted implementation changes. **Not Verified:** deployed version, production schema, infrastructure and live service health. See [documentation gaps](DOCUMENTATION-GAPS.md).

## Purpose and scope

Employee Care System is a Laravel helpdesk for employee concerns. Employees submit catalog or custom issues; organizational heads and HR staff handle tickets, assignment, discussion and resolution. The same application provides public customer satisfaction and ARTA questionnaires, reporting, account preferences, database notifications, and an HR policy assistant.

This is the existing Backpack application. A Filament replacement and framework/runtime upgrade were discussed but are **proposed work**, not implemented features.

## Users

| Role identifier | Intended user | Principal behavior in source |
| --- | --- | --- |
| `admin` | Administrator | All seeded permissions; global ticket visibility |
| `dept_head` | Department Head | Department tickets, assignment and transfer decisions; policy document management |
| `div_head` | Division Head | Division tickets, assignment and transfer decisions |
| `hr_staff` | HR Staff | Own and assigned tickets; resolve assigned tickets |
| `employee` | Employee | Submit and follow own tickets; reopen own resolved tickets |
| Public visitor | Survey respondent | Customer satisfaction and ARTA forms without panel login |

Roles alone do not grant every action. Active status, permissions, ticket visibility and operation-specific checks apply. See the [access matrix](04-FUNCTIONAL-SPECIFICATIONS.md#access-control-matrix).

## Verified capabilities

- Department, division, issue, priority, status, user, role and permission CRUD.
- Ticket reference generation, issue-derived routing, skill/workload auto-assignment and manual assignment.
- Transactional reassignment request, acceptance, rejection and cancellation.
- Ticket discussion with attachments; resolution and creator-controlled reopening.
- Role-scoped dashboard; ticket, satisfaction and ARTA reports with PDF output.
- Public survey kiosk interface, install manifest, service worker and offline viewing.
- Account details, avatar, skills, password and notification preferences.
- HR policy PDF upload/replacement, OCR, versioned queued ingestion, searchable chunks and grounded chat with private conversation history.
- Attachment privatization/reconciliation and avatar synchronization operator commands.

## Technology and requirements

| Layer | Verified evidence |
| --- | --- |
| Backend | PHP CLI observed 7.4.14; project standard PHP 7.4; Composer constraint `^7.3\|^8.0` is not a certification of all PHP 8 releases |
| Framework | Laravel constraint `^8.75`; lockfile records `8.x-dev`, not a numbered stable release |
| Administration | Backpack CRUD 5.6.1; PermissionManager 6.0.17; Spatie Permission 5.11.1 |
| UI | Blade, Livewire 2.12.8, Bootstrap/jQuery; local assets plus CDN Chart.js, DataTables and Marked where referenced |
| Build | Laravel Mix 6/Webpack 5; Axios, Lodash and PostCSS; no Vite build configured |
| Database | MySQL default in configuration; SQL Server support and engine-specific SQL/migrations. This checkout was previously observed configured for SQL Server; no live database inspected for this package |
| Documents | Smalot PDFParser, Dompdf; Tesseract and Poppler for OCR |
| AI | Active HTTP integration: Anthropic; Gemini and OpenRouter service code also exists but is not selected by the chat controller |
| Tests | PHPUnit 9, Node built-in test runner, SQLite isolation and opt-in database-engine probes |

Sources: [composer.json](../composer.json), [composer.lock](../composer.lock), [package.json](../package.json), [database config](../config/database.php), [frontend inventory](IMPLEMENTATION-INVENTORY.md).

Runtime needs writable `storage` and `bootstrap/cache`, configured database drivers, PHP extensions required by the lockfile, and a worker for browser-uploaded policies. OCR requires executable permissions and language data. HTTPS/localhost browser rules and root-relative URLs affect the kiosk. See [installation](05-INSTALLATION-GUIDE.md).

## High-level workflow

1. Authenticate through configured Backpack login behavior.
2. Select an issue or provide a custom issue, destination and message.
3. Save a ticket; derive metadata, assign eligible staff where possible and notify recipients.
4. View authorized tickets, exchange comments/files and optionally request a transfer.
5. An authorized handler resolves; only the creator uses the reopen action.
6. Authorized staff review dashboards/reports. Public visitors submit satisfaction surveys separately.
7. Policy managers upload documents; a worker extracts/chunks them; authenticated users query active policy material.

See [diagrams](DIAGRAMS.md) for the system, ticket, transfer, ingestion and survey flows.

## Known limitations

- Source inspection does not prove deployed behavior, full engine compatibility or end-to-end feature parity.
- No automatic escalation scheduler or ticket escalation entity found; overdue presentation uses a three-day threshold.
- Ticket notifications use the database channel. SMTP configuration does not establish ticket-email delivery.
- Offline survey viewing does not queue submissions. ARTA's service-worker registration does not make its page offline-capable.
- Department ID `1` is embedded in survey/report logic; initial ticket status still defaults to numeric ID `3`.
- No application policy classes registered; authorization is distributed across controllers, scopes, services and components.
- No general system-settings CRUD or verified full audit trail was found.

Primary sources: [Ticket](../app/Models/Ticket.php), [ticket controller](../app/Http/Controllers/Admin/TicketCrudController.php), [survey controller](../app/Http/Controllers/SurveyController.php), [notification](../app/Notifications/TicketSystemNotification.php), [console schedule](../app/Console/Kernel.php).
