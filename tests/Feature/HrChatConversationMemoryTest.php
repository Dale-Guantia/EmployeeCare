<?php

namespace Tests\Feature;

use App\Models\HrChatLog;
use App\Models\HrConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HrChatConversationMemoryTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $tag = 'A'): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'name'      => "ChatUser{$tag}{$seq}",
            'username'  => "chat_user_{$tag}_{$seq}_" . uniqid(),
            'email'     => "chat_user_{$tag}_{$seq}_" . uniqid() . '@example.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('employee');

        return $user;
    }

    // Fakes the Anthropic endpoint and records every request payload sent to
    // it, so assertions can inspect exactly what was sent (message shape,
    // cache_control block, history length) without real network/API calls.
    // Returns an object (not array) so the closure can append to it by handle.
    private function fakeAnthropic(): \stdClass
    {
        $store = new \stdClass();
        $store->requests = [];

        Http::fake(function ($request) use ($store) {
            $store->requests[] = $request->data();
            $turn = count($store->requests);

            return Http::response([
                'content' => [['type' => 'text', 'text' => 'Fake answer #' . $turn]],
                'usage'   => [
                    'input_tokens'                => 10,
                    'output_tokens'               => 5,
                    'cache_creation_input_tokens' => $turn === 1 ? 50 : 0,
                    'cache_read_input_tokens'     => $turn > 1 ? 50 : 0,
                ],
            ], 200);
        });

        return $store;
    }

    // Memory is now scoped by conversation_id (an hr_conversations row), not
    // the Laravel browser session — callers that want a follow-up to carry
    // context must pass back the conversation_id an earlier call returned.
    private function ask(User $user, string $question, ?int $conversationId = null)
    {
        return $this->actingAs($user, 'web')
            ->postJson(route('hr.chat.ask'), array_filter([
                'question'        => $question,
                'conversation_id' => $conversationId,
            ], fn ($v) => $v !== null));
    }

    // T9 — first message of a conversation: empty history, request still succeeds.
    public function test_first_message_of_session_has_empty_history_and_succeeds()
    {
        $store = $this->fakeAnthropic();
        $user = $this->makeUser();

        $resp = $this->ask($user, 'Ilang araw pwede mag apply ng wellness leave?');

        $resp->assertOk();
        $this->assertCount(1, $store->requests);
        $this->assertCount(1, $store->requests[0]['messages']);
        $this->assertSame('user', $store->requests[0]['messages'][0]['role']);
        $this->assertNotNull($resp['conversation_id']);
    }

    // T1 (core assertion) — a bare follow-up must still resolve to Wellness
    // Leave once the subject was established earlier in the same conversation.
    // This exercises real DB retrieval (Root Cause B), only the Anthropic
    // call itself is faked.
    public function test_bare_followup_retrieves_wellness_leave_not_omnibus()
    {
        $store = $this->fakeAnthropic();
        $user = $this->makeUser();

        $r1 = $this->ask($user, 'Ilang araw pwede mag apply ng wellness leave?');
        $r1->assertOk();
        $this->assertContains('Wellness Leave', $this->titlesOnly($r1['sources']));
        $convId = $r1['conversation_id'];

        $r2 = $this->ask($user, 'Sino sino ang pwede mag apply?', $convId);
        $r2->assertOk();

        $r3 = $this->ask($user, 'Ilang araw pwede mag apply?', $convId);
        $r3->assertOk();

        // Core assertion: without conversation context this bare question
        // only ever retrieved the Omnibus Rule on Leave (wrong document).
        $this->assertContains('Wellness Leave', $this->titlesOnly($r3['sources']));

        $this->assertCount(3, $store->requests);
    }

    // T12 — topic switch: once the subject changes, the old topic must not
    // keep dragging retrieval back to the wrong (previous) document.
    public function test_topic_switch_does_not_stick_to_previous_topic()
    {
        $this->fakeAnthropic();
        $user = $this->makeUser();

        $r1 = $this->ask($user, 'Ilang araw pwede mag apply ng wellness leave?');
        $r1->assertOk();
        $convId = $r1['conversation_id'];

        $this->ask($user, 'Sino sino ang pwede mag apply?', $convId)->assertOk();

        $r3 = $this->ask($user, 'Paano mag-apply ng maternity leave?', $convId);
        $r3->assertOk();

        $this->assertNotContains('Wellness Leave', $this->titlesOnly($r3['sources']));
    }

    // T4-a (deterministic half) — grounding is not bypassed on a FIRST turn:
    // a question with zero matching policy content must return the fixed
    // "not found" answer and never call the AI at all. Note: once any prior
    // question in the conversation mentions a common word like "leave"
    // (nearly every chunk in this corpus is leave-related), Part 1's
    // retrieval expansion can legitimately surface *some* chunk for an
    // unrelated follow-up too — at that point grounding is enforced by the
    // system prompt telling the real model to refuse to answer from
    // irrelevant documents, which is a live-model behavior, not something a
    // fake HTTP response can verify. That half was checked separately via a
    // real API call (see the original memory-fix final report).
    public function test_grounding_not_bypassed_on_first_turn()
    {
        $store = $this->fakeAnthropic();
        $user = $this->makeUser();

        $resp = $this->ask($user, 'zzqxjklw ploible unicornify frobnitz');
        $resp->assertOk();

        $this->assertStringContainsString("couldn't find this", $resp['answer']);
        $this->assertSame([], $resp['sources']);
        $this->assertCount(0, $store->requests);
    }

    // T7 — retrieval-miss path must still log a conversation_id (it is a
    // real conversation turn even though the AI was never called).
    public function test_retrieval_miss_path_logs_conversation_id()
    {
        $this->fakeAnthropic();
        $user = $this->makeUser();

        $resp = $this->ask($user, 'asdfghjkl');
        $resp->assertOk();

        $log = HrChatLog::where('id', $resp['log_id'])->first();
        $this->assertNotNull($log);
        $this->assertSame($resp['conversation_id'], $log->conversation_id);
    }

    // T10 — audit trail integrity: hr_chat_logs.question stores the raw
    // question the employee typed, never the expanded retrieval query.
    public function test_audit_log_stores_raw_question_not_expanded_query()
    {
        $this->fakeAnthropic();
        $user = $this->makeUser();

        $r1 = $this->ask($user, 'Ilang araw pwede mag apply ng wellness leave?');
        $r1->assertOk();

        $r2 = $this->ask($user, 'Ilang araw pwede mag apply?', $r1['conversation_id']);
        $r2->assertOk();

        $log = HrChatLog::where('id', $r2['log_id'])->first();
        $this->assertSame('Ilang araw pwede mag apply?', $log->question);
    }

    // T6 — history cap: even after 6+ exchanges, at most 6 history messages
    // (3 exchanges) plus the current question are sent to the model.
    public function test_history_is_capped_at_six_messages()
    {
        $user = $this->makeUser();
        $conversation = HrConversation::create(['user_id' => $user->id, 'title' => 'Seeded']);

        // Seed 8 prior exchanges directly (bypassing the AI call) so the cap
        // is exercised deterministically without 8 real round-trips.
        for ($i = 1; $i <= 8; $i++) {
            HrChatLog::create([
                'user_id'         => $user->id,
                'conversation_id' => $conversation->id,
                'question'        => "Seed question {$i}?",
                'answer'          => "Seed answer {$i}.",
            ]);
        }

        $store = $this->fakeAnthropic();
        $resp = $this->ask($user, 'Ilang araw pwede mag apply ng wellness leave?', $conversation->id);
        $resp->assertOk();

        $messages = $store->requests[0]['messages'];
        $this->assertLessThanOrEqual(7, count($messages));
        $this->assertSame('user', $messages[0]['role']);
    }

    // T8 — no cross-user leakage: user B's history must contain zero rows
    // from user A. Memory is now scoped by conversation_id (each first ask()
    // creates its own conversation), so this is naturally guaranteed rather
    // than depending on browser-session bookkeeping — this test confirms
    // that guarantee holds even with A's conversation active in the DB.
    public function test_no_cross_user_history_leakage()
    {
        $store = $this->fakeAnthropic();

        $userA = $this->makeUser('A');
        $rA1 = $this->ask($userA, 'Ilang araw pwede mag apply ng wellness leave?');
        $rA1->assertOk();
        $this->ask($userA, 'Sino sino ang pwede mag apply?', $rA1['conversation_id'])->assertOk();

        // A real second actor has their own session — flush this test's
        // shared session store before switching the acting user (mirrors
        // TicketReassignmentTest::dispatch()'s handling of the same issue;
        // needed for actingAs()-switching correctness, independent of the
        // conversation_id scoping change).
        $this->app['session']->flush();

        $userB = $this->makeUser('B');
        $this->ask($userB, 'Ilang araw pwede mag apply?')->assertOk();

        $lastRequest = end($store->requests);
        // Only the current question — no user A content should appear.
        $this->assertCount(1, $lastRequest['messages']);
        $this->assertSame('Ilang araw pwede mag apply?', $lastRequest['messages'][0]['content']);
    }

    // T11 — prompt caching survives history: the cached system block (index 0,
    // with cache_control) must be byte-identical across turns. If history ever
    // leaked into that block, this would fail (and so would real cache hits).
    public function test_cached_system_block_is_unaffected_by_history()
    {
        $store = $this->fakeAnthropic();
        $user = $this->makeUser();

        $r1 = $this->ask($user, 'Ilang araw pwede mag apply ng wellness leave?');
        $r1->assertOk();
        $this->ask($user, 'Sino sino ang pwede mag apply?', $r1['conversation_id'])->assertOk();

        $turn1System = $store->requests[0]['system'][0];
        $turn2System = $store->requests[1]['system'][0];

        $this->assertSame($turn1System, $turn2System);
        $this->assertSame('ephemeral', $turn1System['cache_control']['type']);

        // History must live in `messages`, never merged into `system`.
        $this->assertCount(1, $store->requests[0]['messages']);
        $this->assertGreaterThan(1, count($store->requests[1]['messages']));
    }

    private function titlesOnly(array $sources): array
    {
        // sources are formatted as "Title" or "Title — Section"; compare by title only.
        return array_map(function ($s) {
            return trim(explode(' — ', $s)[0]);
        }, $sources);
    }
}
