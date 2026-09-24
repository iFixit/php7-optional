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
         'valueOrThrowWithAMessage' => '41',
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
         'resultAllKeepsTheKeys' => "Optional\\Result<array<'a'|'b', int>, string>",
         'resultPartition' => 'array{0: array<int<0, 1>, int>, 1: array<int<0, 1>, string>}',
         'optionAllOfAList' => 'Optional\\Option<array<int<0, 1>, string>>',
         'resultElseThenMap' => 'Optional\\Result<int, never>',
         'resultFlatMapThenMap' => 'Optional\\Result<int<1, max>, string>',
         'toResultThenMap' => "Optional\\Result<int<0, max>, 'missing'>",
      ];

      foreach ($expected as $variable => $type) {
         yield $variable => [$variable, $type];
      }
   }

   /**
    * The type PHPStan, at level max, must infer for the same traced expressions
    *
    * PHPStan reads the same annotations, so the same fixture checks it. Its
    * types differ from psalm's in style, not in correctness: it widens a
    * literal passed through a method's template (`Option<5>` is `Option<int>`),
    * and adds refinements psalm leaves off (`lowercase-string&non-falsy-string`).
    *
    * @return iterable<string, array{string, string}>
    */
   public static function phpstanExpectationProvider(): iterable {
      $expected = [
         'mapKeepsTheMappedType' => 'Optional\\Option<lowercase-string&non-falsy-string>',
         'filterTrueKeepsTheValue' => 'Optional\\Option<int>',
         'mapSafelyKeepsTheMappedType' => 'Optional\\Option<lowercase-string&non-falsy-string>',
         'fromArrayKeepsTheValueType' => 'Optional\\Option<string>',
         'valueOrThrowReturnsTheValue' => 'int',
         'valueOrThrowWithNoFactory' => 'int',
         'valueOrThrowWithAMessage' => 'int',
         'tapReturnsTheSameBox' => 'Optional\\Option<int>',
         'someNotFalsyKeepsTheType' => 'Optional\\Option<string>',
         'fromCallableKeepsTheReturnType' => 'Optional\\Option<int>',
         'firstWithAPredicateKeepsTheType' => 'Optional\\Option<string>',
         'someNotNullDropsNull' => 'Optional\\Option<string>',
         'someNotFalsyDropsFalse' => 'Optional\\Option<array{id: int}>',
         'notNullDropsNull' => 'Optional\\Option<string>',
         'notFalsyDropsFalse' => 'Optional\\Option<int>',
         'someNotFalseDropsFalse' => 'Optional\\Option<int>',
         'notFalseDropsFalse' => 'Optional\\Option<int>',
         'twoMaps' => 'Optional\\Option<non-falsy-string&uppercase-string>',
         'twoMapsOnAnOption' => 'Optional\\Option<int<0, max>>',
         'notNullThenMap' => 'Optional\\Option<uppercase-string>',
         'someNotNullThenMap' => 'Optional\\Option<uppercase-string>',
         'someNotFalsyThenMap' => 'Optional\\Option<int<0, max>>',
         'filterByABoolThenMap' => 'Optional\\Option<int<0, max>>',
         'filterByACallableThenMap' => 'Optional\\Option<int<0, max>>',
         'orThenMap' => 'Optional\\Option<int<0, max>>',
         'elseThenMap' => 'Optional\\Option<int<0, max>>',
         'mapThenValueOr' => 'int<0, max>',
         'notFalsyThenMap' => 'Optional\\Option<int>',
         'flatMapWithBothBranches' => 'Optional\\Option<non-empty-string>',
         'elseCreateWithBothBranches' => 'Optional\\Option<string>',
         'valueOrReturnKeepsTheType' => 'string',
         'optionDoBlock' => 'Optional\\Option<int<0, max>>',
         'resultMapKeepsTheMappedType' => 'Optional\\Result<lowercase-string&non-falsy-string, never>',
         'resultFromCallableCarriesTheThrowable' => 'Optional\\Result<int, Throwable>',
         'resultFromCallableTypesTheNamedClass' => 'Optional\\Result<int, InvalidArgumentException>',
         'resultMapSafelyTypesTheNamedClass' => 'Optional\\Result<lowercase-string&non-falsy-string, JsonException>',
         'resultValueOrThrowReturnsTheValue' => 'int',
         'resultToOptionKeepsTheValue' => 'Optional\\Option<int>',
         'optionToResultCarriesTheError' => 'Optional\\Result<string, string>',
         'resultOrIsAlwaysOk' => 'Optional\\Result<int, never>',
         'resultFlatMapJoinsTheErrors' => 'Optional\\Result<never, lowercase-string&non-falsy-string>',
         'twoResultMaps' => 'Optional\\Result<int<1, max>, string>',
         'twoMapErrs' => 'Optional\\Result<int, int<0, max>>',
         'resultFilterThenMap' => 'Optional\\Result<int, string>',
         'resultOrThenMap' => 'Optional\\Result<int, never>',
         'resultFlatMapWithBothBranches' => 'Optional\\Result<int<1, max>, string>',
         'resultElseCreateWithBothBranches' => 'Optional\\Result<int, non-empty-string>',
         'resultAllKeepsTheKeys' => 'Optional\\Result<array<string, int>, string>',
         'resultPartition' => 'array{array<0|1, int>, array<0|1, string>}',
         'optionAllOfAList' => 'Optional\\Option<array<int, string>>',
         'resultDoBlock' => 'Optional\\Result<int, mixed>',
         'resultElseThenMap' => 'Optional\\Result<int, never>',
         'resultFlatMapThenMap' => 'Optional\\Result<int<1, max>, string>',
         'toResultThenMap' => 'Optional\\Result<int<0, max>, string>',
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

   #[DataProvider('phpstanExpectationProvider')]
   public function testPhpstanInfersTheRecordedType(string $variable, string $expected): void {
      $inferred = self::phpstan()['types'];

      $this->assertArrayHasKey($variable, $inferred, "PHPStan reported no type for \${$variable}.");
      $this->assertSame(
         $expected,
         $inferred[$variable],
         "PHPStan's inferred type of \${$variable} changed. Read the difference before updating it."
      );
   }

   public function testPhpstanChecksEveryTracedExpression(): void {
      $recorded = array_keys(iterator_to_array(self::phpstanExpectationProvider()));
      $traced = array_keys(self::traces());

      sort($recorded);
      sort($traced);

      $this->assertSame($traced, $recorded, 'Every traced expression needs a recorded PHPStan type too.');
   }

   public function testPhpstanReportsNothingElseInTheFixture(): void {
      $this->assertSame([], self::phpstan()['other'], 'PHPStan reports issues in the type fixture besides its dumps.');
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
    * Runs PHPStan over the fixture, with each trace turned into a dumpType()
    * on the same line, so a reported line leads back to its variable.
    *
    * @return array{types: array<string, string>, other: list<string>}
    */
   private static function phpstan(): array {
      /** @var array{types: array<string, string>, other: list<string>}|null $cached */
      static $cached = null;

      if ($cached !== null) {
         return $cached;
      }

      $root = dirname(__DIR__);
      $fixture = (string) file_get_contents("{$root}/tests/TypeInference/Expressions.php");
      $variables = [];

      foreach (explode("\n", $fixture) as $index => $line) {
         if (preg_match('#/\*\* @psalm-trace \$(\w+) \*/#', $line, $match) === 1) {
            $variables[$index + 1] = $match[1];
         }
      }

      $dir = sys_get_temp_dir() . '/php7-optional-phpstan-' . bin2hex(random_bytes(6));
      is_dir($dir) || mkdir($dir);
      $source = "{$dir}/expressions.php";
      $config = "{$dir}/phpstan.neon";
      file_put_contents($source, (string) preg_replace('#/\*\* @psalm-trace (\$\w+) \*/#', '\\PHPStan\\dumpType($1);', $fixture));
      file_put_contents($config, "parameters:\n    level: max\n");

      $command = sprintf(
         '%s %s analyse --no-progress --error-format=json --configuration=%s --autoload-file=%s %s 2>/dev/null',
         escapeshellarg(PHP_BINARY),
         escapeshellarg("{$root}/vendor/bin/phpstan"),
         escapeshellarg($config),
         escapeshellarg("{$root}/vendor/autoload.php"),
         escapeshellarg($source)
      );

      $output = [];
      exec($command, $output);
      unlink($source);
      unlink($config);
      rmdir($dir);

      /** @var list<string> $output */
      $printed = implode("\n", $output);
      /** @var mixed $report */
      $report = json_decode($printed, true);

      if (!is_array($report) || !is_array($report['files'] ?? null)) {
         self::fail("Could not read PHPStan's JSON output. This suite needs vendor/bin/phpstan.\n{$printed}");
      }

      $types = [];
      $other = [];

      foreach ($report['files'] as $file) {
         self::assertIsArray($file);
         self::assertIsArray($file['messages'] ?? null);

         /** @var mixed $message */
         foreach ($file['messages'] as $message) {
            self::assertIsArray($message);
            /** @var mixed $text */
            $text = $message['message'] ?? null;
            /** @var mixed $line */
            $line = $message['line'] ?? null;
            self::assertIsString($text);
            self::assertIsInt($line);

            if (str_starts_with($text, 'Dumped type: ') && isset($variables[$line])) {
               $types[$variables[$line]] = substr($text, strlen('Dumped type: '));
            } else {
               $other[] = "line {$line}: {$text}";
            }
         }
      }

      $cached = ['types' => $types, 'other' => $other];

      return $cached;
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
