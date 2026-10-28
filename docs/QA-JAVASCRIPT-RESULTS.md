# JavaScript Verification Results

Executed 2026-10-27 using the existing Node test runner. No PHP, browser, external-provider or database-engine certification is implied.

Command: `node --test tests/JavaScript/HrChatCoreTest.cjs tests/JavaScript/MediumSecurityTest.cjs tests/JavaScript/SkillTagsTest.cjs tests/JavaScript/SurveyPwaTest.cjs`

Exit code: 0

```text
✔ send disables repeated requests and includes JSON/session/subdirectory contract (7.1089ms)
✔ HTTP 401 never renders a sourced success or feedback (1.6927ms)
✔ HTTP 419 never renders a sourced success or feedback (1.6318ms)
✔ HTTP 422 never renders a sourced success or feedback (1.8375ms)
✔ HTTP 429 never renders a sourced success or feedback (0.9464ms)
✔ HTTP 500 never renders a sourced success or feedback (0.8152ms)
✔ HTTP 503 never renders a sourced success or feedback (0.9358ms)
✔ network, malformed JSON and empty answer errors restore controls (3.6015ms)
✔ new conversation discards old slow answer and its returned conversation identity (1.2395ms)
✔ clear view stays local and preserves the conversation for the next question (1.3077ms)
✔ a hung request times out, aborts transport and unlocks sending without retrying (7.6059ms)
✔ out-of-order transcript loads cannot overwrite the newest selection (2.2038ms)
✔ failed transcript fetch unlocks sending and retains prior identity (0.9527ms)
✔ failed feedback is retryable and never shows thanks (0.9372ms)
✔ successful clear resets conversation identity; failed clear retains it (1.7387ms)
✔ C10 uploaded filenames remain literal text in the actual preview function (3.1443ms)
✔ H3 frontend lockfile matches all direct dependency requirements (35.3262ms)
✔ saved quotes, selector syntax, HTML and Unicode render as literal text and remove safely (2.9204ms)
✔ adding, case-insensitive duplicate detection and hidden form values remain functional (2.7827ms)
✔ empty-input Backspace removes the last tag and container click focuses the input (0.9855ms)
✔ install precaches real shell resources, not the dynamic document or videos, and waits for safe activation (113.4764ms)
✔ activation removes obsolete survey caches only (1.5144ms)
✔ normal network GET returns current HTML but persists no CSRF token or cookie (3.5631ms)
✔ query states, flashed success/errors, non-HTML, redirects and failures never replace the normal document (6.4276ms)
✔ offline navigation uses normal form or the installed offline fallback (11.244ms)
✔ POSTs, token refresh, admin pages, unrelated assets, third parties and Range requests bypass caching (1.179ms)
✔ local avatars cache on success; 404s, redirects and partial responses do not (1.4978ms)
✔ runtime cache failure never replaces a successful network response with offline HTML (1.0901ms)
✔ offline submission preserves answers and offers manual retry without any network or POST (2.3603ms)
✔ cached form submits only after current token arrives; failure permits manual retry (3.174ms)
✔ incomplete carousel cannot submit, and offline thank-you URL cannot show successful completion (2.5393ms)
✔ worker activation waits until welcome or completed QR state, never during submission (0.82ms)
✔ first controllerchange warms the normal document without reloading; thank-you state never warms it (1.5978ms)
✔ manifest icons exist with the advertised PNG dimensions and scope stays /survey (0.5281ms)
✔ visible install button explains unavailable prompts instead of silently doing nothing (2.1411ms)
✔ in-page install controls trigger the native prompt once, handle dismissal, and hide after installation (1.5232ms)
✔ failed native install prompt leaves usable controls and an explanatory message (0.8199ms)
✔ fullscreen button enters the entire document, exits, and follows browser-initiated exit (1.2917ms)
✔ fullscreen unavailable or forbidden explains the limitation without changing answers (1.7046ms)
✔ fullscreen rejection reenables retry and ignores double taps while pending (0.8236ms)
✔ fullscreen uses legacy WebKit methods when standard methods are unavailable (0.7938ms)
✔ confirmed save, timed reset, and next client all stay in the fullscreen document (1.6721ms)
✔ Rate Again clears completion timer without navigation or stale success state (1.503ms)
✔ failed POST never shows success or resets answers and permits manual retry (6.1767ms)
✔ pending save prevents double submission and reset, and times out without claiming success (16.0574ms)
✔ compact tools stay collapsed initially and close after actions, outside taps, Escape, and reset (1.7891ms)
✔ tools markup uses a closed native disclosure with an accessible icon-only opener (0.3655ms)
✔ E5 manual retries retain the submission UUID and the next client receives a new UUID (2.0963ms)
ℹ tests 48
ℹ suites 0
ℹ pass 48
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
ℹ todo 0
ℹ duration_ms 364.6285

```
