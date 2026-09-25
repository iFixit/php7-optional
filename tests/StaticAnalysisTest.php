<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The psalm plugin and the PHPStan extension, run the way a user runs them
 *
 * tests/StaticAnalysis/dropped-results.php marks each line the dropped-Result
 * check has to report with "// dropped". Every other line is a way of using or
 * handling a Result that must not be reported.
 *
 * tests/StaticAnalysis/branch-built.php traces boxes built in two branches. A
 * variable holding one stays a union, and a method called on it sees one box,
 * but only with the plugin or extension on.
 *
 * tests/StaticAnalysis/still-reported.php holds mistakes each tool has to go
 * on reporting with them on.
 */
final class StaticAnalysisTest extends TestCase {
   private const DROPPED = __DIR__ . '/StaticAnalysis/dropped-results.php';

   private const BRANCH_BUILT = __DIR__ . '/StaticAnalysis/branch-built.php';

   private const STILL_REPORTED = __DIR__ . '/StaticAnalysis/still-reported.php';

   /**
    * What each tool infers for the traced variables. psalm keeps literal
    * types where PHPStan widens them, as in tests/TypeInference.
    */
   private const BRANCH_BUILT_TYPES = [
      'psalm' => [
         'templated' => 'Optional\\Result<int, string>',
         'afterEcho' => "Optional\\Result<int, 'no'>",
         'ternary' => "Optional\\Result<int, never>|Optional\\Result<never, 'no'>",
         'maybeFound' => "Optional\\Result<int, never>|Optional\\Result<never, 'no'>|null",
         'maybeMapped' => "Optional\\Result<int, 'no'>|null",
         'declared' => 'Optional\\Result<int, string>',
         'mapped' => "Optional\\Result<int, 'no'>",
         'length' => 'Optional\\Result<int<0, max>, RuntimeException>',
         'described' => 'Optional\\Result<float(1.5), non-empty-literal-string>',
         'name' => 'Optional\\Option<string>',
         'direct' => 'int',
         'cached' => "Optional\\Result<int, 'missing'>",
         'parsed' => 'Optional\\Result<int, non-empty-string>',
      ],
      'phpstan' => [
         'templated' => 'Optional\\Result<int, string>',
         'afterEcho' => 'Optional\\Result<int, string>',
         'ternary' => 'Optional\\Result<int, never>|Optional\\Result<never, string>',
         'maybeFound' => 'Optional\\Result<int, never>|Optional\\Result<never, string>|null',
         'maybeMapped' => 'Optional\\Result<int, string>|null',
         'declared' => 'Optional\\Result<int, string>',
         'mapped' => 'Optional\\Result<int, string>',
         'length' => 'Optional\\Result<int<0, max>, RuntimeException>',
         'described' => 'Optional\\Result<float, non-falsy-string>',
         'name' => 'Optional\\Option<string>',
         'direct' => 'int',
         'cached' => 'Optional\\Result<int, string>',
         'parsed' => 'Optional\\Result<int, non-falsy-string>',
      ],
   ];

   public function testThePsalmPluginReportsExactlyTheDroppedResults(): void {
      $reported = [];
      $other = [];

      foreach (self::psalm()[self::DROPPED] ?? [] as [$type, $line, $message]) {
         if ($type === 'UnusedResult') {
            $reported[] = $line;
         } else {
            $other[] = "{$type} on line {$line}: {$message}";
         }
      }

      $this->assertSame([], $other, 'the fixture has psalm issues besides the dropped Results');
      $this->assertSame(self::droppedLines(), $reported);
   }

   /**
    * PHPStan's own checks also flag some of these lines, as calls to functions
    * with no side effects or methods marked #[\NoDiscard]. Only the rule's
    * identifier counts here, and the line with droppedResultsWrite() is one
    * PHPStan's own checks let through.
    */
   public function testThePhpstanRuleReportsExactlyTheDroppedResults(): void {
      $reported = [];

      foreach (self::phpstan()[self::DROPPED] ?? [] as [$identifier, $line]) {
         if ($identifier === 'optional.unusedResult') {
            $reported[] = $line;
         }
      }

      sort($reported);
      $this->assertSame(self::droppedLines(), $reported);
   }

   public function testThePsalmPluginTypesABoxBuiltInTwoBranchesAsOne(): void {
      $traced = [];
      $other = [];

      foreach (self::psalm()[self::BRANCH_BUILT] ?? [] as [$type, $line, $message]) {
         $parts = explode(': ', $message, 2);

         if ($type === 'Trace' && count($parts) === 2) {
            $traced[ltrim($parts[0], '$')] = $parts[1];
         } else {
            $other[] = "{$type} on line {$line}: {$message}";
         }
      }

      $this->assertSame([], $other, 'psalm reports issues in the branch-built fixture besides its traces');
      $this->assertSame(self::BRANCH_BUILT_TYPES['psalm'], $traced);
   }

   /**
    * The merge used to crash psalm on a box with the wrong number of types, and
    * a crash loses every issue in the run. Getting any issues back at all is
    * the first check; psalm's own report on the docblock is the second. The
    * third is the call on a template that may be null, which merging it as a
    * box would hide.
    */
   public function testThePsalmPluginLeavesPsalmItsOwnReports(): void {
      $types = array_map(fn(array $issue): string => $issue[0], self::psalm()[self::STILL_REPORTED] ?? []);

      $this->assertContains('MissingTemplateParam', $types);
      $this->assertContains('PossiblyNullReference', $types);
   }

   public function testThePhpstanExtensionLeavesPhpstanItsOwnReports(): void {
      $identifiers = array_map(fn(array $message): string => $message[0], self::phpstan()[self::STILL_REPORTED] ?? []);

      $this->assertContains('generics.lessTypes', $identifiers);
      $this->assertContains('method.nonObject', $identifiers);
   }

   public function testThePhpstanExtensionTypesABoxBuiltInTwoBranchesAsOne(): void {
      $traced = [];
      $other = [];
      $variables = self::tracedVariables();

      foreach (self::phpstan()[self::BRANCH_BUILT] ?? [] as [$identifier, $line, $message]) {
         if ($identifier === 'phpstan.dumpType' && isset($variables[$line])) {
            $traced[$variables[$line]] = substr($message, strlen('Dumped type: '));
         } else {
            $other[] = "{$identifier} on line {$line}: {$message}";
         }
      }

      $this->assertSame([], $other, 'PHPStan reports issues in the branch-built fixture besides its dumps');
      $this->assertSame(self::BRANCH_BUILT_TYPES['phpstan'], $traced);
   }

   /**
    * @return list<int>
    */
   private static function droppedLines(): array {
      $lines = [];
      $source = file(self::DROPPED);
      self::assertIsArray($source);

      foreach ($source as $index => $line) {
         if (str_contains($line, '// dropped')) {
            $lines[] = $index + 1;
         }
      }

      self::assertGreaterThan(5, count($lines), 'the fixture marks suspiciously few lines');

      return $lines;
   }

   /**
    * The variable traced on each line of the branch-built fixture
    *
    * @return array<int, string>
    */
   private static function tracedVariables(): array {
      $variables = [];
      $source = file(self::BRANCH_BUILT);
      self::assertIsArray($source);

      foreach ($source as $index => $line) {
         if (preg_match('#/\*\* @psalm-trace \$(\w+) \*/#', $line, $match) === 1) {
            $variables[$index + 1] = $match[1];
         }
      }

      return $variables;
   }

   /**
    * Every issue psalm reports in the fixtures, by file
    *
    * @return array<string, list<array{string, int, string}>>
    */
   private static function psalm(): array {
      /** @var array<string, list<array{string, int, string}>>|null $cached */
      static $cached = null;

      if ($cached !== null) {
         return $cached;
      }

      $root = dirname(__DIR__);
      $command = sprintf(
         '%s %s --no-cache --no-progress --config=%s --output-format=json 2>/dev/null',
         escapeshellarg(PHP_BINARY),
         escapeshellarg("{$root}/vendor/bin/psalm"),
         escapeshellarg(__DIR__ . '/StaticAnalysis/psalm.xml')
      );

      $output = [];
      exec($command, $output);

      /** @var list<string> $output */
      $printed = implode("\n", $output);
      /** @var mixed $issues */
      $issues = json_decode($printed, true);
      self::assertIsArray($issues, "psalm did not report as JSON:\n{$printed}");

      $cached = [];

      foreach ($issues as $issue) {
         self::assertIsArray($issue);
         /** @var mixed $file */
         $file = $issue['file_path'] ?? null;
         /** @var mixed $type */
         $type = $issue['type'] ?? null;
         /** @var mixed $line */
         $line = $issue['line_from'] ?? null;
         /** @var mixed $message */
         $message = $issue['message'] ?? null;
         self::assertIsString($file);
         self::assertIsString($type);
         self::assertIsInt($line);
         self::assertIsString($message);

         $cached[$file][] = [$type, $line, $message];
      }

      return $cached;
   }

   /**
    * Every message PHPStan reports in the fixtures, by file, with the library's
    * extension.neon included. The branch-built fixture is copied with each
    * trace turned into a dumpType() on the same line, and filed under its own
    * name.
    *
    * @return array<string, list<array{string, int, string}>>
    */
   private static function phpstan(): array {
      /** @var array<string, list<array{string, int, string}>>|null $cached */
      static $cached = null;

      if ($cached !== null) {
         return $cached;
      }

      $root = dirname(__DIR__);
      $dir = sys_get_temp_dir() . '/php7-optional-phpstan-' . bin2hex(random_bytes(6));
      mkdir($dir);
      $copy = "{$dir}/branch-built.php";
      $source = (string) file_get_contents(self::BRANCH_BUILT);
      file_put_contents($copy, (string) preg_replace('#/\*\* @psalm-trace (\$\w+) \*/#', '\\PHPStan\\dumpType($1);', $source));

      $command = sprintf(
         '%s %s analyse --no-progress --error-format=json --level=max --configuration=%s --autoload-file=%s %s %s %s 2>/dev/null',
         escapeshellarg(PHP_BINARY),
         escapeshellarg("{$root}/vendor/bin/phpstan"),
         escapeshellarg("{$root}/extension.neon"),
         escapeshellarg("{$root}/vendor/autoload.php"),
         escapeshellarg(self::DROPPED),
         escapeshellarg(self::STILL_REPORTED),
         escapeshellarg($copy)
      );

      $output = [];
      exec($command, $output);
      unlink($copy);
      rmdir($dir);

      /** @var list<string> $output */
      $printed = implode("\n", $output);
      /** @var mixed $report */
      $report = json_decode($printed, true);
      self::assertIsArray($report, "PHPStan did not report as JSON:\n{$printed}");
      self::assertIsArray($report['files'] ?? null, "PHPStan's report has no files:\n{$printed}");

      $cached = [];

      /** @var array<string, mixed> $files PHPStan keys its report by file path */
      $files = $report['files'];

      foreach ($files as $file => $messages) {
         self::assertIsArray($messages);
         self::assertIsArray($messages['messages'] ?? null);
         $file = $file === $copy ? self::BRANCH_BUILT : $file;

         /** @var mixed $message */
         foreach ($messages['messages'] as $message) {
            self::assertIsArray($message);
            /** @var mixed $identifier */
            $identifier = $message['identifier'] ?? '';
            /** @var mixed $line */
            $line = $message['line'] ?? null;
            /** @var mixed $text */
            $text = $message['message'] ?? null;
            self::assertIsString($identifier);
            self::assertIsInt($line);
            self::assertIsString($text);

            $cached[$file][] = [$identifier, $line, $text];
         }
      }

      return $cached;
   }
}
