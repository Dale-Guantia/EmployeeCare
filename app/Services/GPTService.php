<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GPTService
{
    protected $apiKey;
    protected $model = 'openai/gpt-oss-120b:free'; // OpenRouter free MoE reasoning model

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.key', '');
    }

    public function ask(string $question, string $policyContext): string
    {
        if (empty($this->apiKey)) {
            Log::error('[GPTService] OPENROUTER_API_KEY is not set.');
            return 'Configuration error: API key missing.';
        }

        $systemPrompt = "You are the official HR Policy Assistant of the City Government of Pasig HRDO.\n"
            . "Answer employee questions ONLY using the policy documents provided below.\n"
            . "If the answer is NOT in the documents but involves a general Philippine civil service law "
            . "(CSC rules, RA 6713, RA 2260), answer from your training knowledge and note: "
            . "'Based on general CSC rules — verify with HRDO for Pasig-specific application.'\n"
            . "If the answer is not found at all, say: 'Please contact HRDO directly for this concern.'\n"
            . "Always cite the source document name in your answer.\n"
            . "Format responses clearly using **bold** for key terms and bullet points where helpful.\n\n"
            . "POLICY DOCUMENTS:\n"
            . $policyContext;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization'      => 'Bearer ' . $this->apiKey,
                    'HTTP-Referer'       => config('app.url', 'http://localhost'), // Highly recommended by OpenRouter for ranking apps
                    'X-OpenRouter-Title' => config('app.name', 'Pasig HR Assistant'), // Shows your app name in the OpenRouter dashboard
                    'Content-Type'       => 'application/json',
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model'      => $this->model,
                    'max_tokens' => 1024,
                    'messages'   => [
                        [
                            'role'    => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role'    => 'user',
                            'content' => $question,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('[GPTService] API call failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return $this->friendlyError($response->status(), $response->json());
            }

            // Log OpenAI-style token usage metrics
            $usage = $response->json('usage');
            if ($usage) {
                Log::info('[GPTService] Token usage', [
                    'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens'      => $usage['total_tokens'] ?? 0,
                ]);
            }

            // Parse text response using OpenAI standard formatting path
            $text = $response->json('choices.0.message.content');

            if (empty($text)) {
                return 'I received an empty response. Please try again.';
            }

            return $text;

        } catch (\Exception $e) {
            Log::error('[GPTService] Exception: ' . $e->getMessage());
            return 'Could not connect to the AI service. Please try again.';
        }
    }

    protected function friendlyError($status, $body)
    {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : '';
        switch ($status) {
            case 401: return 'Authentication failed: invalid OpenRouter API key.';
            case 403: return 'Access forbidden. Please verify your OpenRouter limits or account balance.';
            case 400: return "Bad request: {$msg}";
            case 429: return 'Rate limited. Please wait a moment and try again.';
            default:  return "AI service error (HTTP {$status}): {$msg}";
        }
    }
}
