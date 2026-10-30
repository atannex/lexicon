<?php

declare(strict_types=1);

use Atannex\Lexicon\Tests\DatabaseTestCase;
use Atannex\Lexicon\Tests\ModuleTestCase;
use Atannex\Lexicon\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');
pest()->extend(DatabaseTestCase::class)->in('Persistence');
pest()->extend(ModuleTestCase::class)->in('Modules');

require_once __DIR__.'/helpers.php';
