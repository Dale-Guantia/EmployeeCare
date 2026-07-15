<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    protected $apiKey;
    protected $model = 'claude-haiku-4-5';

    // Static, reused across every request — this is the block cache_control
    // targets in ask(). Keep it free of anything that varies per question.
    protected $baseSystemPrompt = "You are an HR Policy Assistant for the City Government of Pasig.\n"
            . "CRITICAL: Always respond in the exact same language the user used to ask the question. If the user asks in Tagalog/Filipino, you MUST reply fluently in Tagalog/Filipino. If the question mixes English and Tagalog (Taglish), reply naturally in that same mixed style rather than forcing it into one language.\n"
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

        $dynamicSystemBlock = "\n\n## OFFICIAL HRDO POLICY DOCUMENTS:\n"
            . $this->truncatePolicyContext($policyContext);

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

                    // Two separate blocks: only the static instructions carry
                    // cache_control. The policy-context block changes on every
                    // question, so it's sent uncached — bundling it into the
                    // cached block would change that block's content per request
                    // and defeat caching entirely (every call becomes a cache miss).
                    'system' => [
                        [
                            'type'          => 'text',
                            'text'          => $this->baseSystemPrompt,
                            'cache_control' => ['type' => 'ephemeral'],
                        ],
                        [
                            'type' => 'text',
                            'text' => $dynamicSystemBlock,
                        ],
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

    // Truncates the dynamic policy-context block, not the fixed system prompt,
    // so the model's core instructions are never at risk of being clipped.
    // Uses mb_substr (not substr) because policy text is UTF-8 and can contain
    // multi-byte characters (curly quotes, em dashes, Tagalog diacritics) —
    // a byte-based cut can land mid-character and corrupt the JSON payload.
    protected function truncatePolicyContext(string $policyContext, int $maxChars = 11000): string
    {
        if (mb_strlen($policyContext) <= $maxChars) {
            return $policyContext;
        }

        $truncated = mb_substr($policyContext, 0, $maxChars);

        // Prefer cutting at a chunk boundary (PolicyRetriever::formatForPrompt
        // joins chunks with "\n\n---\n\n") so we never hand the model half of a
        // document's content with a dangling, incomplete source citation.
        $lastBoundary = mb_strrpos($truncated, "\n\n---\n\n");
        if ($lastBoundary !== false) {
            $truncated = mb_substr($truncated, 0, $lastBoundary);
        }

        return $truncated . "\n\n[Additional policy context truncated. Contact HRDO for the full document.]";
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
