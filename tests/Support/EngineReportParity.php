<?php

namespace Tests\Support;

/** Reuse the parity assertions without invoking the SQLite test bootstrap. */
class EngineReportParity extends \Tests\Feature\ReportQueryParityTest
{
    public function useApplication($app): void { $this->app = $app; }
}
