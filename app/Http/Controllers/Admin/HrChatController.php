<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrChatLog;
use App\Models\HrConversation;
use App\Models\HrPolicyChunk;
use App\Services\PolicyRetriever;
use App\Services\AnthropicService;
// use App\Services\GeminiService;
// use App\Services\GPTService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HrChatController extends Controller
{
    protected $retriever;
    protected $ai;

    public function __construct(PolicyRetriever $retriever, AnthropicService $ai)
    {
        $this->retriever = $retriever;
        $this->ai        = $ai;
    }

    public function index()
    {
        // Top 5 most frequently asked questions — shown as suggestions in the sidebar
        $topPrompts = HrChatLog::select('question')
            ->selectRaw('COUNT(*) as total_count')
            ->groupBy('question')
            ->orderByDesc('total_count')
            ->limit(5)
            ->pluck('question')
            ->all();

        return view('admin.hr_chat', compact('topPrompts'));
    }

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question'        => 'required|string|max:600',
            'conversation_id' => 'nullable|integer',
        ]);

        // FIX: Wrap the entire ask flow in try/catch so any unexpected exception
        // returns a clean JSON error instead of a raw 500 HTML page.
        // This prevents the chat UI from breaking when errors occur.
        try {
            $question = trim($request->input('question'));

            // Greet-back path: a bare "hello"/"hi"/"kumusta" has no keywords for
            // PolicyRetriever to match, so without this it would otherwise fall
            // through to the "couldn't find this in our policy documents" miss
            // message — a wrong and unfriendly reply to a plain greeting.
            // Not logged (log_id: null) so it doesn't clutter recent prompts and
            // doesn't render the not-grounded warning or feedback thumbs on what
            // is just small talk, not a policy answer.
            if ($this->isGreeting($question)) {
                return response()->json([
                    'answer'  => $this->greetingReply(),
                    'sources' => [],
                    'log_id'  => null,
                ]);
            }

            $conversation = $this->resolveConversation(
                $request->input('conversation_id') ? (int) $request->input('conversation_id') : null,
                $question
            );

            $recentQuestions = HrChatLog::where('conversation_id', $conversation->id)
                ->latest('id')
                ->limit(2)
                ->pluck('question')
                ->all();

            // Prepend recent questions so follow-ups like "ilang araw pwede mag apply?"
            // still carry the subject ("wellness leave") into keyword extraction.
            // This affects RETRIEVAL ONLY — $question stays untouched for the model
            // and for the audit log.
            $retrievalQuery = trim(implode(' ', array_reverse($recentQuestions)) . ' ' . $question);

            $chunks = $this->retriever->retrieve($retrievalQuery);

            // Strict grounding: if retrieval found no matching policy chunks, do not
            // call the AI at all — return a fixed "not found" answer instead of risking
            // an ungrounded, invented response. This is the retrieval-miss path.
            if ($chunks->isEmpty()) {
                $answer = "I couldn't find this in our current HR policy documents. Please contact HRDO directly for assistance.\n\n"
                    . "*(Hindi ko ito nakita sa aming kasalukuyang mga dokumento ng patakaran ng HR. Mangyaring makipag-ugnayan nang direkta sa HRDO para sa tulong.)*";

                $log = HrChatLog::create([
                    'user_id'           => backpack_user()->id,
                    'conversation_id'   => $conversation->id,
                    'question'          => $question,
                    'answer'            => $answer,
                    'matched_chunk_ids' => [],
                ]);

                $this->touchConversation($conversation, $question);

                return response()->json([
                    'answer'          => $answer,
                    'sources'         => [],
                    'log_id'          => $log->id,
                    'conversation_id' => $conversation->id,
                ]);
            }

            $policyContext = $this->retriever->formatForPrompt($chunks);

            // Recent exchanges for this conversation, oldest first, flattened into
            // strictly alternating user/assistant messages so the model has
            // short-term memory instead of treating every question in isolation.
            // Scoped to the current conversation so a different conversation's
            // turns never bleed in as false context.
            $history = HrChatLog::where('conversation_id', $conversation->id)
                ->latest('id')
                ->limit(3)
                ->get(['question', 'answer'])
                ->reverse()
                ->flatMap(function ($log) {
                    return [
                        ['role' => 'user',      'content' => $log->question],
                        ['role' => 'assistant', 'content' => $log->answer],
                    ];
                })
                ->values()
                ->all();

            $answer = $this->ai->ask($question, $policyContext, $history);

            // Build source citations from the chunks that were actually used
            $sources = $chunks->map(function ($c) {
                $title   = $c->document->title ?? 'Unknown Document';
                $section = $c->section_title ? ' — ' . $c->section_title : '';
                return $title . $section;
            })->unique()->values()->all();

            $log = HrChatLog::create([
                'user_id'           => backpack_user()->id,
                'conversation_id'   => $conversation->id,
                'question'          => $question,
                'answer'            => $answer,
                'matched_chunk_ids' => $chunks->pluck('id')->all(),
            ]);

            $this->touchConversation($conversation, $question);

            return response()->json([
                'answer'          => $answer,
                'sources'         => $sources,
                'log_id'          => $log->id,
                'conversation_id' => $conversation->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('[HrChatController] ask() failed: ' . $e->getMessage(), [
                'user_id'  => backpack_user()->id ?? null,
                'question' => $request->input('question'),
            ]);

            return response()->json([
                'answer'  => 'A server error occurred while processing your question. Please try again.',
                'sources' => [],
                'log_id'  => null,
            ], 500);
        }
    }

    // Matches only when the *entire* message is a greeting (punctuation/case
    // insensitive) — "hello" greets back, but "hello, what is the leave
    // policy?" still falls through to retrieval so the real question gets
    // answered.
    private function isGreeting(string $question): bool
    {
        $normalized = strtolower(trim($question));
        $normalized = preg_replace('/[^\p{L}\s]/u', '', $normalized);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        $greetings = [
            'hi', 'hello', 'hey', 'hiya', 'yo',
            'good morning', 'good afternoon', 'good evening', 'good day',
            'kumusta', 'kamusta', 'magandang umaga', 'magandang tanghali',
            'magandang hapon', 'magandang gabi',
        ];

        return in_array($normalized, $greetings, true);
    }

    // Resolves the conversation this turn belongs to. An explicit, owned
    // conversation id is reused as-is; a missing or unknown/not-owned id
    // starts a brand-new conversation rather than leaking another user's
    // context or erroring out on a stale client-side id.
    private function resolveConversation(?int $id, string $firstQuestion): HrConversation
    {
        if ($id) {
            $conv = HrConversation::forUser(backpack_user()->id)->find($id);
            if ($conv) {
                return $conv;
            }
        }

        return HrConversation::create([
            'user_id'         => backpack_user()->id,
            'title'           => HrConversation::titleFrom($firstQuestion),
            'last_message_at' => now(),
        ]);
    }

    // Bumps recency (so the sidebar re-sorts) and lazily titles a conversation
    // that was created without one yet (defensive — resolveConversation()
    // always sets a title on creation, but this keeps the two in sync if that
    // ever changes).
    private function touchConversation(HrConversation $conversation, string $question): void
    {
        $conversation->last_message_at = now();
        if (!$conversation->title) {
            $conversation->title = HrConversation::titleFrom($question);
        }
        $conversation->save();
    }

    private function greetingReply(): string
    {
        return "Hello! 👋 I'm your HRDO Policy Assistant. Ask me anything about leave, "
            . "benefits, conduct, performance, or other HR policies.\n\n"
            . "*(Kumusta! Ako ang inyong HRDO Policy Assistant. Magtanong tungkol sa "
            . "leave, benepisyo, asal, performance, o iba pang patakaran ng HR.)*";
    }

    public function feedback(Request $request): JsonResponse
    {
        $request->validate([
            'log_id'   => 'required|integer',
            'feedback' => 'required|in:-1,1',
        ]);

        // FIX: Verify the log belongs to the current user before updating.
        // Without the user_id check, any logged-in user could change anyone's feedback
        // by guessing a log_id — a horizontal privilege escalation vulnerability.
        $updated = HrChatLog::where('id', (int) $request->input('log_id'))
            ->where('user_id', backpack_user()->id)
            ->update(['feedback' => (int) $request->input('feedback')]);

        return response()->json(['ok' => $updated > 0]);
    }

    public function history(): JsonResponse
    {
        // FIX: Scoped strictly to the authenticated user — no risk of data leakage.
        // Added index hint: the query benefits from an index on (user_id, created_at).
        $logs = HrChatLog::where('user_id', backpack_user()->id)
            ->latest()
            ->limit(20)
            ->get(['id', 'question', 'answer', 'matched_chunk_ids', 'created_at']);

        // Resolve source citations for all logs in one batch query (instead of
        // N+1 per-log lookups), mirroring the title + section format ask() uses.
        $chunkIds = $logs->pluck('matched_chunk_ids')->flatten()->filter()->unique()->values();
        $chunksById = HrPolicyChunk::with('document')
            ->whereIn('id', $chunkIds)
            ->get()
            ->keyBy('id');

        $logs->each(function ($log) use ($chunksById) {
            $log->sources = collect($log->matched_chunk_ids ?? [])
                ->map(function ($chunkId) use ($chunksById) {
                    $chunk = $chunksById->get($chunkId);
                    if (!$chunk) {
                        return null;
                    }
                    $title   = $chunk->document->title ?? 'Unknown Document';
                    $section = $chunk->section_title ? ' — ' . $chunk->section_title : '';
                    return $title . $section;
                })
                ->filter()
                ->unique()
                ->values();
            unset($log->matched_chunk_ids);
        });

        return response()->json($logs);
    }

    public function clearHistory(): JsonResponse
    {
        // Scoped to current user — deletes all of this user's conversations
        // and their logs. Other users' conversations are untouched.
        $ids = HrConversation::forUser(backpack_user()->id)->pluck('id');
        HrChatLog::whereIn('conversation_id', $ids)->delete();
        HrConversation::whereIn('id', $ids)->delete();

        return response()->json(['ok' => true]);
    }

    // GET — sidebar list.
    public function conversations(): JsonResponse
    {
        $items = HrConversation::forUser(backpack_user()->id)
            ->sidebarOrder()
            ->limit(100)
            ->get(['id', 'title', 'is_starred', 'last_message_at']);

        return response()->json($items);
    }

    // GET — full transcript of one conversation. 404s on an unknown or
    // not-owned id rather than leaking whether another user's id exists.
    public function conversationMessages(int $id): JsonResponse
    {
        $conv = HrConversation::forUser(backpack_user()->id)->findOrFail($id);

        $messages = $conv->logs()
            ->get(['id', 'question', 'answer', 'matched_chunk_ids', 'feedback', 'created_at']);

        $chunkIds = $messages->pluck('matched_chunk_ids')->flatten()->filter()->unique()->values();
        $chunksById = HrPolicyChunk::with('document')
            ->whereIn('id', $chunkIds)
            ->get()
            ->keyBy('id');

        $messages->each(function ($log) use ($chunksById) {
            $log->sources = collect($log->matched_chunk_ids ?? [])
                ->map(function ($chunkId) use ($chunksById) {
                    $chunk = $chunksById->get($chunkId);
                    if (!$chunk) {
                        return null;
                    }
                    $title   = $chunk->document->title ?? 'Unknown Document';
                    $section = $chunk->section_title ? ' — ' . $chunk->section_title : '';
                    return $title . $section;
                })
                ->filter()
                ->unique()
                ->values();
            unset($log->matched_chunk_ids);
        });

        return response()->json([
            'id'       => $conv->id,
            'title'    => $conv->title,
            'starred'  => $conv->is_starred,
            'messages' => $messages,
        ]);
    }

    // PATCH — rename and/or star. 404s on an unknown or not-owned id.
    public function updateConversation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title'      => 'sometimes|nullable|string|max:120',
            'is_starred' => 'sometimes|boolean',
        ]);

        $conv = HrConversation::forUser(backpack_user()->id)->findOrFail($id);

        if ($request->has('title')) {
            $conv->title = trim((string) $request->input('title')) ?: $conv->title;
        }
        if ($request->has('is_starred')) {
            $conv->is_starred = (bool) $request->input('is_starred');
        }
        $conv->save();

        return response()->json([
            'ok'      => true,
            'id'      => $conv->id,
            'title'   => $conv->title,
            'starred' => $conv->is_starred,
        ]);
    }

    // DELETE — remove conversation and its logs. 404s on an unknown or
    // not-owned id. No DB cascade by design — children are deleted explicitly.
    public function deleteConversation(int $id): JsonResponse
    {
        $conv = HrConversation::forUser(backpack_user()->id)->findOrFail($id);
        $conv->logs()->delete();
        $conv->delete();

        return response()->json(['ok' => true]);
    }
}
