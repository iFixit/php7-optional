<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Fails when tests/generated/DocblockExamples.php is behind the examples.
 *
 * That file is what psalm analyses, so a stale copy means the documentation is
 * no longer being type-checked and nothing says so. It is committed rather than
 * built on the fly because psalm runs in its own CI job with no build step.
 *
 * Regenerate with `php bin/generate-example-checks.php`.
 */
final class GeneratedExamplesAreCurrentTest extends TestCase {
   public function testGeneratedFileMatchesTheExamples(): void {
      $path = dirname(__DIR__) . '/tests/generated/DocblockExamples.php';

      $this->assertFileExists($path, 'Run: php bin/generate-example-checks.php');
      $this->assertSame(
         ExampleSource::render(),
         (string) file_get_contents($path),
         'tests/generated/DocblockExamples.php is stale, so psalm is checking old examples. '
         . 'Run: php bin/generate-example-checks.php'
      );
   }

   public function testEveryExampleReachedTheGeneratedFile(): void {
      $rendered = ExampleSource::render();

      $this->assertGreaterThan(50, count(ExampleSource::all()), 'Suspiciously few examples extracted');
      $this->assertSame(
         count(ExampleSource::all()),
         substr_count($rendered, "\nfunction example"),
         'An example was extracted but not rendered into the generated file'
      );
   }
}
