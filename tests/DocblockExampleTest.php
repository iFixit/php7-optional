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
 * Runs every ```php example in the library's docblocks.
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
   private const SOURCE_FILES = [
      'src/Option.php',
      'src/Either.php',
      'src/Exceptions/MissingValueException.php',
   ];

   /**
    * Names the examples may use, injected into each example's scope.
    */
   private const PRELUDE = <<<'PHP'
      use Optional\Either;
      use Optional\Option;
      use Optional\Tests\Fixtures\NewObject;
      use Optional\Tests\Fixtures\SomeComplexThing;
      use Optional\Tests\Fixtures\SomeObject;
      PHP;

   /**
    * @return iterable<string, array{string, string}>
    */
   public static function docblockExampleProvider(): iterable {
      $root = dirname(__DIR__);

      foreach (self::SOURCE_FILES as $relativePath) {
         $source = (string) file_get_contents("{$root}/{$relativePath}");

         foreach (self::extractExamples($source) as $index => $example) {
            $label = sprintf('%s #%d %s()', $relativePath, $index + 1, $example['symbol']);
            yield $label => [$example['code'], $label];
         }
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

      foreach (['src/Option.php', 'src/Either.php'] as $relativePath) {
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
         $this->assertStringContainsString($class, self::PRELUDE, "PRELUDE no longer imports {$class}");
      }

      $this->assertSame('work', SomeComplexThing::doWork('work'));
      $this->expectException(\Exception::class);
      SomeComplexThing::doWork('work', 'boom');
   }

   /**
    * @return list<array{symbol: string, code: string}>
    */
   private static function extractExamples(string $source): array {
      $examples = [];
      $offset = 0;
      $open = "```php\n";

      while (($start = strpos($source, $open, $offset)) !== false) {
         $bodyAt = $start + strlen($open);
         $end = strpos($source, '```', $bodyAt);
         if ($end === false) {
            break;
         }

         $code = trim(implode("\n", array_map(
            static fn(string $line): string => (string) preg_replace('/^\s*\*\s?/', '', $line),
            explode("\n", substr($source, $bodyAt, $end - $bodyAt))
         )));

         if ($code !== '') {
            $examples[] = ['symbol' => self::symbolAfter($source, $end), 'code' => $code];
         }

         $offset = $end + 3;
      }

      return $examples;
   }

   /**
    * Names the method the example documents, so a failure says which one.
    */
   private static function symbolAfter(string $source, int $offset): string {
      $tail = substr($source, $offset, 4000);

      return preg_match('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $tail, $m) === 1 ? $m[1] : 'unknown';
   }

   /**
    * Several examples echo or var_dump to show a result. That output is part of
    * the documentation, not of the test, so swallow it rather than letting
    * beStrictAboutOutputDuringTests mark every one of them risky.
    *
    * @psalm-suppress ForbiddenCode running the examples is the entire point
    */
   private static function evaluate(string $code): void {
      ob_start();

      try {
         eval(self::PRELUDE . "\n" . $code);
      } finally {
         ob_end_clean();
      }
   }
}
