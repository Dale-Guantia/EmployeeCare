<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\EngineDatabase;

class EngineDatabaseSafetyTest extends TestCase
{
    private function values(): array
    {
        return ['DRIVER' => 'sqlsrv', 'HOST' => 'test-only', 'PORT' => '1433', 'DATABASE' => 'ec_engine_test_abcdefgh',
            'USERNAME' => 'test-only', 'PASSWORD' => '', 'CONFIRM' => 'ec_engine_test_abcdefgh'];
    }

    public function test_valid_configuration_has_no_application_url_or_prefix()
    {
        $config = EngineDatabase::configuration($this->values());
        $this->assertNull($config['url']); $this->assertSame('', $config['prefix']);
        $this->assertSame('ec_engine_test_abcdefgh', $config['database']);
    }

    /** @dataProvider unsafeConfigurations */
    public function test_unsafe_configuration_is_rejected_before_connecting($overrides, $protected)
    {
        $this->expectException(\LogicException::class);
        EngineDatabase::configuration(array_merge($this->values(), $overrides), $protected);
    }

    public function unsafeConfigurations(): array
    {
        return [[['DATABASE' => 'EmployeeCare', 'CONFIRM' => 'EmployeeCare'], []],
            [['CONFIRM' => 'wrong'], []], [['DRIVER' => 'sqlite'], []], [['PASSWORD' => false], []],
            [['HOST' => ''], []], [[], ['EC_ENGINE_TEST_ABCDEFGH']]];
    }

    public function test_application_data_probe_requires_exact_explicit_confirmation()
    {
        $previous = getenv('ENGINE_DATA_TEST_CONFIRM');
        putenv('ENGINE_DATA_TEST_CONFIRM=another-database@another-server:1433');
        try {
            $this->expectException(\LogicException::class);
            \Tests\Support\AuthorizedEngineDatabase::boot();
        } finally {
            putenv($previous === false ? 'ENGINE_DATA_TEST_CONFIRM' : 'ENGINE_DATA_TEST_CONFIRM=' . $previous);
        }
    }
}
