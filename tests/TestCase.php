<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Signatures, signed PDFs and quote documents are real files: tests never write them
        // to the real disks.
        Storage::fake('local');
        Storage::fake('public');
    }
}
