<?php

namespace Tests\Feature;

use Backpack\Basset\Enums\StatusEnum;
use Backpack\Basset\Facades\Basset;
use Tests\TestCase;

class GlobalFontRenderingTest extends TestCase
{
    public function test_shared_head_renders_inter_as_a_direct_stylesheet_link(): void
    {
        // Isolate head rendering from unrelated asset downloads and cache writes.
        Basset::shouldReceive('basset')->andReturn(StatusEnum::IN_CACHE);

        $fontUrl = config('backpack.ui.font_styles')[0];
        $html = view('vendor.backpack.base.inc.head')->render();

        $this->assertStringContainsString('family=Inter:', $fontUrl);
        $this->assertStringContainsString('<link rel="stylesheet" href="'.e($fontUrl).'">', $html);
        $this->assertNotContains($fontUrl, config('backpack.ui.styles'));
    }
}
