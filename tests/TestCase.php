<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The queue is sync in tests, so every issue renders a real PDF. Keep
        // those files out of storage/app.
        Storage::fake('local');
    }
}
