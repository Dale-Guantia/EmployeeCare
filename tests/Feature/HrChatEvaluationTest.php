<?php

namespace Tests\Feature;

use App\Models\HrChatLog;
use App\Models\HrPolicyDocument;
use App\Services\PolicyRetriever;
use Illuminate\Support\Facades\Http;
use Tests\Support\HrChatEvaluation;
use Tests\TestCase;

class HrChatEvaluationTest extends TestCase
{
    private function document(string $key, string $text, string $status = 'active'): array
    {
        $document = HrPolicyDocument::create(['title' => $key, 'filename' => 'synthetic.pdf',
            'status' => $status, 'is_active' => true, 'category' => 'general']);
        $document->chunks()->create(['chunk_index' => 0, 'page_number' => 1, 'content' => $text]);
        return [$document->id => $key];
    }

    private function question(string $id, string $question, string $key, string $quote): array
    {
        return ['id' => $id, 'question' => $question, 'prior_questions' => [], 'critical' => true,
            'expected_behavior' => 'answer', 'hr_review' => ['status' => 'pending'],
            'evidence_groups' => [['label' => 'Required provision', 'any_of' => [['document_key' => $key, 'quote' => $quote]]]]];
    }

    public function test_matching_a_document_without_the_required_provision_is_not_a_complete_evidence_result(): void
    {
        Http::fake();
        $keys = $this->document('wellness', 'Wellness leave is discussed here, but no filing deadline is stated.');
        $case = $this->question('HR-EVAL-001', 'Wellness leave?', 'wellness', 'five working days');
        $report = (new HrChatEvaluation)->run(['cases' => [$case]], $keys, new PolicyRetriever);
        $this->assertNotEmpty($report['results'][0]['retrieved_evidence']);
        $this->assertFalse($report['results'][0]['all_expected_evidence_found']);
        $this->assertNull($report['results'][0]['answer']);
        $this->assertSame('not_evaluated', $report['summary']['answer_quality']);
        $this->assertSame(0, HrChatLog::count());
        Http::assertNothingSent();
    }

    public function test_draft_and_hr_approved_reference_scores_are_kept_separate(): void
    {
        $keys = $this->document('wellness', 'Wellness leave has a maximum of five (5) days in this synthetic test.');
        $draft = $this->question('HR-EVAL-001', 'Wellness leave?', 'wellness', 'maximum of five (5) days');
        $approved = $draft;
        $approved['id'] = 'HR-EVAL-002';
        $approved['hr_review']['status'] = 'approved';
        $report = (new HrChatEvaluation)->run(['cases' => [$draft, $approved]], $keys, new PolicyRetriever);
        $this->assertSame(1, $report['summary']['draft_evidence_cases_complete']);
        $this->assertSame(1, $report['summary']['approved_evidence_cases_complete']);
        $this->assertSame('pending_hr_review_and_answer_evaluation', $report['summary']['release_decision']);
    }

    public function test_all_required_evidence_groups_must_be_found_and_inactive_evidence_is_excluded(): void
    {
        $keys = $this->document('wellness', 'Wellness leave annual limit is five days.');
        $keys += $this->document('unavailable', 'Wellness leave emergency exception is documented.', 'needs_review');
        $case = $this->question('HR-EVAL-001', 'Wellness leave emergency?', 'wellness', 'annual limit');
        $case['evidence_groups'][] = ['label' => 'Exception', 'any_of' => [['document_key' => 'unavailable', 'quote' => 'emergency exception']]];
        $report = (new HrChatEvaluation)->run(['cases' => [$case]], $keys, new PolicyRetriever);
        $this->assertTrue($report['results'][0]['evidence_groups'][0]['found']);
        $this->assertFalse($report['results'][0]['evidence_groups'][1]['found']);
        $this->assertFalse($report['results'][0]['all_expected_evidence_found']);
        $this->assertCount(1, $report['evidence']);
    }

    public function test_followups_replay_source_memory_without_leaking_between_cases(): void
    {
        $keys = $this->document('wellness', 'Wellness leave annual maximum is five days.');
        $followup = $this->question('HR-EVAL-001', 'Please continue.', 'wellness', 'annual maximum');
        $followup['prior_questions'] = array_merge(['Explain wellness leave.'], array_fill(0, 8, 'Please continue.'));
        $fresh = $this->question('HR-EVAL-002', 'Please continue.', 'wellness', 'annual maximum');
        $report = (new HrChatEvaluation)->run(['cases' => [$followup, $fresh]], $keys, new PolicyRetriever);
        $this->assertTrue($report['results'][0]['all_expected_evidence_found']);
        $this->assertFalse($report['results'][1]['all_expected_evidence_found']);
        $this->assertSame([], $report['results'][1]['retrieved_evidence']);
    }

    public function test_clarification_and_referral_cases_do_not_pass_automatically_from_empty_evidence(): void
    {
        $case = $this->question('HR-EVAL-001', 'Can I apply?', 'unused', 'unused');
        $case['expected_behavior'] = 'clarify';
        $case['evidence_groups'] = [];
        $report = (new HrChatEvaluation)->run(['cases' => [$case]], [], new PolicyRetriever);
        $this->assertNull($report['results'][0]['all_expected_evidence_found']);
        $this->assertSame(0, $report['summary']['draft_evidence_cases_complete']);
        $this->assertSame(1, $report['summary']['behavior_only_cases']);
        $this->assertSame('pending', $report['results'][0]['answer_review']['status']);
    }

    public function test_optional_answer_capture_uses_real_prior_answers_with_three_exchange_history_limit(): void
    {
        $keys = $this->document('wellness', 'Wellness leave annual maximum is five days.');
        $case = $this->question('HR-EVAL-001', 'Please continue.', 'wellness', 'annual maximum');
        $case['prior_questions'] = array_merge(['Explain wellness leave.'], array_fill(0, 7, 'Please continue.'));
        $requests = [];
        $answerer = function ($question, $context, $history) use (&$requests) {
            $requests[] = compact('question', 'context', 'history');
            return 'Synthetic provider response ' . count($requests);
        };
        $report = (new HrChatEvaluation)->run(['cases' => [$case]], $keys, new PolicyRetriever, $answerer);
        $this->assertCount(9, $requests);
        $this->assertSame([], $requests[0]['history']);
        $this->assertCount(6, $requests[8]['history']);
        $this->assertSame('Synthetic provider response 6', $requests[8]['history'][1]['content']);
        $this->assertStringContainsString('annual maximum', $requests[8]['context']);
        $this->assertSame('Synthetic provider response 9', $report['results'][0]['answer']);
        $this->assertSame('provider', $report['results'][0]['answer_origin']);
        $this->assertSame('not_evaluated', $report['summary']['answer_quality']);
        $this->assertSame(0, HrChatLog::count());
    }

    public function test_failed_prior_answer_is_not_replaced_by_fabricated_conversation_context(): void
    {
        $keys = $this->document('wellness', 'Wellness leave annual maximum is five days.');
        $case = $this->question('HR-EVAL-001', 'Please continue.', 'wellness', 'annual maximum');
        $case['prior_questions'] = ['Explain wellness leave.'];
        $report = (new HrChatEvaluation)->run(['cases' => [$case]], $keys, new PolicyRetriever,
            function () { throw new \RuntimeException('Sensitive provider error'); });
        $this->assertSame('prior_provider_error', $report['results'][0]['answer_origin']);
        $this->assertNull($report['results'][0]['answer']);
        $this->assertSame([], $report['results'][0]['prior_answer_context']);
        $this->assertStringNotContainsString('Sensitive provider error', json_encode($report));
        $this->assertSame(1, $report['summary']['provider_error_cases']);
    }

    public function test_retrieval_misses_capture_the_application_fallback_without_invoking_a_provider(): void
    {
        $case = $this->question('HR-EVAL-001', 'Astronomy planets?', 'unused', 'unused');
        $calls = 0;
        $report = (new HrChatEvaluation)->run(['cases' => [$case]], [], new PolicyRetriever,
            function () use (&$calls) { $calls++; return 'Should never be used'; });
        $this->assertSame(0, $calls);
        $this->assertSame('application_retrieval_miss', $report['results'][0]['answer_origin']);
        $this->assertStringContainsString("couldn't find this", $report['results'][0]['answer']);
        $this->assertSame(0, $report['summary']['generated_answers']);
    }
}
