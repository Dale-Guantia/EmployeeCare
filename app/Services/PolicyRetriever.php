<?php
namespace App\Services;

use App\Models\HrPolicyChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PolicyRetriever
{
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
                ->limit($limit)
                ->get();

            // FIX: Eager load the document relationship to avoid N+1 queries.
            // Without this, formatForPrompt() fires one SELECT per chunk to get
            // the document title, causing N separate queries for N chunks.
            $results->load('document');

            return $results;

        } catch (\Throwable $e) {
            Log::error('[PolicyRetriever] Query failed: ' . $e->getMessage(), [
                'keywords' => $keywords,
            ]);
            return collect();
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
        ];

        $clean = strtolower(preg_replace('/[^a-zA-Z\s]/', '', $text));
        $words = array_filter(explode(' ', $clean), function ($w) use ($stopWords) {
            return strlen($w) > 3 && !in_array($w, $stopWords);
        });

        // FIX: Deduplicate keywords to prevent the same word appearing multiple
        // times in the score expression and skewing rankings.
        return array_values(array_unique($words));
    }
}
