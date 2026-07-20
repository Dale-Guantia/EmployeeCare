<?php

namespace Tests\Feature;

use App\Models\HrChatLog;
use App\Models\HrConversation;
use App\Models\HrPolicyChunk;
use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\PolicyRetriever;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

class HrConversationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $tag = 'A'): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'name'      => "ConvUser{$tag}{$seq}",
            'username'  => "conv_user_{$tag}_{$seq}_" . uniqid(),
            'email'     => "conv_user_{$tag}_{$seq}_" . uniqid() . '@example.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('employee');

        return $user;
    }

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

    private function fakeAi()
    {
        $fake = new class extends AnthropicService {
            public array $lastHistory = [];
            public function __construct() {}
            public function ask(string $question, string $policyContext, array $history = []): string
            {
                $this->lastHistory = $history;
                return 'Wellness Leave allows up to 5 days. [Source: Wellness Leave]';
            }
        };
        $this->app->instance(AnthropicService::class, $fake);
        return $fake;
    }

    private function ask(User $user, string $question, ?int $conversationId = null)
    {
        return $this->actingAs($user, 'web')->postJson(route('hr.chat.ask'), array_filter([
            'question'        => $question,
            'conversation_id' => $conversationId,
        ], fn ($v) => $v !== null));
    }

    public function test_a_new_chat_creates_exactly_one_conversation_titled_from_the_first_question(): void
    {
        $this->fakeRetriever();
        $this->fakeAi();
        $user = $this->makeUser();

        $res = $this->ask($user, 'What is wellness leave?');

        $res->assertOk()->assertJsonStructure(['answer', 'sources', 'log_id', 'conversation_id']);
        $this->assertSame(1, HrConversation::where('user_id', $user->id)->count());

        $conv = HrConversation::where('user_id', $user->id)->first();
        $this->assertSame($res['conversation_id'], $conv->id);
        $this->assertStringContainsString('wellness', strtolower($conv->title));
        $this->assertSame(1, HrChatLog::where('conversation_id', $conv->id)->count());
    }

    public function test_follow_up_in_same_conversation_attaches_and_carries_history(): void
    {
        $this->fakeRetriever();
        $ai = $this->fakeAi();
        $user = $this->makeUser();

        $first  = $this->ask($user, 'What is wellness leave?');
        $convId = $first['conversation_id'];

        $this->ask($user, 'How can I apply it?', $convId)->assertOk();

        $this->assertSame(1, HrConversation::where('user_id', $user->id)->count());
        $this->assertSame(2, HrChatLog::where('conversation_id', $convId)->count());

        $this->assertNotEmpty($ai->lastHistory);
        $joined = collect($ai->lastHistory)->pluck('content')->implode(' ');
        $this->assertStringContainsString('wellness leave', strtolower($joined));
    }

    public function test_a_brand_new_conversation_has_no_memory_of_the_previous_one(): void
    {
        $this->fakeRetriever();
        $ai = $this->fakeAi();
        $user = $this->makeUser();

        $first = $this->ask($user, 'What is wellness leave?');
        $this->ask($user, 'How can I apply it?', $first['conversation_id']);

        // No conversation_id sent — this is a genuinely new chat.
        $this->ask($user, 'How can I apply it?')->assertOk();

        $this->assertEmpty($ai->lastHistory);
        $this->assertSame(2, HrConversation::where('user_id', $user->id)->count());
    }

    public function test_raw_question_is_stored_not_the_expanded_retrieval_string(): void
    {
        $this->fakeRetriever();
        $this->fakeAi();
        $user = $this->makeUser();

        $first  = $this->ask($user, 'What is wellness leave?');
        $convId = $first['conversation_id'];
        $this->ask($user, 'How can I apply it?', $convId);

        $this->assertDatabaseHas('hr_chat_logs', [
            'conversation_id' => $convId,
            'question'        => 'How can I apply it?',
        ]);
    }

    public function test_conversations_endpoint_orders_starred_first(): void
    {
        $user = $this->makeUser();

        $older = HrConversation::create(['user_id' => $user->id, 'title' => 'Older', 'last_message_at' => now()->subDay()]);
        HrConversation::create(['user_id' => $user->id, 'title' => 'Newer', 'last_message_at' => now()]);

        $this->actingAs($user, 'web')
            ->patchJson(route('hr.chat.conversation.update', $older), ['is_starred' => true])
            ->assertOk();

        $ids = collect(
            $this->actingAs($user, 'web')->getJson(route('hr.chat.conversations'))->json()
        )->pluck('id')->all();

        $this->assertSame($older->id, $ids[0]);
    }

    public function test_rename_updates_title_without_touching_the_logs(): void
    {
        $this->fakeRetriever();
        $this->fakeAi();
        $user = $this->makeUser();

        $res    = $this->ask($user, 'What is wellness leave?');
        $convId = $res['conversation_id'];

        $this->actingAs($user, 'web')
            ->patchJson(route('hr.chat.conversation.update', $convId), ['title' => 'Leave questions'])
            ->assertOk();

        $this->assertSame('Leave questions', HrConversation::find($convId)->title);
        $this->assertDatabaseHas('hr_chat_logs', ['conversation_id' => $convId, 'question' => 'What is wellness leave?']);
    }

    public function test_delete_removes_the_conversation_and_its_logs_only(): void
    {
        $this->fakeRetriever();
        $this->fakeAi();
        $user = $this->makeUser();

        $keep = $this->ask($user, 'Keep me')['conversation_id'];
        $drop = $this->ask($user, 'Delete me')['conversation_id'];

        $this->actingAs($user, 'web')
            ->deleteJson(route('hr.chat.conversation.delete', $drop))
            ->assertOk();

        $this->assertDatabaseMissing('hr_conversations', ['id' => $drop]);
        $this->assertSame(0, HrChatLog::where('conversation_id', $drop)->count());
        $this->assertDatabaseHas('hr_conversations', ['id' => $keep]);
    }

    public function test_a_user_cannot_read_rename_or_delete_another_users_conversation(): void
    {
        $userA = $this->makeUser('A');
        $userB = $this->makeUser('B');
        $othersConv = HrConversation::create(['user_id' => $userA->id, 'title' => 'Private']);

        $this->actingAs($userB, 'web')->getJson(route('hr.chat.conversation.messages', $othersConv))->assertNotFound();
        $this->actingAs($userB, 'web')->patchJson(route('hr.chat.conversation.update', $othersConv), ['title' => 'Hacked'])->assertNotFound();
        $this->actingAs($userB, 'web')->deleteJson(route('hr.chat.conversation.delete', $othersConv))->assertNotFound();

        $ids = collect(
            $this->actingAs($userB, 'web')->getJson(route('hr.chat.conversations'))->json()
        )->pluck('id')->all();
        $this->assertNotContains($othersConv->id, $ids);
    }

    public function test_clear_history_wipes_only_the_current_users_conversations(): void
    {
        $this->fakeRetriever();
        $this->fakeAi();
        $userA = $this->makeUser('A');
        $userB = $this->makeUser('B');
        $othersConv = HrConversation::create(['user_id' => $userB->id, 'title' => 'Theirs']);

        $this->ask($userA, 'Mine one');
        $this->ask($userA, 'Mine two');

        $this->actingAs($userA, 'web')->deleteJson(route('hr.chat.clear_history'))->assertOk();

        $this->assertSame(0, HrConversation::where('user_id', $userA->id)->count());
        $this->assertDatabaseHas('hr_conversations', ['id' => $othersConv->id]);
    }

    public function test_unknown_conversation_id_starts_a_fresh_conversation_instead_of_erroring(): void
    {
        $this->fakeRetriever();
        $this->fakeAi();
        $user = $this->makeUser();

        $res = $this->ask($user, 'What is wellness leave?', 999999);

        $res->assertOk();
        $this->assertNotSame(999999, $res['conversation_id']);
        $this->assertSame(1, HrConversation::where('user_id', $user->id)->count());
    }
}
