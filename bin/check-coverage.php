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
 * Exits 0 when coverage is at or above the floor, 1 when it is below, and 2
 * when the report is missing or unreadable. That last case matters: a CI step
 * that silently passes because the report never got written is worse than no
 * check at all.
 */

$reportPath = $argv[1] ?? 'clover.xml';
$floor = (float) ($argv[2] ?? '90');

if (!is_file($reportPath)) {
   fwrite(STDERR, "No coverage report at {$reportPath}. Did phpunit run with --coverage-clover?\n");
   exit(2);
}

$xml = @simplexml_load_file($reportPath);

if ($xml === false) {
   fwrite(STDERR, "Could not parse {$reportPath} as XML.\n");
   exit(2);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
   fwrite(STDERR, "No <project><metrics> element in {$reportPath}.\n");
   exit(2);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
   fwrite(STDERR, "{$reportPath} reports zero statements, so it covers nothing.\n");
   exit(2);
}

$percent = $covered / $statements * 100;

printf("Line coverage: %.2f%% (%d of %d statements), floor %.2f%%\n", $percent, $covered, $statements, $floor);

if ($percent + 0.0001 < $floor) {
   fwrite(STDERR, sprintf("Coverage %.2f%% is below the %.2f%% floor.\n", $percent, $floor));
   exit(1);
}

exit(0);
