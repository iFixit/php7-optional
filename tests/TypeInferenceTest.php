<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Asserts what psalm infers, not just that the code runs.
 *
 * This library's claim is that it carries types, so the types are the product
 * and they need tests. Every annotation defect found on this branch was found
 * by running @psalm-trace by hand and reading the output:
 *
 *   Option::map            reported Option<never> for a box holding a string
 *   Option::fromArray      collapsed to Option<mixed> at every call site
 *   Option::map            returned Option<U>|Option<never>, so a second map()
 *                          with a typed callback failed psalm at errorLevel 1
 *
 * None of those failed a test or a psalm run. They were all silently wrong, and
 * all of them are asserted below.
 *
 * The expected strings are psalm's own rendering. When one changes, read the
 * diff before updating it: a widened type is usually a regression, and a
 * narrowed one is usually the win you were after.
 */
final class TypeInferenceTest extends TestCase {
   /**
    * Traced expression to the type psalm must infer for it.
    *
    * @return iterable<string, array{string, string}>
    */
   public static function expectationProvider(): iterable {
      $expected = [
         // Option
         'mapKeepsTheMappedType' => "Optional\\Option<'n5'>",
         'filterTrueKeepsTheValue' => 'Optional\\Option<5>',
         'mapSafelyKeepsTheMappedType' => "Optional\\Option<'n5'>",
         'fromArrayKeepsTheValueType' => "Optional\\Option<'world'>",
         'valueOrThrowReturnsTheValue' => '41',
         'valueOrThrowWithNoFactory' => '41',
         'tapReturnsTheSameBox' => 'Optional\\Option<5>',
         'someNotFalsyKeepsTheType' => "Optional\\Option<'Ada'>",
         'fromCallableKeepsTheReturnType' => 'Optional\\Option<7>',
         'firstWithAPredicateKeepsTheType' => "Optional\\Option<'a'|'bb'>",

         // an entrance drops the missing value from the type, not just the box
         'someNotNullDropsNull' => 'Optional\\Option<string>',
         'someNotFalsyDropsFalse' => 'Optional\\Option<array{id: int}>',
         'notNullDropsNull' => 'Optional\\Option<string>',
         'notFalsyDropsFalse' => 'Optional\\Option<int>',
         'someNotFalseDropsFalse' => 'Optional\\Option<int>',
         'notFalseDropsFalse' => 'Optional\\Option<int>',

         // two calls in a row: each is one type, never a union with an empty box
         'twoMaps' => 'Optional\\Option<non-falsy-string>',
         'twoMapsOnAnOption' => 'Optional\\Option<int<0, max>>',
         'notNullThenMap' => 'Optional\\Option<string>',
         'someNotNullThenMap' => 'Optional\\Option<string>',
         'someNotFalsyThenMap' => 'Optional\\Option<int<0, max>>',
         'filterByABoolThenMap' => 'Optional\\Option<int<0, max>>',
         'filterByACallableThenMap' => 'Optional\\Option<int<0, max>>',
         'orThenMap' => 'Optional\\Option<int<0, max>>',
         'elseThenMap' => 'Optional\\Option<int<0, max>>',
         'mapThenValueOr' => 'int<0, max>',
         'notFalsyThenMap' => 'Optional\\Option<int>',

         // a callback that returns a Some on one branch and a None on the other
         // binds, because the box is covariant
         'flatMapWithBothBranches' => 'Optional\\Option<non-empty-string>',
         'elseCreateWithBothBranches' => 'Optional\\Option<string>',
         'valueOrReturnKeepsTheType' => 'string',
         'optionDoBlock' => 'Optional\\Option<int<0, max>>',

         // Result
         'resultMapKeepsTheMappedType' => "Optional\\Result<'n5', never>",
         'resultFromCallableCarriesTheThrowable' => 'Optional\\Result<7, Throwable>',
         'resultFromCallableTypesTheNamedClass' => 'Optional\\Result<7, InvalidArgumentException>',
         'resultMapSafelyTypesTheNamedClass' => "Optional\\Result<'n5', JsonException>",
         'resultValueOrThrowReturnsTheValue' => '41',
         'resultToOptionKeepsTheValue' => 'Optional\\Option<10>',
         'optionToResultCarriesTheError' => "Optional\\Result<'world', 'missing'>",
         'resultOrIsAlwaysOk' => 'Optional\\Result<0, never>',
         'resultFlatMapJoinsTheErrors' => "Optional\\Result<never, 'rejected 1'>",
         'twoResultMaps' => 'Optional\\Result<int<1, max>, string>',
         'twoMapErrs' => 'Optional\\Result<int, int<0, max>>',
         'resultFilterThenMap' => 'Optional\\Result<int, string>',
         'resultOrThenMap' => 'Optional\\Result<int, never>',
         'resultFlatMapWithBothBranches' => 'Optional\\Result<int<1, max>, string>',
         'resultElseCreateWithBothBranches' => 'Optional\\Result<int, non-empty-string>',
         'resultDoBlock' => 'Optional\\Result<int, mixed>',
         'resultElseThenMap' => 'Optional\\Result<int, never>',
         'resultFlatMapThenMap' => 'Optional\\Result<int<1, max>, string>',
         'toResultThenMap' => "Optional\\Result<int<0, max>, 'missing'>",
      ];

      foreach ($expected as $variable => $type) {
         yield $variable => [$variable, $type];
      }
   }

   #[DataProvider('expectationProvider')]
   public function testPsalmInfersTheRecordedType(string $variable, string $expected): void {
      $inferred = self::traces();

      $this->assertArrayHasKey(
         $variable,
         $inferred,
         "psalm reported no type for \${$variable}. Is it still traced in "
         . 'tests/TypeInference/Expressions.php?'
      );

      $this->assertSame(
         $expected,
         $inferred[$variable],
         "The inferred type of \${$variable} changed. Read the difference before updating this "
         . 'expectation: a wider type usually means an annotation stopped carrying information.'
      );
   }

   public function testEveryTracedExpressionIsAsserted(): void {
      $asserted = array_keys(iterator_to_array(self::expectationProvider()));
      $traced = array_keys(self::traces());

      sort($asserted);
      sort($traced);

      $this->assertSame(
         $traced,
         $asserted,
         'Every expression traced in the fixture needs a recorded type, and every recorded '
         . 'type needs an expression. Otherwise one of them is quietly doing nothing.'
      );
   }

   public function testTheFixtureHasNoOtherIssues(): void {
      $this->assertSame(
         [],
         self::nonTraceIssues(),
         'The type-assertion fixture has psalm issues besides its traces, so it is not the '
         . 'clean sample it is meant to be.'
      );
   }

   /**
    * @return array<string, string>
    */
   private static function traces(): array {
      /** @var array<string, string>|null $cached */
      static $cached = null;

      if ($cached === null) {
         $cached = [];

         foreach (self::runPsalm() as $issue) {
            if (($issue['type'] ?? '') !== 'Trace') {
               continue;
            }

            $parts = explode(': ', (string) ($issue['message'] ?? ''), 2);

            if (count($parts) !== 2) {
               continue;
            }

            $cached[ltrim($parts[0], '$')] = $parts[1];
         }
      }

      return $cached;
   }

   /**
    * @return list<string>
    */
   private static function nonTraceIssues(): array {
      $other = [];

      foreach (self::runPsalm() as $issue) {
         if (($issue['type'] ?? '') !== 'Trace') {
            /** @var mixed $type */
            $type = $issue['type'] ?? null;
            /** @var mixed $message */
            $message = $issue['message'] ?? null;

            $other[] = sprintf(
               '%s: %s',
               is_string($type) ? $type : 'unknown issue',
               is_string($message) ? $message : 'no message'
            );
         }
      }

      return $other;
   }

   /**
    * @return list<array<string, mixed>>
    */
   private static function runPsalm(): array {
      /** @var list<array<string, mixed>>|null $cached */
      static $cached = null;

      if ($cached !== null) {
         return $cached;
      }

      $root = dirname(__DIR__);
      $command = sprintf(
         '%s %s --no-cache --no-progress --config=%s --output-format=json 2>/dev/null',
         escapeshellarg(PHP_BINARY),
         escapeshellarg("{$root}/vendor/bin/psalm"),
         escapeshellarg("{$root}/psalm-types.xml")
      );

      $output = [];
      $status = 0;
      exec($command, $output, $status);

      /** @var list<string> $output */
      $decoded = json_decode(implode("\n", $output), true);

      if (!is_array($decoded)) {
         self::fail(
            "Could not read psalm's JSON output. This suite shells out to psalm, so it needs "
            . "vendor/bin/psalm to work.\n" . implode("\n", $output)
         );
      }

      /** @var list<array<string, mixed>> $decoded */
      $cached = $decoded;

      return $cached;
   }
}
