<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\IsolatedDatabase;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_in_memory_database_is_allowed()
    {
        IsolatedDatabase::assertSafeConfiguration('sqlite', ['database' => ':memory:', 'url' => null]);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider unsafeConnections */
    public function test_application_connections_are_rejected($connection, $sqlite)
    {
        $this->expectException(\LogicException::class);
        IsolatedDatabase::assertSafeConfiguration($connection, $sqlite);
    }

    public function unsafeConnections(): array
    {
        return [
            ['mysql', ['database' => ':memory:']],
            ['sqlite', ['database' => 'application.sqlite']],
            ['sqlite', ['database' => ':memory:', 'url' => 'mysql://example.test/application']],
        ];
    }
}
