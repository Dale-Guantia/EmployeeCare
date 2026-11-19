<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\HrChatEvaluation;

class HrChatEvaluationDatasetTest extends TestCase
{
    private function inputs(): array
    {
        $directory = dirname(__DIR__) . '/Fixtures/hr-chat-evaluation/';
        return [HrChatEvaluation::read($directory . 'questions.json'), HrChatEvaluation::read($directory . 'sources.json')];
    }

    public function test_draft_has_representative_source_linked_cases_without_fabricated_hr_approval(): void
    {
        [$dataset, $manifest] = $this->inputs();
        HrChatEvaluation::validate($dataset, $manifest);
        $this->assertGreaterThanOrEqual(30, count($dataset['cases']));
        $this->assertLessThanOrEqual(50, count($dataset['cases']));
        $this->assertCount(10, $manifest['documents']);
        $this->assertSame(12, array_sum(array_map(fn ($d) => 1 + count($d['aliases']), $manifest['documents'])));
        $this->assertSame(['pending'], array_values(array_unique(array_column(array_column($dataset['cases'], 'hr_review'), 'status'))));
        $this->assertSame('proposed_pending_hr', $dataset['acceptance']['status']);
        $this->assertNull($dataset['acceptance']['approved_by']);
        foreach (['en', 'fil', 'taglish'] as $language) {
            $this->assertContains($language, array_column($dataset['cases'], 'language'));
        }
        foreach (['answer', 'clarify', 'refer', 'explain_conflict'] as $behavior) {
            $this->assertContains($behavior, array_column($dataset['cases'], 'expected_behavior'));
        }
        $this->assertGreaterThan(6, max(array_map(fn ($c) => count($c['prior_questions']), $dataset['cases'])));
    }

    public function test_duplicate_case_ids_are_rejected(): void
    {
        [$dataset, $manifest] = $this->inputs();
        $dataset['cases'][] = $dataset['cases'][0];
        $this->expectException(InvalidArgumentException::class);
        HrChatEvaluation::validate($dataset, $manifest);
    }

    public function test_hr_approval_requires_an_identified_reviewer_and_date(): void
    {
        [$dataset, $manifest] = $this->inputs();
        $dataset['cases'][0]['hr_review']['status'] = 'approved';
        $this->expectException(InvalidArgumentException::class);
        HrChatEvaluation::validate($dataset, $manifest);
    }

    public function test_nonexistent_source_references_are_rejected(): void
    {
        [$dataset, $manifest] = $this->inputs();
        $dataset['cases'][0]['evidence_groups'][0]['any_of'][0]['document_key'] = 'invented-policy';
        $this->expectException(InvalidArgumentException::class);
        HrChatEvaluation::validate($dataset, $manifest);
    }

    public function test_questions_respect_the_chatbots_character_limit(): void
    {
        [$dataset, $manifest] = $this->inputs();
        $dataset['cases'][0]['question'] = str_repeat('ñ', 601);
        $this->expectException(InvalidArgumentException::class);
        HrChatEvaluation::validate($dataset, $manifest);
    }

    public function test_policy_answer_rubrics_cannot_omit_supporting_evidence(): void
    {
        [$dataset, $manifest] = $this->inputs();
        $dataset['cases'][0]['evidence_groups'] = [];
        $this->expectException(InvalidArgumentException::class);
        HrChatEvaluation::validate($dataset, $manifest);
    }

    public function test_signed_answer_reviews_do_not_turn_unapproved_reference_answers_into_approved_accuracy(): void
    {
        [$dataset] = $this->inputs();
        $case = $dataset['cases'][0];
        $result = ['id' => $case['id'], 'answer' => 'Synthetic answer', 'answer_review' => [
            'status' => 'reviewed', 'reviewer' => 'Synthetic test reviewer', 'reviewed_at' => '2026-11-10',
            'behavior_correct' => true, 'facts_correct' => true, 'procedure_complete' => true,
            'citations_support_claims' => true, 'language_preserves_conditions' => true,
            'unsupported_claims' => [], 'critical_error' => false,
        ]];
        $draft = HrChatEvaluation::scoreReviews(['results' => [$result]], ['cases' => [$case]]);
        $this->assertSame(1, $draft['signed_complete_reviews']);
        $this->assertSame(0, $draft['hr_approved_reviewed_cases']);
        $case['hr_review']['status'] = 'approved';
        $approved = HrChatEvaluation::scoreReviews(['results' => [$result]], ['cases' => [$case]]);
        $this->assertSame(1, $approved['hr_approved_passed_cases']);
        $result['answer_review']['unsupported_claims'] = ['An invented filing requirement'];
        $failed = HrChatEvaluation::scoreReviews(['results' => [$result]], ['cases' => [$case]]);
        $this->assertSame(0, $failed['hr_approved_passed_cases']);
        $this->assertSame([$case['id']], $failed['critical_failed_cases']);
        $this->assertSame('requires_hr_acceptance', $failed['release_decision']);
    }

    public function test_incomplete_signed_answer_reviews_are_rejected(): void
    {
        [$dataset] = $this->inputs();
        $this->expectException(InvalidArgumentException::class);
        HrChatEvaluation::scoreReviews(['results' => [['id' => $dataset['cases'][0]['id'],
            'answer' => 'Synthetic answer', 'answer_review' => ['status' => 'reviewed']]]], $dataset);
    }
}
