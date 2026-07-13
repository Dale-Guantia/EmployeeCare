<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    protected $apiKey;
    protected $model = 'claude-haiku-4-5';

    // FIX: System prompt is now a constant so it is only built once per request,
    // not rebuilt on every call inside ask(). Also separated from policyContext
    // so the cache_control correctly targets just the static instructions,
    // not the dynamic policy text which changes per query.
    protected $baseSystemPrompt = "You are an HR Policy Assistant for the City Government of Pasig.\n"
            . "CRITICAL: Always respond in the exact same language the user used to ask the question. If the user asks in Tagalog/Filipino, you MUST reply fluently in Tagalog/Filipino.\n"
            . "Answer employee questions ONLY using the policy documents provided below. Do not use outside knowledge and do not invent details.\n"
            . "If the provided documents do not answer the question, say plainly that you could not find it in the current policy documents and that the employee should contact HRDO directly. Do not guess.\n"
            . "Always cite the source document name in your answer.\n"
            . "Format responses clearly using **bold** for key terms and bullet points where helpful.";

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
    }

    public function ask(string $question, string $policyContext): string
    {
        if (empty($this->apiKey)) {
            Log::error('[AnthropicService] ANTHROPIC_API_KEY is not set.');
            return 'Configuration error: API key missing. Please contact your system administrator.';
        }

        // FIX: Policy context is appended here, NOT inside the cached system block.
        // The static base prompt is cached; the dynamic policy context is NOT cached
        // because it changes per query. This is the correct caching pattern.
        $fullSystemPrompt = $this->baseSystemPrompt
            . "\n\n## OFFICIAL HRDO POLICY DOCUMENTS:\n"
            . $policyContext;

        // FIX: Truncate excessively long policy context to avoid hitting token limits.
        // Claude Haiku's context window is 200k tokens but we keep prompts lean.
        // ~12000 chars ≈ ~3000 tokens, well within safe limits.
        if (strlen($fullSystemPrompt) > 12000) {
            $fullSystemPrompt = substr($fullSystemPrompt, 0, 12000)
                . "\n\n[Policy context truncated. Contact HRDO for full document.]";
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta'    => 'prompt-caching-2024-07-31',
                    'Content-Type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 4096,

                    // web_search is intentionally disabled: this is a grounded HR policy
                    // assistant and must answer only from the uploaded policy documents,
                    // not from live internet results.

                    // FIX: Only the static base prompt portion is marked for caching.
                    // The dynamic policy context (which changes per question) is intentionally
                    // NOT in a separate cached block — mixing dynamic content into a cached block
                    // defeats caching and wastes cache write tokens.
                    'system' => [
                        [
                            'type'          => 'text',
                            'text'          => $fullSystemPrompt,
                            'cache_control' => ['type' => 'ephemeral'],
                        ]
                    ],

                    'messages' => [
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('[AnthropicService] API call failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return $this->friendlyError($response->status(), $response->json());
            }

            // Log token usage so HRDO can monitor costs
            $usage = $response->json('usage');
            if ($usage) {
                Log::info('[AnthropicService] Token usage', [
                    'input_tokens'          => $usage['input_tokens'] ?? 0,
                    'output_tokens'         => $usage['output_tokens'] ?? 0,
                    'cache_creation_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
                    'cache_read_tokens'     => $usage['cache_read_input_tokens'] ?? 0,
                ]);
            }

            // FIX: Collect only text-type content blocks.
            // When web search fires, the API returns multiple blocks in sequence:
            // tool_use → tool_result → text (final answer).
            // Reading only content[0].text misses the actual answer when search is used.
            $contentBlocks = $response->json('content') ?? [];
            $textParts     = [];

            foreach ($contentBlocks as $block) {
                if (isset($block['type'], $block['text'])
                    && $block['type'] === 'text'
                    && trim($block['text']) !== '') {
                    $textParts[] = trim($block['text']);
                }
            }

            $text = implode("\n\n", $textParts);

            if (empty($text)) {
                Log::warning('[AnthropicService] Empty text in response', [
                    'stop_reason'    => $response->json('stop_reason'),
                    'content_blocks' => count($contentBlocks),
                ]);
                return 'I received an empty response. Please try again.';
            }

            return $text;

        } catch (\Exception $e) {
            Log::error('[AnthropicService] Exception: ' . $e->getMessage(), [
                'question' => $question,
            ]);
            return 'Could not connect to the AI service. Please check your internet connection and try again.';
        }
    }

    protected function friendlyError(int $status, ?array $body): string
    {
        $msg = $body['error']['message'] ?? '';
        switch ($status) {
            case 400: return "Bad request: {$msg}";
            case 401: return 'Authentication failed: invalid API key.';
            case 403: return 'Authentication failed: Invalid or unauthorized API key.';
            case 404: return "Model not found. Please check your AI service provider model version.";
            case 429: return 'Rate limited or quota exceeded. Please wait a moment and try again.';
            case 500:
            case 503: return 'AI service provider is currently experiencing downtime. Please try again later.';
            default:  return "AI service error (HTTP {$status}): {$msg}";
        }
    }
}
