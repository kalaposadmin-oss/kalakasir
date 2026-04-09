<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected int $baselineOutputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate compiled Blade output per test run to avoid Windows file-lock
        // collisions on storage/framework/views during feature smoke tests.
        $compiledPath = storage_path('framework/testing-views/'.Str::uuid()->toString());
        File::ensureDirectoryExists($compiledPath);

        config()->set('view.compiled', $compiledPath);
        $this->baselineOutputBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->baselineOutputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }
}
