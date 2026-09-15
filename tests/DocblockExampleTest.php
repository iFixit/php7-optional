<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Tests\Fixtures\NewObject;
use Optional\Tests\Fixtures\SomeComplexThing;
use Optional\Tests\Fixtures\SomeObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Runs every ```php example in the library's docblocks and in README.md.
 *
 * Those docblocks are the published API documentation, so a broken example is
 * a broken product. Eight of them had been uncallable for years because
 * nothing ever executed them: calls to methods that do not exist, factories
 * invoked with too few arguments, and two whose stated result was wrong on
 * PHP 8. This suite is what stops that happening again.
 *
 * An example that means to show a throw has to catch it itself, which reads
 * better in the documentation anyway.
 */
final class DocblockExampleTest extends TestCase {
   /**
    * @return iterable<string, array{string, string}>
    */
   public static function docblockExampleProvider(): iterable {
      foreach (ExampleSource::all() as $example) {
         $label = sprintf('%s #%d %s()', $example['file'], $example['index'], $example['symbol']);
         yield $label => [$example['code'], $label];
      }
   }

   #[DataProvider('docblockExampleProvider')]
   public function testExampleRuns(string $code, string $label): void {
      $threw = null;

      try {
         self::evaluate($code);
      } catch (Throwable $e) {
         $threw = $e;
      }

      $this->assertNull($threw, $threw === null ? '' : sprintf(
         "%s does not run.\n\n%s: %s\n\n--- example ---\n%s",
         $label,
         get_class($threw),
         $threw->getMessage(),
         $code
      ));
   }

   /**
    * Guards the extractor itself: a regex that quietly stops matching would
    * leave this suite green with nothing to run.
    */
   public function testEveryDocumentedClassContributesExamples(): void {
      $labels = array_keys(iterator_to_array(self::docblockExampleProvider()));

      $this->assertGreaterThanOrEqual(20, count($labels), 'Too few examples extracted from src/');

      foreach (['src/Option.php', 'src/Either.php', 'README.md'] as $relativePath) {
         $fromFile = array_filter($labels, static fn(string $l): bool => str_starts_with($l, $relativePath));
         $this->assertNotEmpty($fromFile, "No docblock examples extracted from {$relativePath}");
      }
   }

   /**
    * The fixtures are only ever named inside PRELUDE, which is a string, so
    * nothing else in the project references them and a rename would go
    * unnoticed until an example failed for a confusing reason. Naming them
    * here keeps the import list honest and keeps psalm from reporting them
    * as unused classes.
    */
   public function testPreludeImportsResolve(): void {
      $imported = [SomeObject::class, NewObject::class, SomeComplexThing::class];

      foreach ($imported as $class) {
         $this->assertTrue(class_exists($class), "{$class} is imported by PRELUDE but does not exist");
         $this->assertStringContainsString($class, ExampleSource::PRELUDE, "PRELUDE no longer imports {$class}");
      }

      $this->assertSame('work', SomeComplexThing::doWork('work'));
      $this->expectException(\Exception::class);
      SomeComplexThing::doWork('work', 'boom');
   }

   /**
    * Several examples echo or var_dump to show a result, and the tap() examples
    * call error_log, which goes to stderr in CLI and so slips past output
    * buffering. Both are documentation, not test output: swallow stdout, and
    * point the error log at a temp file for the duration.
    *
    * @psalm-suppress ForbiddenCode running the examples is the entire point
    */
   private static function evaluate(string $code): void {
      $previousLog = ini_get('error_log');
      $sink = tempnam(sys_get_temp_dir(), 'docblock-example-');

      if ($sink !== false) {
         ini_set('error_log', $sink);
      }

      ob_start();

      try {
         eval(ExampleSource::PRELUDE . "\n" . $code);
      } finally {
         ob_end_clean();
         ini_set('error_log', $previousLog === false ? '' : $previousLog);

         if ($sink !== false) {
            unlink($sink);
         }
      }
   }
}
