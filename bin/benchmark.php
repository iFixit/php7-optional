#!/usr/bin/env php
<?php

declare(strict_types = 1);

/**
 * Measures what the box costs against the plain PHP it replaces.
 *
 * A library that asks you to allocate an object where you used to write `??`
 * should say what that costs. Run it yourself rather than trusting a number in
 * a README, since the answer moves with the PHP version and the opcache:
 *
 *     php bin/benchmark.php [iterations]
 *
 * These are nanoseconds per operation on one machine with no opcache. Read the
 * ratio, not the absolute number.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Optional\Option;

$iterations = max(1000, (int) ($argv[1] ?? '200000'));

/**
 * @param callable():mixed $subject
 */
function measure(string $label, callable $subject, int $iterations): float {
   // one warm pass, so the first call's autoload and JIT work is not counted
   $subject();

   $start = hrtime(true);

   for ($i = 0; $i < $iterations; $i++) {
      $subject();
   }

   $nanoseconds = (hrtime(true) - $start) / $iterations;
   printf("  %-44s %8.1f ns\n", $label, $nanoseconds);

   return $nanoseconds;
}

/**
 * Declared loosely on purpose. With the literal shape psalm folds the lookups,
 * reports the deliberate miss as an invalid offset, and the benchmark stops
 * resembling the code it is measuring.
 *
 * @var array<string, string> $haystack
 */
$haystack = ['name' => 'Ada'];
$double = static fn(int $n): int => $n * 2;

/** @var array<string, int> $counts */
$counts = ['count' => 21];

echo "php7-optional, {$iterations} iterations per case, PHP " . PHP_VERSION . "\n\n";

echo "Reading a value that is present\n";
$plainHit = measure('$a["name"] ?? "none"', static fn(): string => $haystack['name'] ?? 'none', $iterations);
$boxHit = measure('Option::fromArray(...)->valueOr("none")', static fn(): string
   => Option::fromArray($haystack, 'name')->valueOr('none'), $iterations);

echo "\nReading a value that is absent\n";
$plainMiss = measure('$a["nope"] ?? "none"', static fn(): string => $haystack['nope'] ?? 'none', $iterations);
$boxMiss = measure('Option::fromArray(...)->valueOr("none")', static fn(): string
   => Option::fromArray($haystack, 'nope')->valueOr('none'), $iterations);

echo "\nA two step chain\n";
$plainChain = measure('null checks by hand', static function() use ($counts, $double): int {
   $value = $counts['count'] ?? null;

   return $value === null ? 0 : $double($double($value));
}, $iterations);
$boxChain = measure('->map()->map()->valueOr()', static fn(): int
   => Option::fromArray($counts, 'count')->map($double)->map($double)->valueOr(0), $iterations);

echo "\nConstruction alone\n";
measure('Option::some(1)', static fn(): Option => Option::some(1), $iterations);
measure('Option::none()', static fn(): Option => Option::none(), $iterations);

// Work of the size a box usually wraps, so the overhead has something to be
// compared against that anyone can reproduce.
echo "\nFor scale, work a box typically wraps\n";
$json = '{"id":7,"name":"Ada","tags":["a","b","c"],"meta":{"active":true,"score":9.5}}';
measure('json_decode() of a small object', static fn(): mixed => json_decode($json, true), $iterations);

$file = tempnam(sys_get_temp_dir(), 'php7-optional-benchmark');

if ($file === false) {
   fwrite(STDERR, "Could not create a temporary file to read.\n");
   exit(1);
}

file_put_contents($file, str_repeat('x', 1024));
measure('file_get_contents() of a 1 KB file', static fn(): string|false => file_get_contents($file), $iterations);
unlink($file);

printf(
   "\nOverhead: %.1fx on a hit, %.1fx on a miss, %.1fx on the two step chain.\n",
   $plainHit > 0.0 ? $boxHit / $plainHit : 0.0,
   $plainMiss > 0.0 ? $boxMiss / $plainMiss : 0.0,
   $plainChain > 0.0 ? $boxChain / $plainChain : 0.0
);
printf("In absolute terms the box adds about %.0f ns per call.\n", $boxHit - $plainHit);
