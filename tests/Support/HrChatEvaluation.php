<?php

namespace Tests\Support;

use App\Services\PolicyRetriever;
use InvalidArgumentException;

/** Offline evidence evaluation. HR correctness is deliberately a separate review. */
class HrChatEvaluation
{
    public static function read(string $path): array
    {
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Expected a JSON object: ' . basename($path));
        }
        return $data;
    }

    public static function validate(array $dataset, array $manifest): void
    {
        if (($dataset['schema_version'] ?? null) !== 1 || ($manifest['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported evaluation schema.');
        }
        $documents = $manifest['documents'] ?? [];
        $keys = array_column($documents, 'key');
        if (!$keys || count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Document keys must be unique.');
        }
        foreach ($documents as $document) {
            if (!preg_match('/^[a-z0-9-]+$/', $document['key'])
                || !preg_match('/^[a-f0-9]{64}$/', $document['sha256'] ?? '')
                || basename($document['filename']) !== $document['filename']) {
                throw new InvalidArgumentException('Invalid source manifest.');
            }
        }
        $seen = [];
        foreach ($dataset['cases'] ?? [] as $case) {
            $id = $case['id'] ?? '';
            if (!preg_match('/^HR-EVAL-\d{3}$/', $id) || isset($seen[$id])) {
                throw new InvalidArgumentException('Evaluation case IDs must be unique.');
            }
            $seen[$id] = true;
            $questions = array_merge($case['prior_questions'] ?? [], [$case['question'] ?? '']);
            foreach ($questions as $question) {
                if (!is_string($question) || trim($question) === '' || mb_strlen($question) > 600) {
                    throw new InvalidArgumentException('Invalid question in ' . $id);
                }
            }
            if (!in_array($case['expected_behavior'] ?? '', ['answer', 'clarify', 'refer', 'explain_conflict'], true)
                || !in_array($case['language'] ?? '', ['en', 'fil', 'taglish'], true)
                || empty($case['essential_facts']) || empty($case['must_not_claim'])) {
                throw new InvalidArgumentException('Incomplete answer rubric in ' . $id);
            }
            $review = $case['hr_review'] ?? [];
            if (!in_array($review['status'] ?? '', ['pending', 'approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Invalid HR review status in ' . $id);
            }
            if ($review['status'] !== 'pending' && (empty($review['reviewer']) || empty($review['reviewed_at']))) {
                throw new InvalidArgumentException('HR decisions require a reviewer and date in ' . $id);
            }
            if (in_array($case['expected_behavior'], ['answer', 'explain_conflict'], true) && empty($case['evidence_groups'])) {
                throw new InvalidArgumentException('Policy answers require evidence in ' . $id);
            }
            foreach ($case['evidence_groups'] ?? [] as $group) {
                if (empty($group['any_of'])) {
                    throw new InvalidArgumentException('Empty evidence group in ' . $id);
                }
                foreach ($group['any_of'] as $source) {
                    if (!in_array($source['document_key'] ?? '', $keys, true) || empty($source['pages'])
                        || empty($source['section']) || empty($source['quote']) || self::normalize($source['quote']) === '') {
                        throw new InvalidArgumentException('Incomplete source reference in ' . $id);
                    }
                    foreach ($source['pages'] as $page) {
                        if (!is_int($page) || $page < 1) {
                            throw new InvalidArgumentException('Invalid PDF page in ' . $id);
                        }
                    }
                }
            }
        }
        if (!$seen) {
            throw new InvalidArgumentException('The evaluation dataset is empty.');
        }
    }

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text, 'UTF-8')));
    }

    public static function scoreReviews(array $report, array $dataset): array
    {
        $dimensions = ['behavior_correct', 'facts_correct', 'procedure_complete', 'citations_support_claims', 'language_preserves_conditions'];
        $cases = array_column($dataset['cases'], null, 'id');
        $scores = ['captured_answers' => 0, 'signed_complete_reviews' => 0, 'hr_approved_reviewed_cases' => 0,
            'hr_approved_passed_cases' => 0, 'critical_failed_cases' => [], 'unsupported_claim_cases' => [],
            'dimensions' => array_fill_keys($dimensions, ['passed' => 0, 'reviewed' => 0]),
            'release_decision' => 'requires_hr_acceptance'];
        $seen = [];
        foreach ($report['results'] ?? [] as $result) {
            $id = $result['id'] ?? '';
            if (!isset($cases[$id]) || isset($seen[$id])) { throw new InvalidArgumentException('Unknown or repeated reviewed case ID.'); }
            $seen[$id] = true;
            if (!is_string($result['answer'] ?? null) || trim($result['answer']) === '') { continue; }
            $scores['captured_answers']++;
            $review = $result['answer_review'] ?? [];
            if (($review['status'] ?? '') !== 'reviewed') { continue; }
            if (empty($review['reviewer']) || empty($review['reviewed_at']) || !is_array($review['unsupported_claims'] ?? null)
                || !is_bool($review['critical_error'] ?? null)) {
                throw new InvalidArgumentException('A signed review requires reviewer, date, unsupported_claims array and critical_error boolean.');
            }
            foreach ($dimensions as $dimension) {
                if (!is_bool($review[$dimension] ?? null)) { throw new InvalidArgumentException('Incomplete review dimension: ' . $dimension); }
            }
            $scores['signed_complete_reviews']++;
            // Draft/rejected reference rubrics cannot establish approved answer accuracy.
            if ($cases[$id]['hr_review']['status'] !== 'approved') { continue; }
            $scores['hr_approved_reviewed_cases']++;
            $passed = !$review['critical_error'] && $review['unsupported_claims'] === [];
            foreach ($dimensions as $dimension) {
                $scores['dimensions'][$dimension]['reviewed']++;
                $scores['dimensions'][$dimension]['passed'] += (int) $review[$dimension];
                $passed = $passed && $review[$dimension];
            }
            if ($passed) { $scores['hr_approved_passed_cases']++; }
            if (!$passed && $cases[$id]['critical']) { $scores['critical_failed_cases'][] = $id; }
            if ($review['unsupported_claims']) { $scores['unsupported_claim_cases'][] = $id; }
        }
        return $scores;
    }

    public function run(array $dataset, array $documentKeys, PolicyRetriever $retriever, ?callable $answerer = null): array
    {
        $results = [];
        $evidence = [];
        foreach ($dataset['cases'] as $case) {
            // Each case is independent. Prior questions are replayed in order only
            // to carry retrieved source IDs, exactly as the controller's six-log window does.
            // No fabricated assistant answer is introduced or graded.
            $turns = [];
            $historyComplete = true;
            foreach ($case['prior_questions'] as $previous) {
                $chunks = $this->retrieve($previous, $turns, $retriever);
                $reply = $this->answer($previous, $chunks, $turns, $retriever, $answerer);
                if ($answerer !== null && $reply['answer_origin'] === 'provider_error') {
                    $historyComplete = false;
                    break;
                }
                array_unshift($turns, ['question' => $previous, 'chunk_ids' => $chunks->pluck('id')->all(), 'answer' => $reply['answer']]);
                $turns = array_slice($turns, 0, 6);
            }
            $chunks = $this->retrieve($case['question'], $turns, $retriever);
            $reply = $historyComplete
                ? $this->answer($case['question'], $chunks, $turns, $retriever, $answerer)
                : ['answer' => null, 'answer_origin' => 'prior_provider_error'];
            $selected = [];
            foreach ($chunks as $chunk) {
                $key = $documentKeys[$chunk->document_id] . ':' . $chunk->chunk_index;
                $selected[] = $key;
                $evidence[$key] = [
                    'document_key' => $documentKeys[$chunk->document_id],
                    'title' => $chunk->document->title,
                    'chunk_index' => $chunk->chunk_index,
                    'starting_pdf_page' => $chunk->page_number,
                    'section' => $chunk->section_title,
                    'content_sha256' => hash('sha256', $chunk->content),
                    'content' => $chunk->content,
                ];
            }
            $groups = [];
            foreach ($case['evidence_groups'] as $group) {
                $matches = [];
                foreach ($selected as $key) {
                    foreach ($group['any_of'] as $source) {
                        if ($evidence[$key]['document_key'] === $source['document_key']
                            && str_contains(self::normalize($evidence[$key]['content']), self::normalize($source['quote']))) {
                            $matches[] = $key;
                        }
                    }
                }
                $groups[] = ['label' => $group['label'], 'found' => (bool) $matches,
                    'matched_evidence' => array_values(array_unique($matches))];
            }
            $results[] = [
                'id' => $case['id'], 'question' => $case['question'], 'prior_questions' => $case['prior_questions'],
                'expected_behavior' => $case['expected_behavior'], 'critical' => $case['critical'],
                'hr_reference_status' => $case['hr_review']['status'],
                'retrieved_evidence' => $selected, 'evidence_groups' => $groups,
                'all_expected_evidence_found' => $groups ? !in_array(false, array_column($groups, 'found'), true) : null,
                'answer' => $reply['answer'], 'answer_origin' => $reply['answer_origin'],
                'prior_answer_context' => $answerer !== null ? array_reverse($turns) : null,
                'answer_review' => [
                    'status' => 'pending', 'reviewer' => null, 'reviewed_at' => null,
                    'behavior_correct' => null, 'facts_correct' => null, 'procedure_complete' => null,
                    'citations_support_claims' => null, 'language_preserves_conditions' => null,
                    'unsupported_claims' => null, 'critical_error' => null, 'notes' => null,
                ],
            ];
        }
        $scorable = array_filter($results, fn ($r) => $r['all_expected_evidence_found'] !== null && $r['hr_reference_status'] !== 'rejected');
        $approved = array_filter($scorable, fn ($r) => $r['hr_reference_status'] === 'approved');
        return [
            'summary' => [
                'cases' => count($results),
                'hr_approved_cases' => count(array_filter($results, fn ($r) => $r['hr_reference_status'] === 'approved')),
                'draft_evidence_cases' => count($scorable) - count($approved),
                'draft_evidence_cases_complete' => count(array_filter($scorable, fn ($r) => $r['hr_reference_status'] !== 'approved' && $r['all_expected_evidence_found'])),
                'approved_evidence_cases' => count($approved),
                'approved_evidence_cases_complete' => count(array_filter($approved, fn ($r) => $r['all_expected_evidence_found'])),
                'behavior_only_cases' => count(array_filter($results, fn ($r) => $r['all_expected_evidence_found'] === null)),
                'rejected_reference_cases' => count(array_filter($results, fn ($r) => $r['hr_reference_status'] === 'rejected')),
                'generated_answers' => count(array_filter($results, fn ($r) => $r['answer_origin'] === 'provider')),
                'application_fallback_answers' => count(array_filter($results, fn ($r) => $r['answer_origin'] === 'application_retrieval_miss')),
                'provider_error_cases' => count(array_filter($results, fn ($r) => in_array($r['answer_origin'], ['provider_error', 'prior_provider_error'], true))),
                'answer_quality' => 'not_evaluated',
                'release_decision' => 'pending_hr_review_and_answer_evaluation',
            ],
            'results' => $results, 'evidence' => $evidence,
        ];
    }

    private function answer(string $question, $chunks, array $turns, PolicyRetriever $retriever, ?callable $answerer): array
    {
        if ($chunks->isEmpty()) {
            // Mirrors the controller's deterministic miss response; this is application
            // behavior, not a generated or HR-approved answer. Controller hash is recorded.
            return ['answer_origin' => 'application_retrieval_miss', 'answer' =>
                "I couldn't find this in our current HR policy documents. Please contact HRDO directly for assistance.\n\n"
                . "*(Hindi ko ito nakita sa aming kasalukuyang mga dokumento ng patakaran ng HR. Mangyaring makipag-ugnayan nang direkta sa HRDO para sa tulong.)*"];
        }
        if ($answerer === null) { return ['answer' => null, 'answer_origin' => 'not_generated']; }
        $history = [];
        foreach (array_reverse(array_slice($turns, 0, 3)) as $turn) {
            $history[] = ['role' => 'user', 'content' => $turn['question']];
            $history[] = ['role' => 'assistant', 'content' => $turn['answer']];
        }
        try {
            $answer = $answerer($question, $retriever->formatForPrompt($chunks), $history);
            if (!is_string($answer) || trim($answer) === '') { throw new \RuntimeException('Empty evaluation answer.'); }
            return ['answer' => $answer, 'answer_origin' => 'provider'];
        } catch (\Throwable $error) {
            return ['answer' => null, 'answer_origin' => 'provider_error'];
        }
    }

    private function retrieve(string $question, array $turns, PolicyRetriever $retriever)
    {
        $recent = array_slice($turns, 0, 6);
        $lastGrounded = [];
        foreach ($recent as $turn) {
            if ($turn['chunk_ids']) { $lastGrounded = $turn['chunk_ids']; break; }
        }
        return $retriever->promptChunks($retriever->retrieveForConversation($question, array_column($recent, 'question'), $lastGrounded));
    }
}
