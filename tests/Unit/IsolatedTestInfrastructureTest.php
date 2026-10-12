<?php

namespace Tests\Unit;

use App\Services\SqlDialectHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsolatedTestInfrastructureTest extends TestCase
{
    public function test_unfaked_http_cannot_reach_an_external_service()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('External HTTP requests must be faked in tests.');
        Http::get('https://example.test/never-contacted');
    }

    public function test_explicit_http_fakes_take_effect()
    {
        Http::fake(['https://example.test/*' => Http::response(['fixture' => true])]);
        $this->assertTrue(Http::get('https://example.test/fixture')->json('fixture'));
        Http::assertSentCount(1);
    }

    /** @dataProvider elapsedTimes */
    public function test_sqlite_elapsed_times_match_production_semantics($start, $end, $hours, $minutes)
    {
        $row = DB::query()->fromSub(DB::query()->selectRaw('? AS created_at, ? AS resolved_at', [$start, $end]), 'timing')
            ->selectRaw(SqlDialectHelper::diffHoursSql('created_at', 'resolved_at') . ' AS hours')
            ->selectRaw(SqlDialectHelper::diffMinutesSql('created_at', 'resolved_at') . ' AS minutes')
            ->first();
        $this->assertEquals($hours, $row->hours);
        $this->assertEquals($minutes, $row->minutes);
    }

    public function elapsedTimes(): array
    {
        return [
            ['2026-01-01 00:00:00', '2026-01-04 01:30:59', 73, 4410],
            ['2026-01-01 01:30:59', '2026-01-01 00:00:00', -1, -90],
            ['2026-01-01 00:00:00', null, null, null],
        ];
    }
}
