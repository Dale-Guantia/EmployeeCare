# HR policy/process helper evaluation

This implements the evaluation foundation for implementation stage 1. It does not change the chatbot, its prompts, retrieval rules, policy activation, or application data.

## Inputs and review status

- [Questions and draft reference answers](../../tests/Fixtures/hr-chat-evaluation/questions.json): 45 independent cases, with expected behavior, essential facts, prohibited claims, source quotations, physical PDF pages, user-stated circumstances, and prior-question sequences.
- [Source manifest](../../tests/Fixtures/hr-chat-evaluation/sources.json): all 12 supplied filenames under `policy documents`, grouped into 10 byte-distinct documents using SHA-256. Identical aliases are indexed once. Similar titles with different bytes remain separate; no policy precedence is inferred.
- Every reference answer is **pending HR review**. Source-linked engineering drafts are not an HR determination of current eligibility, legal effect, or document authority. The proposed acceptance criteria have no fabricated approval or numeric threshold.

Cases cover wellness leave, general leave procedures, maternity/paternity/adoption, forms, conduct, appointments, exceptions, English/Filipino/Taglish, topic changes, long conversations, conflicting editions, clarification, unsupported requests, and instruction overrides. Not every provision in every supplied document is covered.

## Reproduce the offline baseline

From the repository root:

```powershell
php tests/Support/run-hr-chat-evaluation.php --output=storage/app/hr-chat-evaluation/baseline-next.json
```

Use a new output filename for each run. Existing reports are never overwritten. `--case=HR-EVAL-001` limits the run to one case. `--rebuild-corpus` forces extraction again.

The runner forces `APP_ENV=testing` and SQLite `:memory:`, rejects cached application configuration, invokes the existing isolated-test bootstrap, blocks external HTTP, and uses its own temporary storage directory. It never reads employee conversations, creates application chat logs, runs a persistent worker, or connects to the application database. Temporary storage is removed at completion; extraction caches and reports remain under the Git-ignored `storage/app/hr-chat-evaluation` directory. The original PDFs are read and copied, never edited or moved.

The first run uses the actual `PolicyIngestService` and `OcrService`. Scanned pages require the existing configured Poppler/Tesseract tools and language packs; no new dependency was added. Each distinct source is processed synchronously in the disposable fixture database. Failed or `needs_review` documents remain visible in the corpus inventory and remain excluded from retrieval. A source upload/processing failure is not silently treated as an empty policy library.

Later runs reuse an extraction cache identified by the source manifest, ingestion/OCR code hashes, and OCR configuration. Every source PDF and duplicate alias is hash-checked before reuse. After changing tools or language packs, force a rebuild. Missing or changed files require deliberate manifest/reference review; they cannot silently redefine the baseline.

Retrieval calls the actual `PolicyRetriever`, including its five-passage/context limits. Prior questions replay the controller's six-exchange source-memory logic, independently for each case. Offline runs do not invent prior assistant answers or evaluate natural-language generation. This is a service evaluation, not an HTTP/controller/browser acceptance test.

## What the report measures

The report captures source and dataset hashes, corpus statuses, code hashes, runtime versions, selected passages, and results for each case. Evidence passages are stored once and referenced by document key and chunk index.

Each evidence group specifies one required provision. Alternatives within a group allow equivalent supplied sources. A group is found only when a selected passage belongs to an allowed document **and contains the normalized reference quotation**. All groups must be found for a case to be complete. This is an evidence-location proxy; it does not establish complete procedural coverage, correct interpretation, or citation/page accuracy. Normalization ignores punctuation/case/whitespace, not missing or misspelled words. Ingestion defects can therefore contribute to a miss.

Cases without source requirements receive `null`, not an automatic retrieval pass. Draft, HR-approved, and rejected references are separated. No-source responses are labeled `application_retrieval_miss`; they are the controller's deterministic fallback, not a generated answer. Other offline answers stay `null`. Every answer-review field starts pending. An answer or source match never grants release approval.

The baseline corpus is an unverified fixture library containing all successfully processed supplied documents. Its simultaneous active documents and missing applicability configuration do not certify the live library or resolve old/new policy conflicts.

## HR review procedure

1. Assign an HR owner. Review each case against the original PDFs, including exceptions and amendments. PDF references are physical pages starting at 1; chunk page metadata identifies only the starting page.
2. Correct the question, essential facts, source references, expected behavior, or prohibited claims where necessary. User circumstances are synthetic/user-stated and do not represent actual personnel records.
3. Set each case's `hr_review.status` to `approved` or `rejected`, with an identified `reviewer`, `reviewed_at`, and notes. No software action represents HR sign-off. Reviewer identity is a manual attestation, not authenticated approval.
4. Decide and document the acceptance criteria and any numeric thresholds in `acceptance`. Proposed critical checks are: no unsupported requirements/entitlements/approval claims, preserved exceptions, evidence supporting policy claims, appropriate clarification/referral, and equivalent meaning across languages.
5. Freeze the approved dataset and manifest in the normal code-review process, then capture a fresh baseline. Changing the files changes their hashes; keep earlier reports for traceability.

## Optional controlled answer capture

The default run makes **zero AI calls**. The live option is provided for a later authorized, budgeted evaluation and was not used for the initial baseline. It sends selected policy excerpts and synthetic conversation turns to the configured Anthropic endpoint and can incur charges.

After reference review, an operator can capture one case using the existing provider configuration:

```powershell
php tests/Support/run-hr-chat-evaluation.php --live-answers --case=HR-EVAL-001 --max-api-calls=1 --output=storage/app/hr-chat-evaluation/answers-001.json
```

For a case with prior questions, the explicit budget must cover the current question plus every prior question, up to 20 attempts. For example, case 008 needs a budget of 9. Retrieval misses do not call the provider. The provider does not automatically retry. Verify a model available to the configured account before running; model availability and credentials were not checked by the offline baseline. Do not put keys in commands, fixtures, reports, or version control.

Capture uses `AnthropicService`/`PolicyChatPrompt`, selected current policy context, and at most three actual prior answer exchanges. Prior provider failure stops that case's generation rather than fabricating history. Reports contain actual answers, prior answer context, model identity, API-attempt count, and sanitized failure states. They contain no credentials. HR review is still pending after successful generation.

## Review and score captured answers

Copy a capture report to a separately named review file. For each captured answer, inspect the question's rubric and original provisions, then fill `answer_review`:

- `status`: `reviewed`; `reviewer` and `reviewed_at`: reviewer attestation.
- `behavior_correct`, `facts_correct`, `procedure_complete`, `citations_support_claims`, `language_preserves_conditions`: booleans. Mark an inapplicable dimension true only after confirming no violation; explain that judgment in `notes`.
- `unsupported_claims`: an array of unsupported claims, or `[]` when none were found.
- `critical_error`: boolean; record the problem and evidence in `notes`.

Do not fill a missing answer with an invented response. Do not change hashes or captured outputs to make a report match a revised dataset. Re-capture when the reference dataset changes. The review scorer counts signed reviews only, rejects incomplete reviews, and excludes unapproved reference answers from approved accuracy metrics:

```powershell
php tests/Support/run-hr-chat-evaluation.php --score=storage/app/hr-chat-evaluation/answers-001-reviewed.json
```

It reports each review dimension, complete passing cases, unsupported claims, and critical failures. It never makes the HR release decision automatically. These are manual judgments; the scorer does not independently prove factual grounding.

## Document-only policy helper (2026-11-12)

The intended flow is: receive a policy question, clarify a short ambiguous request when necessary, search available active PDF passages, answer only from matching document text with citations, or give the fixed HRDO-office referral when retrieval finds no passage. If retrieved excerpts are related but do not actually answer the question, the model is instructed to say so and refer to HRDO; this judgment still needs answer-quality review.

The bot searches active passages by PDF text, section, and document title. It ranks a bounded candidate set, includes an adjacent passage when space permits, and handles a small explicit English/Filipino search vocabulary. Only source title, detected section, PDF page, and extracted PDF text enter the policy context. Older chat logs still use live chunk references; their old citations cannot be recreated after a past replacement.

The upload and update screens accept title, category, effective date, and PDF. The six additional metadata inputs introduced on November 11 were removed after the intended replacement workflow was clarified. Their database columns remain dormant for compatibility because the configured SQL Server connection could not confirm whether the migration has already run. They are not accepted by the forms, used for document filtering, or sent as policy evidence. Availability is determined by the existing active and ingestion status checks. The effective date remains an administrative field; answers must derive dates and applicability from the PDF text.

New answers store immutable citation labels and excerpts with the chat log. Their source pills link to the protected PDF and page when the exact file hash is still current; replacing or deleting the file invalidates that link, while the citation snapshot remains readable. The source excerpt is a location aid, not a proof that every answer sentence is supported. The bot asks for a named topic on a short ambiguous process request and keeps the latest explicit topic in the user's conversation state. A directly stated employment type can be carried as **unverified user context**; other circumstances remain in the transcript and may fall out of the short model history. Starting a new conversation or typing `start over` clears the state.

The live prompt limits the assistant to guidance and asks it to disclose missing procedural parts. If the available excerpts conflict or do not establish an answer, it should explain the uncertainty and refer the employee to HRDO. The prior metadata-based review banner was removed. No employee-record integration or HR action execution was added.

Before release, HR must review and sign off the draft evaluation references and inspect generated answers for unsupported claims. Replacements should be complete current PDFs; an amendment-only PDF may omit unchanged provisions from the old document. Engineering must run the new migration in a controlled environment, re-run the offline evaluation against the same PDFs, then perform browser, OCR/queue, and isolated SQL Server checks. The offline report remains a passage-location proxy, not an accuracy score.

The November 12 report is `storage/app/hr-chat-evaluation/baseline-2026-11-12.json` (Git ignored): 33 of 42 source-scored draft cases located every draft evidence group; 9 remain incomplete. Three cases are behavior-only, and no generated answer or HR approval was scored. The initial comparison baseline below had 26 of 42 complete. The remaining cases should be inspected against the PDF pages before changing retrieval again, because some misses may be extraction wording or reference-quotation mismatch.

## Initial evidence and limits

The initial offline run and its limitations are recorded in [baseline-2026-11-10.json](baseline-2026-11-10.json). All 10 distinct PDFs processed as active **in the disposable fixture database**, including the three scanned PDFs. They produced 440 chunks. All required candidate evidence groups were found for 26 of 42 source-scored cases; 16 were incomplete, and 3 behavior-only cases require human review. This is not a 26/42 answer-accuracy score.

Observed missing evidence includes language follow-ups, filing qualifiers, form instructions, paternity/adoption conditions, SALN exceptions, probation exceptions, cross-policy comparisons, historical maternity wording, and effectivity. These are baseline observations to investigate in later stages, not fixes made here. A miss may reflect ranking, extraction, or reference-quotation matching and needs passage review before root-cause attribution.

No HR reference/acceptance approval, paid API call, generated-answer quality review, production-data operation, deployment, browser check, or SQL Server certification was performed for this baseline. Visual inspection sampled the wellness EO, maternity amendment, and leave-form instructions; it did not certify every OCR page.

Run the new harness tests with:

```powershell
php vendor/phpunit/phpunit/phpunit --filter HrChatEvaluation --do-not-cache-result
```
