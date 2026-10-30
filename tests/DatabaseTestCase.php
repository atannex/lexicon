<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;
}
