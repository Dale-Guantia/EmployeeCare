<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DepartmentDivisionCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_all_cached_matches_a_direct_query()
    {
        $direct = Department::all()->pluck('id')->sort()->values();
        $cached = Department::allCached()->pluck('id')->sort()->values();

        $this->assertEquals($direct, $cached);
    }

    public function test_department_cache_invalidates_on_create()
    {
        Cache::forget(Department::CACHE_KEY);
        Department::allCached();

        $newDept = Department::create(['department_name' => 'CacheTestDept_' . uniqid()]);

        $this->assertTrue(Department::allCached()->pluck('id')->contains($newDept->id));

        $newDept->delete();
    }

    public function test_division_all_cached_matches_a_direct_query()
    {
        $direct = Division::all()->pluck('id')->sort()->values();
        $cached = Division::allCached()->pluck('id')->sort()->values();

        $this->assertEquals($direct, $cached);
    }
}
