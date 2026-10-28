<?php

namespace App\Services;

use App\Exceptions\PolicyChatException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    public function ask(string $question, string $policyContext, array $history = []): string
    {
        $key = config('services.anthropic.key', '');
        if (!$key) { throw new PolicyChatException; }
        try {
            // No automatic retries: a lost response may already have incurred a charge.
            $response = Http::timeout(60)->withOptions(['connect_timeout' => 10])->withHeaders([
                'x-api-key' => $key, 'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-haiku-5-5'),
                'max_tokens' => 4096,
                'system' => [
                    ['type' => 'text', 'text' => PolicyChatPrompt::instructions(), 'cache_control' => ['type' => 'ephemeral']],
                    ['type' => 'text', 'text' => PolicyChatPrompt::context($policyContext)],
                ],
                'messages' => PolicyChatPrompt::messages($question, $history),
            ]);
            if (!$response->successful()) {
                Log::warning('[AnthropicService] Request rejected.', ['status' => $response->status()]);
                throw new PolicyChatException;
            }
            $blocks = $response->json('content');
            $reason = $response->json('stop_reason');
            if (!is_array($blocks) || $reason !== 'end_turn') { throw new PolicyChatException; }
            $parts = [];
            foreach ($blocks as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) { $parts[] = trim($block['text']); }
            }
            $text = trim(implode("\n\n", $parts));
            if ($text === '' || !mb_check_encoding($text, 'UTF-8')) { throw new PolicyChatException; }
            $usage = $response->json('usage');
            if (is_array($usage)) {
                Log::info('[AnthropicService] Token usage', [
                    'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                    'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                    'cache_creation_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
                    'cache_read_tokens' => (int) ($usage['cache_read_input_tokens'] ?? 0),
                ]);
            }
            return $text;
        } catch (\Throwable $e) {
            // Do not log provider bodies, prompts, keys or transport exception messages.
            Log::warning('[AnthropicService] Answer unavailable.', ['error_type' => get_class($e)]);
            throw new PolicyChatException;
        }
    }
}
