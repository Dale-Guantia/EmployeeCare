# Consolidated System Diagrams

Diagrams describe current source behavior, not deployed infrastructure. The architecture/ER blocks below are collected from guides 02/03; keep them synchronized when changing those canonical descriptions. Dotted ER edges mean application references without database FKs. Browser rendering/export was not performed; structural validation and source review are recorded in the coverage report.

## 02 diagram 1
Context and evidence: [02-SYSTEM-ARCHITECTURE.md](02-SYSTEM-ARCHITECTURE.md).

```mermaid
flowchart LR
    Browser[Browser or survey kiosk] --> HTTP[Laravel HTTP middleware]
    HTTP --> Panel[Backpack controllers and Blade]
    HTTP --> Public[Public survey controllers]
    HTTP --> Livewire[TicketChat Livewire]
    Panel --> Services[Application services]
    Public --> Services
    Livewire --> Services
    Services --> DB[(Configured database)]
    Services --> Files[Laravel storage disks]
    Services --> AI[Anthropic HTTP API]
    Services --> Queue[(policy_jobs)]
    Queue --> Worker[Policy queue worker]
    Worker --> OCR[PDFParser and OCR tools]
    Worker --> DB
    OCR --> Files
```

## 02 diagram 2
Context and evidence: [02-SYSTEM-ARCHITECTURE.md](02-SYSTEM-ARCHITECTURE.md).

```mermaid
sequenceDiagram
    participant U as User
    participant C as Ticket controller
    participant R as TicketRequest
    participant T as Ticket model
    participant A as Auto-assignment
    participant D as Database
    U->>C: POST ticket with CSRF token
    C->>R: Authorize and validate
    R-->>C: Validated input
    C->>T: Save ticket
    T->>D: Transactional insert
    T->>A: Created observer
    A->>D: Read eligible staff and grouped workload
    A->>T: Assign selected staff when available
    T->>D: Save assignment and database notifications
    T-->>C: Saved entry
    C-->>U: Redirect and feedback
```

## 03 diagram 1
Context and evidence: [03-DATABASE-DOCUMENTATION.md](03-DATABASE-DOCUMENTATION.md).

```mermaid
erDiagram
    departments ||--o{ divisions : contains
    departments o|--o{ users : locates
    divisions o|--o{ users : locates
    departments ||--o{ issues : categorizes
    divisions ||--o{ issues : categorizes
    priorities o|--o{ issues : prioritizes
    users ||--o{ tickets : creates
    users o|--o{ tickets : assigned_or_resolved
    departments o|--o{ tickets : routes
    divisions o|--o{ tickets : routes
    statuses ||--o{ tickets : labels
    issues o|--o{ tickets : classifies
    priorities o|--o{ tickets : prioritizes
    tickets ||--o{ ticket_comments : contains
    users ||--o{ ticket_comments : writes
    tickets ||--o{ ticket_reassignment_requests : transfers
    departments ||--o{ ticket_reassignment_requests : destination
    divisions o|--o{ ticket_reassignment_requests : destination
    users ||--o{ ticket_reassignment_requests : requests
    users ||--o{ surveys : rated_staff
    issues ||--o{ surveys : rated_service
    hr_policy_documents ||--o{ hr_policy_chunks : contains
    roles ||--o{ role_has_permissions : grants
    permissions ||--o{ role_has_permissions : links
```

## 03 diagram 2
Context and evidence: [03-DATABASE-DOCUMENTATION.md](03-DATABASE-DOCUMENTATION.md).

```mermaid
erDiagram
    users ||..o{ hr_conversations : Eloquent_user_id
    users o|..o{ hr_chat_logs : application_user_id
    hr_conversations o|..o{ hr_chat_logs : Eloquent_conversation_id
    hr_policy_chunks }o..o{ hr_chat_logs : JSON_matched_chunk_ids
    users ||..o{ notifications : polymorphic_notifiable
    users o|..o{ sessions : indexed_user_id
    users ||..o{ model_has_roles : polymorphic_model
    users ||..o{ model_has_permissions : polymorphic_model
```

## Ticket status workflow

Source: [Ticket model](../app/Models/Ticket.php), [controller](../app/Http/Controllers/Admin/TicketCrudController.php), [assignment service](../app/Services/TicketAutoAssignmentService.php). This shows the principal workflow; head/admin generic status editing can introduce additional configured transitions.

```mermaid
stateDiagram-v2
    [*] --> Unassigned: Create with no selected staff
    Unassigned --> Pending: Eligible staff assigned
    Pending --> Unassigned: Assignee cleared
    Reopened --> Pending: Assignee changed
    Unassigned --> Resolved: Authorized resolve
    Pending --> Resolved: Authorized resolve
    Reopened --> Resolved: Authorized resolve
    Resolved --> Reopened: Creator reopens
```

Deadline calculations use original creation time. No escalation transition or scheduled escalation worker is represented because none was found.

## Reassignment request lifecycle

Source: [controller transition handlers](../app/Http/Controllers/Admin/TicketCrudController.php), [request model](../app/Models/TicketReassignmentRequest.php).

```mermaid
stateDiagram-v2
    [*] --> Pending: Authorized unresolved-ticket request
    Pending --> Accepted: Receiving head or admin
    Pending --> Rejected: Receiving head or admin
    Pending --> Cancelled: Requester or admin
    Pending --> Cancelled: Ticket resolved
    Accepted --> [*]
    Rejected --> [*]
    Cancelled --> [*]
```

Acceptance changes location and reruns staff assignment; request status and ticket status are separate fields. Parent/pending rows are locked for transitions.

## Policy ingestion states

Source: [ingestion service](../app/Services/PolicyIngestService.php), [job](../app/Jobs/IngestPolicyDocumentJob.php), [queue config](../config/queue.php).

```mermaid
flowchart TD
    Upload[Validated browser upload or replacement] --> Pending[Pending generation]
    Pending --> Queue[Atomic policy_jobs enqueue and queued marker]
    Queue --> Claim[Worker claims current generation]
    Claim --> Processing[Processing]
    Processing --> Extraction[Native extraction and page OCR]
    Extraction --> Quality{Extraction complete and confidence sufficient?}
    Quality -->|Yes| Commit[Atomic chunk and metadata replacement]
    Commit --> Flag{Document active flag?}
    Flag -->|Yes| Active[Active]
    Flag -->|No| Inactive[Inactive]
    Quality -->|Low or incomplete OCR| Review[Needs review]
    Processing -->|Exception or exhausted job| Failed[Failed]
    Active -->|Toggle| Inactive
    Inactive -->|Toggle| Active
    Review -->|Approved user re-run after correction| Pending
    Failed -->|Approved user re-run after correction| Pending
```

Needs-review chunk/metadata updates are also transactional. Retrieval excludes pending/processing/failed/needs_review/inactive documents. Effective date is not an eligibility predicate. Re-run is not a quality-override approval action.

## Policy upload sequence

```mermaid
sequenceDiagram
    participant M as Policy manager
    participant C as Upload controller
    participant S as Ingestion service
    participant D as Database
    participant W as Continuous worker
    participant O as Extraction tools
    M->>C: Submit title/category/date/PDF
    C->>S: Validate readiness and store
    S->>D: Create pending versioned document
    S->>D: Transactionally enqueue job and marker
    C-->>M: Uploaded and queued feedback
    W->>D: Reserve job and claim current generation
    W->>O: Extract pages and OCR where needed
    O-->>W: Text, page attribution and confidence
    W->>D: Guarded transaction replaces chunks and state
    M->>C: Refresh document list
    C-->>M: Current ingestion state
```

A saved document can remain pending if dispatch fails or no worker runs. Generation guards discard stale jobs after replacement; deletion is not prohibited by the service's processing edit guard.

## Survey submission and retry

Source: [survey browser script](../public/survey-assets/js/survey.js), [controller](../app/Http/Controllers/SurveyController.php), [submission service](../app/Services/SurveySubmission.php), [worker](../public/sw-survey.js).

```mermaid
sequenceDiagram
    participant K as Kiosk browser
    participant SW as Service worker
    participant C as Survey controller
    participant D as Database
    K->>SW: GET survey page/assets
    alt Network available
        SW->>C: Fetch current public survey
        C-->>SW: Eligible cacheable HTML
        SW-->>K: Network response; cache sanitized copy
    else Network unavailable
        SW-->>K: Cached public view or offline fallback
        Note over K: Reconnect before submitting; no offline outbox
    end
    K->>C: GET current CSRF token
    C-->>K: Session token with no-store
    K->>C: POST answers with stable submission UUID
    C->>D: Validate and firstOrCreate UUID in transaction
    D-->>C: Existing identical response or new row
    C-->>K: Saved confirmation
    Note over K: Retry retains UUID; next respondent gets a new UUID
```

Reusing the same UUID with changed answers fails validation; the database unique constraint resolves races. ARTA uses the shared persistence service but follows ordinary form redirects, not the same explicit JSON submission contract.

