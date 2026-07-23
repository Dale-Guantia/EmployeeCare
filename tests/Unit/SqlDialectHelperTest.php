<?php

namespace Tests\Unit;

use App\Services\SqlDialectHelper;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SqlDialectHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_diff_hours_sql_uses_datediff_on_sqlsrv()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
        DB::shouldReceive('connection')->andReturn($connection);

        $sql = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');

        $this->assertSame('DATEDIFF(HOUR, created_at, resolved_at)', $sql);
    }

    public function test_diff_hours_sql_uses_timestampdiff_on_mysql()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        DB::shouldReceive('connection')->andReturn($connection);

        $sql = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');

        $this->assertSame('TIMESTAMPDIFF(HOUR, created_at, resolved_at)', $sql);
    }

    public function test_diff_minutes_sql_uses_datediff_on_sqlsrv()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
        DB::shouldReceive('connection')->andReturn($connection);

        $sql = SqlDialectHelper::diffMinutesSql('tickets.created_at', 'tickets.resolved_at');

        $this->assertSame('DATEDIFF(MINUTE, tickets.created_at, tickets.resolved_at)', $sql);
    }
}
