<?php
namespace App\Services;

use App\Models\HrPolicyChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PolicyRetriever
{
    public function retrieveForConversation(string $question, array $recentQuestions): Collection
    {
        // Only expand subject-free follow-ups. Explicit new subjects must stand alone.
        if (!preg_match('/[\p{L}\p{N}]/u', $question)) { return collect(); }
        $procedural = ['apply', 'magapply', 'application', 'applications', 'requirements', 'required', 'eligible', 'eligibility', 'avail', 'availing', 'qualify', 'qualified', 'days', 'araw', 'ilang', 'sino', 'sinu', 'process', 'procedure', 'long', 'last'];
        $subjects = array_diff($this->extractKeywords($question), $procedural);
        $query = $subjects ? $question : implode(' ', array_reverse($recentQuestions)) . ' ' . $question;
        return $this->retrieve(trim($query));
    }

    public function promptChunks(Collection $chunks): Collection
    {
        $selected = collect();
        foreach ($chunks as $chunk) {
            $candidate = $selected->concat([$chunk]);
            if (mb_strlen($this->formatForPrompt($candidate), 'UTF-8') > PolicyChatPrompt::CONTEXT_LIMIT) { break; }
            $selected->push($chunk);
        }
        return $selected;
    }

    // FIX: Increased default limit from 3 to 5.
    // 3 chunks of 300 words = ~900 words of context. Often not enough for complex
    // policy questions. 5 chunks gives Claude richer context without token issues.
    public function retrieve(string $question, int $limit = 5): Collection
    {
        $keywords = $this->extractKeywords($question);

        // FIX: If no keywords were extracted (e.g. pure greeting "Hello"),
        // return empty rather than running a query that would match every row.
        if (empty($keywords)) {
            return collect();
        }

        try {
            // Score expression: counts how many keywords match per chunk.
            // Chunks matching more keywords float to the top — gives Claude
            // the most relevant context rather than just any matching chunk.
            $scoreExpr = '';
            $bindings  = [];

            foreach ($keywords as $word) {
                $scoreExpr .= ($scoreExpr ? ' + ' : '') . 'CASE WHEN content LIKE ? THEN 1 ELSE 0 END';
                $bindings[] = '%' . $word . '%';
            }

            $results = HrPolicyChunk::whereHas('document', function ($q) {
                    $q->where('is_active', true)
                      ->where('status', 'active'); // FIX: Also filter by status, not just is_active
                })
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $word) {
                        $q->orWhere('content', 'LIKE', '%' . $word . '%');
                    }
                })
                ->orderByRaw('(' . $scoreExpr . ') DESC', $bindings)
                ->orderBy('id')
                ->limit(max(1, min(5, $limit)))
                ->get();

            // FIX: Eager load the document relationship to avoid N+1 queries.
            // Without this, formatForPrompt() fires one SELECT per chunk to get
            // the document title, causing N separate queries for N chunks.
            $results->load('document');

            return $results;

        } catch (\Throwable $e) {
            Log::error('[PolicyRetriever] Query unavailable.', ['error_type' => get_class($e)]);
            throw new \RuntimeException('Policy retrieval is temporarily unavailable.');
        }
    }

    public function formatForPrompt(Collection $chunks): string
    {
        if ($chunks->isEmpty()) return '';

        return $chunks->map(function ($chunk) {
            $source  = $chunk->document->title ?? 'Unknown Document';
            $section = $chunk->section_title ? ' — ' . $chunk->section_title : '';
            return '[Source: ' . $source . $section . "]\n" . $chunk->content;
        })->join("\n\n---\n\n");
    }

    private function extractKeywords(string $text): array
    {
        // FIX: Expanded stop words list to include more common Filipino/Taglish
        // phrases that appear in HR questions but add no retrieval value.
        $stopWords = [
            'what', 'how', 'when', 'where', 'does', 'the', 'for',
            'and', 'our', 'can', 'will', 'are', 'is', 'my', 'your',
            'that', 'this', 'with', 'have', 'from', 'about', 'show',
            'tell', 'give', 'want', 'need', 'please', 'much', 'many',
            'could', 'would', 'should', 'been', 'also', 'some', 'like',
            'just', 'more', 'than', 'then', 'them', 'they', 'were',
            // Common Taglish stop words in HR questions
            'yung', 'nung', 'para', 'lang', 'nang', 'kong', 'pero',
            'kasi', 'sana', 'talaga', 'pwede', 'hindi', 'naman',
            'ang', 'ano', 'mga', 'mag', 'may', 'ng', 'sa', 'ako', 'ito', 'ba', 'get', 'who', 'why', 'let',
            'paano', 'kailan', 'saan', 'kanino',
        ];

        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8')), function ($w) use ($stopWords) {
            return mb_strlen($w, 'UTF-8') >= 3 && !in_array($w, $stopWords, true);
        });

        // FIX: Deduplicate keywords to prevent the same word appearing multiple
        // times in the score expression and skewing rankings.
        return array_slice(array_values(array_unique($words)), 0, 12);
    }
}
