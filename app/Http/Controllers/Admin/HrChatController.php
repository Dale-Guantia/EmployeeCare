<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrChatLog;
use App\Services\PolicyRetriever;
use App\Services\AnthropicService;
// use App\Services\GeminiService;
// use App\Services\GPTService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        $request->validate(['question' => 'required|string|max:600']);

        // FIX: Wrap the entire ask flow in try/catch so any unexpected exception
        // returns a clean JSON error instead of a raw 500 HTML page.
        // This prevents the chat UI from breaking when errors occur.
        try {
            $question = trim($request->input('question'));

            $chunks = $this->retriever->retrieve($question);

            // Pass context to AI — if no chunks found, send an empty context string
            // so the system prompt's fallback instructions still apply (web search, etc.)
            $policyContext = $chunks->isEmpty()
                ? 'No matching policy documents were found for this question.'
                : $this->retriever->formatForPrompt($chunks);

            $answer = $this->ai->ask($question, $policyContext);

            // Build source citations only from chunks that were actually used
            $sources = $chunks->isEmpty() ? [] : $chunks->map(function ($c) {
                $title   = $c->document->title ?? 'Unknown Document';
                $section = $c->section_title ? ' — ' . $c->section_title : '';
                return $title . $section;
            })->unique()->values()->all();

            $log = HrChatLog::create([
                'user_id'           => backpack_user()->id,
                'question'          => $question,
                'answer'            => $answer,
                'matched_chunk_ids' => $chunks->pluck('id')->all(),
            ]);

            return response()->json([
                'answer'  => $answer,
                'sources' => $sources,
                'log_id'  => $log->id,
            ]);

        } catch (\Throwable $e) {
            \Log::error('[HrChatController] ask() failed: ' . $e->getMessage(), [
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
            ->get(['id', 'question', 'answer', 'created_at']);

        return response()->json($logs);
    }

    public function clearHistory(): JsonResponse
    {
        // Scoped to current user — only deletes that user's own logs
        HrChatLog::where('user_id', backpack_user()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
