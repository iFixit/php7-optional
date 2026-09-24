<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The dropped-Result check, run the way a user runs it
 *
 * tests/StaticAnalysis/dropped-results.php marks each line the check has to
 * report with "// dropped". Every other line is a way of using or handling a
 * Result that must not be reported.
 */
final class StaticAnalysisTest extends TestCase {
   private const FIXTURE = __DIR__ . '/StaticAnalysis/dropped-results.php';

   public function testThePsalmPluginReportsExactlyTheDroppedResults(): void {
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
      $this->assertIsArray($issues, "psalm did not report as JSON:\n{$printed}");

      $reported = [];
      $other = [];

      foreach ($issues as $issue) {
         $this->assertIsArray($issue);
         /** @var mixed $type */
         $type = $issue['type'] ?? null;
         /** @var mixed $line */
         $line = $issue['line_from'] ?? null;
         $this->assertIsString($type);
         $this->assertIsInt($line);

         if ($type === 'UnusedResult') {
            $reported[] = $line;
         } else {
            $other[] = "{$type} on line {$line}";
         }
      }

      $this->assertSame([], $other, 'the fixture has psalm issues besides the dropped Results');
      $this->assertSame(self::droppedLines(), $reported);
   }

   /**
    * PHPStan's own checks also flag some of these lines, as calls to functions
    * with no side effects. Only the rule's identifier counts here, and the line
    * with droppedResultsWrite() is one PHPStan's own checks let through.
    */
   public function testThePhpstanRuleReportsExactlyTheDroppedResults(): void {
      $root = dirname(__DIR__);
      $command = sprintf(
         '%s %s analyse --no-progress --error-format=json --level=max --configuration=%s --autoload-file=%s %s 2>/dev/null',
         escapeshellarg(PHP_BINARY),
         escapeshellarg("{$root}/vendor/bin/phpstan"),
         escapeshellarg("{$root}/extension.neon"),
         escapeshellarg("{$root}/vendor/autoload.php"),
         escapeshellarg(self::FIXTURE)
      );

      $output = [];
      exec($command, $output);

      /** @var list<string> $output */
      $printed = implode("\n", $output);
      /** @var mixed $report */
      $report = json_decode($printed, true);
      $this->assertIsArray($report, "PHPStan did not report as JSON:\n{$printed}");
      $this->assertIsArray($report['files'] ?? null, "PHPStan's report has no files:\n{$printed}");

      $reported = [];

      foreach ($report['files'] as $file) {
         $this->assertIsArray($file);
         $this->assertIsArray($file['messages'] ?? null);

         /** @var mixed $message */
         foreach ($file['messages'] as $message) {
            $this->assertIsArray($message);

            if (($message['identifier'] ?? null) === 'optional.unusedResult') {
               /** @var mixed $line */
               $line = $message['line'] ?? null;
               $this->assertIsInt($line);
               $reported[] = $line;
            }
         }
      }

      sort($reported);
      $this->assertSame(self::droppedLines(), $reported);
   }

   /**
    * @return list<int>
    */
   private static function droppedLines(): array {
      $lines = [];
      $source = file(self::FIXTURE);
      self::assertIsArray($source);

      foreach ($source as $index => $line) {
         if (str_contains($line, '// dropped')) {
            $lines[] = $index + 1;
         }
      }

      self::assertGreaterThan(5, count($lines), 'the fixture marks suspiciously few lines');

      return $lines;
   }
}
