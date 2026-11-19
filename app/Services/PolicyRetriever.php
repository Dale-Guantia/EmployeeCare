<?php
namespace App\Services;

use App\Models\HrPolicyChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PolicyRetriever
{
    public function retrieveForConversation(string $question, array $recentQuestions, array $recentChunkIds = []): Collection
    {
        if (!preg_match('/[\p{L}\p{N}]/u', $question)) { return collect(); }
        if ($this->hasSubject($question)) {
            $currentMatches = $this->retrieve($question);
            if (! $this->refersToCurrentTopic($question)) { return $currentMatches; }
            $namedMatches = $this->namedPolicyMatches($question, $currentMatches);
            if ($namedMatches->isNotEmpty()) { return $namedMatches; }
        }

        // Carry the latest topic, rather than mixing all earlier subjects together.
        $anchor = '';
        foreach (array_slice($recentQuestions, 0, 6) as $previous) {
            if (is_string($previous) && $this->hasSubject($previous) && ! $this->refersToCurrentTopic($previous)) {
                $anchor = $previous;
                break;
            }
        }
        // Re-resolve the named topic from current active policies. A generic word
        // such as "file" must not replace Wellness Leave with a general leave rule.
        if ($anchor !== '') {
            $topicChunks = $this->retrieve($anchor);
            if ($topicChunks->isEmpty()) { return collect(); }
            $documentId = $topicChunks->first()->document_id;
            if ($documentId === null) { return $topicChunks; }
            if (preg_match('/\b(?:compare|versus|vs|difference|between|kumpara|pagkakaiba)\b/iu', $question)) {
                return $this->retrieve(trim($anchor . ' ' . $question));
            }
            $related = $this->retrieveMatching(trim($anchor . ' ' . $question), 5, [$documentId]);
            return $related->concat($topicChunks->where('document_id', $documentId))->unique('id')->take(5)->values();
        }

        $anchors = HrPolicyChunk::whereIn('id', array_slice($recentChunkIds, 0, 5))
            ->whereHas('document', fn ($q) => $q->where('is_active', true)->where('status', 'active'))
            ->with('document')->limit(5)->get()
            ->sortBy(fn ($chunk) => array_search($chunk->id, $recentChunkIds, true))->values();
        if ($anchors->isNotEmpty()) {
            $documentId = $anchors->first()->document_id;
            // Historical answers are not evidence; these chunks belong to active documents now.
            $related = $this->retrieveMatching($question, 5, [$documentId]);
            return $related->concat($anchors->where('document_id', $documentId))->unique('id')->take(5)->values();
        }
        return $this->retrieve($question);
    }

    private function refersToCurrentTopic(string $question): bool
    {
        return (bool) preg_match('/\b(?:it|its|this|that|these|those|them|same|previous|earlier|'
            . 'ito|nito|dito|iyan|niyan|diyan|iyon|yon|yan|niya|sakin|saakin|tagalog|filipino)\b/iu', $question);
    }

    private function namedPolicyMatches(string $question, Collection $chunks): Collection
    {
        // A pronoun can coexist with an explicit new topic ("this maternity leave").
        // Only words from an active retrieved policy title can override the old topic.
        $generic = ['leave', 'policy', 'policies', 'rule', 'rules', 'benefit', 'benefits',
            'order', 'series', 'document', 'documents', 'government', 'pasig', 'executive',
            'omnibus', 'hrdo', 'city', 'employee', 'employees'];
        $words = array_diff($this->extractKeywords($question), $generic);
        if (! $words) { return collect(); }
        return $chunks->filter(function ($chunk) use ($words, $generic) {
            $titleWords = array_diff($this->extractKeywords($chunk->document->title ?? ''), $generic);
            return count(array_intersect($words, $titleWords)) > 0;
        })->values();
    }

    private function hasSubject(string $question): bool
    {
        $procedural = [
            'apply', 'magapply', 'application', 'applications', 'requirements', 'requirement', 'required',
            'eligible', 'eligibility', 'avail', 'availing', 'available', 'qualify', 'qualified', 'days', 'day', 'araw', 'ilang',
            'sino', 'sinu', 'process', 'procedure', 'long', 'last', 'documents', 'document', 'paperwork',
            'submit', 'submission', 'forms', 'form', 'steps', 'step', 'file', 'filing', 'paid', 'unpaid', 'explain', 'explanation',
            'elaborate', 'clarify', 'clarification', 'continue', 'example', 'examples', 'simpler', 'simply', 'terms',
            'further', 'detail', 'details', 'next', 'number', 'paki',
            'mean', 'means', 'meaning', 'summarize', 'summary', 'again', 'you', 'work', 'works', 'happens',
            'approved', 'approve', 'approval', 'denied', 'denial', 'rejected', 'deadline', 'before', 'after',
            'maximum', 'minimum', 'limit', 'limits', 'duration', 'pay', 'payment', 'salary', 'salaries',
            'contractual', 'regular', 'permanent', 'temporary', 'probationary', 'employee', 'employees',
            'ako', 'nito', 'niyan', 'iyan', 'iyon', 'pano', 'ulit', 'paliwanag', 'kailangan', 'kailangang',
            'ipaliwanag', 'pakipaliwanag', 'halimbawa', 'bayad', 'puwede', 'pwedeng', 'natin', 'kami', 'sila', 'siya',
            'proseso', 'pag', 'pagfile', 'magfile', 'ifile', 'pagapply', 'iapply', 'magagamit', 'gamitin', 'makuha',
        ];
        return count(array_filter(array_diff($this->extractKeywords($question), $procedural), fn ($word) => !is_numeric($word))) > 0;
    }

    public function promptChunks(Collection $chunks): Collection
    {
        $selected = collect();
        foreach ($chunks as $chunk) {
            $candidate = $selected->concat([$chunk]);
            if (mb_strlen($this->formatForPrompt($candidate), 'UTF-8') > PolicyChatPrompt::CONTEXT_LIMIT) { continue; }
            $selected->push($chunk);
        }
        return $selected;
    }

    // FIX: Increased default limit from 3 to 5.
    // 3 chunks of 300 words = ~900 words of context. Often not enough for complex
    // policy questions. 5 chunks gives Claude richer context without token issues.
    public function retrieve(string $question, int $limit = 5): Collection
    {
        return $this->retrieveMatching($question, $limit);
    }

    private function retrieveMatching(string $question, int $limit, ?array $documentIds = null): Collection
    {
        $keywords = $this->searchTerms($question);

        // FIX: If no keywords were extracted (e.g. pure greeting "Hello"),
        // return empty rather than running a query that would match every row.
        if (empty($keywords)) {
            return collect();
        }

        try {
            $results = HrPolicyChunk::when($documentIds !== null, fn ($q) => $q->whereIn('document_id', $documentIds))
                ->whereHas('document', function ($q) {
                    $q->where('is_active', true)
                      ->where('status', 'active');
                })
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $word) {
                        $q->orWhere('content', 'LIKE', '%' . $word . '%');
                        $q->orWhere('section_title', 'LIKE', '%' . $word . '%');
                    }
                    $q->orWhereHas('document', function ($doc) use ($keywords) {
                        $doc->where(function ($titles) use ($keywords) {
                            foreach ($keywords as $word) { $titles->orWhere('title', 'LIKE', '%' . $word . '%'); }
                        });
                    });
                })
                ->orderBy('id')
                ->limit(500)
                ->get();

            // Rank a bounded candidate set in PHP for SQL Server/MySQL/SQLite parity.
            $results->load('document');

            $original = $this->extractKeywords($question);
            preg_match('/\b(?:section|sec\.?)\s*(\d+[a-z]?)/iu', $question, $sectionMatch);
            $sectionNumber = $sectionMatch[1] ?? null;
            $temporal = (bool) preg_match('/\b(?:effectivity|effective|before|after|rely|took|kailan|noong|hunyo|hulyo|june|july)\b/iu', $question);
            $ranked = $results->sort(function ($a, $b) use ($keywords, $original, $sectionNumber, $temporal) {
                return $this->score($b, $keywords, $original, $sectionNumber, $temporal) <=> $this->score($a, $keywords, $original, $sectionNumber, $temporal)
                    ?: $a->id <=> $b->id;
            })->values();
            $max = max(1, min(5, $limit));
            $procedural = (bool) preg_match('/\b(?:apply|application|requirements|steps|process|procedure|submit|filing|form|magapply|proseso)\b/iu', $question);
            $selected = $ranked->take($procedural && $max > 1 ? $max - 1 : $max);
            if ($procedural) {
                foreach ($selected->take(2) as $chunk) {
                    if ($selected->count() >= $max) { break; }
                    $neighbor = HrPolicyChunk::where('document_id', $chunk->document_id)
                        ->where('chunk_index', $chunk->chunk_index + 1)->with('document')->first();
                    if ($neighbor && ! $selected->contains('id', $neighbor->id)) { $selected->push($neighbor); }
                }
            }
            foreach ($ranked as $chunk) {
                if ($selected->count() >= $max) { break; }
                if (! $selected->contains('id', $chunk->id)) { $selected->push($chunk); }
            }
            return $selected->values();

        } catch (\Throwable $e) {
            Log::error('[PolicyRetriever] Query unavailable.', ['error_type' => get_class($e)]);
            throw new \RuntimeException('Policy retrieval is temporarily unavailable.');
        }
    }

    public function formatForPrompt(Collection $chunks): string
    {
        if ($chunks->isEmpty()) return '';

        return $chunks->map(function ($chunk) {
            $doc = $chunk->document;
            $parts = [$doc->title ?? 'Unknown Document'];
            if ($chunk->section_title) { $parts[] = $chunk->section_title; }
            if ($chunk->page_number) { $parts[] = 'PDF p. ' . $chunk->page_number . ($chunk->end_page_number && $chunk->end_page_number !== $chunk->page_number ? '-' . $chunk->end_page_number : ''); }
            return '[Source: ' . implode(' | ', $parts) . "]\n" . $chunk->content;
        })->join("\n\n---\n\n");
    }

    private function searchTerms(string $question): array
    {
        $words = $this->extractKeywords($question);
        $equivalents = [
            'benepisyo' => ['benefit'], 'pagliban' => ['leave'], 'bakasyon' => ['leave'],
            'magapply' => ['application', 'apply'], 'pagapply' => ['application'],
            'requirements' => ['required', 'requirement'], 'kailangan' => ['required'],
            'filing' => ['file', 'submit'], 'saln' => ['assets', 'liabilities'],
            'regalo' => ['gift', 'gifts'], 'pagtanggap' => ['acceptance'],
            'gifts' => ['gift'], 'epektibo' => ['effective', 'effectivity'],
            'rely' => ['effectivity', 'effective'], 'probationary' => ['probation'],
        ];
        foreach ($words as $word) {
            foreach ($equivalents[$word] ?? [] as $equivalent) { $words[] = $equivalent; }
        }
        return array_slice(array_values(array_unique($words)), 0, 18);
    }

    private function score(HrPolicyChunk $chunk, array $terms, array $original, ?string $sectionNumber, bool $temporal): int
    {
        $title = mb_strtolower($chunk->document->title ?? '', 'UTF-8');
        $section = mb_strtolower($chunk->section_title ?? '', 'UTF-8');
        $content = mb_strtolower($chunk->content, 'UTF-8');
        $score = 0;
        foreach ($terms as $term) {
            $weight = in_array($term, $original, true) ? 2 : 1;
            if (str_contains($title, $term)) { $score += 4 * $weight; }
            if (str_contains($section, $term)) { $score += 3 * $weight; }
            $score += min(3, substr_count($content, $term)) * $weight;
        }
        if ($sectionNumber && preg_match('/\b(?:section|sec\.?)\s*' . preg_quote($sectionNumber, '/') . '\b/iu', $section . ' ' . $content)) {
            $score += 30;
        }
        if ($temporal && (str_contains($content, 'effectivity') || str_contains($content, 'take effect'))) {
            $score += 12;
        }
        return $score;
    }

    private function extractKeywords(string $text): array
    {
        // FIX: Expanded stop words list to include more common Filipino/Taglish
        // phrases that appear in HR questions but add no retrieval value.
        $stopWords = [
            'what', 'how', 'when', 'where', 'which', 'whether', 'does', 'the', 'for',
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
