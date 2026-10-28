<?php

namespace App\Services;

use App\Exceptions\PolicyChatException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function ask(string $question, string $policyContext, array $history = []): string
    {
        $key = config('services.gemini.key', '');
        $model = config('services.gemini.model', 'gemini-3-flash-preview');
        if (!$key || !is_string($model) || !preg_match('/^[a-zA-Z0-9.-]+$/D', $model)) { throw new PolicyChatException; }
        $contents = array_map(function ($message) {
            return ['role' => $message['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $message['content']]]];
        }, PolicyChatPrompt::messages($question, $history));
        try {
            $response = Http::timeout(60)->withOptions(['connect_timeout' => 10])->withHeaders([
                'x-goog-api-key' => $key,
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', [
                'systemInstruction' => ['parts' => [['text' => PolicyChatPrompt::instructions() . "\n\n" . PolicyChatPrompt::context($policyContext)]]],
                'contents' => $contents,
                'generationConfig' => ['maxOutputTokens' => 4096],
            ]);
            if (!$response->successful()) {
                Log::warning('[GeminiService] Request rejected.', ['status' => $response->status()]);
                throw new PolicyChatException;
            }
            $candidate = $response->json('candidates.0');
            if (!is_array($candidate) || ($candidate['finishReason'] ?? null) !== 'STOP'
                || $response->json('promptFeedback.blockReason') || !is_array($candidate['content']['parts'] ?? null)) { throw new PolicyChatException; }
            $parts = [];
            foreach ($candidate['content']['parts'] as $part) {
                if (is_array($part) && empty($part['thought']) && is_string($part['text'] ?? null)) { $parts[] = trim($part['text']); }
            }
            $text = trim(implode("\n\n", $parts));
            if ($text === '' || !mb_check_encoding($text, 'UTF-8')) { throw new PolicyChatException; }
            $usage = $response->json('usageMetadata');
            if (is_array($usage)) {
                Log::info('[GeminiService] Token usage', [
                    'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
                    'candidate_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
                    'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
                ]);
            }
            return $text;
        } catch (\Throwable $e) {
            Log::warning('[GeminiService] Answer unavailable.', ['error_type' => get_class($e)]);
            throw new PolicyChatException;
        }
    }
}
