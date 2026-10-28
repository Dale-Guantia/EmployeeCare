# Implementation Inventory and Documentation Plan

Snapshot: 2026-10-27. Source checkout, including existing uncommitted work. No live database inventory or production certification. Inventory excludes dependency internals, employee uploads, avatars, secrets, caches and logs.

## Documentation plan

1. Inventory routes, classes, migrations, dependencies and tests.
2. Trace modules through authorization, validation, services, persistence and UI.
3. Write guides 01-14 and supporting traceability, gaps and diagram references.
4. Validate links, source coverage, route inventory, schema inventory and diagram structure.
5. Record results and limitations in DOCUMENTATION-COVERAGE-REPORT.md.

## Component inventory

| Source | Declared methods / responsibility clues |
| --- | --- |
| [app/Console/Commands/IngestHrPolicyPdf.php](../app/Console/Commands/IngestHrPolicyPdf.php) | `__construct`, `handle` |
| [app/Console/Commands/PrivatizeTicketAttachments.php](../app/Console/Commands/PrivatizeTicketAttachments.php) | `handle` |
| [app/Console/Commands/ReconcileTicketAttachments.php](../app/Console/Commands/ReconcileTicketAttachments.php) | `handle`, `reportFile` |
| [app/Console/Commands/SyncUserAvatars.php](../app/Console/Commands/SyncUserAvatars.php) | `handle` |
| [app/Console/Commands/TestOcrSetup.php](../app/Console/Commands/TestOcrSetup.php) | `__construct`, `handle` |
| [app/Console/Kernel.php](../app/Console/Kernel.php) | `schedule`, `commands` |
| [app/Exceptions/AttachmentInspectionException.php](../app/Exceptions/AttachmentInspectionException.php) | Configuration, route declarations, fixture or procedural source |
| [app/Exceptions/Handler.php](../app/Exceptions/Handler.php) | `register` |
| [app/Exceptions/PolicyChatException.php](../app/Exceptions/PolicyChatException.php) | `__construct` |
| [app/Exceptions/PolicyIngestionDispatchException.php](../app/Exceptions/PolicyIngestionDispatchException.php) | Configuration, route declarations, fixture or procedural source |
| [app/Exceptions/PolicyIngestionSetupException.php](../app/Exceptions/PolicyIngestionSetupException.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Controllers/Admin/ArtaSurveyReportsController.php](../app/Http/Controllers/Admin/ArtaSurveyReportsController.php) | `__construct`, `index`, `responses`, `downloadPdf`, `getReportData` |
| [app/Http/Controllers/Admin/Auth/LoginController.php](../app/Http/Controllers/Admin/Auth/LoginController.php) | `showLoginForm`, `credentials` |
| [app/Http/Controllers/Admin/Auth/RegisterController.php](../app/Http/Controllers/Admin/Auth/RegisterController.php) | `validator`, `create` |
| [app/Http/Controllers/Admin/DashboardController.php](../app/Http/Controllers/Admin/DashboardController.php) | `dashboard`, `getDashboardData` |
| [app/Http/Controllers/Admin/DepartmentCrudController.php](../app/Http/Controllers/Admin/DepartmentCrudController.php) | `setup`, `setupListOperation`, `setupCreateOperation`, `setupUpdateOperation` |
| [app/Http/Controllers/Admin/DivisionCrudController.php](../app/Http/Controllers/Admin/DivisionCrudController.php) | `setup`, `setupListOperation`, `setupCreateOperation`, `setupUpdateOperation` |
| [app/Http/Controllers/Admin/HrChatController.php](../app/Http/Controllers/Admin/HrChatController.php) | `__construct`, `index`, `ask`, `saveAnswer`, `isGreeting`, `resolveConversation`, `touchConversation`, `greetingReply`, `feedback`, `history`, `clearHistory`, `conversations`, `conversationMessages`, `updateConversation`, `deleteConversation` |
| [app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php](../app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php) | `setup`, `setupListOperation`, `uploadForm`, `uploadStore`, `updateForm`, `updateStore`, `toggleActive`, `reingest`, `destroy` |
| [app/Http/Controllers/Admin/IssueCrudController.php](../app/Http/Controllers/Admin/IssueCrudController.php) | `setup`, `setupListOperation`, `setupCreateOperation`, `setupUpdateOperation`, `setupShowOperation` |
| [app/Http/Controllers/Admin/MyAccountController.php](../app/Http/Controllers/Admin/MyAccountController.php) | `getAccountInfoForm`, `postAccountInfoForm`, `postChangePasswordForm`, `postNotificationsForm` |
| [app/Http/Controllers/Admin/NotificationController.php](../app/Http/Controllers/Admin/NotificationController.php) | `feed`, `markAsRead`, `markAllAsRead`, `clearAll`, `index` |
| [app/Http/Controllers/Admin/PermissionCrudController.php](../app/Http/Controllers/Admin/PermissionCrudController.php) | `setup` |
| [app/Http/Controllers/Admin/PriorityCrudController.php](../app/Http/Controllers/Admin/PriorityCrudController.php) | `setup`, `setupListOperation`, `setupCreateOperation`, `setupUpdateOperation` |
| [app/Http/Controllers/Admin/ReportsController.php](../app/Http/Controllers/Admin/ReportsController.php) | `__construct`, `index`, `downloadPdf`, `getReportData` |
| [app/Http/Controllers/Admin/RoleCrudController.php](../app/Http/Controllers/Admin/RoleCrudController.php) | `setup`, `setupCreateOperation`, `setupUpdateOperation`, `store`, `update`, `syncPermissionsFromRequest`, `renderGroupedPermissions` |
| [app/Http/Controllers/Admin/StatusCrudController.php](../app/Http/Controllers/Admin/StatusCrudController.php) | `setup`, `setupListOperation`, `setupCreateOperation`, `setupUpdateOperation` |
| [app/Http/Controllers/Admin/SubmitTicketController.php](../app/Http/Controllers/Admin/SubmitTicketController.php) | `show` |
| [app/Http/Controllers/Admin/SurveyReportsController.php](../app/Http/Controllers/Admin/SurveyReportsController.php) | `__construct`, `index`, `responses`, `downloadPdf`, `getReportData` |
| [app/Http/Controllers/Admin/TicketAttachmentController.php](../app/Http/Controllers/Admin/TicketAttachmentController.php) | `show`, `comment`, `respond` |
| [app/Http/Controllers/Admin/TicketCrudController.php](../app/Http/Controllers/Admin/TicketCrudController.php) | `setup`, `destroy`, `setupListOperation`, `setupCreateOperation`, `renderAttachments`, `setupUpdateOperation`, `isReceivingHead`, `handleReopen`, `handleResolve`, `handleQuickAssign`, `handleReassignRequest`, `handleReassignCancel`, `handleReassignAccept`, `handleReassignReject`, `runReassignmentAction`, `update`, `setupShowOperation` |
| [app/Http/Controllers/Admin/UserCrudController.php](../app/Http/Controllers/Admin/UserCrudController.php) | `setup`, `setupListOperation`, `setupCreateOperation`, `setupUpdateOperation`, `addDynamicDropdownFields` |
| [app/Http/Controllers/ArtaController.php](../app/Http/Controllers/ArtaController.php) | `showForm`, `submitForm` |
| [app/Http/Controllers/Controller.php](../app/Http/Controllers/Controller.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Controllers/SurveyController.php](../app/Http/Controllers/SurveyController.php) | `showForm`, `csrfToken`, `surveyStaff`, `submitForm` |
| [app/Http/Kernel.php](../app/Http/Kernel.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Livewire/TicketChat.php](../app/Http/Livewire/TicketChat.php) | `getTicketProperty`, `getIsResolvedProperty`, `render`, `removeAttachment`, `sendComment` |
| [app/Http/Middleware/Authenticate.php](../app/Http/Middleware/Authenticate.php) | `redirectTo` |
| [app/Http/Middleware/CheckIfAdmin.php](../app/Http/Middleware/CheckIfAdmin.php) | `checkIfUserIsAdmin`, `respondToUnauthorizedRequest`, `handle` |
| [app/Http/Middleware/EncryptCookies.php](../app/Http/Middleware/EncryptCookies.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Middleware/PreventRequestsDuringMaintenance.php](../app/Http/Middleware/PreventRequestsDuringMaintenance.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Middleware/RedirectIfAuthenticated.php](../app/Http/Middleware/RedirectIfAuthenticated.php) | `handle` |
| [app/Http/Middleware/TrimStrings.php](../app/Http/Middleware/TrimStrings.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Middleware/TrustHosts.php](../app/Http/Middleware/TrustHosts.php) | `hosts` |
| [app/Http/Middleware/TrustProxies.php](../app/Http/Middleware/TrustProxies.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Middleware/VerifyCsrfToken.php](../app/Http/Middleware/VerifyCsrfToken.php) | Configuration, route declarations, fixture or procedural source |
| [app/Http/Requests/DepartmentRequest.php](../app/Http/Requests/DepartmentRequest.php) | `authorize`, `rules`, `attributes`, `messages` |
| [app/Http/Requests/DivisionRequest.php](../app/Http/Requests/DivisionRequest.php) | `authorize`, `rules`, `attributes`, `messages` |
| [app/Http/Requests/IssueRequest.php](../app/Http/Requests/IssueRequest.php) | `authorize`, `rules`, `attributes`, `messages` |
| [app/Http/Requests/PriorityRequest.php](../app/Http/Requests/PriorityRequest.php) | `authorize`, `rules`, `attributes`, `messages` |
| [app/Http/Requests/StatusRequest.php](../app/Http/Requests/StatusRequest.php) | `authorize`, `rules`, `attributes`, `messages` |
| [app/Http/Requests/TicketRequest.php](../app/Http/Requests/TicketRequest.php) | `authorize`, `rules`, `prepareForValidation`, `attributes`, `messages` |
| [app/Jobs/IngestPolicyDocumentJob.php](../app/Jobs/IngestPolicyDocumentJob.php) | `__construct`, `handle`, `failed` |
| [app/Listeners/UpdateLastLoginAt.php](../app/Listeners/UpdateLastLoginAt.php) | `__construct`, `handle` |
| [app/Models/ArtaSurvey.php](../app/Models/ArtaSurvey.php) | Configuration, route declarations, fixture or procedural source |
| [app/Models/Department.php](../app/Models/Department.php) | `booted`, `allCached` |
| [app/Models/Division.php](../app/Models/Division.php) | `booted`, `department`, `tickets`, `allCached` |
| [app/Models/HrChatLog.php](../app/Models/HrChatLog.php) | `user`, `conversation`, `scopeHelpful`, `scopeUnhelpful` |
| [app/Models/HrConversation.php](../app/Models/HrConversation.php) | `logs`, `user`, `scopeForUser`, `scopeSidebarOrder`, `titleFrom` |
| [app/Models/HrPolicyChunk.php](../app/Models/HrPolicyChunk.php) | `document` |
| [app/Models/HrPolicyDocument.php](../app/Models/HrPolicyDocument.php) | `chunks`, `getStatusBadgeAttribute`, `getOcrBadgeAttribute`, `scopeActive` |
| [app/Models/Issue.php](../app/Models/Issue.php) | `department`, `division`, `priority` |
| [app/Models/Priority.php](../app/Models/Priority.php) | Configuration, route declarations, fixture or procedural source |
| [app/Models/Region.php](../app/Models/Region.php) | Configuration, route declarations, fixture or procedural source |
| [app/Models/Status.php](../app/Models/Status.php) | `booted`, `tickets`, `idsByName`, `idByName` |
| [app/Models/Survey.php](../app/Models/Survey.php) | `staff`, `service` |
| [app/Models/Ticket.php](../app/Models/Ticket.php) | `newReference`, `save`, `delete`, `booted`, `scopeVisibleTo`, `setAttachmentsAttribute`, `getOverdueBadgeAttribute`, `issue`, `user`, `status`, `department`, `division`, `priority`, `assignee`, `comments`, `reassignmentRequests`, `pendingReassignmentRequest` |
| [app/Models/TicketComment.php](../app/Models/TicketComment.php) | `booted`, `user`, `ticket` |
| [app/Models/TicketReassignmentRequest.php](../app/Models/TicketReassignmentRequest.php) | `ticket`, `requester`, `responder`, `fromDepartment`, `fromDivision`, `toDepartment`, `toDivision` |
| [app/Models/User.php](../app/Models/User.php) | `getAvatarUrl`, `getAvatarWebpUrl`, `resolvedTickets`, `overdueTickets`, `assignedTickets`, `department`, `division`, `scopeActiveToday`, `wantsNotification`, `isAdmin`, `isDeptHead`, `isDivHead`, `isHrStaff`, `isEmployee` |
| [app/Notifications/TicketSystemNotification.php](../app/Notifications/TicketSystemNotification.php) | `__construct`, `via`, `toArray` |
| [app/Observers/TicketObserver.php](../app/Observers/TicketObserver.php) | `__construct`, `created`, `updated`, `deleted`, `restored`, `forceDeleted` |
| [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) | `register`, `boot` |
| [app/Providers/AuthServiceProvider.php](../app/Providers/AuthServiceProvider.php) | `boot` |
| [app/Providers/BroadcastServiceProvider.php](../app/Providers/BroadcastServiceProvider.php) | `boot` |
| [app/Providers/EventServiceProvider.php](../app/Providers/EventServiceProvider.php) | `boot` |
| [app/Providers/RouteServiceProvider.php](../app/Providers/RouteServiceProvider.php) | `boot`, `configureRateLimiting` |
| [app/Services/AnthropicService.php](../app/Services/AnthropicService.php) | `ask` |
| [app/Services/AttachmentInventory.php](../app/Services/AttachmentInventory.php) | `references`, `validPath`, `files`, `root`, `assertSeparateRoots`, `snapshot` |
| [app/Services/AttachmentStorage.php](../app/Services/AttachmentStorage.php) | `randomizedFilename`, `storeFiles`, `discard`, `deleteFiles`, `discardUnreferenced`, `readableDisk`, `ticketUrl`, `commentUrl` |
| [app/Services/AttachmentTransactions.php](../app/Services/AttachmentTransactions.php) | `__construct`, `track`, `complete` |
| [app/Services/GeminiService.php](../app/Services/GeminiService.php) | `ask` |
| [app/Services/GPTService.php](../app/Services/GPTService.php) | `__construct`, `ask`, `friendlyError` |
| [app/Services/OcrService.php](../app/Services/OcrService.php) | `__construct`, `extractDocument`, `isOcrAvailable`, `isFilipinoLanguageAvailable`, `extractNativePages`, `charDensity`, `rasterizeWholeDocumentAsBlankPages`, `ocrPages`, `rasterizePage`, `ocrImagesConcurrently`, `parseTsv`, `makeTempDir`, `sortedPageImages`, `runCommand`, `normalizePath`, `cleanupDir` |
| [app/Services/PolicyChatPrompt.php](../app/Services/PolicyChatPrompt.php) | `instructions`, `context`, `text`, `messages` |
| [app/Services/PolicyIngestService.php](../app/Services/PolicyIngestService.php) | `__construct`, `store`, `assertUploadReady`, `ingest`, `replace`, `markPendingForReingest`, `queue`, `editableDocument`, `destroy`, `deleteStoredFile`, `findCachedExtraction`, `buildWordPageMap`, `chunkWords`, `detectSection`, `buildVector` |
| [app/Services/PolicyRetriever.php](../app/Services/PolicyRetriever.php) | `retrieveForConversation`, `promptChunks`, `retrieve`, `formatForPrompt`, `extractKeywords` |
| [app/Services/ReportPeriodResolver.php](../app/Services/ReportPeriodResolver.php) | `resolve` |
| [app/Services/SqlDialectHelper.php](../app/Services/SqlDialectHelper.php) | `diffHoursSql`, `diffMinutesSql`, `isSqlServer` |
| [app/Services/SurveyReportQueries.php](../app/Services/SurveyReportQueries.php) | `summary` |
| [app/Services/SurveyResponseTable.php](../app/Services/SurveyResponseTable.php) | `data` |
| [app/Services/SurveySubmission.php](../app/Services/SurveySubmission.php) | `save` |
| [app/Services/TicketAssignmentRules.php](../app/Services/TicketAssignmentRules.php) | `canAssign`, `candidates`, `validation` |
| [app/Services/TicketAutoAssignmentService.php](../app/Services/TicketAutoAssignmentService.php) | `assignTicket`, `analyzeContentAndMatch`, `normalizeForMatching` |
| [app/Services/TicketNotificationService.php](../app/Services/TicketNotificationService.php) | `ticketUrl`, `notifyUsers`, `excludeActor`, `commentParticipants`, `candidates`, `shouldReceiveTicketCreated`, `shouldReceiveTicketAssigned`, `shouldReceiveTicketStatusChanged`, `shouldReceiveTicketCommented`, `assignmentMessageFor`, `notifyTicketCreated`, `notifyTicketAssigned`, `notifyTicketStatusChanged`, `reassignmentReceivers`, `notifyReassignmentRequested`, `notifyReassignmentAccepted`, `notifyReassignmentRejected`, `notifyTicketCommented` |
| [app/Services/TicketReportQueries.php](../app/Services/TicketReportQueries.php) | `widgets`, `staffResolutionAverages` |
| [app/Services/TicketReportWidgets.php](../app/Services/TicketReportWidgets.php) | `isTicketOverdue`, `formatMinutes`, `buildKpiWidget`, `buildVolumeTrendWidget`, `buildResolutionDistributionWidget`, `buildSlaBreakdownWidget`, `buildStatusFunnelWidget`, `buildReassignmentWidget` |
| [config/app.php](../config/app.php) | Configuration, route declarations, fixture or procedural source |
| [config/auth.php](../config/auth.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/base.php](../config/backpack/base.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/crud.php](../config/backpack/crud.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/operations/create.php](../config/backpack/operations/create.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/operations/list.php](../config/backpack/operations/list.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/operations/reorder.php](../config/backpack/operations/reorder.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/operations/show.php](../config/backpack/operations/show.php) | Configuration, route declarations, fixture or procedural source |
| [config/backpack/operations/update.php](../config/backpack/operations/update.php) | Configuration, route declarations, fixture or procedural source |
| [config/broadcasting.php](../config/broadcasting.php) | Configuration, route declarations, fixture or procedural source |
| [config/cache.php](../config/cache.php) | Configuration, route declarations, fixture or procedural source |
| [config/cors.php](../config/cors.php) | Configuration, route declarations, fixture or procedural source |
| [config/database.php](../config/database.php) | Configuration, route declarations, fixture or procedural source |
| [config/filesystems.php](../config/filesystems.php) | Configuration, route declarations, fixture or procedural source |
| [config/gravatar.php](../config/gravatar.php) | Configuration, route declarations, fixture or procedural source |
| [config/hashing.php](../config/hashing.php) | Configuration, route declarations, fixture or procedural source |
| [config/livewire.php](../config/livewire.php) | Configuration, route declarations, fixture or procedural source |
| [config/logging.php](../config/logging.php) | Configuration, route declarations, fixture or procedural source |
| [config/mail.php](../config/mail.php) | Configuration, route declarations, fixture or procedural source |
| [config/ocr.php](../config/ocr.php) | Configuration, route declarations, fixture or procedural source |
| [config/permission.php](../config/permission.php) | Configuration, route declarations, fixture or procedural source |
| [config/queue.php](../config/queue.php) | Configuration, route declarations, fixture or procedural source |
| [config/reports.php](../config/reports.php) | Configuration, route declarations, fixture or procedural source |
| [config/sanctum.php](../config/sanctum.php) | Configuration, route declarations, fixture or procedural source |
| [config/services.php](../config/services.php) | Configuration, route declarations, fixture or procedural source |
| [config/session.php](../config/session.php) | Configuration, route declarations, fixture or procedural source |
| [config/view.php](../config/view.php) | Configuration, route declarations, fixture or procedural source |
| [database/migrations/2014_10_12_100000_create_password_resets_table.php](../database/migrations/2014_10_12_100000_create_password_resets_table.php) | `up`, `down` |
| [database/migrations/2019_08_19_000000_create_failed_jobs_table.php](../database/migrations/2019_08_19_000000_create_failed_jobs_table.php) | `up`, `down` |
| [database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php](../database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php) | `up`, `down` |
| [database/migrations/2020_03_31_114745_remove_backpackuser_model.php](../database/migrations/2020_03_31_114745_remove_backpackuser_model.php) | `up`, `replaceModels` |
| [database/migrations/2026_02_23_070500_create_permission_tables.php](../database/migrations/2026_02_23_070500_create_permission_tables.php) | `up`, `down` |
| [database/migrations/2026_02_24_022107_create_priorities_table.php](../database/migrations/2026_02_24_022107_create_priorities_table.php) | `up`, `down` |
| [database/migrations/2026_02_24_022453_create_departments_table.php](../database/migrations/2026_02_24_022453_create_departments_table.php) | `up`, `down` |
| [database/migrations/2026_02_24_022509_create_divisions_table.php](../database/migrations/2026_02_24_022509_create_divisions_table.php) | `up`, `down` |
| [database/migrations/2026_02_24_022528_create_issues_table.php](../database/migrations/2026_02_24_022528_create_issues_table.php) | `up`, `down` |
| [database/migrations/2026_02_24_022530_create_users_table.php](../database/migrations/2026_02_24_022530_create_users_table.php) | `up`, `down` |
| [database/migrations/2026_02_24_024606_create_statuses_table.php](../database/migrations/2026_02_24_024606_create_statuses_table.php) | `up`, `down` |
| [database/migrations/2026_02_24_073426_create_tickets_table.php](../database/migrations/2026_02_24_073426_create_tickets_table.php) | `up`, `down` |
| [database/migrations/2026_03_05_074049_surveys_table.php](../database/migrations/2026_03_05_074049_surveys_table.php) | `up`, `down` |
| [database/migrations/2026_03_11_061811_create_ticket_comments_table.php](../database/migrations/2026_03_11_061811_create_ticket_comments_table.php) | `up`, `down` |
| [database/migrations/2026_03_13_080231_create_notifications_table.php](../database/migrations/2026_03_13_080231_create_notifications_table.php) | `up`, `down` |
| [database/migrations/2026_04_16_021820_create_arta_surveys_table.php](../database/migrations/2026_04_16_021820_create_arta_surveys_table.php) | `up`, `down` |
| [database/migrations/2026_06_09_011605_create_hr_chatbot_tables.php](../database/migrations/2026_06_09_011605_create_hr_chatbot_tables.php) | `up`, `down` |
| [database/migrations/2026_06_24_064940_create_sessions_table.php](../database/migrations/2026_06_24_064940_create_sessions_table.php) | `up`, `down` |
| [database/migrations/2026_07_13_083609_add_ocr_fields_to_hr_policy_tables.php](../database/migrations/2026_07_13_083609_add_ocr_fields_to_hr_policy_tables.php) | `up`, `down` |
| [database/migrations/2026_07_15_090000_create_ticket_reassignment_requests_table.php](../database/migrations/2026_07_15_090000_create_ticket_reassignment_requests_table.php) | `up`, `down` |
| [database/migrations/2026_07_16_100000_add_session_id_to_hr_chat_logs.php](../database/migrations/2026_07_16_100000_add_session_id_to_hr_chat_logs.php) | `up`, `down` |
| [database/migrations/2026_07_20_090000_create_hr_conversations.php](../database/migrations/2026_07_20_090000_create_hr_conversations.php) | `up`, `down` |
| [database/migrations/2026_07_20_090001_add_conversation_id_to_hr_chat_logs.php](../database/migrations/2026_07_20_090001_add_conversation_id_to_hr_chat_logs.php) | `up`, `down` |
| [database/migrations/2026_07_20_090002_backfill_hr_conversations_from_logs.php](../database/migrations/2026_07_20_090002_backfill_hr_conversations_from_logs.php) | `up`, `down` |
| [database/migrations/2026_07_21_000000_bound_hr_chat_logs_question_column.php](../database/migrations/2026_07_21_000000_bound_hr_chat_logs_question_column.php) | `up`, `down` |
| [database/migrations/2026_07_23_000001_add_indexes_to_tickets_table.php](../database/migrations/2026_07_23_000001_add_indexes_to_tickets_table.php) | `up`, `down` |
| [database/migrations/2026_07_23_000002_add_indexes_to_ticket_reassignment_requests_table.php](../database/migrations/2026_07_23_000002_add_indexes_to_ticket_reassignment_requests_table.php) | `up`, `down` |
| [database/migrations/2026_07_23_000003_add_indexes_to_users_table.php](../database/migrations/2026_07_23_000003_add_indexes_to_users_table.php) | `up`, `down` |
| [database/migrations/2026_10_07_100000_widen_ticket_attachment_columns.php](../database/migrations/2026_10_07_100000_widen_ticket_attachment_columns.php) | `up`, `down` |
| [database/migrations/2026_10_07_110000_add_survey_submission_ids.php](../database/migrations/2026_10_07_110000_add_survey_submission_ids.php) | `up`, `down` |
| [database/migrations/2026_10_07_120000_add_explicit_ticket_assignment_permission.php](../database/migrations/2026_10_07_120000_add_explicit_ticket_assignment_permission.php) | `up`, `down` |
| [database/migrations/2026_10_14_100000_preserve_unicode_ticket_attachment_columns.php](../database/migrations/2026_10_14_100000_preserve_unicode_ticket_attachment_columns.php) | `up`, `down` |
| [database/migrations/2026_10_14_110000_add_policy_ingestion_queue.php](../database/migrations/2026_10_14_110000_add_policy_ingestion_queue.php) | `up`, `down` |
| [routes/api.php](../routes/api.php) | Configuration, route declarations, fixture or procedural source |
| [routes/backpack/custom.php](../routes/backpack/custom.php) | Configuration, route declarations, fixture or procedural source |
| [routes/backpack/permissionmanager.php](../routes/backpack/permissionmanager.php) | Configuration, route declarations, fixture or procedural source |
| [routes/channels.php](../routes/channels.php) | Configuration, route declarations, fixture or procedural source |
| [routes/console.php](../routes/console.php) | Configuration, route declarations, fixture or procedural source |
| [routes/web.php](../routes/web.php) | Configuration, route declarations, fixture or procedural source |
| [tests/CreatesApplication.php](../tests/CreatesApplication.php) | `createApplication` |
| [tests/Engine/AuthorizedEngineDataTest.php](../tests/Engine/AuthorizedEngineDataTest.php) | `test_authorized_engine_records_queries_and_concurrent_retries`, `verifyMetadataAndAttachmentCapacity`, `verifyReports`, `payload`, `cleanupOwnedRecords` |
| [tests/Engine/DeploymentEngineTest.php](../tests/Engine/DeploymentEngineTest.php) | `test_migrations_queries_and_real_concurrent_submission_retries`, `migrate`, `createLegacySchema`, `verifyForwardMigrations`, `verifyReports`, `payload`, `race` |
| [tests/Engine/submission-worker.php](../tests/Engine/submission-worker.php) | Configuration, route declarations, fixture or procedural source |
| [tests/Feature/AttachmentOperationsTest.php](../tests/Feature/AttachmentOperationsTest.php) | `setUp`, `file`, `reference`, `test_default_audit_retains_orphans_and_reports_missing_references_without_filenames`, `test_cleanup_retains_ticket_comment_recent_and_public_files`, `test_cleanup_requires_explicit_paused_uploads_and_conservative_age`, `test_malformed_references_prevent_all_cleanup`, `malformedReferences`, `test_unsafe_filesystem_path_aborts_before_deleting_other_candidates`, `test_legacy_spaces_and_unicode_names_are_safely_retained_when_referenced`, `test_unavailable_disk_root_is_retained_without_creating_it`, `test_overlapping_disk_roots_prevent_cleanup`, `test_candidate_changed_since_scan_is_retained`, `test_database_failure_retains_everything`, `test_incomplete_second_disk_scan_cannot_authorize_deletion`, `test_fresh_reference_check_retains_a_newly_referenced_file`, `test_failed_fresh_reference_check_retains_candidate`, `test_failed_storage_delete_is_reported_and_file_retained`, `test_privatization_dry_run_detects_conflict_without_changing_either_disk`, `test_privatization_resumes_verified_copies_and_preserves_other_assets` |
| [tests/Feature/AuditSecurityRegressionTest.php](../tests/Feature/AuditSecurityRegressionTest.php) | `setUp`, `user`, `ticket`, `dispatchAs`, `updateData`, `signedChat`, `sendSignedChat`, `test_signed_livewire_ticket_id_change_cannot_read_or_write_another_ticket`, `test_owner_can_send_a_comment_through_the_ticket_page_livewire_endpoint`, `test_chat_assets_messages_and_signed_uploads_use_the_page_origin`, `livewireBasePaths`, `test_signed_livewire_cannot_write_after_account_is_deactivated`, `test_livewire_reauthorizes_after_a_computed_ticket_was_cached_earlier_in_the_request`, `test_owner_can_still_comment_and_upload_an_attachment`, `test_inactive_user_cannot_log_in_but_active_user_can`, `test_deactivated_existing_session_is_logged_out`, `test_inactive_staff_are_excluded_from_auto_assignment_and_manual_assignment`, `test_department_head_without_delete_permission_cannot_delete_even_own_ticket`, `test_delete_permission_still_requires_ticket_visibility`, `test_rejected_updates_preserve_attachments`, `test_forged_retained_paths_are_rejected_on_create_and_update`, `test_valid_update_retains_existing_uploads_and_removes_only_omitted_files`, `test_malformed_and_unusually_keyed_attachments_cannot_bypass_validation`, `test_request_body_cannot_redirect_update_to_another_ticket`, `test_update_and_delete_rollback_leave_files_and_records_intact`, `test_staff_dashboard_only_lists_owned_or_assigned_tickets`, `test_non_debug_500_does_not_render_exception_details`, `test_policy_upload_failure_logs_details_without_flashing_them`, `test_reseeding_preserves_existing_passwords`, `test_demo_seeder_refuses_production_before_any_query`, `test_production_database_seeder_does_not_create_or_reset_users`, `test_issue_create_and_edit_forms_render_compatible_select_fields` |
| [tests/Feature/CrudAuthorizationGatesTest.php](../tests/Feature/CrudAuthorizationGatesTest.php) | `makeUser`, `test_dept_head_can_view_departments_index`, `test_dept_head_cannot_create_departments`, `test_dept_head_cannot_store_departments`, `test_dept_head_can_store_issues_but_not_delete`, `test_admin_retains_full_department_access`, `test_div_head_still_blocked_from_departments_entirely` |
| [tests/Feature/CrudRequestUniqueIgnoreSelfTest.php](../tests/Feature/CrudRequestUniqueIgnoreSelfTest.php) | `rulesWithRouteId`, `__construct`, `parameter`, `test_status_request_ignores_its_own_id_on_update`, `test_priority_request_ignores_its_own_id_on_update`, `test_department_request_ignores_its_own_id_on_update`, `test_status_request_still_rejects_a_real_duplicate_name` |
| [tests/Feature/CrudRequestValidationTest.php](../tests/Feature/CrudRequestValidationTest.php) | `test_department_create_rejects_empty_name`, `test_department_create_accepts_a_real_name` |
| [tests/Feature/DashboardLatestTicketsQueryCountTest.php](../tests/Feature/DashboardLatestTicketsQueryCountTest.php) | `test_latest_tickets_do_not_trigger_n_plus_one` |
| [tests/Feature/DashboardVisibilityTest.php](../tests/Feature/DashboardVisibilityTest.php) | `setUp`, `tearDown`, `test_every_analytics_query_uses_ticket_visibility`, `roles`, `test_ticket_view_is_required_and_employee_redirect_is_preserved`, `test_empty_visible_scope_does_not_use_hidden_history_or_reassignment_totals` |
| [tests/Feature/HrChatConversationMemoryTest.php](../tests/Feature/HrChatConversationMemoryTest.php) | `setUp`, `makeUser`, `fakeAnthropic`, `ask`, `test_first_message_of_session_has_empty_history_and_succeeds`, `test_bare_followup_retrieves_wellness_leave_not_omnibus`, `test_topic_switch_does_not_stick_to_previous_topic`, `test_grounding_not_bypassed_on_first_turn`, `test_retrieval_miss_path_logs_conversation_id`, `test_audit_log_stores_raw_question_not_expanded_query`, `test_history_is_capped_at_six_messages`, `test_no_cross_user_history_leakage`, `test_cached_system_block_is_unaffected_by_history`, `titlesOnly` |
| [tests/Feature/HrChatRateLimitTest.php](../tests/Feature/HrChatRateLimitTest.php) | `fakeRetriever`, `__construct`, `retrieve`, `formatForPrompt`, `fakeAi`, `__construct`, `ask`, `test_ask_endpoint_is_throttled_after_configured_limit`, `test_normal_conversation_activity_does_not_consume_question_allowance`, `test_conversation_requests_remain_limited_without_blocking_page_or_questions`, `test_users_on_the_same_ip_have_separate_question_limits` |
| [tests/Feature/HrChatReliabilityTest.php](../tests/Feature/HrChatReliabilityTest.php) | `document`, `user`, `test_failed_provider_is_not_persisted_as_a_successful_answer`, `test_gemini_runs_the_unchanged_controller_flow_when_only_its_dependency_is_substituted`, `__construct`, `test_suggestions_are_private_and_clear_includes_legacy_logs`, `test_topic_change_and_unicode_punctuation_do_not_reuse_stale_subjects`, `test_unavailable_documents_are_excluded_even_if_chunks_remain`, `unavailablePolicies`, `test_only_chunks_in_the_bounded_prompt_are_cited`, `test_deleting_a_conversation_during_provider_call_does_not_create_orphan_logs`, `ask`, `test_inactive_and_unauthenticated_accounts_cannot_use_chat_endpoints`, `test_history_and_feedback_cannot_read_or_modify_another_users_log`, `test_validation_rejects_empty_overlong_utf8_questions_and_invalid_identifiers`, `test_star_and_unstar_persist_through_the_owned_conversation_endpoint`, `test_unavailable_policy_schema_returns_failure_instead_of_a_successful_retrieval_miss` |
| [tests/Feature/HrConversationTest.php](../tests/Feature/HrConversationTest.php) | `makeUser`, `fakeRetriever`, `__construct`, `retrieve`, `formatForPrompt`, `fakeAi`, `__construct`, `ask`, `ask`, `test_a_new_chat_creates_exactly_one_conversation_titled_from_the_first_question`, `test_follow_up_in_same_conversation_attaches_and_carries_history`, `test_a_brand_new_conversation_has_no_memory_of_the_previous_one`, `test_raw_question_is_stored_not_the_expanded_retrieval_string`, `test_conversations_endpoint_orders_starred_first`, `test_rename_updates_title_without_touching_the_logs`, `test_delete_removes_the_conversation_and_its_logs_only`, `test_a_user_cannot_read_rename_or_delete_another_users_conversation`, `test_clear_history_wipes_only_the_current_users_conversations`, `test_unknown_conversation_id_starts_a_fresh_conversation_instead_of_erroring` |
| [tests/Feature/MediumFindingsRegressionTest.php](../tests/Feature/MediumFindingsRegressionTest.php) | `setUp`, `actor`, `ticket`, `dispatch`, `quickAssign`, `test_c8_private_ticket_and_comment_downloads_enforce_ticket_and_comment_scope`, `test_c8_legacy_migration_preserves_bytes_and_other_public_files`, `test_d2_notification_settings_alias_updates_only_the_authenticated_account`, `test_d4_admin_can_assign_any_eligible_hr_staff_but_not_wrong_roles_inactive_or_inconsistent_locations`, `test_d4_department_and_division_heads_assign_only_within_their_own_scope`, `test_d4_hr_assignment_requires_an_explicit_permission`, `test_d5_ticket_input_types_required_fields_and_custom_location_are_validated`, `test_d5_issue_and_user_divisions_must_belong_to_the_submitted_department`, `test_d6_arta_rejects_empty_or_unknown_answers_and_preserves_optional_fields_and_cc_normalization`, `test_d7_only_resolved_tickets_can_reopen_and_other_statuses_clear_resolution_fields`, `test_e2_attachment_columns_store_long_arrays_and_file_names_are_bounded`, `test_e3_reference_collisions_retry_only_the_unique_reference_constraint`, `test_e5_idempotency_retains_identical_answers_from_different_clients_and_rejects_id_reuse`, `test_e5_unique_constraint_race_returns_the_matching_winner_and_does_not_hide_other_database_errors`, `test_d4_full_ticket_update_uses_the_same_assignment_validator_and_preserves_a_valid_form`, `test_c11_public_survey_posts_are_throttled_without_disabling_an_ordinary_kiosk_session`, `test_j1_former_heads_do_not_receive_comments_after_transfer_but_current_participants_do`, `test_f2_notifications_select_candidates_and_mark_all_read_in_one_scoped_update`, `test_f2_candidate_queries_preserve_the_existing_event_recipient_matrix`, `test_f1_server_side_response_tables_preserve_full_history_search_order_and_permissions`, `test_j2_partial_storage_failure_is_visible_and_removes_only_staged_files`, `test_j2_ticket_persistence_failure_removes_uploads_and_retains_existing_files`, `test_j2_comment_persistence_failure_rolls_back_row_and_upload_and_retains_input`, `test_j2_outer_and_nested_rollbacks_cleanup_new_files_but_committed_files_survive`, `test_j2_exception_after_commit_does_not_delete_a_committed_ticket_upload`, `test_j2_unverifiable_commit_state_preserves_files_for_reconciliation`, `newReference`, `getAttribute`, `firstOrCreate` |
| [tests/Feature/NotificationSettingsTest.php](../tests/Feature/NotificationSettingsTest.php) | `makeUser`, `makeTicket`, `saveSettings`, `assertRenderedSettings`, `test_all_sixteen_switch_combinations_persist_and_render_after_reload`, `test_all_five_roles_can_save_their_visible_settings`, `test_guest_cannot_save_preferences`, `test_inactive_user_cannot_save_preferences`, `test_saved_switch_controls_real_event_delivery`, `ticketEvents`, `test_creation_preferences_do_not_override_role_or_department_scope`, `test_status_notification_reports_new_status_when_relation_was_already_loaded`, `test_initial_auto_assignment_does_not_send_a_status_update_but_later_updates_do` |
| [tests/Feature/PolicyChatIngestionTest.php](../tests/Feature/PolicyChatIngestionTest.php) | `test_incomplete_or_unscored_ocr_is_never_chatbot_visible`, `incompleteResults`, `test_successful_reingestion_preserves_inactive_state`, `test_activation_rechecks_current_state_instead_of_trusting_stale_route_model`, `test_failed_conversion_cannot_be_treated_as_success_from_partial_stdout`, `test_pending_dispatch_failure_remains_visible_in_the_policy_list`, `test_zero_padded_poppler_pages_are_resolved_by_their_actual_page_numbers`, `test_real_process_polling_enforces_per_page_timeout` |
| [tests/Feature/PolicyChatProvidersTest.php](../tests/Feature/PolicyChatProvidersTest.php) | `setUp`, `test_both_payloads_use_the_same_bounded_utf8_history_and_policy_only_instructions`, `test_provider_failures_are_safe_exceptions_without_retries`, `failures`, `test_missing_credentials_and_invalid_model_do_not_send_http` |
| [tests/Feature/PolicyIngestAtomicityTest.php](../tests/Feature/PolicyIngestAtomicityTest.php) | `document`, `ocrResult`, `service`, `upload`, `test_replacement_invalidates_old_extraction_and_duplicate_upload_extracts_new_text`, `test_unfinished_extractions_are_not_cache_candidates`, `unfinishedStatuses`, `test_completed_extraction_cache_and_quality_gate_are_preserved`, `test_failed_replacement_storage_preserves_the_original_file_and_record`, `test_replacement_database_failure_preserves_original_and_removes_unused_upload`, `test_replacement_waits_for_commit_before_deleting_original`, `test_chunk_insert_failure_rolls_back_previous_chunks_and_extraction`, `test_successful_ingestion_replaces_chunks_and_metadata_together`, `test_same_name_uploads_have_distinct_paths_and_contents`, `test_delete_failure_rolls_back_chunks_and_preserves_file`, `test_replacing_a_legacy_shared_path_preserves_the_other_document_file`, `test_successful_delete_removes_record_chunks_and_file`, `test_delete_response_does_not_disclose_service_exception`, `test_update_failure_does_not_flash_service_exception` |
| [tests/Feature/PolicyQueueTest.php](../tests/Feature/PolicyQueueTest.php) | `setUp`, `document`, `forbidOcr`, `queuedJob`, `test_upload_replacement_and_reingestion_queue_with_sync_default_without_inline_ocr`, `test_dispatch_failure_preserves_uploaded_pdf_and_pending_record_with_accurate_message`, `test_replacement_and_reingestion_dispatch_failures_keep_recoverable_state`, `breakQueue`, `test_old_generation_and_completed_duplicates_skip_ocr_and_overlap_is_rejected`, `test_stale_job_failure_cannot_overwrite_replacement_and_current_failure_is_visible`, `test_worker_failure_keeps_pdf_and_old_chunks_and_records_failure`, `test_queue_marker_failure_rolls_back_the_inserted_job`, `test_sync_override_is_rejected_instead_of_running_ocr_inline`, `test_a_real_database_queue_job_runs_once_and_finishes_when_consumed`, `test_expired_reserved_job_marks_interrupted_document_failed_without_repeating_ocr` |
| [tests/Feature/PolicyUploadReadinessTest.php](../tests/Feature/PolicyUploadReadinessTest.php) | `setUp`, `legacySchema`, `test_unmigrated_upload_returns_a_clear_form_error_without_storing_an_orphan_pdf`, `test_the_forward_migration_preserves_legacy_records_and_supplies_upload_requirements`, `test_missing_queue_schema_preserves_an_existing_policy_during_replacement`, `test_failed_record_creation_compensates_only_its_unused_uploaded_file`, `test_forward_migration_compiles_native_sql_server_types_without_destructive_queries`, `__construct`, `connection` |
| [tests/Feature/PolicyUploadSamplesTest.php](../tests/Feature/PolicyUploadSamplesTest.php) | `test_supplied_pdfs_upload_queue_and_ingest_with_real_extraction`, `samples` |
| [tests/Feature/ReassignmentWidgetXssTest.php](../tests/Feature/ReassignmentWidgetXssTest.php) | `test_division_name_with_raw_angle_brackets_is_hex_escaped_in_reassignment_widget` |
| [tests/Feature/ReportQueryParityTest.php](../tests/Feature/ReportQueryParityTest.php) | `tearDown`, `test_ticket_queries_equal_original_widgets_across_chunks_and_date_buckets`, `test_survey_grouped_queries_equal_original_for_all_staff_ratings_and_zero_activity` |
| [tests/Feature/ReportsPageWidgetsRenderTest.php](../tests/Feature/ReportsPageWidgetsRenderTest.php) | `actingAdmin`, `test_reports_page_renders_kpi_strip_and_trend_charts`, `test_reports_page_renders_sla_and_funnel_charts`, `test_reports_page_renders_staff_workload_columns_and_reassignment_widget`, `test_reports_page_shows_exactly_six_kpi_cards_with_period_control` |
| [tests/Feature/ReportsPdfRegressionTest.php](../tests/Feature/ReportsPdfRegressionTest.php) | `actingAdmin`, `test_pdf_download_still_succeeds_and_is_a_pdf`, `test_pdf_download_succeeds_on_a_zero_ticket_range`, `test_reports_page_renders_on_effectively_empty_range`, `test_survey_and_arta_report_pages_still_load_on_mssql` |
| [tests/Feature/ReportsPeriodControlTest.php](../tests/Feature/ReportsPeriodControlTest.php) | `actingAdmin`, `makeDeptDiv`, `makeTicket`, `test_default_period_with_no_query_params_is_this_month`, `test_all_time_period_is_unbounded_and_includes_old_tickets`, `test_semestral_defaults_to_the_half_today_falls_in`, `test_semestral_h1_window_is_jan_to_jun`, `test_semestral_h2_window_is_jul_to_dec`, `test_custom_range_applies_exact_dates`, `test_custom_range_with_end_before_start_falls_back_gracefully`, `test_custom_range_with_missing_dates_falls_back_gracefully`, `test_volume_trend_buckets_daily_for_a_short_custom_range`, `test_volume_trend_buckets_weekly_for_a_six_month_custom_range`, `test_volume_trend_buckets_monthly_for_all_time`, `test_volume_trend_zero_days_show_as_zero_not_missing`, `test_reassignment_widget_is_scoped_to_hr_office_divisions` |
| [tests/Feature/ReportsStaffWorkloadTest.php](../tests/Feature/ReportsStaffWorkloadTest.php) | `test_staff_workload_includes_assigned_count_and_avg_resolution` |
| [tests/Feature/ReportsWidgetsTest.php](../tests/Feature/ReportsWidgetsTest.php) | `makeAdmin`, `makeDeptDiv`, `test_reports_page_returns_widget_data_with_known_counts`, `test_resolution_distribution_buckets_sum_to_resolved_count` |
| [tests/Feature/SkillTagsTest.php](../tests/Feature/SkillTagsTest.php) | `test_skill_values_round_trip_as_strings_and_blade_escapes_saved_inputs`, `test_existing_skill_validation_rejects_non_strings_and_oversized_values` |
| [tests/Feature/SurveyPwaTest.php](../tests/Feature/SurveyPwaTest.php) | `setUp`, `tearDown`, `makeStaff`, `makeService`, `payload`, `test_crafted_submissions_are_rejected`, `invalidCombinations`, `test_submission_id_retries_are_idempotent_and_new_clients_remain_distinct`, `test_public_form_uses_database_divisions_and_only_marks_normal_pages_cacheable`, `test_validation_error_pages_are_visible_and_not_cacheable`, `test_token_refresh_uses_current_session_and_cannot_be_http_cached`, `test_csrf_middleware_rejects_old_token_and_accepts_refreshed_token`, `test_division_heads_remain_eligible_for_valid_submissions`, `test_json_submission_confirms_save_without_redirect_or_success_flash`, `test_json_submission_still_requires_current_csrf_token` |
| [tests/Feature/SyncUserAvatarsTest.php](../tests/Feature/SyncUserAvatarsTest.php) | `setUp`, `tearDown`, `employee`, `source`, `test_sync_copies_matches_replaces_existing_paths_and_preserves_sources`, `test_dry_run_does_not_copy_or_update`, `test_duplicate_employee_numbers_are_rejected`, `test_failed_copy_does_not_save_avatar_path` |
| [tests/Feature/TicketAttachmentDeletionTest.php](../tests/Feature/TicketAttachmentDeletionTest.php) | `setUp`, `ticketWithComment`, `assertFiles`, `test_delete_endpoint_removes_ticket_comments_and_files_but_preserves_other_tickets`, `test_files_are_removed_only_after_outer_transaction_commits`, `test_rollback_preserves_ticket_comments_and_all_files`, `test_failed_ticket_delete_restores_comments_and_preserves_files`, `test_cancelled_comment_delete_keeps_ticket_and_files`, `test_cancelled_ticket_delete_restores_previously_deleted_comments`, `test_standalone_comment_delete_cleans_its_files_after_commit`, `test_deleting_more_than_one_batch_removes_all_comment_files` |
| [tests/Feature/TicketAutoAssignmentQueryCountTest.php](../tests/Feature/TicketAutoAssignmentQueryCountTest.php) | `test_workload_counting_uses_one_grouped_query_not_one_per_staff` |
| [tests/Feature/TicketChatAttachmentValidationTest.php](../tests/Feature/TicketChatAttachmentValidationTest.php) | `makeTicket`, `test_disallowed_file_extension_is_rejected`, `test_allowed_file_extension_is_accepted`, `test_temporary_upload_errors_are_visible_in_the_discussion`, `test_livewire_can_remove_a_temporary_upload_by_filename`, `test_failed_livewire_storage_does_not_create_a_comment_or_clear_the_draft`, `test_plain_text_persistence_errors_are_visible_and_retain_the_draft`, `test_temporary_upload_accepts_a_file_between_twelve_and_twenty_megabytes`, `test_temporary_upload_rejects_files_over_twenty_megabytes` |
| [tests/Feature/TicketNotificationServiceQueryCountTest.php](../tests/Feature/TicketNotificationServiceQueryCountTest.php) | `test_notify_ticket_created_does_not_lazy_load_roles_per_user` |
| [tests/Feature/TicketPerformanceTest.php](../tests/Feature/TicketPerformanceTest.php) | `test_list_and_show_pages_query_counts` |
| [tests/Feature/TicketReassignmentTest.php](../tests/Feature/TicketReassignmentTest.php) | `setUp`, `test_acceptance_failure_rolls_back_ticket_request_and_notifications`, `test_new_request_reads_pending_state_again_even_when_entry_was_cached`, `makeDeptDiv`, `makeUser`, `makeTicket`, `dispatch`, `requestReassignment`, `acceptReassignment`, `rejectReassignment`, `cancelReassignment`, `test_t1_request_creates_pending_request_without_moving_ticket`, `test_t2_div_head_blocked_outside_own_division`, `test_t3_only_one_pending_request_at_a_time`, `test_t4_accept_moves_ticket_and_persists_for_standard_issue`, `test_t4_accept_moves_ticket_and_persists_for_custom_issue`, `test_t5_accept_auto_assigns_best_staff_in_new_division`, `test_t6_accept_onto_empty_division_leaves_unassigned_no_exception`, `test_t7_reject_leaves_ticket_unchanged_and_records_note`, `test_t8_only_receiving_head_can_accept_or_reject`, `test_t9_resolving_ticket_cancels_pending_request`, `test_t10_requester_can_cancel_own_pending_request`, `test_t11_deleting_ticket_with_reassignment_requests_does_not_fk_error`, `test_t12_normal_full_edit_regression`, `test_t13_request_notifies_receiving_heads_only`, `test_t14_accept_notifies_requester_and_assigned_staff`, `test_t15_reject_notifies_requester_with_note`, `test_t16_wants_notification_defaults_true_for_unset_reassignment_keys`, `test_resolve_action_still_works_via_ticket_action_dispatch`, `test_reopen_action_still_works_via_ticket_action_dispatch`, `test_quick_assign_action_still_works_via_ticket_action_dispatch`, `test_show_page_html_has_tom_select_assets_and_modal_body_move_fix`, `test_create_page_html_has_tom_select_init_with_placeholders` |
| [tests/Feature/UserAssignedTicketsTest.php](../tests/Feature/UserAssignedTicketsTest.php) | `test_assigned_tickets_returns_tickets_assigned_to_the_user` |
| [tests/JavaScript/HrChatCoreTest.cjs](../tests/JavaScript/HrChatCoreTest.cjs) | Configuration, route declarations, fixture or procedural source |
| [tests/JavaScript/MediumSecurityTest.cjs](../tests/JavaScript/MediumSecurityTest.cjs) | Configuration, route declarations, fixture or procedural source |
| [tests/JavaScript/SkillTagsTest.cjs](../tests/JavaScript/SkillTagsTest.cjs) | Configuration, route declarations, fixture or procedural source |
| [tests/JavaScript/SurveyPwaTest.cjs](../tests/JavaScript/SurveyPwaTest.cjs) | Configuration, route declarations, fixture or procedural source |
| [tests/Support/AuthorizedEngineDatabase.php](../tests/Support/AuthorizedEngineDatabase.php) | `boot`, `assertOwner` |
| [tests/Support/build-chat-browser-fixture.php](../tests/Support/build-chat-browser-fixture.php) | Configuration, route declarations, fixture or procedural source |
| [tests/Support/chat-browser-fixture.js](../tests/Support/chat-browser-fixture.js) | Configuration, route declarations, fixture or procedural source |
| [tests/Support/chat-browser-server.cjs](../tests/Support/chat-browser-server.cjs) | Configuration, route declarations, fixture or procedural source |
| [tests/Support/EngineDatabase.php](../tests/Support/EngineDatabase.php) | `configuration`, `boot`, `bootApplication`, `claimEmptyDatabase`, `assertOwner` |
| [tests/Support/EngineReportParity.php](../tests/Support/EngineReportParity.php) | `useApplication` |
| [tests/Support/EngineSubmissionRace.php](../tests/Support/EngineSubmissionRace.php) | `run` |
| [tests/Support/IsolatedDatabase.php](../tests/Support/IsolatedDatabase.php) | `prepare`, `assertSafeConfiguration` |
| [tests/Support/LegacySurveyReports.php](../tests/Support/LegacySurveyReports.php) | `__construct`, `index`, `downloadPdf`, `getReportData` |
| [tests/Support/OfflineHttpFactory.php](../tests/Support/OfflineHttpFactory.php) | `newPendingRequest` |
| [tests/Support/production-build-check.cjs](../tests/Support/production-build-check.cjs) | Configuration, route declarations, fixture or procedural source |
| [tests/Support/SqliteTestGrammar.php](../tests/Support/SqliteTestGrammar.php) | `compileSelect` |
| [tests/TestCase.php](../tests/TestCase.php) | Configuration, route declarations, fixture or procedural source |
| [tests/Unit/AnthropicServiceLoggingTest.php](../tests/Unit/AnthropicServiceLoggingTest.php) | `test_long_question_is_truncated_before_logging` |
| [tests/Unit/AttachmentStorageTest.php](../tests/Unit/AttachmentStorageTest.php) | `test_randomized_filename_is_not_the_original_name`, `test_two_calls_for_the_same_original_name_produce_different_filenames`, `test_disallowed_extension_is_preserved_as_given_not_sanitized_here` |
| [tests/Unit/DepartmentDivisionCacheTest.php](../tests/Unit/DepartmentDivisionCacheTest.php) | `test_department_all_cached_matches_a_direct_query`, `test_department_cache_invalidates_on_create`, `test_division_all_cached_matches_a_direct_query` |
| [tests/Unit/EngineDatabaseSafetyTest.php](../tests/Unit/EngineDatabaseSafetyTest.php) | `values`, `test_valid_configuration_has_no_application_url_or_prefix`, `test_unsafe_configuration_is_rejected_before_connecting`, `unsafeConfigurations`, `test_application_data_probe_requires_exact_explicit_confirmation` |
| [tests/Unit/ExampleTest.php](../tests/Unit/ExampleTest.php) | `test_example` |
| [tests/Unit/IsolatedTestInfrastructureTest.php](../tests/Unit/IsolatedTestInfrastructureTest.php) | `test_unfaked_http_cannot_reach_an_external_service`, `test_explicit_http_fakes_take_effect`, `test_sqlite_elapsed_times_match_production_semantics`, `elapsedTimes` |
| [tests/Unit/SqlDialectHelperTest.php](../tests/Unit/SqlDialectHelperTest.php) | `tearDown`, `test_diff_hours_sql_uses_datediff_on_sqlsrv`, `test_diff_hours_sql_uses_timestampdiff_on_mysql`, `test_diff_minutes_sql_uses_datediff_on_sqlsrv` |
| [tests/Unit/StatusIdsByNameTest.php](../tests/Unit/StatusIdsByNameTest.php) | `test_ids_by_name_returns_the_real_seeded_statuses`, `test_id_by_name_returns_an_int_matching_a_direct_query`, `test_id_by_name_returns_null_for_unknown_name`, `test_cache_is_invalidated_when_a_status_is_saved` |
| [tests/Unit/TestDatabaseIsolationTest.php](../tests/Unit/TestDatabaseIsolationTest.php) | `test_in_memory_database_is_allowed`, `test_application_connections_are_rejected`, `unsafeConnections` |
| [tests/Unit/TicketObserverErrorHandlingTest.php](../tests/Unit/TicketObserverErrorHandlingTest.php) | `tearDown`, `test_ticket_creation_succeeds_even_if_auto_assignment_throws` |

## Frontend inventory

- [public/arta-assets/css/arta.css](../public/arta-assets/css/arta.css)
- [public/arta-assets/js/arta.js](../public/arta-assets/js/arta.js)
- [public/js/hr-chat-core.js](../public/js/hr-chat-core.js)
- [public/manifest-survey.json](../public/manifest-survey.json)
- [public/offline-survey.html](../public/offline-survey.html)
- [public/survey-assets/css/survey.css](../public/survey-assets/css/survey.css)
- [public/survey-assets/js/survey.js](../public/survey-assets/js/survey.js)
- [public/sw-survey.js](../public/sw-survey.js)
- [resources/css/app.css](../resources/css/app.css)
- [resources/js/app.js](../resources/js/app.js)
- [resources/js/bootstrap.js](../resources/js/bootstrap.js)
- [resources/views/admin/arta_survey_reports.blade.php](../resources/views/admin/arta_survey_reports.blade.php)
- [resources/views/admin/buttons/policy_reingest_btn.blade.php](../resources/views/admin/buttons/policy_reingest_btn.blade.php)
- [resources/views/admin/buttons/policy_toggle_btn.blade.php](../resources/views/admin/buttons/policy_toggle_btn.blade.php)
- [resources/views/admin/buttons/policy_update_btn.blade.php](../resources/views/admin/buttons/policy_update_btn.blade.php)
- [resources/views/admin/buttons/policy_upload_btn.blade.php](../resources/views/admin/buttons/policy_upload_btn.blade.php)
- [resources/views/admin/hr_chat.blade.php](../resources/views/admin/hr_chat.blade.php)
- [resources/views/admin/hr_policy_update.blade.php](../resources/views/admin/hr_policy_update.blade.php)
- [resources/views/admin/hr_policy_upload.blade.php](../resources/views/admin/hr_policy_upload.blade.php)
- [resources/views/admin/notifications/index.blade.php](../resources/views/admin/notifications/index.blade.php)
- [resources/views/admin/pdf/arta_survey_pdf.blade.php](../resources/views/admin/pdf/arta_survey_pdf.blade.php)
- [resources/views/admin/pdf/reports_pdf.blade.php](../resources/views/admin/pdf/reports_pdf.blade.php)
- [resources/views/admin/pdf/survey_pdf.blade.php](../resources/views/admin/pdf/survey_pdf.blade.php)
- [resources/views/admin/reports.blade.php](../resources/views/admin/reports.blade.php)
- [resources/views/admin/survey_reports.blade.php](../resources/views/admin/survey_reports.blade.php)
- [resources/views/admin/ticket/reassignment_widget.blade.php](../resources/views/admin/ticket/reassignment_widget.blade.php)
- [resources/views/arta_form.blade.php](../resources/views/arta_form.blade.php)
- [resources/views/errors/400.blade.php](../resources/views/errors/400.blade.php)
- [resources/views/errors/401.blade.php](../resources/views/errors/401.blade.php)
- [resources/views/errors/403.blade.php](../resources/views/errors/403.blade.php)
- [resources/views/errors/404.blade.php](../resources/views/errors/404.blade.php)
- [resources/views/errors/405.blade.php](../resources/views/errors/405.blade.php)
- [resources/views/errors/408.blade.php](../resources/views/errors/408.blade.php)
- [resources/views/errors/429.blade.php](../resources/views/errors/429.blade.php)
- [resources/views/errors/500.blade.php](../resources/views/errors/500.blade.php)
- [resources/views/errors/503.blade.php](../resources/views/errors/503.blade.php)
- [resources/views/errors/layout.blade.php](../resources/views/errors/layout.blade.php)
- [resources/views/livewire/ticket-chat.blade.php](../resources/views/livewire/ticket-chat.blade.php)
- [resources/views/submit-ticket.blade.php](../resources/views/submit-ticket.blade.php)
- [resources/views/survey_form.blade.php](../resources/views/survey_form.blade.php)
- [resources/views/vendor/backpack/base/auth/login.blade.php](../resources/views/vendor/backpack/base/auth/login.blade.php)
- [resources/views/vendor/backpack/base/auth/register.blade.php](../resources/views/vendor/backpack/base/auth/register.blade.php)
- [resources/views/vendor/backpack/base/dashboard.blade.php](../resources/views/vendor/backpack/base/dashboard.blade.php)
- [resources/views/vendor/backpack/base/inc/footer.blade.php](../resources/views/vendor/backpack/base/inc/footer.blade.php)
- [resources/views/vendor/backpack/base/inc/head.blade.php](../resources/views/vendor/backpack/base/inc/head.blade.php)
- [resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php](../resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php)
- [resources/views/vendor/backpack/base/inc/sidebar_content.blade.php](../resources/views/vendor/backpack/base/inc/sidebar_content.blade.php)
- [resources/views/vendor/backpack/base/inc/topbar_left_content.blade.php](../resources/views/vendor/backpack/base/inc/topbar_left_content.blade.php)
- [resources/views/vendor/backpack/base/inc/topbar_right_content.blade.php](../resources/views/vendor/backpack/base/inc/topbar_right_content.blade.php)
- [resources/views/vendor/backpack/base/layouts/top_left.blade.php](../resources/views/vendor/backpack/base/layouts/top_left.blade.php)
- [resources/views/vendor/backpack/base/my_account.blade.php](../resources/views/vendor/backpack/base/my_account.blade.php)
- [resources/views/vendor/backpack/crud/edit.blade.php](../resources/views/vendor/backpack/crud/edit.blade.php)
- [resources/views/vendor/backpack/crud/fields/iconpicker.blade.php](../resources/views/vendor/backpack/crud/fields/iconpicker.blade.php)
- [resources/views/vendor/backpack/crud/fields/modern_upload.blade.php](../resources/views/vendor/backpack/crud/fields/modern_upload.blade.php)
- [resources/views/vendor/backpack/crud/show.blade.php](../resources/views/vendor/backpack/crud/show.blade.php)
- [resources/views/welcome.blade.php](../resources/views/welcome.blade.php)

## Dependency declarations

| Package | Constraint | Locked version |
| --- | --- | --- |
| `php` (require) | `^7.3|^8.0` | `runtime constraint` |
| `backpack/crud` (require) | `^5.6` | `5.6.1` |
| `backpack/permissionmanager` (require) | `^6.0` | `6.0.17` |
| `barryvdh/laravel-dompdf` (require) | `^2.2` | `v2.2.0` |
| `fruitcake/laravel-cors` (require) | `^2.0` | `v2.2.0` |
| `guzzlehttp/guzzle` (require) | `^7.0.1` | `7.10.0` |
| `laravel/framework` (require) | `^8.75` | `8.x-dev` |
| `laravel/sanctum` (require) | `^2.11` | `v2.15.1` |
| `laravel/tinker` (require) | `^2.5` | `v2.11.1` |
| `livewire/livewire` (require) | `^2.12` | `v2.12.8` |
| `simplesoftwareio/simple-qrcode` (require) | `^4.2` | `4.2.0` |
| `smalot/pdfparser` (require) | `^2.12` | `v2.12.5` |
| `spatie/laravel-permission` (require) | `^5.11` | `5.11.1` |
| `symfony/process` (require) | `^5.4` | `v5.4.51` |
| `backpack/generators` (require-dev) | `^3.3` | `v3.3.17` |
| `facade/ignition` (require-dev) | `^2.5` | `2.17.7` |
| `fakerphp/faker` (require-dev) | `^1.9.1` | `v1.24.1` |
| `laravel/sail` (require-dev) | `^1.0.1` | `v1.19.0` |
| `mockery/mockery` (require-dev) | `^1.4.4` | `1.6.12` |
| `nunomaduro/collision` (require-dev) | `^5.10` | `v5.11.0` |
| `phpunit/phpunit` (require-dev) | `^9.5.10` | `9.6.34` |

## Existing documentation reviewed

Historical pass counts are not current-run results. Existing supplements are retained; see DOCUMENTATION-GAPS.md for discrepancies.

- [README.md](../README.md): 92 lines; headings: LARAVEL HELPDESK (EMPLOYEE CARE) WITH BACKPACK; System Requirements; Installation; Tools needed to Enable OCR Processing of PDF Files for the HR Policy Chatbot.; Login using Admin account; License
- [AUDIT_REPORT.md](../AUDIT_REPORT.md): 622 lines; headings: EmployeeCare — Full Codebase Audit; Summary Table; PART 1 — SECURITY; PART 2 — PERFORMANCE / OPTIMIZATION; PART 3 — CODE QUALITY / MAINTAINABILITY; Appendix A — `composer audit` full output (verbatim); Top 3 Recommended Next Actions
- [docs/DEPLOYMENT_READINESS.md](../docs/DEPLOYMENT_READINESS.md): 123 lines; headings: Deployment and attachment operations; Evidence and prerequisites; Ordered release checklist (live actions require authorization); Isolated deployed-engine suite; Attachment reconciliation; Local verification; Changed files
- [docs/HR_AI_CHATBOT.md](../docs/HR_AI_CHATBOT.md): 56 lines; headings: HR Assistant review and provider switching; Confirmed problems fixed; Minimal future Gemini switch; Executed verification and reproduction; Changed files in this review; Remaining limits and operational work
- [docs/MEDIUM_FIXES.md](../docs/MEDIUM_FIXES.md): 154 lines; headings: Requested medium finding fixes; Finding outcomes; F1 original implementation and parity; H3 remaining compatibility limit; Verification; Deployment; Every changed file
- [docs/POLICY_INGESTION_QUEUE.md](../docs/POLICY_INGESTION_QUEUE.md): 74 lines; headings: Dedicated policy ingestion queue; Release prerequisites and procedure; Exact worker commands; Lifecycle and recovery; Changed files and verification
- [docs/superpowers/plans/2026-07-22-reports-analytics-widgets.md](../docs/superpowers/plans/2026-07-22-reports-analytics-widgets.md): 1716 lines; headings: Reports Analytics Widgets + MSSQL SQL Cleanup Implementation Plan; Global Constraints; Post-Plan: Final Report Checklist
- [docs/superpowers/plans/2026-07-22-reports-page-revisions.md](../docs/superpowers/plans/2026-07-22-reports-page-revisions.md): 1451 lines; headings: Reports Page Revisions Implementation Plan; Global Constraints; Post-Plan: Final Report Checklist
- [docs/superpowers/plans/2026-07-23-audit-fixes.md](../docs/superpowers/plans/2026-07-23-audit-fixes.md): 2931 lines; headings: Audit Findings Remediation Implementation Plan; Global Constraints

## Additional operator text notes

The following .txt files were inspected as operator notes, not registered PHP commands. Their sample filenames/identities are deliberately not copied. Current PHP command definitions and deployment guidance take precedence.

- [PolicyDocumentJobCommand.txt](../app/Console/Commands/PolicyDocumentJobCommand.txt)
- [SyncUserAvatar.txt](../app/Console/Commands/SyncUserAvatar.txt)
