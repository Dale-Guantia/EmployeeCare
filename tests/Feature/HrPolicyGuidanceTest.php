<?php

namespace Tests\Feature;

use App\Models\HrChatLog;
use App\Models\HrConversation;
use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\PolicyRetriever;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrPolicyGuidanceTest extends TestCase
{
    private function document(string $title, string $content, array $attributes = []): HrPolicyDocument
    {
        $document = HrPolicyDocument::create(array_merge([
            'title' => $title, 'filename' => 'guide.pdf', 'file_path' => 'policies/guide.pdf',
            'file_hash' => str_repeat('a', 64), 'category' => 'leave', 'is_active' => true,
            'status' => 'active',
        ], $attributes));
        $document->chunks()->create(['chunk_index' => 0, 'content' => $content,
            'page_number' => 2, 'end_page_number' => 3, 'section_title' => 'Section 2']);
        return $document;
    }

    public function test_title_and_section_are_searchable_without_metadata_date_filtering(): void
    {
        $future = $this->document('Wellness Leave', 'A passage without the policy name.', ['effective_date' => '2027-01-01']);
        $current = $this->document('Wellness Leave', 'A different passage without the policy name.');
        $matches = (new PolicyRetriever)->retrieve('Wellness');
        $this->assertEqualsCanonicalizing([$future->id, $current->id], $matches->pluck('document_id')->unique()->all());
        $this->assertStringContainsString('PDF p. 2-3', (new PolicyRetriever)->formatForPrompt($matches));
    }

    public function test_ambiguous_process_question_clarifies_without_calling_provider(): void
    {
        $this->app->instance(AnthropicService::class, new class extends AnthropicService {
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                throw new \LogicException('Provider must not be called.');
            }
        });
        $user = User::role('employee')->first();
        $this->actingAs($user)->postJson(route('hr.chat.ask'), ['question' => 'How do I apply?'])
            ->assertOk()->assertSee('Which HR policy or process');
        $this->assertSame(1, HrChatLog::where('user_id', $user->id)->count());
    }

    public function test_no_document_match_refers_the_user_to_hrdo_without_calling_provider(): void
    {
        $this->app->instance(AnthropicService::class, new class extends AnthropicService {
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                throw new \LogicException('Provider must not be called.');
            }
        });
        $this->actingAs(User::role('employee')->first())
            ->postJson(route('hr.chat.ask'), ['question' => 'Astronomy planets?'])
            ->assertOk()->assertJsonPath('sources', [])->assertSee('HRDO office');
    }

    public function test_historical_question_searches_available_documents_without_metadata_rules(): void
    {
        $old = $this->document('Maternity leave 2021', 'Maternity leave instructions.',
            ['effective_date' => '2020-01-01']);
        $new = $this->document('Maternity leave 2025', 'Maternity leave revised instructions.',
            ['effective_date' => '2025-01-01']);
        $this->assertEqualsCanonicalizing([$old->id, $new->id], (new PolicyRetriever)->retrieve('Maternity leave in 2021?')
            ->pluck('document_id')->unique()->all());
    }

    public function test_named_month_does_not_hide_an_active_document(): void
    {
        $this->document('Wellness Leave EO', 'Wellness leave is available under this order.',
            ['effective_date' => '2026-07-15']);
        $this->assertCount(1, (new PolicyRetriever)->retrieve('Could I rely on this wellness leave EO in June 2026?'));
    }

    public function test_legacy_metadata_is_not_used_as_policy_evidence(): void
    {
        $document = $this->document('Wellness Leave', 'Only this PDF passage is policy evidence.');
        DB::table('hr_policy_documents')->where('id', $document->id)->update([
            'edition' => 'UNTRUSTED EDITION',
            'audience' => 'UNTRUSTED AUDIENCE',
            'relationship_notes' => 'UNTRUSTED POLICY CLAIM',
            'authority_status' => 'disputed',
        ]);
        $prompt = (new PolicyRetriever)->formatForPrompt((new PolicyRetriever)->retrieve('Wellness'));
        $this->assertStringContainsString('Only this PDF passage is policy evidence.', $prompt);
        $this->assertStringNotContainsString('UNTRUSTED', $prompt);
        $this->assertStringNotContainsString('disputed', $prompt);
    }

    public function test_upload_form_only_asks_for_document_fields(): void
    {
        $this->actingAs(User::role('admin')->first())->get(route('hr.policy.upload.form'))
            ->assertOk()->assertSee('name="category"', false)
            ->assertDontSee('name="policy_key"', false)
            ->assertDontSee('name="authority_status"', false)
            ->assertDontSee('name="relationship_notes"', false);
    }

    public function test_start_over_creates_a_fresh_topic_scope(): void
    {
        $this->document('Wellness Leave', 'Wellness leave application guidance.');
        $this->app->instance(AnthropicService::class, new class extends AnthropicService {
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                return 'Synthetic policy guidance.';
            }
        });
        $this->actingAs(User::role('employee')->first());
        $first = $this->postJson(route('hr.chat.ask'), ['question' => 'What is wellness leave?'])->assertOk();
        $this->assertNotEmpty(HrConversation::findOrFail($first['conversation_id'])->context_state['topic_question']);
        $reset = $this->postJson(route('hr.chat.ask'), ['question' => 'start over',
            'conversation_id' => $first['conversation_id']])->assertOk();
        $this->assertNotSame($first['conversation_id'], $reset['conversation_id']);
        $this->assertEmpty(HrConversation::findOrFail($reset['conversation_id'])->context_state['topic_question'] ?? null);
        $this->postJson(route('hr.chat.ask'), ['question' => 'How do I apply?',
            'conversation_id' => $reset['conversation_id']])->assertOk()->assertSee('Which HR policy or process');
    }

    public function test_latest_employee_statement_replaces_old_unverified_context(): void
    {
        $this->document('Wellness Leave', 'Wellness leave guidance for employees.');
        $provider = new class extends AnthropicService {
            public array $questions = [];
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                $this->questions[] = $question;
                return 'Synthetic policy guidance.';
            }
        };
        $this->app->instance(AnthropicService::class, $provider);
        $this->actingAs(User::role('employee')->first());
        $first = $this->postJson(route('hr.chat.ask'), ['question' => 'I am contractual. What is wellness leave?'])->assertOk();
        $this->postJson(route('hr.chat.ask'), ['question' => 'Actually I am regular. What about this leave?',
            'conversation_id' => $first['conversation_id']])->assertOk();
        $this->assertStringNotContainsString('employment type contractual', end($provider->questions));
        $this->assertSame('regular', HrConversation::findOrFail($first['conversation_id'])->context_state['employment_type']);
    }

    public function test_citation_survives_chunk_replacement_and_file_link_is_hash_bound(): void
    {
        Storage::fake(config('filesystems.default'));
        Storage::put('policies/guide.pdf', '%PDF-1.4 synthetic');
        $document = $this->document('Wellness Leave', 'Wellness leave application instructions.');
        $this->app->instance(AnthropicService::class, new class extends AnthropicService {
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                return 'Synthetic answer citing the supplied excerpt.';
            }
        });
        $user = User::role('employee')->first();
        $answer = $this->actingAs($user)->postJson(route('hr.chat.ask'), ['question' => 'Wellness leave application?'])
            ->assertOk()->assertJsonPath('sources.0', 'Wellness Leave — Section 2');
        $log = HrChatLog::findOrFail($answer['log_id']);
        $this->assertSame('Wellness Leave', $log->source_snapshot[0]['title']);
        $oldUrl = $answer['source_details'][0]['url'];
        $this->get($oldUrl)->assertOk();
        $document->chunks()->delete();
        $document->update(['title' => 'Replacement', 'file_hash' => str_repeat('b', 64)]);
        $this->getJson(route('hr.chat.history'))->assertOk()->assertSee('Wellness Leave');
        $this->get($oldUrl)->assertNotFound();
    }
}
