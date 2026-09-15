#!/usr/bin/env php
<?php

declare(strict_types = 1);

/**
 * Fails the build when line coverage drops below a floor.
 *
 * PHPUnit can report coverage but cannot fail on it, so CI would otherwise
 * print a number nobody reads. Run it after phpunit --coverage-clover:
 *
 *     php bin/check-coverage.php clover.xml 90
 *
 * Exit codes:
 *
 *     0  coverage is at or above the floor
 *     1  coverage is below the floor
 *     2  the arguments or the report are unusable
 *
 * The third case is why this is a script and not a grep. A coverage step that
 * passes because phpunit never wrote the report, or because the floor arrived
 * as an empty shell variable, looks exactly like a coverage step that passes
 * because the code is covered. Every one of those exits 2 here.
 */

function fail(string $message, int $code): never {
   fwrite(STDERR, $message . "\n");
   exit($code);
}

$reportPath = $argv[1] ?? 'clover.xml';
$rawFloor = $argv[2] ?? '90';

if (!is_numeric($rawFloor)) {
   fail("Floor '{$rawFloor}' is not a number. An unset shell variable lands here, and a floor of zero passes anything.", 2);
}

$floor = (float) $rawFloor;

if ($floor < 0.0 || $floor > 100.0) {
   fail(sprintf('Floor %.2f is outside 0 to 100.', $floor), 2);
}

if (!is_file($reportPath)) {
   fail("No coverage report at {$reportPath}. Did phpunit run with --coverage-clover?", 2);
}

$previous = libxml_use_internal_errors(true);
$xml = simplexml_load_file($reportPath);
libxml_use_internal_errors($previous);

if ($xml === false) {
   fail("Could not parse {$reportPath} as XML.", 2);
}

$metrics = $xml->xpath('/coverage/project/metrics');

if ($metrics === null || $metrics === []) {
   fail("No <project><metrics> element in {$reportPath}.", 2);
}

if (count($metrics) > 1) {
   fail(sprintf('%s has %d <project><metrics> elements; expected one.', $reportPath, count($metrics)), 2);
}

$statements = (int) $metrics[0]['statements'];
$covered = (int) $metrics[0]['coveredstatements'];

if ($statements <= 0) {
   fail("{$reportPath} reports {$statements} statements, so it measured nothing.", 2);
}

if ($covered < 0 || $covered > $statements) {
   fail("{$reportPath} reports {$covered} of {$statements} statements covered, which is impossible.", 2);
}

$percent = (float) $covered / (float) $statements * 100.0;

printf("Line coverage: %.2f%% (%d of %d statements), floor %.2f%%\n", $percent, $covered, $statements, $floor);

if ($percent < $floor) {
   fail(sprintf('Coverage %.2f%% is below the %.2f%% floor.', $percent, $floor), 1);
}

exit(0);
