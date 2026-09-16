<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test feature tidak merender asset frontend; tanpa ini,
        // halaman Inertia 500 karena Vite manifest belum di-build.
        $this->withoutVite();
    }
}
