<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $model = 'gemini-3-flash-preview';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
    }

    protected $baseSystemPrompt = "You are an HR Policy Assistant for the City Government of Pasig.\n"
            . "Answer employee questions ONLY using the policy documents provided below.\n"
            . "If the answer is NOT in the documents but involves a general Philippine civil service law "
            . "(CSC rules, RA 6713, RA 2260), answer from your training knowledge.'\n"
            . "If the answer is not found at all, say: 'Please contact HRDO directly for this concern.'\n"
            . "Always cite the source document name in your answer.\n"
            . "Format responses clearly using **bold** for key terms and bullet points where helpful.";

    public function ask(string $question, string $policyContext): string
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiService] GEMINI_API_KEY is not set.');
            return 'Configuration error: API key missing.';
        }

        $fullSystemPrompt = $this->baseSystemPrompt
            . "\n\n## OFFICIAL HRDO POLICY DOCUMENTS:\n"
            . $policyContext;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $fullSystemPrompt . "\n\nUser Question: " . $question]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 4096, // Add this to force a larger output allowance
                    'temperature' => 0.2,      // Keep temperature low for factual HR policies
                ]
            ]);

            if ($response->failed()) {
                Log::error('[GeminiService] API call failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return $this->friendlyError($response->status(), $response->json());
            }

            // Log Gemini token usage metrics
            $usage = $response->json('usageMetadata');
            if ($usage) {
                Log::info('[GeminiService] Token usage', [
                    'prompt_tokens'     => $usage['promptTokenCount'] ?? 0,
                    'candidate_tokens'  => $usage['candidatesTokenCount'] ?? 0,
                    'total_tokens'      => $usage['totalTokenCount'] ?? 0,
                ]);
            }

            // Parse text response using Gemini's standard schema
            $text = $response->json('candidates.0.content.parts.0.text');

            if (empty($text)) {
                return 'I received an empty response. Please try again.';
            }

            return $text;

        } catch (\Exception $e) {
            Log::error('[GeminiService] Exception: ' . $e->getMessage());
            return 'Could not connect to the AI service. Please try again.';
        }
    }

    protected function friendlyError($status, $body)
    {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';

        switch ($status) {
            case 400: return "Bad request: {$msg}";
            case 401: return 'Authentication failed: invalid API key.';
            case 403: return 'Authentication failed: Invalid or unauthorized API key.';
            case 404: return "Model not found. Please check your AI service provider model version.";
            case 429: return 'Rate limited or quota exceeded. Please wait a moment and try again.';
            case 503: return 'AI service provider is currently experiencing downtime. Please try again later.';
            default:  return "AI service error (HTTP {$status}): {$msg}";
        }
    }
}
