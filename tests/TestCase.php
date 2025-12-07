<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\CreatesTestDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesTestDatabase;
}
