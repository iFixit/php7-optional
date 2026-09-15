#!/usr/bin/env php
<?php

declare(strict_types = 1);

/**
 * Writes every docblock and README example into one file so psalm can analyse them.
 *
 * DocblockExampleTest executes the examples, which proves they run. It does not
 * prove they type-check, and this library's whole claim is that it type-checks
 * at errorLevel 1. Either::flatMapLeft shipped for years demanding an
 * impossible `Either<never, never>` from its callback, so its own documented
 * example was an InvalidArgument that nothing reported.
 *
 * The generated file is committed, because psalm runs in its own CI job and
 * cannot generate it first. ExampleTypeCheckTest fails when it is stale.
 *
 *     php bin/generate-example-checks.php
 */

require_once __DIR__ . '/../tests/ExampleSource.php';

use Optional\Tests\ExampleSource;

$target = __DIR__ . '/../tests/generated/DocblockExamples.php';
@mkdir(dirname($target), 0o777, true);
file_put_contents($target, ExampleSource::render());

echo "Wrote " . count(ExampleSource::all()) . " examples to tests/generated/DocblockExamples.php\n";
