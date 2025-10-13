<?php

namespace Tests;

use Tests\Traits\CreatesTestDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesTestDatabase;
}
