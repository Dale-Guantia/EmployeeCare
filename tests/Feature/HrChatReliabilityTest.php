<?php

namespace Tests\Feature;

use App\Models\HrChatLog;
use App\Models\HrConversation;
use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\PolicyRetriever;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HrChatReliabilityTest extends TestCase
{
    private function document(string $title, string $content, string $status = 'active', bool $active = true)
    {
        $document = HrPolicyDocument::create(['title' => $title, 'filename' => 'fixture.pdf', 'file_path' => 'policies/fixture.pdf',
            'status' => $status, 'is_active' => $active, 'category' => 'general']);
        $document->chunks()->create(['content' => $content, 'search_vector' => '', 'chunk_index' => 0, 'page_number' => 1]);
        return $document;
    }

    private function user()
    {
        return User::role('employee')->first();
    }

    public function test_failed_provider_is_not_persisted_as_a_successful_answer()
    {
        $this->document('Leave rules', 'Wellness leave applications are permitted.');
        config(['services.anthropic.key' => 'mock-key']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'SECRET provider prompt']], 429)]);
        $this->actingAs($this->user())->postJson(route('hr.chat.ask'), ['question' => 'Wellness leave?'])
            ->assertStatus(503)->assertJson(['sources' => [], 'log_id' => null])->assertDontSee('SECRET');
        $this->assertSame(0, HrChatLog::count());
        Http::assertSentCount(1);
    }

    public function test_gemini_runs_the_unchanged_controller_flow_when_only_its_dependency_is_substituted()
    {
        $this->document('Leave rules', 'Wellness leave applications are permitted.');
        config(['services.gemini.key' => 'mock-gemini']);
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'Synthetic Gemini answer']]]]]])]);
        $this->app->instance(\App\Http\Controllers\Admin\HrChatController::class,
            new class(new PolicyRetriever, new \App\Services\GeminiService) extends \App\Http\Controllers\Admin\HrChatController {
                public function __construct(PolicyRetriever $retriever, \App\Services\GeminiService $ai)
                {
                    $this->retriever = $retriever; $this->ai = $ai;
                }
            });
        $response = $this->actingAs($this->user())->postJson(route('hr.chat.ask'), ['question' => 'Wellness leave?']);
        $response->assertOk()->assertJson(['answer' => 'Synthetic Gemini answer', 'sources' => ['Leave rules']]);
        $this->postJson(route('hr.chat.ask'), ['question' => 'How many days?', 'conversation_id' => $response['conversation_id']])->assertOk();
        Http::assertSent(function ($request) {
            return count($request->data()['contents']) === 3 && $request->data()['contents'][1]['role'] === 'model';
        });
        $this->assertSame(2, HrChatLog::count());
    }

    public function test_suggestions_are_private_and_clear_includes_legacy_logs()
    {
        $own = $this->user();
        $other = User::role('admin')->first();
        HrChatLog::create(['user_id' => $own->id, 'question' => 'My private question', 'answer' => 'Own answer']);
        HrChatLog::create(['user_id' => $other->id, 'question' => 'Other private question', 'answer' => 'Other answer']);
        $this->actingAs($own)->get(route('hr.chat.index'))->assertOk()->assertViewHas('topPrompts', ['My private question']);
        $this->deleteJson(route('hr.chat.clear_history'))->assertOk();
        $this->assertSame(0, HrChatLog::where('user_id', $own->id)->count());
        $this->assertSame(1, HrChatLog::where('user_id', $other->id)->count());
    }

    public function test_topic_change_and_unicode_punctuation_do_not_reuse_stale_subjects()
    {
        $this->document('Leave', 'Wellness leave wellness leave applications.');
        $this->document('Benefits', 'Maternity benefits maternity benefits eligibility.');
        $this->document('Filipino', 'Patakaran tungkol sa benepisyo at pag-aáral para sa empleyado.');
        $retriever = new PolicyRetriever;
        $this->assertSame(['Benefits'], $retriever->retrieveForConversation('Maternity-benefits?', ['Wellness leave?'])->pluck('document.title')->unique()->all());
        $this->assertSame(['Leave'], $retriever->retrieveForConversation('How many days?', ['Wellness leave?'])->pluck('document.title')->unique()->all());
        $this->assertSame(['Leave'], $retriever->retrieveForConversation('Paano mag-apply?', ['Wellness leave?'])->pluck('document.title')->unique()->all());
        $this->assertSame(['Filipino'], $retriever->retrieve('Ano ang pag-aáral?')->pluck('document.title')->unique()->all());
        $this->assertCount(0, $retriever->retrieveForConversation('Astronomy planets?', ['Wellness leave?']));
        $this->assertCount(0, $retriever->retrieve('!!!'));
        $this->assertCount(0, $retriever->retrieveForConversation('!!!', ['Wellness leave?']));
    }

    /** @dataProvider unavailablePolicies */
    public function test_unavailable_documents_are_excluded_even_if_chunks_remain($status, $active)
    {
        $this->document('Unavailable', 'Wellness leave applications.', $status, $active);
        $this->assertCount(0, (new PolicyRetriever)->retrieve('Wellness leave?'));
    }

    public static function unavailablePolicies(): array
    {
        return [['pending', true], ['processing', true], ['failed', true], ['needs_review', true], ['inactive', false], ['active', false]];
    }

    public function test_only_chunks_in_the_bounded_prompt_are_cited()
    {
        $this->document('First', 'wellness ' . str_repeat('é', 7000));
        $this->document('Second', 'wellness ' . str_repeat('ñ', 7000));
        $retriever = new PolicyRetriever;
        $selected = $retriever->promptChunks($retriever->retrieve('wellness'));
        $this->assertCount(1, $selected);
        $this->assertSame('First', $selected[0]->document->title);
        $this->assertStringNotContainsString('Second', $retriever->formatForPrompt($selected));
    }

    public function test_deleting_a_conversation_during_provider_call_does_not_create_orphan_logs()
    {
        $this->document('Leave', 'Wellness leave application.');
        $this->app->instance(AnthropicService::class, new class extends AnthropicService {
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                HrConversation::query()->delete();
                return 'Synthetic answer';
            }
        });
        $this->actingAs($this->user())->postJson(route('hr.chat.ask'), ['question' => 'Wellness leave?'])->assertStatus(409);
        $this->assertSame(0, HrChatLog::count());
    }

    public function test_inactive_and_unauthenticated_accounts_cannot_use_chat_endpoints()
    {
        $this->postJson(route('hr.chat.ask'), ['question' => 'Leave?'])->assertStatus(401);
        $user = $this->user();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->postJson(route('hr.chat.ask'), ['question' => 'Leave?'])->assertStatus(401);
    }

    public function test_history_and_feedback_cannot_read_or_modify_another_users_log()
    {
        $own = $this->user();
        $other = User::role('admin')->first();
        $ownLog = HrChatLog::create(['user_id' => $own->id, 'question' => 'Own question', 'answer' => 'Own answer']);
        $otherLog = HrChatLog::create(['user_id' => $other->id, 'question' => 'PRIVATE other question', 'answer' => 'Other answer']);
        $this->actingAs($own)->getJson(route('hr.chat.history'))->assertOk()->assertJsonCount(1)->assertDontSee('PRIVATE');
        $this->postJson(route('hr.chat.feedback'), ['log_id' => $otherLog->id, 'feedback' => 1])->assertOk()->assertJson(['ok' => false]);
        $this->assertNull($otherLog->fresh()->feedback);
        $this->postJson(route('hr.chat.feedback'), ['log_id' => $ownLog->id, 'feedback' => -1])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(-1, $ownLog->fresh()->feedback);
    }

    public function test_validation_rejects_empty_overlong_utf8_questions_and_invalid_identifiers()
    {
        Http::fake();
        $this->actingAs($this->user());
        foreach ([['question' => '   '], ['question' => str_repeat('ñ', 601)], ['question' => 'Leave?', 'conversation_id' => -1]] as $data) {
            $this->postJson(route('hr.chat.ask'), $data)->assertStatus(422);
        }
        Http::assertNothingSent();
        $this->assertSame(0, HrChatLog::count());
    }

    public function test_star_and_unstar_persist_through_the_owned_conversation_endpoint()
    {
        $user = $this->user();
        $conversation = HrConversation::create(['user_id' => $user->id, 'title' => 'Synthetic chat']);
        $this->actingAs($user);
        foreach ([true, false] as $starred) {
            $this->patchJson(route('hr.chat.conversation.update', $conversation->id), ['is_starred' => $starred])
                ->assertOk()->assertJson(['starred' => $starred]);
            $this->assertSame($starred, $conversation->fresh()->is_starred);
        }
    }

    public function test_unavailable_policy_schema_returns_failure_instead_of_a_successful_retrieval_miss()
    {
        // The test bootstrap has already guarded this disposable SQLite :memory: schema.
        \Tests\Support\IsolatedDatabase::assertSafeConfiguration(config('database.default'), config('database.connections.sqlite'));
        \Illuminate\Support\Facades\Schema::drop('hr_policy_chunks');
        Http::fake();
        $this->actingAs($this->user())->postJson(route('hr.chat.ask'), ['question' => 'Wellness leave?'])
            ->assertStatus(500)->assertJson(['sources' => [], 'log_id' => null]);
        $this->assertSame(0, HrChatLog::count());
        Http::assertNothingSent();
    }
}
