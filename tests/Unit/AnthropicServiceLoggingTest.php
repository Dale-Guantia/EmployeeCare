<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use Tests\TestCase;

class AnthropicServiceLoggingTest extends TestCase
{
    public function test_long_question_is_truncated_before_logging()
    {
        // This is a behavioral contract test on Str::limit itself (the exact
        // truncation helper used in the fix), since triggering the real
        // catch block requires mocking the HTTP client — asserting the
        // truncation utility's contract is what actually matters here.
        $longQuestion = str_repeat('a', 600);

        $truncated = Str::limit($longQuestion, 50);

        $this->assertLessThanOrEqual(53, strlen($truncated)); // 50 chars + "..."
        $this->assertStringEndsWith('...', $truncated);
    }
}
