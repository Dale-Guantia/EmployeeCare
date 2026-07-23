<?php

namespace Tests\Unit;

use App\Models\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StatusIdsByNameTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ids_by_name_returns_the_real_seeded_statuses()
    {
        $map = Status::idsByName();

        $this->assertTrue($map->has('Resolved'));
        $this->assertTrue($map->has('Pending'));
        $this->assertTrue($map->has('Unassigned'));
        $this->assertTrue($map->has('Reopened'));
    }

    public function test_id_by_name_returns_an_int_matching_a_direct_query()
    {
        $direct = (int) Status::where('status_name', 'Resolved')->value('id');

        $this->assertSame($direct, Status::idByName('Resolved'));
    }

    public function test_id_by_name_returns_null_for_unknown_name()
    {
        $this->assertNull(Status::idByName('NotARealStatus_' . uniqid()));
    }

    public function test_cache_is_invalidated_when_a_status_is_saved()
    {
        Cache::forget('ref:statuses.by_name');
        Status::idsByName(); // warm the cache

        $newStatus = Status::create(['status_name' => 'TestCacheInvalidation_' . uniqid(), 'status_color' => '#ccc']);

        $map = Status::idsByName();

        $this->assertTrue($map->has($newStatus->status_name));

        $newStatus->delete();
    }
}
