<?php

namespace Tests\Support;

use Illuminate\Http\Client\Factory;

class OfflineHttpFactory extends Factory
{
    protected function newPendingRequest()
    {
        // Explicit Http::fake() responses still work; an unfaked request cannot leave the test process.
        return parent::newPendingRequest()->setHandler(function () {
            throw new \LogicException('External HTTP requests must be faked in tests.');
        });
    }
}
