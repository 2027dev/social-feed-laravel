<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Redis;

trait ResetsRedis
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();
    }
}
