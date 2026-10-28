<?php

namespace Tests\Feature;

use App\Models\HrPolicyChunk;
use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\PolicyRetriever;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

class HrChatRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeRetriever(): void
    {
        $this->app->instance(PolicyRetriever::class, new class extends PolicyRetriever {
            public function __construct() {}
            public function retrieve(string $question, int $limit = 5): Collection
            {
                $doc = new HrPolicyDocument(['title' => 'Wellness Leave']);
                $chunk = new HrPolicyChunk([
                    'section_title' => null,
                    'content'       => 'Wellness Leave: max 5 days.',
                ]);
                $chunk->id = 1;
                $chunk->setRelation('document', $doc);
                return collect([$chunk]);
            }
            public function formatForPrompt(Collection $chunks): string
            {
                return '[Source: Wellness Leave] Wellness Leave: max 5 days.';
            }
        });
    }

    private function fakeAi(): void
    {
        $fake = new class extends AnthropicService {
            public function __construct() {}
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                return 'Wellness Leave allows up to 5 days. [Source: Wellness Leave]';
            }
        };
        $this->app->instance(AnthropicService::class, $fake);
    }

    public function test_ask_endpoint_is_throttled_after_configured_limit()
    {
        $this->fakeRetriever();
        $this->fakeAi();

        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertOk();
        }

        $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])
            ->assertStatus(429)->assertHeader('Retry-After');

        // Hitting the question limit must not replace the page with a 429.
        $this->get(route('hr.chat.index'))->assertOk();
        $this->getJson(route('hr.chat.conversations'))->assertOk();
        $this->getJson(route('hr.chat.history'))->assertOk();

        $this->travel(61)->seconds();
        $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertOk();
    }

    public function test_normal_conversation_activity_does_not_consume_question_allowance()
    {
        $this->actingAs(User::role('admin')->first(), 'web');

        // Each turn refreshes the sidebar; switching chats also loads a transcript.
        $conversation = \App\Models\HrConversation::create([
            'user_id' => auth()->id(), 'title' => 'Rate limit regression',
        ]);
        for ($i = 0; $i < 21; $i++) {
            $this->getJson(route('hr.chat.conversations'))->assertOk();
            $this->getJson(route('hr.chat.conversation.messages', $conversation->id))->assertOk();
            $this->getJson(route('hr.chat.history'))->assertOk();
        }

        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertOk();
        }
        $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertStatus(429);
    }

    public function test_conversation_requests_remain_limited_without_blocking_page_or_questions()
    {
        $this->actingAs(User::role('admin')->first(), 'web');

        for ($i = 0; $i < 120; $i++) {
            $this->getJson(route('hr.chat.conversations'))->assertOk();
        }
        $this->getJson(route('hr.chat.history'))->assertStatus(429)->assertHeader('Retry-After');
        $this->get(route('hr.chat.index'))->assertOk();
        $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertOk();
    }

    public function test_users_on_the_same_ip_have_separate_question_limits()
    {
        $this->actingAs(User::role('admin')->first(), 'web');
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertOk();
        }
        $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertStatus(429);

        $this->flushSession();
        $this->actingAs(User::role('employee')->first(), 'web');
        $this->postJson(route('hr.chat.ask'), ['question' => 'hi'])->assertOk();
    }
}
