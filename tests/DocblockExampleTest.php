<?php

declare(strict_types = 1);

namespace Optional\Tests;

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

   /**
    * Examples that print something, for checking what they print
    *
    * @return iterable<string, array{string, string}>
    */
   public static function printingExampleProvider(): iterable {
      foreach (self::docblockExampleProvider() as $label => [$code, $name]) {
         if (ExampleSource::statedOutput($code) !== []) {
            yield $label => [$code, $name];
         }
      }
   }

   #[DataProvider('docblockExampleProvider')]
   public function testExampleRuns(string $code, string $label): void {
      $threw = null;

      try {
         ExampleSource::run($code);
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
    * Running is not enough: an example that runs and prints the wrong thing
    * teaches the wrong thing. Each line printed has to match the comment on
    * its echo, the way a Rust doctest compares its output.
    */
   #[DataProvider('printingExampleProvider')]
   public function testExamplePrintsWhatItsCommentsSay(string $code, string $label): void {
      $stated = ExampleSource::statedOutput($code);

      foreach ($stated as $position => $line) {
         $this->assertNotNull($line, sprintf(
            '%s: echo #%d has no comment saying what it prints. Put one after its ";", on the same line.',
            $label,
            $position + 1
         ));
      }

      $printed = explode("\n", rtrim(ExampleSource::run($code), "\n"));

      $this->assertCount(
         count($stated),
         $printed,
         sprintf(
            '%s has %d echo statements but printed %d lines. Each echo in an example has to print exactly one '
            . 'line, ending in "\n", so its comment can be checked.',
            $label,
            count($stated),
            count($printed)
         )
      );

      foreach ($stated as $position => $line) {
         $this->assertSame($line, $printed[$position], sprintf(
            '%s: the comment on echo #%d says it prints [%s], but it printed [%s]',
            $label,
            $position + 1,
            (string) $line,
            $printed[$position]
         ));
      }
   }

   public function testStatedOutputReadsTheCommentConventions(): void {
      $code = <<<'PHP'
         echo 1 + 2, "\n"; // 3
         echo (string) $box, "\n"; // Some(3), after the retry
         echo $pair, "\n"; // "a, b" with the comma kept
         echo $noComment, "\n";
         echo $long
            ->chained() . "\n"; // Ok(both [lines, joined])
         echo Option::do(function() {
            $inner = 1; // not this one
            return "{$inner}; {$inner}";
         }), "\n"; // Some('1; 1')
         echo $onTheNextLine, "\n";
         // "this comment could be about the next line"
         PHP;

      $this->assertSame(
         ['3', 'Some(3)', 'a, b', null, 'Ok(both [lines, joined])', "Some('1; 1')", null],
         ExampleSource::statedOutput($code)
      );
   }

   /**
    * Guards the output check the way the next test guards the extractor: a
    * comment convention that quietly stops matching would check nothing.
    */
   public function testTheOutputCheckSeesTheComments(): void {
      $stated = 0;

      foreach (ExampleSource::all() as $example) {
         $stated += count(array_filter(ExampleSource::statedOutput($example['code']), fn(?string $line) => $line !== null));
      }

      $this->assertGreaterThan(90, $stated, 'Suspiciously few example comments state an output');
   }

   /**
    * Guards the extractor itself: a regex that quietly stops matching would
    * leave this suite green with nothing to run.
    */
   public function testEveryDocumentedClassContributesExamples(): void {
      $labels = array_keys(iterator_to_array(self::docblockExampleProvider()));

      $this->assertGreaterThanOrEqual(20, count($labels), 'Too few examples extracted from src/');

      foreach (['src/Option.php', 'src/Result.php', 'README.md'] as $relativePath) {
         $fromFile = array_filter($labels, static fn(string $l): bool => str_starts_with($l, $relativePath));
         $this->assertNotEmpty($fromFile, "No docblock examples extracted from {$relativePath}");
      }
   }
}
