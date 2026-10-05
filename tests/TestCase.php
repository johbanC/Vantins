<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Signed PDFs, signatures of quotes and quote documents go to the private disk:
        // tests never write real files there.
        Storage::fake('local');
    }
}
