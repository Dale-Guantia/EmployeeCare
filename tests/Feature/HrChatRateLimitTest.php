<?php

namespace Tests\Feature;

use App\Models\HrPolicyChunk;
use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\PolicyRetriever;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HrChatRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('hr-chat');
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

        $lastResponse = null;
        for ($i = 0; $i < 21; $i++) {
            $lastResponse = $this->postJson(route('hr.chat.ask'), ['question' => 'hi']);
        }

        $lastResponse->assertStatus(429);
    }
}
